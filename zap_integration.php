<?php
// zap_integration.php
// adapta conforme sua estrutura. Recebe $pdo, $usuario_id e $target_id (id do target na tabela)

$zapBase = 'http://127.0.0.1:8080'; // endereço do ZAP
$zapApiKey = ''; // coloque a API key se você configurou no ZAP, ou deixe '' se não há key

function zap_request($path, $params = [], $method = 'GET') {
    global $zapBase, $zapApiKey;
    if ($zapApiKey) $params['apikey'] = $zapApiKey;

    $url = $zapBase . $path . '?' . http_build_query($params);

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    // timeout razoável
    curl_setopt($ch, CURLOPT_TIMEOUT, 120);
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
    }
    $resp = curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);

    if ($resp === false) {
        throw new Exception("ZAP request failed: $err");
    }
    // ZAP retorna JSON para endpoints /JSON/...
    $decoded = json_decode($resp, true);
    return $decoded ?? $resp;
}

function zap_access_url($target) {
    // Acessa a URL para o ZAP registrar a sessão
    return zap_request('/JSON/core/action/accessUrl/', ['url' => $target]);
}

function zap_spider_start($target) {
    return zap_request('/JSON/spider/action/scan/', ['url' => $target, 'maxChildren' => 0]);
}

function zap_spider_status($scanId) {
    return zap_request('/JSON/spider/view/status/', ['scanId' => $scanId]);
}

function zap_ajax_spider_start($target) {
    return zap_request('/JSON/ajaxSpider/action/scan/', ['url' => $target]);
}

function zap_ascan_start($target) {
    // inicia active scan no alvo
    return zap_request('/JSON/ascan/action/scan/', ['url' => $target, 'recurse' => true]);
}

function zap_ascan_status($scanId) {
    return zap_request('/JSON/ascan/view/status/', ['scanId' => $scanId]);
}

function zap_get_alerts($target) {
    // Pegamos alerts relacionados ao site (ou todos se preferir)
    return zap_request('/JSON/alert/view/alerts/', ['site' => $target]);
}

function zap_generate_html_report() {
    // Se a add-on Report Generation estiver disponível, use o endpoint correspondente.
    // Alternativa: usar core/other/jsonreport ou core/other/har/ etc. Vamos tentar o json report e então converter
    // Aqui usamos core/other/jsonreport (retorna JSON) e também podemos solicitar HTML por outro endpoint se disponível.
    $resp = zap_request('/OTHER/core/other/jsonreport/');
    if (is_string($resp)) return $resp;
    return json_encode($resp, JSON_PRETTY_PRINT);
}

try {
    // --- Exemplos de integração ---
    // 1) Pegue dados do target (url) via sua tabela targets
    $targetId = intval($argv[1] ?? ($_POST['target_id'] ?? 0)); // ajustar conforme chamada
    if (!$targetId) throw new Exception("target_id não fornecido");

    // busca URL do target
    $stmt = $pdo->prepare("SELECT url_ip FROM targets WHERE id = ?");
    $stmt->execute([$targetId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) throw new Exception("Target não encontrado");
    $targetUrl = $row['url_ip'];

    // 2) Inserir scan na tabela scans (status Em execução)
    $stmtIns = $pdo->prepare("INSERT INTO scans (usuario_id, target_id, scanner, parametros, status, iniciado_em) VALUES (?, ?, ?, ?, 'Em execução', NOW())");
    $paramJson = json_encode(['zap_host' => $zapBase]);
    $stmtIns->execute([$_SESSION['usuario_id'], $targetId, 'OWASP ZAP', $paramJson]);
    $scanId = $pdo->lastInsertId();

    // 3) Dê um accessUrl para ZAP
    zap_access_url($targetUrl);

    // 4) Spider (ou ajaxSpider)
    $s = zap_spider_start($targetUrl);
    // s pode retornar scan id: 'scan'
    $spiderScanId = $s['scan'] ?? null;

    // Opcional: poll do spider até 100% (simplificado)
    if ($spiderScanId) {
        while (true) {
            sleep(2);
            $statusResp = zap_spider_status($spiderScanId);
            $percent = intval($statusResp['status'] ?? 0);
            if ($percent >= 100) break;
        }
    } else {
        // fallback: ajaxSpider
        zap_ajax_spider_start($targetUrl);
        sleep(3); // deixe o spider rodar um pouco (ou implemente polling do ajaxSpider/view/status)
    }

    // 5) Active scan
    $a = zap_ascan_start($targetUrl);
    $ascanId = $a['scan'] ?? null;

    // poll do ascan
    if ($ascanId) {
        while (true) {
            sleep(5);
            $status = zap_ascan_status($ascanId);
            $pct = intval($status['status'] ?? 0);
            // opcional: escreva logs na tabela logs
            if ($pct >= 100) break;
        }
    } else {
        // Alguns ZAPs retornam vazio; então também podemos consultar /JSON/ascan/view/status/ sem scanId
        while (true) {
            sleep(5);
            $statusAll = zap_request('/JSON/ascan/view/status/', []);
            $pct = intval($statusAll['status'] ?? 0);
            if ($pct >= 100) break;
        }
    }

    // 6) Puxar alerts e salvar em vulnerabilidades
    $alerts = zap_get_alerts($targetUrl);
    if (is_array($alerts)) {
        foreach ($alerts as $alert) {
            $titulo = $alert['alert'] ?? 'Unknown';
            $severidade = $alert['risk'] ?? 'Média';
            $descricao = $alert['description'] ?? '';
            $cve = $alert['cweid'] ?? '';
            // cvss não vem diretamente; pode mapear de outro campo
            $cvss = null;

            $stmtV = $pdo->prepare("INSERT INTO vulnerabilidades (scan_id, titulo, severidade, cve, cwe, cvss, descricao, prova, criado_em)
                                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())");
            $prova = json_encode($alert);
            $stmtV->execute([$scanId, $titulo, $severidade, $cve, $alert['cweid'] ?? '', $cvss, $descricao, $prova]);
        }
    }

    // 7) Gerar relatório (JSON) e gravar em arquivo + tabela relatorios
    $report = zap_generate_html_report(); // aqui retornamos JSON ou texto
    $filename = 'reports/report_' . $scanId . '_' . time() . '.json';
    if (!is_dir(dirname(__FILE__).'/reports')) mkdir(dirname(__FILE__).'/reports', 0755, true);
    file_put_contents(dirname(__FILE__).'/'.$filename, $report);

    $stmtR = $pdo->prepare("INSERT INTO relatorios (scan_id, arquivo_path, gerado_em) VALUES (?, ?, NOW())");
    $stmtR->execute([$scanId, $filename]);

    // 8) Atualiza scans como Concluído e calcula um score simples
    // Exemplo de cálculo simples: 10 * número de alerts de risco Alta/Crítica (ajuste conforme desejar)
    $criticalCount = 0;
    foreach ($alerts as $a) {
        if (in_array(strtolower($a['risk']), ['high','critical','alta','crítica'])) $criticalCount++;
    }
    $score = min(10, $criticalCount * 2.5); // exemplo
    $stmtUpd = $pdo->prepare("UPDATE scans SET status = 'Concluído', score_risco = ?, finalizado_em = NOW() WHERE id = ?");
    $stmtUpd->execute([$score, $scanId]);

    echo "Scan finalizado. Relatório salvo em: $filename\n";
} catch (Exception $e) {
    // marca scan como Erro se existir
    if (!empty($scanId ?? null)) {
        $stmtErr = $pdo->prepare("UPDATE scans SET status = 'Erro', finalizado_em = NOW() WHERE id = ?");
        $stmtErr->execute([$scanId]);
    }
    echo "Erro: " . $e->getMessage();
}
