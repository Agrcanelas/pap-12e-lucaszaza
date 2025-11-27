<?php
set_time_limit(1800); // 30 minutos
session_start();
require 'conexao.php';

// Verifica se o usuário está logado
if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit;
}

$usuario_id = $_SESSION['usuario_id'];

// CONFIGURAÇÕES DO ZAP
$ZAP_API = 'http://127.0.0.1:8080';
$ZAP_KEY = '12345';

/**
 * Faz requisição ao ZAP com retry automático
 */
function zapRequest($endpoint, $params = [], $maxRetries = 3)
{
    global $ZAP_API, $ZAP_KEY;

    if ($ZAP_KEY !== '') {
        $params['apikey'] = $ZAP_KEY;
    }

    $url = rtrim($ZAP_API, '/') . $endpoint;
    if (!empty($params)) {
        $url .= '?' . http_build_query($params);
    }

    $lastException = null;

    for ($tentativa = 1; $tentativa <= $maxRetries; $tentativa++) {
        try {
            $opts = [
                'http' => [
                    'timeout' => 600,
                    'ignore_errors' => true
                ]
            ];
            $context = stream_context_create($opts);

            error_log("Tentativa $tentativa: $url");
            $response = @file_get_contents($url, false, $context);

            if ($response === false) {
                $error = error_get_last();
                throw new Exception("Falha na comunicação com ZAP: " . ($error['message'] ?? 'Erro desconhecido'));
            }

            $decoded = json_decode($response, true);
            
            // Debug: mostra a resposta
            error_log("Response: " . substr($response, 0, 200));
            
            return $decoded ?? $response;

        } catch (Exception $e) {
            $lastException = $e;
            if ($tentativa < $maxRetries) {
                $delay = $tentativa * 3;
                error_log("⚠ Tentativa $tentativa falhou. Aguardando {$delay}s...");
                sleep($delay);
            }
        }
    }

    throw new Exception("Falha após $maxRetries tentativas: " . $lastException->getMessage());
}

/**
 * Verifica se o ZAP está rodando
 */
function verificarZAP()
{
    try {
        $response = zapRequest('/JSON/core/view/version/', [], 2);
        if (isset($response['version'])) {
            error_log("✓ ZAP conectado. Versão: " . $response['version']);
            return true;
        }
    } catch (Exception $e) {
        error_log("✗ ZAP não está respondendo: " . $e->getMessage());
        throw new Exception("OWASP ZAP não está rodando ou não está acessível em $GLOBALS[ZAP_API]");
    }
    return false;
}

/**
 * Aguarda o spider completar com timeout
 */
function aguardarSpider($spiderId, $timeoutSegundos = 300)
{
    $inicio = time();
    $ultimoProgresso = -1;

    while ((time() - $inicio) < $timeoutSegundos) {
        try {
            $spiderStatus = zapRequest('/JSON/spider/view/status/', ['scanId' => $spiderId]);
            $progresso = intval($spiderStatus['status'] ?? 0);

            if ($progresso !== $ultimoProgresso) {
                error_log("🕷️ Spider: $progresso%");
                $ultimoProgresso = $progresso;
            }

            if ($progresso >= 100) {
                error_log("✓ Spider concluído!");
                return true;
            }

        } catch (Exception $e) {
            error_log("⚠ Erro ao verificar spider: " . $e->getMessage());
            break;
        }

        sleep(3);
    }

    error_log("⚠ Spider timeout após {$timeoutSegundos}s");
    return false;
}

/**
 * Aguarda o Active Scan completar
 */
function aguardarActiveScan($ascanId, $timeoutSegundos = 900)
{
    $inicio = time();
    $ultimoLog = -1;

    while ((time() - $inicio) < $timeoutSegundos) {
        try {
            $statusResp = zapRequest('/JSON/ascan/view/status/', ['scanId' => $ascanId]);
            $progresso = intval($statusResp['status'] ?? 0);

            if (floor($progresso / 10) > $ultimoLog) {
                error_log("🔍 Active Scan: $progresso%");
                $ultimoLog = floor($progresso / 10);
            }

            if ($progresso >= 100) {
                error_log("✓ Active Scan concluído!");
                return true;
            }

        } catch (Exception $e) {
            error_log("⚠ Erro ao verificar scan: " . $e->getMessage());
        }

        sleep(5);
    }

    error_log("⚠ Active Scan timeout após {$timeoutSegundos}s. Coletando resultados parciais...");
    return false;
}

// ========== INÍCIO DO PROCESSAMENTO ==========

try {
    error_log("========================================");
    error_log("=== INICIANDO NOVO SCAN ===");
    error_log("========================================");

    // Verifica se ZAP está rodando
    verificarZAP();

    // Valida target_id
    $target_id = intval($_POST['target_id'] ?? 0);
    if ($target_id <= 0) {
        throw new Exception("ID do alvo inválido.");
    }

    // Busca target
    $stmt = $pdo->prepare("SELECT url_ip, nome FROM targets WHERE id = ? AND usuario_id = ?");
    $stmt->execute([$target_id, $usuario_id]);
    $target = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$target) {
        throw new Exception("Alvo não encontrado.");
    }

    $targetUrl = $target['url_ip'];

    if (!filter_var($targetUrl, FILTER_VALIDATE_URL)) {
        throw new Exception("URL inválida: $targetUrl");
    }

    error_log("🎯 Target: $targetUrl");

    // ===== 1. CRIAR SCAN NO BANCO =====
    $stmtScan = $pdo->prepare("
        INSERT INTO scans (usuario_id, target_id, scanner, parametros, status, iniciado_em) 
        VALUES (?, ?, ?, ?, 'Em execução', NOW())
    ");

    $parametros = json_encode(['zap_api' => $ZAP_API]);
    $stmtScan->execute([$usuario_id, $target_id, 'OWASP ZAP', $parametros]);
    $scan_id = $pdo->lastInsertId();

    error_log("📝 Scan ID: $scan_id criado no banco");

    // ===== 2. LIMPAR SESSÃO DO ZAP =====
    error_log("🧹 Limpando sessão anterior do ZAP...");
    try {
        zapRequest('/JSON/core/action/newSession/', ['name' => 'scan_' . $scan_id, 'overwrite' => 'true']);
        sleep(3); // Aguarda criação da sessão
    } catch (Exception $e) {
        error_log("⚠ Aviso: " . $e->getMessage());
    }

    // ===== 3. ACESSAR URL NO ZAP (CRÍTICO!) =====
    error_log("🌐 Acessando URL no ZAP para popular a árvore de sites...");

    // Primeiro acesso para adicionar à árvore
    for ($i = 1; $i <= 3; $i++) {
        try {
            zapRequest('/JSON/core/action/accessUrl/', ['url' => $targetUrl]);
            error_log("✓ Acesso $i/3 concluído");
            sleep(2);
        } catch (Exception $e) {
            error_log("⚠ Falha no acesso $i: " . $e->getMessage());
        }
    }

    // AGUARDA MAIS TEMPO para o site aparecer na árvore do ZAP
    error_log("⏳ Aguardando 10 segundos para URL ser registrada no ZAP...");
    sleep(10);

    // Verifica se a URL foi adicionada
    try {
        $sitesResp = zapRequest('/JSON/core/view/sites/', []);
        error_log("Sites no ZAP: " . json_encode($sitesResp));
    } catch (Exception $e) {
        error_log("⚠ Não conseguiu verificar sites: " . $e->getMessage());
    }

    // ===== 4. SPIDER TRADICIONAL =====
    error_log("🕷️ Iniciando Spider tradicional...");

    $spiderResp = zapRequest('/JSON/spider/action/scan/', [
        'url' => $targetUrl,
        'maxChildren' => '', // String vazia ao invés de 0
        'recurse' => 'true',
        'subtreeOnly' => 'false'
    ]);

    error_log("Spider Response completa: " . json_encode($spiderResp));

    // O ZAP retorna {"scan":"0"}, {"scan":"1"}, etc
    $spiderId = 0;
    if (isset($spiderResp['scan'])) {
        $spiderId = intval($spiderResp['scan']);
    }

    error_log("🕷️ Spider ID extraído: $spiderId");

    // Spider ID 0 é inválido, mas vamos tentar continuar mesmo assim
    if ($spiderId > 0) {
        error_log("✓ Spider iniciado com ID: $spiderId");
        
        // Aguarda spider
        $spiderConcluido = aguardarSpider($spiderId, 300);
        
        // Aguarda processamento
        sleep(15);
    } else {
        error_log("⚠ Spider retornou ID inválido ($spiderId), mas continuando...");
        
        // Mesmo sem spider ID válido, aguarda um tempo
        sleep(20);
    }

    // ===== 5. VALIDAR URLs ENCONTRADAS =====
    error_log("📊 Verificando URLs descobertas...");

    $urlsEncontradas = 0;
    try {
        // Tenta diferentes formas de buscar URLs
        $urlsResp = zapRequest('/JSON/core/view/urls/', []);
        
        if (isset($urlsResp['urls'])) {
            $urlsEncontradas = count($urlsResp['urls']);
            error_log("✓ Total de URLs no ZAP: $urlsEncontradas");
            
            // Filtra URLs do target
            $urlsDoTarget = array_filter($urlsResp['urls'], function($url) use ($targetUrl) {
                return strpos($url, parse_url($targetUrl, PHP_URL_HOST)) !== false;
            });
            
            $urlsEncontradas = count($urlsDoTarget);
            error_log("✓ URLs do target encontradas: $urlsEncontradas");
        }
    } catch (Exception $e) {
        error_log("⚠ Erro ao verificar URLs: " . $e->getMessage());
    }

    if ($urlsEncontradas < 3) {
        error_log("⚠ Poucas URLs encontradas ($urlsEncontradas). Tentando Ajax Spider...");
        
        try {
            error_log("🔄 Iniciando Ajax Spider...");
            $ajaxResp = zapRequest('/JSON/ajaxSpider/action/scan/', [
                'url' => $targetUrl,
                'inScope' => 'true'
            ]);

            $tempoAjax = 120;
            $inicioAjax = time();
            while ((time() - $inicioAjax) < $tempoAjax) {
                $ajaxStatus = zapRequest('/JSON/ajaxSpider/view/status/', []);
                $statusAjax = $ajaxStatus['status'] ?? '';
                
                if ($statusAjax === 'stopped') {
                    error_log("✓ Ajax Spider concluído!");
                    break;
                }
                
                error_log("🔄 Ajax Spider: $statusAjax");
                sleep(5);
            }

            sleep(10);

            // Verifica novamente
            $urlsResp = zapRequest('/JSON/core/view/urls/', []);
            if (isset($urlsResp['urls'])) {
                $urlsEncontradas = count($urlsResp['urls']);
                error_log("✓ Após Ajax Spider: $urlsEncontradas URLs");
            }

        } catch (Exception $e) {
            error_log("⚠ Ajax Spider falhou: " . $e->getMessage());
        }
    }

    // Mesmo que encontre 0 URLs, vamos continuar
    if ($urlsEncontradas === 0) {
        error_log("⚠ AVISO: Nenhuma URL encontrada pelo Spider, mas continuando com Active Scan na URL base");
    }

    // ===== 6. CONFIGURAR SCAN POLICY =====
    error_log("⚙️ Configurando scan policy agressiva...");

    try {
        zapRequest('/JSON/ascan/action/setOptionAttackStrength/', ['String' => 'INSANE']);
        zapRequest('/JSON/ascan/action/setOptionAlertThreshold/', ['String' => 'LOW']);
        
        $scanners = zapRequest('/JSON/ascan/view/scanners/', []);
        if (isset($scanners['scanners'])) {
            $totalScanners = count($scanners['scanners']);
            error_log("⚙️ Configurando $totalScanners scanners...");
            
            foreach ($scanners['scanners'] as $scanner) {
                $scannerId = $scanner['id'] ?? null;
                if ($scannerId) {
                    try {
                        zapRequest('/JSON/ascan/action/enableScanners/', ['ids' => $scannerId]);
                        zapRequest('/JSON/ascan/action/setScannerAttackStrength/', [
                            'id' => $scannerId,
                            'attackStrength' => 'INSANE'
                        ]);
                        zapRequest('/JSON/ascan/action/setScannerAlertThreshold/', [
                            'id' => $scannerId,
                            'alertThreshold' => 'LOW'
                        ]);
                    } catch (Exception $e) {
                        // Ignora
                    }
                }
            }
            error_log("✓ Scanners configurados!");
        }
    } catch (Exception $e) {
        error_log("⚠ Erro ao configurar policy: " . $e->getMessage());
    }

    // ===== 7. INICIAR ACTIVE SCAN =====
    error_log("🔍 Iniciando Active Scan...");

    $ascanResponse = zapRequest('/JSON/ascan/action/scan/', [
        'url' => $targetUrl,
        'recurse' => 'true',
        'inScopeOnly' => 'false',
        'scanPolicyName' => ''
    ]);

    error_log("Active Scan Response: " . json_encode($ascanResponse));

    $ascanId = 0;
    if (isset($ascanResponse['scan'])) {
        $ascanId = intval($ascanResponse['scan']);
    }

    if ($ascanId <= 0) {
        // Tenta método alternativo
        error_log("⚠ Active Scan principal retornou ID inválido, tentando método alternativo...");
        
        // Força o scan mesmo sem spider
        $ascanResponse2 = zapRequest('/JSON/ascan/action/scan/', [
            'url' => $targetUrl,
            'recurse' => 'false', // Sem recursão
            'inScopeOnly' => 'false'
        ]);
        
        if (isset($ascanResponse2['scan'])) {
            $ascanId = intval($ascanResponse2['scan']);
        }
        
        if ($ascanId <= 0) {
            throw new Exception("Active Scan não retornou ID válido após múltiplas tentativas. Response: " . json_encode($ascanResponse));
        }
    }

    error_log("✓ Active Scan ID: $ascanId");

    // ===== 8. AGUARDAR CONCLUSÃO =====
    $scanConcluido = aguardarActiveScan($ascanId, 900);

    sleep(10);

    // ===== 9. COLETAR ALERTAS =====
    error_log("📋 Coletando alertas...");

    $alerts = [];
    $tentativasColeta = [
        [],
        ['baseurl' => $targetUrl],
        ['url' => $targetUrl]
    ];

    foreach ($tentativasColeta as $params) {
        try {
            $alertsResponse = zapRequest('/JSON/core/view/alerts/', $params);

            if (is_array($alertsResponse) && isset($alertsResponse['alerts']) && !empty($alertsResponse['alerts'])) {
                $alerts = $alertsResponse['alerts'];
                error_log("✓ Encontrados " . count($alerts) . " alertas");
                break;
            }
        } catch (Exception $e) {
            error_log("⚠ Tentativa de coleta falhou: " . $e->getMessage());
        }
    }

    $totalAlertas = count($alerts);
    error_log("📊 TOTAL de alertas: $totalAlertas");

    // ===== 10. SALVAR VULNERABILIDADES =====
    $stmtVuln = $pdo->prepare("
        INSERT INTO vulnerabilidades 
        (scan_id, titulo, severidade, cve, cwe, cvss, descricao, prova, criado_em) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ");

    $contadorVuln = 0;
    $severidadeCounts = ['Crítica' => 0, 'Alta' => 0, 'Média' => 0, 'Baixa' => 0];

    foreach ($alerts as $alert) {
        $titulo = $alert['alert'] ?? $alert['name'] ?? 'Vulnerabilidade desconhecida';
        $risco = $alert['risk'] ?? 'Medium';

        $mapa = [
            'Critical' => 'Crítica',
            'High' => 'Alta',
            'Medium' => 'Média',
            'Low' => 'Baixa',
            'Informational' => 'Baixa'
        ];

        $severidade = $mapa[$risco] ?? 'Média';

        $cwe = $alert['cweid'] ?? null;
        $cve = null;
        $descricao = $alert['description'] ?? '';
        $prova = json_encode($alert, JSON_UNESCAPED_UNICODE);
        $cvss = null;

        $stmtVuln->execute([
            $scan_id,
            $titulo,
            $severidade,
            $cve,
            $cwe,
            $cvss,
            $descricao,
            $prova
        ]);

        $contadorVuln++;
        $severidadeCounts[$severidade]++;
    }

    error_log("✓ Salvas $contadorVuln vulnerabilidades");
    error_log("   Críticas: {$severidadeCounts['Crítica']}, Altas: {$severidadeCounts['Alta']}, Médias: {$severidadeCounts['Média']}, Baixas: {$severidadeCounts['Baixa']}");

    // ===== 11. GERAR RELATÓRIO =====
    $reportDir = __DIR__ . '/reports';
    if (!is_dir($reportDir)) {
        mkdir($reportDir, 0755, true);
    }

    $relatorioConteudo = '';
    $extensao = 'json';

    try {
        error_log("📄 Gerando relatório...");
        $htmlReport = zapRequest('/OTHER/core/other/htmlreport/', []);
        if (!empty($htmlReport) && is_string($htmlReport)) {
            $relatorioConteudo = $htmlReport;
            $extensao = 'html';
        }
    } catch (Exception $e) {
        try {
            $jsonReport = zapRequest('/OTHER/core/other/jsonreport/', []);
            $relatorioConteudo = is_string($jsonReport) ? $jsonReport : json_encode($jsonReport, JSON_PRETTY_PRINT);
        } catch (Exception $e2) {
            $relatorioConteudo = '';
        }
    }

    if (!empty($relatorioConteudo)) {
        $nomeArquivo = "report_scan_{$scan_id}_" . time() . ".{$extensao}";
        file_put_contents($reportDir . '/' . $nomeArquivo, $relatorioConteudo);

        $stmtRelatorio = $pdo->prepare("
            INSERT INTO relatorios (scan_id, arquivo_path, gerado_em) 
            VALUES (?, ?, NOW())
        ");
        $stmtRelatorio->execute([$scan_id, 'reports/' . $nomeArquivo]);
        error_log("✓ Relatório salvo: $nomeArquivo");
    }

    // ===== 12. CALCULAR SCORE =====
    $pontuacao = ($severidadeCounts['Crítica'] * 4) +
        ($severidadeCounts['Alta'] * 3) +
        ($severidadeCounts['Média'] * 2) +
        ($severidadeCounts['Baixa'] * 1);

    $scoreRisco = min(10, round($pontuacao * 0.5, 2));

    // ===== 13. ATUALIZAR SCAN =====
    $stmtUpdate = $pdo->prepare("
        UPDATE scans 
        SET status = 'Concluído', 
            score_risco = ?, 
            finalizado_em = NOW() 
        WHERE id = ?
    ");
    $stmtUpdate->execute([$scoreRisco, $scan_id]);

    // ===== 14. LOG =====
    $stmtLog = $pdo->prepare("
        INSERT INTO logs (usuario_id, acao, detalhes, criado_em) 
        VALUES (?, 'scan_concluido', ?, NOW())
    ");
    $detalhesLog = json_encode([
        'scan_id' => $scan_id,
        'target_url' => $targetUrl,
        'vulnerabilidades' => $contadorVuln,
        'score' => $scoreRisco,
        'urls_encontradas' => $urlsEncontradas
    ]);
    $stmtLog->execute([$usuario_id, $detalhesLog]);

    error_log("========================================");
    error_log("=== SCAN CONCLUÍDO ===");
    error_log("========================================");

    $_SESSION['mensagem_scan'] = "✅ Scan concluído! Vulnerabilidades: {$contadorVuln} | Score: {$scoreRisco}/10";
    header("Location: scans.php");
    exit;

} catch (Exception $e) {
    error_log("========================================");
    error_log("=== ERRO NO SCAN ===");
    error_log("Mensagem: " . $e->getMessage());
    error_log("========================================");

    if (!empty($scan_id)) {
        $pdo->prepare("UPDATE scans SET status = 'Erro', finalizado_em = NOW() WHERE id = ?")->execute([$scan_id]);

        $pdo->prepare("INSERT INTO logs (usuario_id, acao, detalhes, criado_em) VALUES (?, 'scan_erro', ?, NOW())")
            ->execute([$usuario_id, json_encode(['scan_id' => $scan_id, 'erro' => $e->getMessage()])]);
    }

    $_SESSION['mensagem_scan'] = "❌ Erro: " . $e->getMessage();
    header("Location: scans.php");
    exit;
}