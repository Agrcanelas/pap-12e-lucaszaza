<?php
// Configurações do ZAP
$zapApiKey = "12345";
$zapHost = "127.0.0.1";
$zapPort = "8080";

// URL do alvo (exemplo: http://testphp.vulnweb.com)
$target = $_GET['url'] ?? null;

if (!$target) {
    die("Erro: nenhum alvo informado.");
}

$zapApiUrl = "http://$zapHost:$zapPort";

// 1. Iniciar o scan ativo
$scanStart = file_get_contents("$zapApiUrl/JSON/ascan/action/scan/?apikey=$zapApiKey&url=" . urlencode($target));
$scanId = json_decode($scanStart, true)['scan'] ?? null;

if (!$scanId) {
    die("Erro ao iniciar o scan.");
}

echo "<h3>Scan iniciado para $target (ID $scanId)</h3>";

// 2. Acompanhar o progresso
do {
    sleep(5);
    $status = file_get_contents("$zapApiUrl/JSON/ascan/view/status/?scanId=$scanId");
    $status = json_decode($status, true)['status'];
    echo "Progresso: $status%<br>";
    ob_flush(); flush();
} while ($status < 100);

echo "<h3>Scan concluído!</h3>";

// 3. Obter alertas
$alerts = file_get_contents("$zapApiUrl/JSON/core/view/alerts/?baseurl=" . urlencode($target));
$alerts = json_decode($alerts, true)['alerts'] ?? [];

if (count($alerts) > 0) {
    echo "<h3>Vulnerabilidades encontradas:</h3>";
    foreach ($alerts as $a) {
        echo "<b>{$a['alert']}</b> - {$a['risk']}<br>";
    }
} else {
    echo "<p>Nenhuma vulnerabilidade encontrada.</p>";
}
?>
