<?php
session_start();
require 'conexao.php';

// Verifica se o usuário está logado
if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit;
}

$usuario_id = $_SESSION['usuario_id'];

<<<<<<< HEAD
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
=======
// Buscar todos os scans do usuário
$stmt = $pdo->prepare("
    SELECT s.*, t.url_ip, t.nome as target_nome 
    FROM scans s 
    JOIN targets t ON s.target_id = t.id 
    WHERE s.usuario_id = ? 
    ORDER BY s.iniciado_em DESC
");
$stmt->execute([$usuario_id]);
$scans = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Buscar targets disponíveis para novo scan
$stmtTargets = $pdo->prepare("SELECT id, url_ip, nome FROM targets WHERE usuario_id = ?");
$stmtTargets->execute([$usuario_id]);
$targets = $stmtTargets->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Scans de Segurança - Painel</title>
    <link rel="stylesheet" href="style.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
            color: #fff;
            min-height: 100vh;
            padding: 20px;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        h1 {
            text-align: center;
            color: #0dcaf0;
            margin-bottom: 10px;
            font-size: 2.5em;
        }
        .subtitle {
            text-align: center;
            color: #aaa;
            margin-bottom: 40px;
        }
        .mensagem {
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            text-align: center;
            font-weight: bold;
        }
        .mensagem.sucesso {
            background-color: rgba(13, 202, 240, 0.2);
            border: 2px solid #0dcaf0;
            color: #0dcaf0;
        }
        .mensagem.erro {
            background-color: rgba(255, 85, 85, 0.2);
            border: 2px solid #ff5555;
            color: #ff5555;
        }
        .card {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 30px;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
        }
        .card h2 {
            color: #0dcaf0;
            margin-bottom: 20px;
            font-size: 1.8em;
        }
        .form-group {
            margin-bottom: 20px;
        }
        label {
            display: block;
            margin-bottom: 8px;
            color: #0dcaf0;
            font-weight: 600;
        }
        select {
            width: 100%;
            padding: 12px;
            border-radius: 8px;
            background-color: rgba(255, 255, 255, 0.1);
            color: #fff;
            border: 2px solid rgba(13, 202, 240, 0.3);
            font-size: 16px;
            transition: all 0.3s;
        }
        select:focus {
            outline: none;
            border-color: #0dcaf0;
            background-color: rgba(255, 255, 255, 0.15);
        }
        select option {
            background-color: #1a1a2e;
            color: #fff;
        }
        .btn {
            padding: 14px 30px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-block;
            text-decoration: none;
        }
        .btn-primary {
            background: linear-gradient(135deg, #0dcaf0 0%, #0a9fc7 100%);
            color: #fff;
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(13, 202, 240, 0.4);
        }
        .btn-secondary {
            background: rgba(255, 255, 255, 0.1);
            color: #fff;
            border: 2px solid rgba(255, 255, 255, 0.3);
        }
        .btn-secondary:hover {
            background: rgba(255, 255, 255, 0.2);
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        thead {
            background: rgba(13, 202, 240, 0.2);
        }
        th, td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        th {
            color: #0dcaf0;
            font-weight: 600;
        }
        tbody tr {
            transition: background 0.3s;
        }
        tbody tr:hover {
            background: rgba(255, 255, 255, 0.05);
        }
        .status {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: bold;
            display: inline-block;
        }
        .status.pendente {
            background: rgba(255, 193, 7, 0.2);
            color: #ffc107;
            border: 1px solid #ffc107;
        }
        .status.execucao {
            background: rgba(13, 202, 240, 0.2);
            color: #0dcaf0;
            border: 1px solid #0dcaf0;
            animation: pulse 2s infinite;
        }
        .status.concluido {
            background: rgba(40, 167, 69, 0.2);
            color: #28a745;
            border: 1px solid #28a745;
        }
        .status.erro {
            background: rgba(220, 53, 69, 0.2);
            color: #dc3545;
            border: 1px solid #dc3545;
        }
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }
        .score {
            font-weight: bold;
            font-size: 1.2em;
        }
        .score.baixo { color: #28a745; }
        .score.medio { color: #ffc107; }
        .score.alto { color: #ff5555; }
        .empty-state {
            text-align: center;
            padding: 40px;
            color: #888;
        }
        .empty-state i {
            font-size: 60px;
            margin-bottom: 20px;
            opacity: 0.5;
        }
>>>>>>> 16e81cb (CODIGO FUNCIONANDOOOOO)
    </style>
</head>
<body>

<<<<<<< HEAD
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
=======
<div class="container">
    <h1>🛡️ Scans de Segurança</h1>
    <p class="subtitle">Execute varreduras automatizadas com OWASP ZAP</p>

    <?php if (isset($_SESSION['mensagem_scan'])): ?>
        <div class="mensagem <?php echo (strpos($_SESSION['mensagem_scan'], '✅') !== false) ? 'sucesso' : 'erro'; ?>">
            <?php 
                echo $_SESSION['mensagem_scan']; 
                unset($_SESSION['mensagem_scan']);
            ?>
        </div>
    <?php endif; ?>

    <!-- Formulário para Novo Scan -->
    <div class="card">
        <h2>🚀 Iniciar Novo Scan</h2>
        
        <?php if (empty($targets)): ?>
            <div class="empty-state">
                <p>⚠️ Você precisa adicionar um alvo primeiro!</p>
                <a href="targets.php" class="btn btn-primary" style="margin-top: 15px;">Adicionar Alvo</a>
            </div>
        <?php else: ?>
            <form method="POST" action="processar_scan.php">
                <div class="form-group">
                    <label for="target_id">Selecione um alvo:</label>
                    <select name="target_id" id="target_id" required>
                        <option value="">-- Escolha um target --</option>
                        <?php foreach ($targets as $target): ?>
                            <option value="<?php echo $target['id']; ?>">
                                <?php echo htmlspecialchars($target['url_ip']); ?>
                                <?php if ($target['nome']): ?>
                                    (<?php echo htmlspecialchars($target['nome']); ?>)
                                <?php endif; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary">🔍 Iniciar Scan</button>
            </form>
        <?php endif; ?>
    </div>

    <!-- Histórico de Scans -->
    <div class="card">
        <h2>📊 Histórico de Scans</h2>
        
        <?php if (empty($scans)): ?>
            <div class="empty-state">
                <p>Nenhum scan realizado ainda.</p>
            </div>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Alvo</th>
                        <th>Scanner</th>
                        <th>Status</th>
                        <th>Score</th>
                        <th>Iniciado em</th>
                        <th>Finalizado em</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($scans as $scan): ?>
                        <tr>
                            <td>#<?php echo $scan['id']; ?></td>
                            <td>
                                <strong><?php echo htmlspecialchars($scan['url_ip']); ?></strong>
                                <?php if ($scan['target_nome']): ?>
                                    <br><small style="color: #888;"><?php echo htmlspecialchars($scan['target_nome']); ?></small>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($scan['scanner']); ?></td>
                            <td>
                                <span class="status <?php echo strtolower(str_replace(['Pendente', 'Em execução', 'Concluído', 'Erro'], ['pendente', 'execucao', 'concluido', 'erro'], $scan['status'])); ?>">
                                    <?php echo $scan['status']; ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($scan['score_risco'] !== null): ?>
                                    <?php 
                                        $score = floatval($scan['score_risco']);
                                        $classe = $score < 3 ? 'baixo' : ($score < 7 ? 'medio' : 'alto');
                                    ?>
                                    <span class="score <?php echo $classe; ?>">
                                        <?php echo number_format($score, 1); ?>/10
                                    </span>
                                <?php else: ?>
                                    <span style="color: #888;">-</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo date('d/m/Y H:i', strtotime($scan['iniciado_em'])); ?></td>
                            <td>
                                <?php if ($scan['finalizado_em']): ?>
                                    <?php echo date('d/m/Y H:i', strtotime($scan['finalizado_em'])); ?>
                                <?php else: ?>
                                    <span style="color: #888;">-</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <div style="text-align: center; margin-top: 30px;">
        <a href="home.php" class="btn btn-secondary">← Voltar ao Painel</a>
    </div>
</div>

</body>
</html>
>>>>>>> 16e81cb (CODIGO FUNCIONANDOOOOO)
