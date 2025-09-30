<?php
session_start();
require 'conexao.php';

// Verifica se o usuário está logado
if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit;
}

$usuario_id = $_SESSION['usuario_id'];

// Detecta automaticamente a coluna de URL/IP do target
$colUrl = null;
$stmt = $pdo->query("DESCRIBE targets");
$columns = $stmt->fetchAll(PDO::FETCH_COLUMN);

foreach ($columns as $col) {
    if (stripos($col, 'url') !== false || stripos($col, 'target') !== false) {
        $colUrl = $col;
        break;
    }
}

if (!$colUrl) {
    die("Não foi possível identificar a coluna de URL/IP na tabela targets.");
}

// Lista os targets do usuário
$stmtTargets = $pdo->prepare("SELECT id, `$colUrl` AS target FROM targets WHERE usuario_id = ?");
$stmtTargets->execute([$usuario_id]);
$targets = $stmtTargets->fetchAll(PDO::FETCH_ASSOC);

// Tipos de scanners disponíveis
$scanners = ['OWASP ZAP', 'Nikto', 'SQLMap'];

// Tratar envio do novo scan
$mensagem = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['target_id'], $_POST['scanner'])) {
    $target_id = intval($_POST['target_id']);
    $scanner = $_POST['scanner'];

    // Inserir novo scan com status "Em execução" e score_risco nulo
    $stmtInsert = $pdo->prepare("INSERT INTO scans (usuario_id, target_id, scanner, status, score_risco, iniciado_em) 
                                 VALUES (?, ?, ?, 'Em execução', NULL, NOW())");
    if ($stmtInsert->execute([$usuario_id, $target_id, $scanner])) {
        $mensagem = "✅ Scan iniciado com sucesso!";
    } else {
        $mensagem = "❌ Erro ao iniciar o scan.";
    }
}

// Consulta os scans do usuário
$sql = "SELECT s.id, s.scanner, s.status, s.score_risco AS risco, t.`$colUrl` AS target, s.iniciado_em
        FROM scans s
        JOIN targets t ON s.target_id = t.id
        WHERE s.usuario_id = ?
        ORDER BY s.iniciado_em DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute([$usuario_id]);
$scans = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>Executar Scans - Secure Systems</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #0d1117; color: #f0f6fc; font-family: 'Segoe UI', sans-serif; }
        .navbar { background-color: #161b22; border-bottom: 1px solid #30363d; }
        .navbar-brand { font-weight: bold; color: #58a6ff !important; }
        .dashboard-title { margin-bottom: 10px; font-size: 1.8rem; font-weight: bold; }
        .subtitle { color: #8b949e; }
        .scan-card { background: #161b22; border: 1px solid #30363d; border-radius: 12px; padding: 20px; transition: transform 0.2s ease; }
        .scan-card:hover { transform: scale(1.02); }
        .status-success { color: #58d68d; font-weight: bold; }
        .status-warning { color: #f1c40f; font-weight: bold; }
        .status-danger { color: #e74c3c; font-weight: bold; }
        .btn-scan { margin-bottom: 20px; }
        .form-select, .form-control { background-color: #161b22; color: #f0f6fc; border: 1px solid #30363d; }
        .form-select option, .form-control::placeholder { color: #8b949e; }
    </style>
</head>
<body>

<!-- Navbar -->
<nav class="navbar navbar-expand-lg navbar-dark px-4 py-3">
    <div class="container-fluid">
        <a class="navbar-brand" href="home.php">🔒 Secure Systems</a>
        <div class="d-flex align-items-center gap-3 ms-auto">
            <span class="text-light">👤 <?= htmlspecialchars($_SESSION['username']) ?></span>
            <a href="perfil.php" class="btn btn-outline-info btn-sm">Perfil</a>
            <a href="logout.php" class="btn btn-outline-danger btn-sm">Sair</a>
        </div>
    </div>
</nav>

<!-- Conteúdo -->
<div class="container py-5">
    <h3 class="dashboard-title">🛠️ Executar Scans</h3>
    <p class="subtitle">Escolha um target e um scanner para iniciar o teste.</p>

    <?php if($mensagem): ?>
        <div class="alert alert-info"><?= $mensagem ?></div>
    <?php endif; ?>

    <!-- Formulário Novo Scan -->
    <form method="POST" class="row g-3 mb-4">
        <div class="col-md-6">
            <label for="target" class="form-label">Target</label>
            <?php 
$targetSelecionado = isset($_GET['target_id']) ? intval($_GET['target_id']) : null;
?>
<select name="target_id" id="target" class="form-select" required>
    <option value="">-- Selecione --</option>
    <?php foreach($targets as $t): ?>
        <option value="<?= $t['id'] ?>" <?= ($targetSelecionado === (int)$t['id']) ? 'selected' : '' ?>>
            <?= htmlspecialchars($t['target']) ?>
        </option>
    <?php endforeach; ?>
</select>

        </div>
        <div class="col-md-6">
            <label for="scanner" class="form-label">Tipo de Scanner</label>
            <select name="scanner" id="scanner" class="form-select" required>
                <option value="">-- Selecione --</option>
                <?php foreach($scanners as $s): ?>
                    <option value="<?= htmlspecialchars($s) ?>"><?= htmlspecialchars($s) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-12">
            <button type="submit" class="btn btn-success btn-scan">🚀 Iniciar Scan</button>
        </div>
    </form>

    <!-- Exibir scans existentes -->
    <div class="row g-4 mt-2">
        <?php if(count($scans) > 0): ?>
            <?php foreach($scans as $scan): ?>
                <div class="col-md-6 col-lg-4">
                    <div class="scan-card">
                        <h5>🎯 <?= htmlspecialchars($scan['target']) ?></h5>
                        <p><strong>Scanner:</strong> <?= htmlspecialchars($scan['scanner']) ?></p>
                        <p>
                            <strong>Status:</strong>
                            <?php
                                $statusClass = match($scan['status']) {
                                    'Concluído' => 'status-success',
                                    'Em execução' => 'status-warning',
                                    default => 'status-danger'
                                };
                            ?>
                            <span class="<?= $statusClass ?>"><?= htmlspecialchars($scan['status']) ?></span>
                        </p>
                        <p>
                            <strong>Risco:</strong>
                            <?php
                                if(is_numeric($scan['risco'])){
                                    if($scan['risco'] >= 7) $riscoClass='status-danger';
                                    elseif($scan['risco'] >=4) $riscoClass='status-warning';
                                    else $riscoClass='status-success';
                                } else { $riscoClass=''; }
                            ?>
                            <span class="<?= $riscoClass ?>"><?= htmlspecialchars($scan['risco'] ?? 'N/A') ?></span>
                        </p>
                        <p><strong>Data:</strong> <?= date('d/m/Y H:i', strtotime($scan['iniciado_em'])) ?></p>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="col-12">
                <div class="alert alert-info">Nenhum scan executado até agora.</div>
            </div>
        <?php endif; ?>
    </div>

</div>

</body>
</html>
