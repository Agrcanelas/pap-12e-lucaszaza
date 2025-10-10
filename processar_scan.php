<?php
set_time_limit(600); // 10 minutos de timeout
session_start();
require 'conexao.php';

// Verifica se o usuário está logado
if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit;
}

$usuario_id = $_SESSION['usuario_id'];

// CONFIGURAÇÕES DO ZAP
$ZAP_API = 'http://127.0.0.1:8090';
$ZAP_KEY = '12345';

/**
 * Faz requisição ao ZAP e retorna resposta decodificada
 */
function zapRequest($endpoint, $params = []) {
    global $ZAP_API, $ZAP_KEY;
    
    // Adiciona API key aos parâmetros
    if ($ZAP_KEY !== '') {
        $params['apikey'] = $ZAP_KEY;
    }
    
    // Monta URL completa
    $url = rtrim($ZAP_API, '/') . $endpoint;
    if (!empty($params)) {
        $url .= '?' . http_build_query($params);
    }
    
    // Configurações de contexto para timeout maior
    $opts = [
        'http' => [
            'timeout' => 300, // 5 minutos
            'ignore_errors' => true
        ]
    ];
    $context = stream_context_create($opts);
    
    // Faz requisição
    $response = @file_get_contents($url, false, $context);
    
    if ($response === false) {
        $error = error_get_last();
        throw new Exception("Falha na comunicação com ZAP: " . ($error['message'] ?? 'Erro desconhecido'));
    }
    
    // Decodifica JSON
    $decoded = json_decode($response, true);
    return $decoded ?? $response;
}

/**
 * Aguarda conclusão do spider
 */
function aguardarSpider($scanId, $timeoutSegundos = 300) {
    $inicio = time();
    
    while ((time() - $inicio) < $timeoutSegundos) {
        try {
            $status = zapRequest('/JSON/spider/view/status/', ['scanId' => $scanId]);
            $progresso = intval($status['status'] ?? 0);
            
            if ($progresso >= 100) {
                return true;
            }
            
            sleep(2); // Aguarda 2 segundos
        } catch (Exception $e) {
            // Continua tentando
            sleep(2);
        }
    }
    
    return false; // Timeout
}

/**
 * Aguarda conclusão do Active Scan
 */
function aguardarActiveScan($scanId, $timeoutSegundos = 300) {
    $inicio = time();
    
    while ((time() - $inicio) < $timeoutSegundos) {
        try {
            $status = zapRequest('/JSON/ascan/view/status/', ['scanId' => $scanId]);
            $progresso = intval($status['status'] ?? 0);
            
            if ($progresso >= 100) {
                return true;
            }
            
            sleep(3); // Aguarda 3 segundos
        } catch (Exception $e) {
            sleep(3);
        }
    }
    
    return false; // Timeout
}

// ========== INÍCIO DO PROCESSAMENTO ==========

try {
    // Valida target_id
    $target_id = intval($_POST['target_id'] ?? 0);
    if ($target_id <= 0) {
        throw new Exception("ID do alvo inválido.");
    }
    
    // Busca informações do target e valida permissão
    $stmt = $pdo->prepare("SELECT url_ip, nome FROM targets WHERE id = ? AND usuario_id = ?");
    $stmt->execute([$target_id, $usuario_id]);
    $target = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$target) {
        throw new Exception("Alvo não encontrado ou você não tem permissão para acessá-lo.");
    }
    
    $targetUrl = $target['url_ip'];
    
    // Verifica se a URL é válida
    if (!filter_var($targetUrl, FILTER_VALIDATE_URL)) {
        throw new Exception("URL do alvo inválida: $targetUrl");
    }
    
    // ===== 1. CRIAR REGISTRO DO SCAN NO BANCO =====
    $stmtScan = $pdo->prepare("
        INSERT INTO scans (usuario_id, target_id, scanner, parametros, status, iniciado_em) 
        VALUES (?, ?, ?, ?, 'Em execução', NOW())
    ");
    
    $parametros = json_encode([
        'zap_api' => $ZAP_API,
        'mode' => 'quick_scan'
    ]);
    
    $stmtScan->execute([$usuario_id, $target_id, 'OWASP ZAP', $parametros]);
    $scan_id = $pdo->lastInsertId();
    
    // ===== 2. ACESSAR URL NO ZAP (para registrar na sessão) =====
    zapRequest('/JSON/core/action/accessUrl/', ['url' => $targetUrl]);
    sleep(1);
    
    // ===== 3. INICIAR SPIDER (PASSIVE SCAN) =====
    $spiderResponse = zapRequest('/JSON/spider/action/scan/', [
        'url' => $targetUrl,
        'maxChildren' => 10, // Limita profundidade para ser mais rápido
        'recurse' => 'true'
    ]);
    
    $spiderScanId = $spiderResponse['scan'] ?? null;
    
    if ($spiderScanId) {
        // Aguarda spider (máximo 2 minutos)
        aguardarSpider($spiderScanId, 120);
    }
    
    // ===== 4. ADICIONAR URL AO CONTEXTO (SCOPE) =====
    try {
        zapRequest('/JSON/core/action/includeInContext/', [
            'contextName' => 'Default Context',
            'regex' => preg_quote($targetUrl, '/') . '.*'
        ]);
    } catch (Exception $e) {
        // Se não existir contexto, ignora o erro
        error_log("Aviso ao adicionar ao contexto: " . $e->getMessage());
    }
    
    // ===== 5. INICIAR ACTIVE SCAN =====
    $ascanResponse = zapRequest('/JSON/ascan/action/scan/', [
        'url' => $targetUrl,
        'recurse' => 'true',
        'inScopeOnly' => 'false'
    ]);
    
    // DEBUG: Registra resposta do ZAP
    error_log("ZAP Active Scan Response: " . print_r($ascanResponse, true));
    
    $ascanId = $ascanResponse['scan'] ?? null;
    
    if (!$ascanId) {
        // Tenta verificar se há erro na resposta
        $errorMsg = "Falha ao iniciar Active Scan no ZAP.";
        if (isset($ascanResponse['code'])) {
            $errorMsg .= " Código: " . $ascanResponse['code'];
        }
        if (isset($ascanResponse['message'])) {
            $errorMsg .= " Mensagem: " . $ascanResponse['message'];
        }
        if (isset($ascanResponse['detail'])) {
            $errorMsg .= " Detalhe: " . $ascanResponse['detail'];
        }
        // Mostra resposta completa
        $errorMsg .= " | Resposta completa: " . json_encode($ascanResponse);
        throw new Exception($errorMsg);
    }
    
    // ===== 12. REGISTRAR LOG =====
    
    if (!$scanConcluido) {
        // Se timeout, continua mesmo assim para pegar o que foi encontrado
        error_log("Timeout no Active Scan, mas continuando para coletar resultados parciais.");
    }
    
    // ===== 6. AGUARDA ACTIVE SCAN =====
    $alertsResponse = zapRequest('/JSON/core/view/alerts/', [
        'baseurl' => $targetUrl,
        'start' => 0,
        'count' => 9999
    ]);
    
    $alerts = [];
    if (is_array($alertsResponse) && isset($alertsResponse['alerts'])) {
        $alerts = $alertsResponse['alerts'];
    }
    
    // ===== 7. COLETAR ALERTAS (VULNERABILIDADES) =====
    $stmtVuln = $pdo->prepare("
        INSERT INTO vulnerabilidades 
        (scan_id, titulo, severidade, cve, cwe, cvss, descricao, prova, criado_em) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ");
    
    $contadorVuln = 0;
    $severidadeCounts = ['Crítica' => 0, 'Alta' => 0, 'Média' => 0, 'Baixa' => 0];
    
    foreach ($alerts as $alert) {
        $titulo = $alert['alert'] ?? $alert['name'] ?? 'Vulnerabilidade sem título';
        $risco = $alert['risk'] ?? 'Média';
        
        // Normaliza severidade
        $risco = ucfirst(strtolower($risco));
        if ($risco === 'High') $risco = 'Alta';
        elseif ($risco === 'Medium') $risco = 'Média';
        elseif ($risco === 'Low') $risco = 'Baixa';
        elseif ($risco === 'Critical') $risco = 'Crítica';
        
        // Garante que está em um dos valores aceitos pelo ENUM
        if (!in_array($risco, ['Baixa', 'Média', 'Alta', 'Crítica'])) {
            $risco = 'Média';
        }
        
        $cwe = $alert['cweid'] ?? null;
        $cve = $alert['cve'] ?? null;
        $descricao = $alert['description'] ?? '';
        $prova = json_encode($alert, JSON_UNESCAPED_UNICODE);
        $cvss = null; // ZAP não retorna CVSS diretamente
        
        $stmtVuln->execute([
            $scan_id,
            $titulo,
            $risco,
            $cve,
            $cwe,
            $cvss,
            $descricao,
            $prova
        ]);
        
        $contadorVuln++;
        $severidadeCounts[$risco]++;
    }
    
    // ===== 8. SALVAR VULNERABILIDADES NO BANCO =====
    $reportDir = __DIR__ . '/reports';
    if (!is_dir($reportDir)) {
        mkdir($reportDir, 0755, true);
    }
    
    $relatorioConteudo = '';
    $extensao = 'json';
    
    // Tenta gerar relatório HTML
    try {
        $relatorioConteudo = zapRequest('/OTHER/core/other/htmlreport/', []);
        if (!empty($relatorioConteudo)) {
            $extensao = 'html';
        }
    } catch (Exception $e) {
        // Fallback para JSON
        try {
            $jsonReport = zapRequest('/OTHER/core/other/jsonreport/', []);
            $relatorioConteudo = is_string($jsonReport) ? $jsonReport : json_encode($jsonReport, JSON_PRETTY_PRINT);
        } catch (Exception $e2) {
            $relatorioConteudo = '';
        }
    }
    
    // Salva relatório em arquivo
    if (!empty($relatorioConteudo)) {
        $nomeArquivo = "report_scan_{$scan_id}_" . time() . ".{$extensao}";
        $caminhoCompleto = $reportDir . '/' . $nomeArquivo;
        file_put_contents($caminhoCompleto, $relatorioConteudo);
        
        // Registra na tabela relatorios
        $stmtRelatorio = $pdo->prepare("
            INSERT INTO relatorios (scan_id, arquivo_path, gerado_em) 
            VALUES (?, ?, NOW())
        ");
        $stmtRelatorio->execute([$scan_id, 'reports/' . $nomeArquivo]);
    }
    
    // ===== 9. GERAR E SALVAR RELATÓRIO =====
    // Fórmula: Crítica=4pts, Alta=3pts, Média=2pts, Baixa=1pt
    $pontuacao = ($severidadeCounts['Crítica'] * 4) + 
                 ($severidadeCounts['Alta'] * 3) + 
                 ($severidadeCounts['Média'] * 2) + 
                 ($severidadeCounts['Baixa'] * 1);
    
    // Normaliza para escala de 0 a 10
    $scoreRisco = min(10, round($pontuacao * 0.5, 2));
    
    // ===== 10. CALCULAR SCORE DE RISCO =====
    $stmtUpdate = $pdo->prepare("
        UPDATE scans 
        SET status = 'Concluído', 
            score_risco = ?, 
            finalizado_em = NOW() 
        WHERE id = ?
    ");
    $stmtUpdate->execute([$scoreRisco, $scan_id]);
    
    // ===== 11. ATUALIZAR STATUS DO SCAN =====
    $stmtLog = $pdo->prepare("
        INSERT INTO logs (usuario_id, acao, detalhes, criado_em) 
        VALUES (?, 'scan_concluido', ?, NOW())
    ");
    $detalhesLog = json_encode([
        'scan_id' => $scan_id,
        'target_url' => $targetUrl,
        'vulnerabilidades_encontradas' => $contadorVuln,
        'score' => $scoreRisco
    ]);
    $stmtLog->execute([$usuario_id, $detalhesLog]);
    
    // Mensagem de sucesso
    $_SESSION['mensagem_scan'] = "✅ Scan concluído com sucesso! Encontradas {$contadorVuln} vulnerabilidades. Score de risco: {$scoreRisco}/10";
    header("Location: scans.php");
    exit;
    
} catch (Exception $e) {
    // Em caso de erro, atualiza status do scan (se foi criado)
    if (!empty($scan_id)) {
        $stmtErro = $pdo->prepare("
            UPDATE scans 
            SET status = 'Erro', 
                finalizado_em = NOW() 
            WHERE id = ?
        ");
        $stmtErro->execute([$scan_id]);
        
        // Registra log de erro
        $stmtLogErro = $pdo->prepare("
            INSERT INTO logs (usuario_id, acao, detalhes, criado_em) 
            VALUES (?, 'scan_erro', ?, NOW())
        ");
        $detalhesErro = json_encode([
            'scan_id' => $scan_id,
            'erro' => $e->getMessage()
        ]);
        $stmtLogErro->execute([$usuario_id, $detalhesErro]);
    }
    
    $_SESSION['mensagem_scan'] = "❌ Erro ao executar scan: " . $e->getMessage();
    header("Location: scans.php");
    exit;
}