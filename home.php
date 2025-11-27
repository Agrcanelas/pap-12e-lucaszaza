<?php
session_start();
require_once "conexao.php";

// Verifica se o usuário está logado
if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit;
}

$usuario_id = $_SESSION['usuario_id'];

// Buscar nome do usuário
$stmt = $pdo->prepare("SELECT nome_completo, username FROM usuarios WHERE id = ?");
$stmt->execute([$usuario_id]);
$usuario = $stmt->fetch(PDO::FETCH_ASSOC);

// Estatísticas
$stmt = $pdo->prepare("SELECT COUNT(*) FROM targets WHERE usuario_id = ?");
$stmt->execute([$usuario_id]);
$totalTargets = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM scans WHERE usuario_id = ?");
$stmt->execute([$usuario_id]);
$totalScans = $stmt->fetchColumn();

$stmt = $pdo->prepare("
    SELECT COUNT(v.id)
    FROM vulnerabilidades v
    JOIN scans s ON v.scan_id = s.id
    WHERE s.usuario_id = ? AND v.severidade = 'Crítica'
");
$stmt->execute([$usuario_id]);
$totalCriticas = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM scans WHERE usuario_id = ? AND status = 'Concluído'");
$stmt->execute([$usuario_id]);
$totalRelatorios = $stmt->fetchColumn();

// Scans em execução
$stmt = $pdo->prepare("SELECT COUNT(*) FROM scans WHERE usuario_id = ? AND status = 'Em execução'");
$stmt->execute([$usuario_id]);
$scansAtivos = $stmt->fetchColumn();

// Total de vulnerabilidades
$stmt = $pdo->prepare("
    SELECT COUNT(v.id)
    FROM vulnerabilidades v
    JOIN scans s ON v.scan_id = s.id
    WHERE s.usuario_id = ?
");
$stmt->execute([$usuario_id]);
$totalVulns = $stmt->fetchColumn();

// Últimos scans
$stmt = $pdo->prepare("
    SELECT s.id, s.status, s.score_risco, s.iniciado_em, t.url_ip, t.nome as target_nome
    FROM scans s
    JOIN targets t ON s.target_id = t.id
    WHERE s.usuario_id = ?
    ORDER BY s.iniciado_em DESC
    LIMIT 5
");
$stmt->execute([$usuario_id]);
$ultimosScans = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Vulnerabilidades recentes
$stmt = $pdo->prepare("
    SELECT v.titulo, v.severidade, v.criado_em, t.url_ip
    FROM vulnerabilidades v
    JOIN scans s ON v.scan_id = s.id
    JOIN targets t ON s.target_id = t.id
    WHERE s.usuario_id = ?
    ORDER BY v.criado_em DESC
    LIMIT 5
");
$stmt->execute([$usuario_id]);
$vulnsRecentes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Horário
$hora = date('H');
$saudacao = $hora < 12 ? 'Bom dia' : ($hora < 18 ? 'Boa tarde' : 'Boa noite');
?>

<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Dashboard - Secure Systems</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #0d1117;
            color: #f0f6fc;
            font-family: 'Segoe UI', sans-serif;
        }
        .navbar {
            background-color: #161b22;
            border-bottom: 1px solid #30363d;
        }
        .navbar-brand {
            font-weight: bold;
            color: #58a6ff !important;
        }
        .welcome-banner {
            
            padding: 40px;
            border-radius: 12px;
            margin-bottom: 30px;
            position: relative;
            overflow: hidden;
        }
        .welcome-banner::before {
            position: absolute;
            right: 40px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 5rem;
            opacity: 0.2;
        }
        .welcome-banner h2 {
            font-weight: bold;
            margin-bottom: 10px;
            position: relative;
            z-index: 1;
        }
        .welcome-banner p {
            margin: 0;
            opacity: 0.9;
            position: relative;
            z-index: 1;
        }
        .stats-card {
            background: #161b22;
            border: 1px solid #30363d;
            border-radius: 12px;
            padding: 25px;
            text-align: center;
            transition: all 0.3s;
            height: 100%;
            position: relative;
            overflow: hidden;
        }
        .stats-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #58a6ff 0%, #2ea043 100%);
            transform: scaleX(0);
            transition: transform 0.3s;
        }
        .stats-card:hover {
            transform: translateY(-5px);
            border-color: #58a6ff;
            box-shadow: 0 5px 20px rgba(88, 166, 255, 0.3);
        }
        .stats-card:hover::before {
            transform: scaleX(1);
        }
        .stats-icon {
            font-size: 3rem;
            margin-bottom: 15px;
        }
        .stats-number {
            font-size: 2.8rem;
            font-weight: bold;
            margin-bottom: 5px;
        }
        .stats-label {
            color: #8b949e;
            font-size: 0.95rem;
        }
        .color-blue { color: #58a6ff; }
        .color-green { color: #3fb950; }
        .color-orange { color: #f0883e; }
        .color-red { color: #f85149; }
        .color-purple { color: #bc8cff; }
        .color-yellow { color: #d29922; }
        .action-card {
            background: #161b22;
            border: 1px solid #30363d;
            border-radius: 12px;
            padding: 30px;
            text-align: center;
            transition: all 0.3s;
            cursor: pointer;
            height: 100%;
            text-decoration: none;
            color: inherit;
            display: block;
            position: relative;
            overflow: hidden;
        }
        .action-card::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 0;
            height: 0;
            border-radius: 50%;
            background: rgba(88, 166, 255, 0.1);
            transform: translate(-50%, -50%);
            transition: width 0.6s, height 0.6s;
        }
        .action-card:hover::before {
            width: 500px;
            height: 500px;
        }
        .action-card:hover {
            transform: scale(1.05);
            border-color: #58a6ff;
            box-shadow: 0 5px 20px rgba(88, 166, 255, 0.3);
        }
        .action-icon {
            font-size: 3rem;
            margin-bottom: 15px;
            position: relative;
            z-index: 1;
        }
        .action-card h5 {
            font-weight: bold;
            margin-bottom: 10px;
            position: relative;
            z-index: 1;
        }
        .action-card p {
            color: #8b949e;
            font-size: 0.9rem;
            position: relative;
            z-index: 1;
        }
        .section-title {
            color: #58a6ff;
            font-weight: 600;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .activity-card {
            background: #161b22;
            border: 1px solid #30363d;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .scan-item {
            background: #0d1117;
            border-radius: 8px;
            padding: 12px;
            margin-bottom: 10px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: all 0.3s;
        }
        .scan-item:hover {
            background: #161b22;
            transform: translateX(5px);
        }
        .scan-url {
            font-weight: 600;
            color: #f0f6fc;
        }
        .scan-status {
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        .status-concluido { background: rgba(63, 185, 80, 0.2); color: #3fb950; }
        .status-execucao { background: rgba(88, 166, 255, 0.2); color: #58a6ff; animation: pulse 2s infinite; }
        .status-erro { background: rgba(248, 81, 73, 0.2); color: #f85149; }
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }
        .vuln-item {
            background: #0d1117;
            border-left: 4px solid;
            border-radius: 6px;
            padding: 12px;
            margin-bottom: 10px;
            transition: all 0.3s;
        }
        .vuln-item:hover {
            transform: translateX(5px);
            background: #161b22;
        }
        .vuln-item.critica { border-color: #f85149; }
        .vuln-item.alta { border-color: #f0883e; }
        .vuln-item.média { border-color: #d29922; }
        .vuln-item.baixa { border-color: #3fb950; }
        .vuln-title {
            font-weight: 600;
            color: #f0f6fc;
            margin-bottom: 5px;
        }
        .vuln-meta {
            font-size: 0.85rem;
            color: #8b949e;
        }
        .badge-severidade {
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        .badge-crítica { background: #f85149; color: white; }
        .badge-alta { background: #f0883e; color: white; }
        .badge-média { background: #d29922; color: white; }
        .badge-baixa { background: #3fb950; color: white; }
        .empty-state {
            text-align: center;
            padding: 40px;
            color: #8b949e;
        }
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
                <a href="site_config.php" class="btn btn-outline-light btn-sm">⚙️</a>
                <a href="logout.php" class="btn btn-outline-danger btn-sm">Sair</a>
            </div>
        </div>
    </nav>

    <div class="container py-5">
        
        <!-- Welcome Banner -->
        <div class="welcome-banner">
            <h2><?= $saudacao ?>, <?= htmlspecialchars(explode(' ', $usuario['nome_completo'])[0]) ?>!</h2>
            <p>Bem-vindo(a) de volta. O que gostaria de realizar hoje?</p>
        </div>

        <div class="row mb-5 g-4">
            <div class="col-md-6 col-lg-4">
                <a href="targets.php" class="action-card">
                    <div class="action-icon">🎯</div>
                    <h5>Gestão de Targets</h5>
                    <p>Adicione e gerencie os sistemas a serem analisados</p>
                </a>
            </div>
            <div class="col-md-6 col-lg-4">
                <a href="scans.php" class="action-card">
                    <div class="action-icon">🔍</div>
                    <h5>Executar Scans</h5>
                    <p>Rode testes de segurança com OWASP ZAP</p>
                </a>
            </div>
            <div class="col-md-6 col-lg-4">
                <a href="relatorios.php" class="action-card">
                    <div class="action-icon">📊</div>
                    <h5>Relatórios</h5>
                    <p>Visualize dashboards e vulnerabilidades</p>
                </a>
            </div>
        </div>

        <div class="row">
            <!-- Últimos Scans -->
            <div class="col-lg-6 mb-4">
                <div class="activity-card">
                    <h5 class="section-title">🕐 Últimos Scans</h5>
                    
                    <?php if (empty($ultimosScans)): ?>
                        <div class="empty-state">
                            <p>Nenhum scan realizado ainda</p>
                            <a href="scans.php" class="btn btn-sm btn-outline-primary mt-2">Executar Scan</a>
                        </div>
                    <?php else: ?>
                        <?php foreach ($ultimosScans as $scan): ?>
                            <div class="scan-item">
                                <div>
                                    <div class="scan-url">
                                        <?= htmlspecialchars($scan['url_ip']) ?>
                                        <?php if ($scan['target_nome']): ?>
                                            <small style="color: #8b949e;">
                                                (<?= htmlspecialchars($scan['target_nome']) ?>)
                                            </small>
                                        <?php endif; ?>
                                    </div>
                                    <small style="color: #8b949e;">
                                        <?= date('d/m/Y H:i', strtotime($scan['iniciado_em'])) ?>
                                    </small>
                                </div>
                                <div>
                                    <?php
                                        $statusClass = [
                                            'Concluído' => 'status-concluido',
                                            'Em execução' => 'status-execucao',
                                            'Erro' => 'status-erro'
                                        ];
                                        $class = $statusClass[$scan['status']] ?? 'status-concluido';
                                    ?>
                                    <span class="scan-status <?= $class ?>">
                                        <?= $scan['status'] ?>
                                    </span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        <div class="text-center mt-3">
                            <a href="scans.php" class="btn btn-sm btn-outline-info">Ver Todos</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Vulnerabilidades Recentes -->
            <div class="col-lg-6 mb-4">
                <div class="activity-card">
                    <h5 class="section-title">⚠️ Vulnerabilidades Recentes</h5>
                    
                    <?php if (empty($vulnsRecentes)): ?>
                        <div class="empty-state">
                            <p>✅ Nenhuma vulnerabilidade detectada</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($vulnsRecentes as $vuln): ?>
                            <div class="vuln-item <?= strtolower($vuln['severidade']) ?>">
                                <div class="vuln-title">
                                    <?= htmlspecialchars($vuln['titulo']) ?>
                                    <span class="badge-severidade badge-<?= strtolower($vuln['severidade']) ?>">
                                        <?= $vuln['severidade'] ?>
                                    </span>
                                </div>
                                <div class="vuln-meta">
                                    📍 <?= htmlspecialchars($vuln['url_ip']) ?> • 
                                    🕐 <?= date('d/m/Y H:i', strtotime($vuln['criado_em'])) ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        <div class="text-center mt-3">
                            <a href="relatorios.php" class="btn btn-sm btn-outline-info">Ver Todos</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>