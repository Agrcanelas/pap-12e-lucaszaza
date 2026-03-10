<?php
session_start();
require_once "conexao.php";

if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit;
}

$usuario_id = $_SESSION['usuario_id'];

// --- BUSCAS NO BANCO (Mantidas) ---
$stmt = $pdo->prepare("SELECT nome_completo, username FROM usuarios WHERE id = ?");
$stmt->execute([$usuario_id]);
$usuario = $stmt->fetch(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare("SELECT COUNT(*) FROM targets WHERE usuario_id = ?");
$stmt->execute([$usuario_id]);
$totalTargets = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM scans WHERE usuario_id = ?");
$stmt->execute([$usuario_id]);
$totalScans = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(v.id) FROM vulnerabilidades v JOIN scans s ON v.scan_id = s.id WHERE s.usuario_id = ? AND v.severidade = 'Crítica'");
$stmt->execute([$usuario_id]);
$totalCriticas = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT s.id, s.status, s.iniciado_em, t.url_ip, t.nome as target_nome FROM scans s JOIN targets t ON s.target_id = t.id WHERE s.usuario_id = ? ORDER BY s.iniciado_em DESC LIMIT 5");
$stmt->execute([$usuario_id]);
$ultimosScans = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare("SELECT v.titulo, v.severidade, v.criado_em, t.url_ip FROM vulnerabilidades v JOIN scans s ON v.scan_id = s.id JOIN targets t ON s.target_id = t.id WHERE s.usuario_id = ? ORDER BY v.criado_em DESC LIMIT 5");
$stmt->execute([$usuario_id]);
$vulnsRecentes = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    
    <style>
        body {
            background-color: #0d1117;
            color: #f0f6fc;
            font-family: 'Segoe UI', sans-serif;
        }

        /* --- NOVA NAVBAR PROFISSIONAL --- */
        .navbar {
            background-color: rgba(22, 27, 34, 0.8) !important;
            backdrop-filter: blur(12px); /* Efeito de vidro */
            -webkit-backdrop-filter: blur(12px);
            border-bottom: 1px solid #30363d;
            padding: 0.75rem 1.5rem;
            position: sticky;
            top: 0;
            z-index: 1000;
        }

        .navbar-brand {
            font-weight: 700;
            letter-spacing: -0.5px;
            color: #f0f6fc !important;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .navbar-brand span {
            color: #58a6ff;
        }

        /* Estilo do Dropdown de Usuário */
        .user-dropdown-toggle {
            background: #21262d;
            border: 1px solid #30363d;
            color: #f0f6fc !important;
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 0.85rem;
            font-weight: 500;
            transition: all 0.2s;
        }

        .user-dropdown-toggle:hover {
            background: #30363d;
            border-color: #8b949e;
        }

        .dropdown-menu {
            background-color: #161b22;
            border: 1px solid #30363d;
            box-shadow: 0 8px 24px rgba(0,0,0,0.5);
            margin-top: 10px !important;
        }

        .dropdown-item {
            color: #c9d1d9;
            font-size: 0.9rem;
            padding: 8px 20px;
        }

        .dropdown-item:hover {
            background-color: #1f242c;
            color: #58a6ff;
        }

        .dropdown-divider { border-top: 1px solid #30363d; }

        /* --- MANTENDO O RESTO IGUAL --- */
        .welcome-banner {
            padding: 40px;
            border-radius: 12px;
            margin-bottom: 30px;
            background: linear-gradient(135deg, #161b22 0%, #0d1117 100%);
            border: 1px solid #30363d;
        }

        .stats-card, .action-card, .activity-card {
            background: #161b22;
            border: 1px solid #30363d;
            border-radius: 12px;
            padding: 25px;
            transition: all 0.3s;
        }

        .action-card { text-decoration: none; color: inherit; display: block; text-align: center; }
        .action-card:hover { transform: translateY(-5px); border-color: #58a6ff; box-shadow: 0 5px 20px rgba(88, 166, 255, 0.2); }
        .action-icon { font-size: 2.5rem; margin-bottom: 10px; }

        .scan-item, .vuln-item {
            background: #0d1117;
            border-radius: 8px;
            padding: 12px;
            margin-bottom: 10px;
            border: 1px solid #30363d;
        }

        .section-title { color: #58a6ff; font-weight: 600; margin-bottom: 20px; }
        .status-concluido { background: rgba(63, 185, 80, 0.1); color: #3fb950; padding: 4px 10px; border-radius: 12px; font-size: 0.8rem; }
    </style>
</head>
<body>

    <nav class="navbar navbar-expand-lg">
        <div class="container-fluid">
            <a class="navbar-brand" href="home.php">
                <i class="bi bi-shield-lock-fill"></i> 
                SECURE<span>SYSTEMS</span>
            </a>

            <div class="ms-auto d-flex align-items-center gap-3">
                <div class="dropdown">
                    <a class="nav-link dropdown-toggle user-dropdown-toggle d-flex align-items-center gap-2" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="bi bi-person-circle"></i>
                        <span><?= htmlspecialchars($usuario['username']) ?></span>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="perfil.php"><i class="bi bi-person me-2"></i> Meu Perfil</a></li>
                        <li><a class="dropdown-item" href="site_config.php"><i class="bi bi-gear me-2"></i> Configurações</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item text-danger" href="logout.php"><i class="bi bi-box-arrow-right me-2"></i> Sair</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </nav>

    <div class="container py-5">
        
        
            <h2 class="fw-bold"><?= $saudacao ?>, <?= htmlspecialchars(explode(' ', $usuario['nome_completo'])[0]) ?>!</h2>
            <p class="text-secondary">O seu ambiente de segurança está estável. Confira os últimos registros abaixo.</p>
        

        <div class="row mb-5 g-4">
            <div class="col-md-4">
                <a href="targets.php" class="action-card">
                    <div class="action-icon">🎯</div>
                    <h5>Gestão de Targets</h5>
                    <p class="text-secondary small">Monitore domínios ativos</p>
                </a>
            </div>
            <div class="col-md-4">
                <a href="scans.php" class="action-card">
                    <div class="action-icon text-info">🔍</div>
                    <h5>Executar Scans</h5>
                    <p class="text-secondary small">Inicie testes automáticos com OWASP</p>
                </a>
            </div>
            <div class="col-md-4">
                <a href="relatorios.php" class="action-card">
                    <div class="action-icon text-warning">📊</div>
                    <h5>Relatórios</h5>
                    <p class="text-secondary small">Analise falhas e vulnerabilidades</p>
                </a>
            </div>
        </div>

        
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>