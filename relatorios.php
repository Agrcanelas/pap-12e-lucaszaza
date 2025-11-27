<?php
session_start();
require 'conexao.php';

if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit;
}

$usuario_id = $_SESSION['usuario_id'];

// Apagar todos os relatórios
if (isset($_GET['apagar_tudo']) && $_GET['apagar_tudo'] === '1') {
    $stmt = $pdo->prepare("DELETE FROM scans WHERE usuario_id = ?");
    $stmt->execute([$usuario_id]);
    $_SESSION['mensagem_relat'] = "🗑️ Todos os relatórios foram apagados com sucesso!";
    header("Location: relatorios.php");
    exit;
}

// Obter lista de scans do usuário
$sql = "SELECT s.id, s.scanner, s.status, s.score_risco, s.iniciado_em, s.finalizado_em, t.url_ip, t.nome as target_nome,
        (SELECT COUNT(*) FROM vulnerabilidades WHERE scan_id = s.id) as total_vulns,
        (SELECT COUNT(*) FROM vulnerabilidades WHERE scan_id = s.id AND severidade = 'Crítica') as vulns_criticas
        FROM scans s
        JOIN targets t ON s.target_id = t.id
        WHERE s.usuario_id = ?
        ORDER BY s.iniciado_em DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute([$usuario_id]);
$scans = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Estatísticas gerais
$stmtStats = $pdo->prepare("SELECT COUNT(*) FROM scans WHERE usuario_id = ? AND status = 'Concluído'");
$stmtStats->execute([$usuario_id]);
$totalConcluidos = $stmtStats->fetchColumn();

$stmtStats = $pdo->prepare("
    SELECT COUNT(DISTINCT v.id)
    FROM vulnerabilidades v
    JOIN scans s ON v.scan_id = s.id
    WHERE s.usuario_id = ? AND v.severidade = 'Crítica'
");
$stmtStats->execute([$usuario_id]);
$totalCriticas = $stmtStats->fetchColumn();

$stmtStats = $pdo->prepare("
    SELECT COUNT(DISTINCT v.id)
    FROM vulnerabilidades v
    JOIN scans s ON v.scan_id = s.id
    WHERE s.usuario_id = ?
");
$stmtStats->execute([$usuario_id]);
$totalVulns = $stmtStats->fetchColumn();

// Verificar se o usuário clicou em um scan específico
$vulnerabilidades = [];
$scanSelecionado = null;

if (isset($_GET['scan_id'])) {
    $scan_id = intval($_GET['scan_id']);

    $stmt = $pdo->prepare("SELECT s.*, t.url_ip, t.nome as target_nome
                           FROM scans s 
                           JOIN targets t ON s.target_id = t.id 
                           WHERE s.id = ? AND s.usuario_id = ?");
    $stmt->execute([$scan_id, $usuario_id]);
    $scanSelecionado = $stmt->fetch(PDO::FETCH_ASSOC);

    $stmtVuln = $pdo->prepare("SELECT * FROM vulnerabilidades WHERE scan_id = ? ORDER BY 
        FIELD(severidade, 'Crítica', 'Alta', 'Média', 'Baixa')");
    $stmtVuln->execute([$scan_id]);
    $vulnerabilidades = $stmtVuln->fetchAll(PDO::FETCH_ASSOC);
    
    // Estatísticas do scan
    $vulnPorSeveridade = [
        'Crítica' => 0,
        'Alta' => 0,
        'Média' => 0,
        'Baixa' => 0
    ];
    foreach ($vulnerabilidades as $v) {
        if (isset($vulnPorSeveridade[$v['severidade']])) {
            $vulnPorSeveridade[$v['severidade']]++;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Relatórios - Secure Systems</title>
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
        .page-header {
            padding: 30px;
            border-radius: 12px;
            margin-bottom: 30px;
            position: relative;
            overflow: hidden;
        }
        .page-header::before {
            content: '📊';
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
            transform: translateY(-3px);
        }
        .stats-number {
            font-size: 2.5rem;
            font-weight: bold;
            margin-bottom: 5px;
        }
        .stats-label {
            color: #8b949e;
            font-size: 0.9rem;
        }
        .color-blue { color: #58a6ff; }
        .color-red { color: #f85149; }
        .color-orange { color: #f0883e; }
        .report-card {
            background: #161b22;
            border: 1px solid #30363d;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            transition: all 0.3s;
            position: relative;
            overflow: hidden;
        }
        .report-card::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 4px;
            background: linear-gradient(135deg, #58a6ff 0%, #1f6feb 100%);
        }
        .report-card:hover {
            border-color: #58a6ff;
            transform: translateX(5px);
            box-shadow: 0 5px 20px rgba(88, 166, 255, 0.2);
        }
        .report-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 15px;
        }
        .report-url {
            font-size: 1.1rem;
            font-weight: 600;
            color: #f0f6fc;
            margin-bottom: 5px;
        }
        .report-meta {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            margin-top: 10px;
        }
        .meta-item {
            background: #0d1117;
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 0.85rem;
        }
        .status-badge {
            padding: 6px 12px;
            border-radius: 12px;
            font-size: 0.85rem;
            font-weight: 600;
        }
        .status-concluido {
            background-color: rgba(63, 185, 80, 0.2);
            color: #3fb950;
            border: 1px solid #3fb950;
        }
        .status-erro {
            background-color: rgba(248, 81, 73, 0.2);
            color: #f85149;
            border: 1px solid #f85149;
        }
        .score-badge {
            padding: 8px 15px;
            border-radius: 8px;
            font-weight: bold;
            font-size: 1.1rem;
        }
        .score-baixo { background-color: rgba(63, 185, 80, 0.2); color: #3fb950; border: 2px solid #3fb950; }
        .score-medio { background-color: rgba(240, 136, 62, 0.2); color: #f0883e; border: 2px solid #f0883e; }
        .score-alto { background-color: rgba(248, 81, 73, 0.2); color: #f85149; border: 2px solid #f85149; }
        .vuln-card {
            background: #161b22;
            border: 1px solid #30363d;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 15px;
            transition: all 0.3s;
            position: relative;
            overflow: hidden;
        }
        .vuln-card::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 4px;
        }
        .vuln-card.critica::before { background: #f85149; }
        .vuln-card.alta::before { background: #f0883e; }
        .vuln-card.média::before { background: #d29922; }
        .vuln-card.baixa::before { background: #3fb950; }
        .vuln-card:hover {
            transform: translateX(5px);
            border-color: #58a6ff;
        }
        .vuln-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 15px;
        }
        .vuln-title {
            font-size: 1.2rem;
            font-weight: 600;
            color: #f0f6fc;
            margin-bottom: 5px;
        }
        .badge-severidade {
            padding: 6px 12px;
            border-radius: 12px;
            font-size: 0.85rem;
            font-weight: 600;
        }
        .badge-crítica { background-color: #f85149; color: white; }
        .badge-alta { background-color: #f0883e; color: white; }
        .badge-média { background-color: #d29922; color: white; }
        .badge-baixa { background-color: #3fb950; color: white; }
        .vuln-detail {
            background: #0d1117;
            border-radius: 8px;
            padding: 12px;
            margin-top: 10px;
            font-size: 0.9rem;
            color: #8b949e;
        }
        .vuln-detail strong {
            color: #58a6ff;
        }
        .btn-custom {
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s;
            border: none;
        }
        .btn-view {
            background: linear-gradient(135deg, #238636 0%, #2ea043 100%);
            color: white;
        }
        .btn-view:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(46, 160, 67, 0.4);
        }
        .btn-delete {
            background: linear-gradient(135deg, #da3633 0%, #f85149 100%);
            color: white;
        }
        .btn-delete:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(218, 54, 51, 0.4);
        }
        .btn-back {
            background-color: #30363d;
            color: #f0f6fc;
        }
        .btn-back:hover {
            background-color: #484f58;
        }
        .alert-custom {
            border-radius: 8px;
            border: none;
            padding: 15px 20px;
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
        .detail-card {
            background: #161b22;
            border: 1px solid #30363d;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 20px;
        }
        .detail-card h5 {
            color: #58a6ff;
            margin-bottom: 15px;
            font-weight: 600;
        }
        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
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
    </style>
    <script>
        function confirmarLimpeza() {
            if (confirm("⚠️ Tem certeza que deseja apagar TODOS os relatórios?\n\nEsta ação é IRREVERSÍVEL e removerá:\n• Todos os scans\n• Todas as vulnerabilidades\n• Todos os relatórios gerados")) {
                window.location.href = "relatorios.php?apagar_tudo=1";
            }
        }
    </script>
</head>
<body>

    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark px-4 py-3">
        <div class="container-fluid">
            <a class="navbar-brand" href="home.php">🔒 Secure Systems</a>
            <div class="ms-auto d-flex align-items-center gap-2">
                <?php if (!isset($_GET['scan_id'])): ?>
                    <button onclick="confirmarLimpeza()" class="btn btn-custom btn-delete btn-sm">
                        🗑️ Limpar Tudo
                    </button>
                <?php endif; ?>
                <a href="home.php" class="btn btn-outline-info btn-sm">← Dashboard</a>
                <a href="scans.php" class="btn btn-outline-light btn-sm">Scans</a>
                <a href="logout.php" class="btn btn-outline-danger btn-sm">Sair</a>
            </div>
        </div>
    </nav>

    <div class="container py-5">

        <!-- Mensagem -->
        <?php if (isset($_SESSION['mensagem_relat'])): ?>
            <div class="alert alert-success alert-custom alert-dismissible fade show">
                <?= htmlspecialchars($_SESSION['mensagem_relat']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['mensagem_relat']); ?>
        <?php endif; ?>

        <?php if (!$scanSelecionado): ?>
            <!-- LISTA DE RELATÓRIOS -->
            
            <!-- Header -->
            <div class="page-header">
                <h3>Relatórios de Segurança</h3>
                <p>Visualize e analise os resultados dos scans de vulnerabilidade</p>
            </div>

            <!-- Estatísticas -->
            <div class="row mb-4 g-3">
                <div class="col-md-4">
                    <div class="stats-card">
                        <div class="stats-number color-blue"><?= $totalConcluidos ?></div>
                        <div class="stats-label">Scans Concluídos</div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="stats-card">
                        <div class="stats-number color-orange"><?= $totalVulns ?></div>
                        <div class="stats-label">Vulnerabilidades Total</div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="stats-card">
                        <div class="stats-number color-red"><?= $totalCriticas ?></div>
                        <div class="stats-label">Vulnerabilidades Críticas</div>
                    </div>
                </div>
            </div>

            <!-- Lista de Relatórios -->
            <?php if (empty($scans)): ?>
                <div class="empty-state">
                    <i>📊</i>
                    <h5>Nenhum relatório disponível</h5>
                    <p>Execute scans em seus targets para gerar relatórios</p>
                    <a href="scans.php" class="btn btn-custom btn-view mt-3">
                        Executar Scan
                    </a>
                </div>
            <?php else: ?>
                <div class="row">
                    <?php foreach ($scans as $scan): ?>
                        <div class="col-md-6">
                            <div class="report-card">
                                <div class="report-header">
                                    <div style="flex: 1;">
                                        <div class="report-url">
                                            🎯 <?= htmlspecialchars($scan['url_ip']) ?>
                                            <?php if ($scan['target_nome']): ?>
                                                <small style="color: #8b949e;">
                                                    (<?= htmlspecialchars($scan['target_nome']) ?>)
                                                </small>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div>
                                        <span class="status-badge <?= $scan['status'] == 'Concluído' ? 'status-concluido' : 'status-erro' ?>">
                                            <?= htmlspecialchars($scan['status']) ?>
                                        </span>
                                    </div>
                                </div>

                                <div class="report-meta">
                                    <div class="meta-item">
                                        <span style="color: #8b949e;">Scanner:</span>
                                        <span style="color: #f0f6fc;"><?= htmlspecialchars($scan['scanner']) ?></span>
                                    </div>
                                    
                                    <?php if ($scan['score_risco'] !== null): ?>
                                        <?php
                                            $score = floatval($scan['score_risco']);
                                            $scoreClass = $score < 3 ? 'score-baixo' : ($score < 7 ? 'score-medio' : 'score-alto');
                                        ?>
                                        <div class="meta-item">
                                            <span class="score-badge <?= $scoreClass ?>">
                                                Score: <?= number_format($score, 1) ?>/10
                                            </span>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="meta-item">
                                        <span style="color: #8b949e;">Data:</span>
                                        <span style="color: #f0f6fc;">
                                            <?= date('d/m/Y H:i', strtotime($scan['iniciado_em'])) ?>
                                        </span>
                                    </div>
                                    
                                    <?php if ($scan['total_vulns'] > 0): ?>
                                        <div class="meta-item" style="background: rgba(248, 81, 73, 0.2);">
                                            <span style="color: #f85149;">
                                                ⚠️ <?= $scan['total_vulns'] ?> vulnerabilidade(s)
                                            </span>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($scan['vulns_criticas'] > 0): ?>
                                        <div class="meta-item" style="background: rgba(248, 81, 73, 0.3);">
                                            <span style="color: #f85149; font-weight: bold;">
                                                🔴 <?= $scan['vulns_criticas'] ?> crítica(s)
                                            </span>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <div class="mt-3">
                                    <a href="relatorios.php?scan_id=<?= $scan['id'] ?>" class="btn btn-custom btn-view btn-sm">
                                        📑 Ver Detalhes
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

        <?php else: ?>
            <!-- DETALHES DO RELATÓRIO -->
            
            <a href="relatorios.php" class="btn btn-custom btn-back mb-4">
                ⬅️ Voltar aos Relatórios
            </a>

            <div class="page-header">
                <h3>📋 Detalhes do Relatório</h3>
                <p><?= htmlspecialchars($scanSelecionado['url_ip']) ?></p>
            </div>

            <!-- Informações do Scan -->
            <div class="detail-card">
                <h5>ℹ️ Informações do Scan</h5>
                
                <div class="info-row">
                    <span class="info-label">Target</span>
                    <span class="info-value"><?= htmlspecialchars($scanSelecionado['url_ip']) ?></span>
                </div>
                
                <?php if ($scanSelecionado['target_nome']): ?>
                    <div class="info-row">
                        <span class="info-label">Nome</span>
                        <span class="info-value"><?= htmlspecialchars($scanSelecionado['target_nome']) ?></span>
                    </div>
                <?php endif; ?>
                
                <div class="info-row">
                    <span class="info-label">Scanner</span>
                    <span class="info-value"><?= htmlspecialchars($scanSelecionado['scanner']) ?></span>
                </div>
                
                <div class="info-row">
                    <span class="info-label">Status</span>
                    <span class="info-value">
                        <span class="status-badge <?= $scanSelecionado['status'] == 'Concluído' ? 'status-concluido' : 'status-erro' ?>">
                            <?= htmlspecialchars($scanSelecionado['status']) ?>
                        </span>
                    </span>
                </div>
                
                <?php if ($scanSelecionado['score_risco'] !== null): ?>
                    <div class="info-row">
                        <span class="info-label">Score de Risco</span>
                        <span class="info-value">
                            <?php
                                $score = floatval($scanSelecionado['score_risco']);
                                $scoreClass = $score < 3 ? 'score-baixo' : ($score < 7 ? 'score-medio' : 'score-alto');
                            ?>
                            <span class="score-badge <?= $scoreClass ?>">
                                <?= number_format($score, 1) ?>/10
                            </span>
                        </span>
                    </div>
                <?php endif; ?>
                
                <div class="info-row">
                    <span class="info-label">Iniciado em</span>
                    <span class="info-value"><?= date('d/m/Y H:i:s', strtotime($scanSelecionado['iniciado_em'])) ?></span>
                </div>
                
                <?php if ($scanSelecionado['finalizado_em']): ?>
                    <div class="info-row">
                        <span class="info-label">Finalizado em</span>
                        <span class="info-value"><?= date('d/m/Y H:i:s', strtotime($scanSelecionado['finalizado_em'])) ?></span>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Estatísticas de Vulnerabilidades -->
            <?php if (!empty($vulnerabilidades)): ?>
                <div class="row mb-4 g-3">
                    <div class="col-md-3">
                        <div class="stats-card">
                            <div class="stats-number" style="color: #f85149;"><?= $vulnPorSeveridade['Crítica'] ?></div>
                            <div class="stats-label">Críticas</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stats-card">
                            <div class="stats-number" style="color: #f0883e;"><?= $vulnPorSeveridade['Alta'] ?></div>
                            <div class="stats-label">Altas</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stats-card">
                            <div class="stats-number" style="color: #d29922;"><?= $vulnPorSeveridade['Média'] ?></div>
                            <div class="stats-label">Médias</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stats-card">
                            <div class="stats-number" style="color: #3fb950;"><?= $vulnPorSeveridade['Baixa'] ?></div>
                            <div class="stats-label">Baixas</div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Lista de Vulnerabilidades -->
            <h5 style="color: #58a6ff; margin-bottom: 20px;">⚠️ Vulnerabilidades Detectadas (<?= count($vulnerabilidades) ?>)</h5>

            <?php if (empty($vulnerabilidades)): ?>
                <div class="alert alert-success alert-custom">
                    ✅ Nenhuma vulnerabilidade encontrada neste scan! O target está seguro.
                </div>
            <?php else: ?>
                <?php foreach ($vulnerabilidades as $v): ?>
                    <div class="vuln-card <?= strtolower($v['severidade']) ?>">
                        <div class="vuln-header">
                            <div class="vuln-title">
                                ⚠️ <?= htmlspecialchars($v['titulo']) ?>
                            </div>
                            <span class="badge-severidade badge-<?= strtolower($v['severidade']) ?>">
                                <?= htmlspecialchars($v['severidade']) ?>
                            </span>
                        </div>

                        <div class="vuln-detail">
                            <p style="color: #f0f6fc; margin-bottom: 10px;">
                                <strong>Descrição:</strong><br>
                                <?= nl2br(htmlspecialchars($v['descricao'])) ?>
                            </p>

                            <div class="row g-2 mt-2">
                                <?php if ($v['cve']): ?>
                                    <div class="col-md-6">
                                        <strong>CVE:</strong> <?= htmlspecialchars($v['cve']) ?>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if ($v['cwe']): ?>
                                    <div class="col-md-6">
                                        <strong>CWE:</strong> <?= htmlspecialchars($v['cwe']) ?>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if ($v['cvss']): ?>
                                    <div class="col-md-6">
                                        <strong>CVSS:</strong> <?= htmlspecialchars($v['cvss']) ?>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="col-md-6">
                                    <strong>Detectado em:</strong> <?= date('d/m/Y H:i', strtotime($v['criado_em'])) ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>

        <?php endif; ?>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>