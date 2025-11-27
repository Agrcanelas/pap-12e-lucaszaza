<?php
session_start();
require_once "conexao.php";

if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit;
}

$id = $_SESSION['usuario_id'];

// Buscar dados do usuário
$stmt = $pdo->prepare("SELECT * FROM usuarios WHERE id = ?");
$stmt->execute([$id]);
$usuario = $stmt->fetch(PDO::FETCH_ASSOC);

// Estatísticas do usuário
$stmt = $pdo->prepare("SELECT COUNT(*) FROM targets WHERE usuario_id = ?");
$stmt->execute([$id]);
$totalTargets = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM scans WHERE usuario_id = ?");
$stmt->execute([$id]);
$totalScans = $stmt->fetchColumn();

$stmt = $pdo->prepare("
    SELECT COUNT(v.id)
    FROM vulnerabilidades v
    JOIN scans s ON v.scan_id = s.id
    WHERE s.usuario_id = ?
");
$stmt->execute([$id]);
$totalVulns = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT MIN(iniciado_em) FROM scans WHERE usuario_id = ?");
$stmt->execute([$id]);
$primeiroScan = $stmt->fetchColumn();

// Calcular dias desde o registro
$dataRegistro = new DateTime($usuario['data_nascimento'] ?? 'now');
$hoje = new DateTime();
$idade = $hoje->diff($dataRegistro)->y;
?>

<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Meu Perfil - Secure Systems</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #0d1117;
            color: #f0f6fc;
            font-family: 'Segoe UI', sans-serif;
            min-height: 100vh;
        }
        .navbar {
            background-color: #161b22;
            border-bottom: 1px solid #30363d;
        }
        .navbar-brand {
            font-weight: bold;
            color: #58a6ff !important;
        }
        .profile-header {
            background: linear-gradient(135deg, #1f6feb 0%, #58a6ff 100%);
            padding: 40px;
            border-radius: 12px;
            margin-bottom: 30px;
            position: relative;
            overflow: hidden;
        }
        .profile-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1440 320"><path fill="rgba(255,255,255,0.1)" d="M0,96L48,112C96,128,192,160,288,160C384,160,480,128,576,122.7C672,117,768,139,864,138.7C960,139,1056,117,1152,101.3C1248,85,1344,75,1392,69.3L1440,64L1440,320L1392,320C1344,320,1248,320,1152,320C1056,320,960,320,864,320C768,320,672,320,576,320C480,320,384,320,288,320C192,320,96,320,48,320L0,320Z"></path></svg>') bottom center no-repeat;
            background-size: cover;
            opacity: 0.3;
        }
        .profile-avatar {
            width: 120px;
            height: 120px;
            background: linear-gradient(135deg, #238636 0%, #2ea043 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3rem;
            margin: 0 auto 20px;
            border: 4px solid rgba(255,255,255,0.3);
            position: relative;
            z-index: 1;
        }
        .profile-name {
            font-size: 2rem;
            font-weight: bold;
            margin-bottom: 5px;
            text-align: center;
            position: relative;
            z-index: 1;
        }
        .profile-username {
            font-size: 1.2rem;
            opacity: 0.9;
            text-align: center;
            position: relative;
            z-index: 1;
        }
        .info-card {
            background: #161b22;
            border: 1px solid #30363d;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 20px;
            transition: transform 0.2s, border-color 0.2s;
        }
        .info-card:hover {
            transform: translateY(-2px);
            border-color: #58a6ff;
        }
        .info-card h5 {
            color: #58a6ff;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 1.3rem;
        }
        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 12px 0;
            border-bottom: 1px solid #30363d;
        }
        .info-row:last-child {
            border-bottom: none;
        }
        .info-label {
            color: #8b949e;
            font-weight: 600;
        }
        .info-value {
            color: #f0f6fc;
            font-weight: bold;
        }
        .stats-card {
            background: #161b22;
            border: 1px solid #30363d;
            border-radius: 12px;
            padding: 20px;
            text-align: center;
            transition: all 0.3s;
        }
        .stats-card:hover {
            border-color: #58a6ff;
            transform: scale(1.05);
        }
        .stats-number {
            font-size: 2.5rem;
            font-weight: bold;
            color: #58d68d;
            margin-bottom: 5px;
        }
        .stats-label {
            color: #8b949e;
            font-size: 0.9rem;
        }
        .btn-custom {
            padding: 12px 30px;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s;
            border: none;
        }
        .btn-edit {
            background: linear-gradient(135deg, #238636 0%, #2ea043 100%);
            color: white;
        }
        .btn-edit:hover {
            transform: scale(1.05);
            box-shadow: 0 5px 15px rgba(46, 160, 67, 0.4);
        }
        .btn-config {
            background: linear-gradient(135deg, #1f6feb 0%, #58a6ff 100%);
            color: white;
        }
        .btn-config:hover {
            transform: scale(1.05);
            box-shadow: 0 5px 15px rgba(88, 166, 255, 0.4);
        }
        .btn-logout {
            background: linear-gradient(135deg, #da3633 0%, #ff5555 100%);
            color: white;
        }
        .btn-logout:hover {
            transform: scale(1.05);
            box-shadow: 0 5px 15px rgba(218, 54, 51, 0.4);
        }
        .btn-back {
            background-color: #30363d;
            color: #f0f6fc;
        }
        .btn-back:hover {
            background-color: #484f58;
        }
        .badge-custom {
            background-color: #238636;
            color: white;
            padding: 5px 12px;
            border-radius: 12px;
            font-size: 0.85rem;
            font-weight: 600;
        }
        .activity-timeline {
            position: relative;
            padding-left: 30px;
        }
        .activity-item {
            position: relative;
            padding-bottom: 20px;
        }
        .activity-item::before {
            content: '';
            position: absolute;
            left: -23px;
            top: 5px;
            width: 10px;
            height: 10px;
            background-color: #58a6ff;
            border-radius: 50%;
        }
        .activity-item::after {
            content: '';
            position: absolute;
            left: -19px;
            top: 15px;
            width: 2px;
            height: calc(100% - 10px);
            background-color: #30363d;
        }
        .activity-item:last-child::after {
            display: none;
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
                <a href="home.php" class="btn btn-outline-info btn-sm">← Dashboard</a>
                <a href="logout.php" class="btn btn-outline-danger btn-sm">Sair</a>
            </div>
        </div>
    </nav>

    <div class="container py-5">
        
        <!-- Header do Perfil -->
        <div class="profile-header">
            <div class="profile-avatar">
                <?= strtoupper(substr($usuario['nome_completo'], 0, 1)) ?>
            </div>
            <div class="profile-name"><?= htmlspecialchars($usuario['nome_completo']) ?></div>
            <div class="profile-username">@<?= htmlspecialchars($usuario['username']) ?></div>
            <div class="text-center mt-3">
                <span class="badge-custom">✓ Conta Ativa</span>
            </div>
        </div>

        <div class="row">
            <!-- Coluna Esquerda -->
            <div class="col-lg-8">
                
                <!-- Informações Pessoais -->
                <div class="info-card">
                    <h5>👤 Informações Pessoais</h5>
                    
                    <div class="info-row">
                        <span class="info-label">Nome Completo</span>
                        <span class="info-value"><?= htmlspecialchars($usuario['nome_completo']) ?></span>
                    </div>
                    
                    <div class="info-row">
                        <span class="info-label">Email</span>
                        <span class="info-value"><?= htmlspecialchars($usuario['email']) ?></span>
                    </div>
                    
                    <div class="info-row">
                        <span class="info-label">Username</span>
                        <span class="info-value">@<?= htmlspecialchars($usuario['username']) ?></span>
                    </div>
                    
                    <div class="info-row">
                        <span class="info-label">Data de Nascimento</span>
                        <span class="info-value">
                            <?= date('d/m/Y', strtotime($usuario['data_nascimento'])) ?>
                            <small style="color: #8b949e;">(<?= $idade ?> anos)</small>
                        </span>
                    </div>
                    
                    <div class="info-row">
                        <span class="info-label">Cidade</span>
                        <span class="info-value"><?= htmlspecialchars($usuario['cidade']) ?></span>
                    </div>
                </div>

                <!-- Estatísticas de Atividade -->
                <div class="info-card">
                    <h5>📊 Estatísticas de Uso</h5>
                    
                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <div class="stats-card">
                                <div class="stats-number"><?= $totalTargets ?></div>
                                <div class="stats-label">Targets</div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="stats-card">
                                <div class="stats-number"><?= $totalScans ?></div>
                                <div class="stats-label">Scans</div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="stats-card">
                                <div class="stats-number"><?= $totalVulns ?></div>
                                <div class="stats-label">Vulnerabilidades</div>
                            </div>
                        </div>
                    </div>
                    
                    <?php if ($primeiroScan): ?>
                    <div class="info-row">
                        <span class="info-label">Primeiro Scan</span>
                        <span class="info-value"><?= date('d/m/Y H:i', strtotime($primeiroScan)) ?></span>
                    </div>
                    <?php endif; ?>
                </div>

            </div>

            <!-- Coluna Direita -->
            <div class="col-lg-4">
                
                <!-- Ações Rápidas -->
                <div class="info-card">
                    <h5>⚡ Ações Rápidas</h5>
                    
                    <a href="editar_perfil.php" class="btn btn-custom btn-edit w-100 mb-3">
                        ✏️ Editar Perfil
                    </a>
                    
                    <a href="site_config.php" class="btn btn-custom btn-config w-100 mb-3">
                        ⚙️ Configurações
                    </a>
                    
                    <a href="home.php" class="btn btn-custom btn-back w-100 mb-3">
                        ← Voltar ao Dashboard
                    </a>
                    
                    <a href="logout.php" class="btn btn-custom btn-logout w-100" onclick="return confirm('Deseja realmente sair?')">
                        🚪 Sair da Conta
                    </a>
                </div>

                <!-- Atividade Recente -->
                <div class="info-card">
                    <h5>🕐 Atividade Recente</h5>
                    
                    <div class="activity-timeline">
                        <?php
                        $stmt = $pdo->prepare("
                            SELECT acao, detalhes, criado_em 
                            FROM logs 
                            WHERE usuario_id = ? 
                            ORDER BY criado_em DESC 
                            LIMIT 5
                        ");
                        $stmt->execute([$id]);
                        $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        
                        if (empty($logs)) {
                            echo '<p style="color: #8b949e;">Nenhuma atividade registrada ainda.</p>';
                        } else {
                            foreach ($logs as $log) {
                                $acao_texto = str_replace('_', ' ', $log['acao']);
                                $acao_texto = ucfirst($acao_texto);
                                $tempo = date('d/m/Y H:i', strtotime($log['criado_em']));
                                
                                echo "<div class='activity-item'>";
                                echo "<div style='font-weight: 600; color: #f0f6fc;'>$acao_texto</div>";
                                echo "<div style='font-size: 0.85rem; color: #8b949e;'>$tempo</div>";
                                echo "</div>";
                            }
                        }
                        ?>
                    </div>
                </div>

                <!-- Segurança -->
                <div class="info-card">
                    <h5>🔐 Segurança</h5>
                    
                    <div class="info-row">
                        <span class="info-label">Última Senha Alterada</span>
                        <span class="info-value" style="font-size: 0.9rem;">Nunca</span>
                    </div>
                    
                    <a href="site_config.php" class="btn btn-custom btn-config w-100 mt-3" style="font-size: 0.9rem;">
                        🔑 Alterar Senha
                    </a>
                </div>

            </div>
        </div>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>