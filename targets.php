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
        // Validação básica de URL/IP
        if (!filter_var($novo_target, FILTER_VALIDATE_URL) && !filter_var($novo_target, FILTER_VALIDATE_IP)) {
            $mensagem = "❌ URL ou IP inválido! Use formato: https://site.com ou 192.168.0.1";
            $mensagem_tipo = "danger";
        } else {
            try {
                $stmtInsert = $pdo->prepare("INSERT INTO targets (usuario_id, url_ip, nome, endereco, data_adicionado) VALUES (?, ?, ?, ?, NOW())");
                if ($stmtInsert->execute([$usuario_id, $novo_target, $nome, $endereco])) {
                    $mensagem = "✅ Target adicionado com sucesso!";
                    $mensagem_tipo = "success";
                    
                    // Log
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
        // Verifica se tem scans associados
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
                
                // Log
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

// Buscar targets do usuário com contagem de scans
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

// Estatísticas
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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
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
        .page-header {
            padding: 30px;
            border-radius: 12px;
            margin-bottom: 30px;
            position: relative;
            overflow: hidden;
        }
        .page-header::before {
            content: '🎯';
            position: absolute;
            right: 30px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 5rem;
            opacity: 0.2;
        }
        .page-header h3 {
            font-weight: bold;
            margin-bottom: 5px;
            position: relative;
            z-index: 1;
        }
        .page-header p {
            margin: 0;
            opacity: 0.9;
            position: relative;
            z-index: 1;
        }
        .stats-mini {
            background: #161b22;
            border: 1px solid #30363d;
            border-radius: 8px;
            padding: 20px;
            text-align: center;
            transition: all 0.3s;
        }
        .stats-mini:hover {
            border-color: #58a6ff;
            transform: translateY(-2px);
        }
        .stats-mini h4 {
            color: #58d68d;
            font-size: 2rem;
            margin-bottom: 5px;
            font-weight: bold;
        }
        .stats-mini p {
            color: #8b949e;
            margin: 0;
            font-size: 0.9rem;
        }
        .add-card {
            background: #161b22;
            border: 2px dashed #30363d;
            border-radius: 12px;
            padding: 30px;
            margin-bottom: 30px;
            transition: all 0.3s;
        }
        .add-card:hover {
            border-color: #58a6ff;
            background: #1c2128;
        }
        .add-card h5 {
            color: #58a6ff;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
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
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 4px;
            background: linear-gradient(135deg, #58a6ff 0%, #1f6feb 100%);
        }
        .target-card:hover {
            border-color: #58a6ff;
            transform: translateX(5px);
            box-shadow: 0 5px 20px rgba(88, 166, 255, 0.2);
        }
        .target-url {
            font-size: 1.1rem;
            font-weight: 600;
            color: #f0f6fc;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .target-url i {
            color: #58a6ff;
        }
        .target-info {
            color: #8b949e;
            font-size: 0.9rem;
            margin-bottom: 10px;
        }
        .target-meta {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            margin-top: 12px;
        }
        .meta-item {
            background: #0d1117;
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 0.85rem;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .meta-item i {
            color: #58a6ff;
        }
        .btn-action {
            padding: 8px 16px;
            border-radius: 6px;
            font-size: 0.9rem;
            border: none;
            transition: all 0.3s;
            font-weight: 600;
        }
        .btn-scan {
            background: linear-gradient(135deg, #238636 0%, #2ea043 100%);
            color: white;
        }
        .btn-scan:hover {
            transform: scale(1.05);
            box-shadow: 0 3px 12px rgba(46, 160, 67, 0.4);
        }
        .btn-delete {
            background: linear-gradient(135deg, #da3633 0%, #ff5555 100%);
            color: white;
        }
        .btn-delete:hover {
            transform: scale(1.05);
            box-shadow: 0 3px 12px rgba(218, 54, 51, 0.4);
        }
        .form-control, .form-select {
            background-color: #0d1117;
            border: 2px solid #30363d;
            color: #f0f6fc;
            padding: 12px;
            border-radius: 8px;
        }
        .form-control:focus, .form-select:focus {
            background-color: #0d1117;
            border-color: #58a6ff;
            color: #f0f6fc;
            box-shadow: 0 0 0 0.25rem rgba(88, 166, 255, 0.25);
        }
        .form-control::placeholder {
            color: #484f58;
        }
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #8b949e;
        }
        .empty-state i {
            font-size: 4rem;
            margin-bottom: 20px;
            opacity: 0.3;
        }
        .badge-scan {
            background-color: #238636;
            color: white;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        .alert-custom {
            border-radius: 8px;
            border: none;
            padding: 15px 20px;
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
                <a href="home.php" class="btn btn-outline-info btn-sm">← Home</a>
                <a href="logout.php" class="btn btn-outline-danger btn-sm">Sair</a>
            </div>
        </div>
    </nav>

    <div class="container py-5">
        
        <!-- Header -->
        <div class="page-header">
            <h3>Gestão de Targets</h3>
            <p>Adicione e gerencie os alvos para análise de segurança</p>
        </div>

        <!-- Mensagem -->
        <?php if ($mensagem): ?>
            <div class="alert alert-<?= $mensagem_tipo ?> alert-custom alert-dismissible fade show" role="alert">
                <?= $mensagem ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Estatísticas -->
        <div class="row mb-4 g-3">
            <div class="col-md-6">
                <div class="stats-mini">
                    <h4><?= $totalTargets ?></h4>
                    <p>Targets Cadastrados</p>
                </div>
            </div>
            <div class="col-md-6">
                <div class="stats-mini">
                    <h4><?= $totalScansGeral ?></h4>
                    <p>Scans Realizados</p>
                </div>
            </div>
        </div>

        <!-- Formulário Adicionar -->
        <div class="add-card">
            <h5><i class="fas fa-plus-circle"></i> Adicionar Novo Target</h5>
            <form method="POST" action="targets.php">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label" style="color: #8b949e; font-weight: 600;">
                            <i class="fas fa-link"></i> URL <span style="color: #ff5555;">*</span>
                        </label>
                        <input 
                            type="text" 
                            name="novo_target" 
                            class="form-control" 
                            placeholder="https://exemplo.com" 
                            required
                        >
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" style="color: #8b949e; font-weight: 600;">
                            <i class="fas fa-tag"></i> Nome (Opcional)
                        </label>
                        <input 
                            type="text" 
                            name="nome" 
                            class="form-control" 
                            placeholder="Ex: Site Principal"
                        >
                    </div>
                    <div class="col-md-8">
                        <label class="form-label" style="color: #8b949e; font-weight: 600;">
                            <i class="fas fa-map-marker-alt"></i> Endereço/Localização (Opcional)
                        </label>
                        <input 
                            type="text" 
                            name="endereco" 
                            class="form-control" 
                            placeholder="Ex: Servidor AWS - us-east-1"
                        >
                    </div>
                    <div class="col-md-4 d-flex align-items-end">
                        <button type="submit" name="adicionar" class="btn btn-action btn-scan w-100">
                            <i class="fas fa-plus"></i> Adicionar Target
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <!-- Lista de Targets -->
        <div class="mb-3" style="color: #8b949e; font-weight: 600;">
            <i class="fas fa-list"></i> Targets Cadastrados (<?= $totalTargets ?>)
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
                                    <span class="badge-scan"><?= $t['total_scans'] ?> scan(s)</span>
                                <?php endif; ?>
                            </div>
                            
                            <?php if (!empty($t['nome'])): ?>
                                <div class="target-info">
                                    <i class="fas fa-tag"></i> <?= htmlspecialchars($t['nome']) ?>
                                </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($t['endereco'])): ?>
                                <div class="target-info">
                                    <i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($t['endereco']) ?>
                                </div>
                            <?php endif; ?>
                            
                            <div class="target-meta">
                                <div class="meta-item">
                                    <i class="fas fa-calendar-plus"></i>
                                    Adicionado: <?= date('d/m/Y H:i', strtotime($t['data_adicionado'])) ?>
                                </div>
                                
                                <?php if ($t['ultimo_scan']): ?>
                                    <div class="meta-item">
                                        <i class="fas fa-clock"></i>
                                        Último scan: <?= date('d/m/Y H:i', strtotime($t['ultimo_scan'])) ?>
                                    </div>
                                <?php else: ?>
                                    <div class="meta-item" style="color: #8b949e;">
                                        <i class="fas fa-info-circle"></i>
                                        Nenhum scan realizado
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="col-md-4 text-end">
                            <div class="d-flex gap-2 justify-content-end">
                                <a href="scans.php?target_id=<?= $t['id'] ?>" class="btn btn-action btn-scan">
                                    <i class="fas fa-search"></i> Scan
                                </a>
                                <a 
                                    href="targets.php?delete=<?= $t['id'] ?>" 
                                    class="btn btn-action btn-delete"
                                    onclick="return confirm('⚠️ Tem certeza que deseja remover este target?\n\n<?= htmlspecialchars($t['url_ip']) ?>')"
                                >
                                    <i class="fas fa-trash"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-bullseye"></i>
                <h5>Nenhum target cadastrado</h5>
                <p>Adicione seu primeiro target usando o formulário acima</p>
            </div>
        <?php endif; ?>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>