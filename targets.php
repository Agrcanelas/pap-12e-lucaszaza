<?php
session_start();
require 'conexao.php';

// Verifica se usuário está logado
if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit();
}

$usuario_id = $_SESSION['usuario_id'];
$mensagem = "";
$mensagem_tipo = "";

// Adicionar target
if (isset($_POST['adicionar'])) {
    $novo_target = trim($_POST['novo_target']);
    $nome = trim($_POST['nome'] ?? '');
    $endereco = trim($_POST['endereco'] ?? '');
    
    if (!empty($novo_target)) {
        if (!filter_var($novo_target, FILTER_VALIDATE_URL) && !filter_var($novo_target, FILTER_VALIDATE_IP)) {
            $mensagem = "❌ URL ou IP inválido! Use formato: https://site.com ou 192.168.0.1";
            $mensagem_tipo = "danger";
        } else {
            try {
                $stmtInsert = $pdo->prepare("INSERT INTO targets (usuario_id, url_ip, nome, endereco, data_adicionado) VALUES (?, ?, ?, ?, NOW())");
                if ($stmtInsert->execute([$usuario_id, $novo_target, $nome, $endereco])) {
                    $mensagem = "✅ Target adicionado com sucesso!";
                    $mensagem_tipo = "success";
                    $pdo->prepare("INSERT INTO logs (usuario_id, acao, detalhes, criado_em) VALUES (?, 'target_adicionado', ?, NOW())")
                        ->execute([$usuario_id, json_encode(['url' => $novo_target])]);
                } else {
                    $mensagem = "❌ Erro ao adicionar target.";
                    $mensagem_tipo = "danger";
                }
            } catch (Exception $e) {
                $mensagem = "❌ Erro: " . $e->getMessage();
                $mensagem_tipo = "danger";
            }
        }
    } else {
        $mensagem = "❌ O campo URL/IP não pode ficar vazio.";
        $mensagem_tipo = "danger";
    }
}

// Remover target
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM scans WHERE target_id = ?");
        $stmt->execute([$id]);
        $totalScans = $stmt->fetchColumn();
        
        if ($totalScans > 0) {
            $mensagem = "⚠️ Este target possui $totalScans scan(s) associado(s). Remova os scans primeiro.";
            $mensagem_tipo = "warning";
        } else {
            $stmtDelete = $pdo->prepare("DELETE FROM targets WHERE id = ? AND usuario_id = ?");
            if ($stmtDelete->execute([$id, $usuario_id])) {
                $mensagem = "🗑️ Target removido com sucesso!";
                $mensagem_tipo = "success";
                $pdo->prepare("INSERT INTO logs (usuario_id, acao, detalhes, criado_em) VALUES (?, 'target_removido', ?, NOW())")
                    ->execute([$usuario_id, json_encode(['target_id' => $id])]);
            } else {
                $mensagem = "❌ Erro ao remover target.";
                $mensagem_tipo = "danger";
            }
        }
    } catch (Exception $e) {
        $mensagem = "❌ Erro: " . $e->getMessage();
        $mensagem_tipo = "danger";
    }
}

// Buscar targets
$stmtTargets = $pdo->prepare("
    SELECT t.id, t.url_ip, t.nome, t.endereco, t.data_adicionado,
           COUNT(s.id) as total_scans,
           MAX(s.iniciado_em) as ultimo_scan
    FROM targets t
    LEFT JOIN scans s ON t.id = s.target_id
    WHERE t.usuario_id = ?
    GROUP BY t.id
    ORDER BY t.data_adicionado DESC
");
$stmtTargets->execute([$usuario_id]);
$targets = $stmtTargets->fetchAll(PDO::FETCH_ASSOC);

$totalTargets = count($targets);
$totalScansGeral = array_sum(array_column($targets, 'total_scans'));
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestão de Targets - Secure Systems</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        body {
            background-color: #0d1117;
            color: #f0f6fc;
             font-family: 'Segoe UI', sans-serif;
        }

        /* --- NAVBAR PROFISSIONAL (ESTILO HOME) --- */
        /* --- NOVA NAVBAR PROFISSIONAL (IGUAL À HOME) --- */
        .navbar {
            background-color: rgba(22, 27, 34, 0.8) !important;
            backdrop-filter: blur(12px);
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
            text-decoration: none;
        }

        .navbar-brand span {
            color: #58a6ff;
        }

        .user-dropdown-toggle {
            background: #21262d;
            border: 1px solid #30363d;
            color: #f0f6fc !important;
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 0.85rem;
            font-weight: 500;
            transition: all 0.2s;
            text-decoration: none;
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

        .dropdown-divider { 
            border-top: 1px solid #30363d; 
        }

        /* --- SEUS ESTILOS ORIGINAIS (MANTIDOS) --- */
        /* Clarear o texto de exemplo (placeholder) */
.form-control::placeholder {
    color: #8b949e !important;
    opacity: 1;
}
/* Clarear labels e textos informativos */
.text-secondary, .text-muted {
    color: #8b949e !important; /* Um cinza claro que dá leitura no preto */
}
        .page-header {
            padding: 30px;
            border-radius: 12px;
            margin-bottom: 30px;
            position: relative;
            overflow: hidden;
            background: #161b22;
            border: 1px solid #30363d;
        }
        
        .stats-mini h4 { color: #58d68d; font-size: 2rem; margin-bottom: 5px; font-weight: bold; }
        .add-card {
            background: #161b22;
            border: 2px dashed #30363d;
            border-radius: 12px;
            padding: 30px;
            margin-bottom: 30px;
        }
        .target-card {
            background: #161b22;
            border: 1px solid #30363d;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 15px;
            transition: all 0.3s;
            position: relative;
            overflow: hidden;
        }
        .target-card::before {
            content: ''; position: absolute; left: 0; top: 0; bottom: 0; width: 4px;
            background: linear-gradient(135deg, #58a6ff 0%, #1f6feb 100%);
        }
        .target-url { font-size: 1.1rem; font-weight: 600; color: #f0f6fc; margin-bottom: 8px; display: flex; align-items: center; gap: 10px; }
        .btn-scan { background: linear-gradient(135deg, #238636 0%, #2ea043 100%); color: white; }
        .btn-delete { background: linear-gradient(135deg, #da3633 0%, #ff5555 100%); color: white; }
        .form-control { background-color: #0d1117; border: 2px solid #abb2b9; color: #f0f6fc; padding: 12px; }
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
                    <a class="nav-link dropdown-toggle user-dropdown-toggle d-flex align-items-center gap-2" href="#" role="button" data-bs-toggle="dropdown">
                        <i class="bi bi-person-circle"></i>
                        <span><?= htmlspecialchars($_SESSION['username']) ?></span>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="home.php"><i class="bi bi-house me-2"></i> Home</a></li>
                        <li><a class="dropdown-item" href="site_config.php"><i class="bi bi-gear me-2"></i> Configurações</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item text-danger" href="logout.php"><i class="bi bi-box-arrow-right me-2"></i> Sair</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </nav>

    <div class="container py-5">
        
        
            <h3>Gestão de Targets</h3>
            <p>Adicione e gerencie os alvos para análise de segurança</p>
        

        <?php if ($mensagem): ?>
            <div class="alert alert-<?= $mensagem_tipo ?> alert-dismissible fade show" role="alert" style="border-radius: 8px; border: none;">
                <?= $mensagem ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>


        <div class="add-card">
            <h5 style="color: #58a6ff;"><i class="fas fa-plus-circle me-2"></i>Adicionar Novo Target</h5>
            <form method="POST" action="targets.php">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label small text-secondary fw-bold">URL *</label>
                        <input type="text" name="novo_target" class="form-control" placeholder="https://exemplo.com" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small text-secondary fw-bold">Nome (Opcional)</label>
                        <input type="text" name="nome" class="form-control" placeholder="Ex: Site Principal">
                    </div>
                    <div class="col-md-8">
                        <label class="form-label small text-secondary fw-bold">Endereço (Opcional)</label>
                        <input type="text" name="endereco" class="form-control" placeholder="Ex: Servidor AWS">
                    </div>
                    <div class="col-md-4 d-flex align-items-end">
                        <button type="submit" name="adicionar" class="btn btn-scan w-100 fw-bold py-2">
                            <i class="fas fa-plus me-2"></i>Adicionar Target
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <div class="mb-3 fw-bold text-secondary">
            <i class="fas fa-list me-2"></i>Targets Cadastrados (<?= $totalTargets ?>)
        </div>

        <?php if (count($targets) > 0): ?>
            <?php foreach ($targets as $t): ?>
                <div class="target-card">
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <div class="target-url">
                                <i class="fas fa-bullseye"></i>
                                <?= htmlspecialchars($t['url_ip']) ?>
                                <?php if ($t['total_scans'] > 0): ?>
                                    <span class="badge bg-success" style="font-size: 0.7rem;"><?= $t['total_scans'] ?> SCANS</span>
                                <?php endif; ?>
                            </div>
                            <div class="text-secondary small">
                                <?php if (!empty($t['nome'])): ?> <span class="me-3"><i class="fas fa-tag"></i> <?= htmlspecialchars($t['nome']) ?></span> <?php endif; ?>
                                <?php if (!empty($t['endereco'])): ?> <span><i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($t['endereco']) ?></span> <?php endif; ?>
                            </div>
                            <div class="mt-2 text-muted" style="font-size: 0.75rem;">
                                <i class="fas fa-calendar-plus me-1"></i> Adicionado em: <?= date('d/m/Y H:i', strtotime($t['data_adicionado'])) ?>
                            </div>
                        </div>
                        <div class="col-md-4 text-end">
                            <a href="scans.php?target_id=<?= $t['id'] ?>" class="btn btn-scan btn-sm px-3 fw-bold">SCAN</a>
                            <a href="targets.php?delete=<?= $t['id'] ?>" class="btn btn-delete btn-sm px-3" onclick="return confirm('Excluir target?')"><i class="fas fa-trash"></i></a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="text-center py-5 text-muted">
                <i class="fas fa-bullseye fa-3x mb-3 opacity-25"></i>
                <h5>Nenhum target encontrado</h5>
            </div>
        <?php endif; ?>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>