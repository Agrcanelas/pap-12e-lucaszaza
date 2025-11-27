<?php
session_start();
require_once "conexao.php";

// Verifica se o usuário está logado
if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit;
}

$usuario_id = $_SESSION['usuario_id'];

// Buscar os targets disponíveis
$stmtTargets = $pdo->prepare("SELECT id, url_ip, nome FROM targets WHERE usuario_id = ?");
$stmtTargets->execute([$usuario_id]);
$targets = $stmtTargets->fetchAll(PDO::FETCH_ASSOC);

// Buscar histórico de scans
$stmtScans = $pdo->prepare("
    SELECT s.*, t.url_ip, t.nome as target_nome,
           (SELECT COUNT(*) FROM vulnerabilidades v WHERE v.scan_id = s.id) as total_vulns
    FROM scans s
    JOIN targets t ON s.target_id = t.id
    WHERE s.usuario_id = ?
    ORDER BY s.iniciado_em DESC
    LIMIT 10
");
$stmtScans->execute([$usuario_id]);
$scans = $stmtScans->fetchAll(PDO::FETCH_ASSOC);

// Estatísticas
$stmtStats = $pdo->prepare("SELECT COUNT(*) FROM scans WHERE usuario_id = ?");
$stmtStats->execute([$usuario_id]);
$totalScans = $stmtStats->fetchColumn();

$stmtStats = $pdo->prepare("SELECT COUNT(*) FROM scans WHERE usuario_id = ? AND status = 'Em execução'");
$stmtStats->execute([$usuario_id]);
$scansAtivos = $stmtStats->fetchColumn();

$stmtStats = $pdo->prepare("
    SELECT COUNT(DISTINCT v.id)
    FROM vulnerabilidades v
    JOIN scans s ON v.scan_id = s.id
    WHERE s.usuario_id = ?
");
$stmtStats->execute([$usuario_id]);
$totalVulns = $stmtStats->fetchColumn();

$stmtStats = $pdo->prepare("
    SELECT AVG(score_risco)
    FROM scans
    WHERE usuario_id = ? AND score_risco IS NOT NULL
");
$stmtStats->execute([$usuario_id]);
$scoreMedio = round($stmtStats->fetchColumn(), 1);
?>

<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Scans de Segurança - Secure Systems</title>
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
            content: '🔍';
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
            height: 100%;
        }
        .stats-card:hover {
            border-color: #58a6ff;
            transform: translateY(-3px);
            box-shadow: 0 5px 20px rgba(88, 166, 255, 0.2);
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
        .color-green { color: #3fb950; }
        .color-blue { color: #58a6ff; }
        .color-orange { color: #f0883e; }
        .color-purple { color: #bc8cff; }
        .scan-form-card {
            background: #161b22;
            border: 2px solid #30363d;
            border-radius: 12px;
            padding: 30px;
            margin-bottom: 30px;
            transition: all 0.3s;
        }
        .scan-form-card:hover {
            border-color: #238636;
        }
        .scan-form-card h5 {
            color: #3fb950;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 1.3rem;
        }
        .form-label {
            color: #8b949e;
            font-weight: 600;
            margin-bottom: 8px;
        }
        .form-select {
            background-color: #0d1117;
            border: 2px solid #30363d;
            color: #f0f6fc;
            padding: 12px;
            border-radius: 8px;
        }
        .form-select:focus {
            background-color: #0d1117;
            border-color: #58a6ff;
            color: #f0f6fc;
            box-shadow: 0 0 0 0.25rem rgba(88, 166, 255, 0.25);
        }
        .btn-scan {
            background: linear-gradient(135deg, #238636 0%, #2ea043 100%);
            color: white;
            padding: 14px 30px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 1rem;
            transition: all 0.3s;
        }
        .btn-scan:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(46, 160, 67, 0.4);
        }
        .btn-scan:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }
        .scan-card {
            background: #161b22;
            border: 1px solid #30363d;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 15px;
            transition: all 0.3s;
            position: relative;
        }
        .scan-card:hover {
            border-color: #58a6ff;
            transform: translateX(5px);
        }
        .scan-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 15px;
        }
        .scan-id {
            font-size: 0.9rem;
            color: #8b949e;
        }
        .scan-url {
            font-size: 1.1rem;
            font-weight: 600;
            color: #f0f6fc;
            margin-bottom: 5px;
        }
        .scan-meta {
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
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .status-badge {
            padding: 6px 12px;
            border-radius: 12px;
            font-size: 0.85rem;
            font-weight: 600;
        }
        .status-pendente {
            background-color: rgba(240, 136, 62, 0.2);
            color: #f0883e;
            border: 1px solid #f0883e;
        }
        .status-execucao {
            background-color: rgba(88, 166, 255, 0.2);
            color: #58a6ff;
            border: 1px solid #58a6ff;
            animation: pulse 2s infinite;
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
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
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
        .info-box {
            background: linear-gradient(135deg, rgba(88, 166, 255, 0.1) 0%, rgba(31, 111, 235, 0.1) 100%);
            border: 1px solid #58a6ff;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
        }
        .info-box-title {
            color: #58a6ff;
            font-weight: 600;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .info-box-text {
            color: #8b949e;
            font-size: 0.9rem;
        }

        /* MODAL DE LOADING */
        .modal-loading {
            background: rgba(13, 17, 23, 0.98);
            backdrop-filter: blur(10px);
        }
        .modal-loading .modal-content {
            background: #161b22;
            border: 2px solid #30363d;
            border-radius: 16px;
        }
        .spinner-container {
            display: flex;
            justify-content: center;
            margin: 30px 0;
        }
        .spinner {
            width: 80px;
            height: 80px;
            border: 6px solid #30363d;
            border-top-color: #3fb950;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        .progress-container {
            position: relative;
            height: 12px;
            background: #0d1117;
            border-radius: 10px;
            overflow: hidden;
            margin: 20px 0;
        }
        .progress-bar-custom {
            height: 100%;
            background: linear-gradient(90deg, #238636, #3fb950, #238636);
            background-size: 200% 100%;
            animation: progressGlow 2s ease-in-out infinite, progressMove 15s linear infinite;
            border-radius: 10px;
            width: 0%;
        }
        @keyframes progressGlow {
            0%, 100% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
        }
        @keyframes progressMove {
            0% { width: 0%; }
            100% { width: 95%; }
        }
        .scan-info-item {
            background: #0d1117;
            padding: 15px;
            border-radius: 8px;
            border: 1px solid #30363d;
            text-align: center;
        }
        .scan-info-label {
            color: #8b949e;
            font-size: 0.85rem;
            margin-bottom: 5px;
        }
        .scan-info-value {
            color: #f0f6fc;
            font-weight: 600;
            font-size: 1.1rem;
        }
        .timer {
            font-family: 'Courier New', monospace;
            font-size: 1.5rem;
            color: #3fb950;
            font-weight: bold;
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
            <h3>Scans de Segurança</h3>
            <p>Execute varreduras automatizadas com OWASP ZAP e monitore vulnerabilidades</p>
        </div>

        <!-- Mensagem -->
        <?php if (isset($_SESSION['mensagem_scan'])): ?>
            <?php
                $msg = $_SESSION['mensagem_scan'];
                $tipo = (strpos($msg, '✅') !== false) ? 'success' : 'danger';
            ?>
            <div class="alert alert-<?= $tipo ?> alert-custom alert-dismissible fade show">
                <?= $msg ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['mensagem_scan']); ?>
        <?php endif; ?>



        <div class="row">
            <!-- Coluna Esquerda: Formulário -->
            <div class="col-lg-5">
                
                <!-- Info Box -->
                <div class="info-box">
                    <div class="info-box-title">
                        💡 Como funciona
                    </div>
                    <div class="info-box-text">
                        1. Selecione um target cadastrado<br>
                        2. Clique em "Iniciar Scan"<br>
                        3. Aguarde a análise (pode levar 10-15 minutos)<br>
                        4. Confira os resultados no histórico
                    </div>
                </div>

                <!-- Formulário -->
                <div class="scan-form-card">
                    <h5>🚀 Iniciar Novo Scan</h5>

                    <?php if (empty($targets)): ?>
                        <div class="alert alert-warning" style="background: rgba(240, 136, 62, 0.15); border: 1px solid #f0883e; color: #f0883e;">
                            ⚠️ Nenhum target cadastrado!
                        </div>
                        <a href="targets.php" class="btn btn-scan w-100">
                            + Adicionar Target
                        </a>
                    <?php else: ?>
                        <form method="POST" action="processar_scan.php" id="formScan">
                            <div class="mb-4">
                                <label for="target_id" class="form-label">
                                    🎯 Selecione um alvo:
                                </label>
                                <select name="target_id" id="target_id" class="form-select" required>
                                    <option value="">-- Escolha um target --</option>
                                    <?php foreach ($targets as $target): ?>
                                        <option value="<?= $target['id'] ?>" data-url="<?= htmlspecialchars($target['url_ip']) ?>">
                                            <?= htmlspecialchars($target['url_ip']) ?>
                                            <?php if ($target['nome']): ?>
                                                - <?= htmlspecialchars($target['nome']) ?>
                                            <?php endif; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <button type="submit" class="btn btn-scan w-100">
                                🔍 Iniciar Scan
                            </button>
                        </form>

                        <div class="mt-3" style="font-size: 0.85rem; color: #8b949e; text-align: center;">
                            ⏱️ Tempo estimado: 10-15 minutos
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Botões Adicionais -->
                <div class="d-grid gap-2">
                    <a href="targets.php" class="btn btn-secondary">
                        🎯 Gerenciar Targets
                    </a>
                    <a href="relatorios.php" class="btn btn-secondary">
                        📊 Ver Relatórios
                    </a>
                </div>

            </div>

            <!-- Coluna Direita: Histórico -->
            <div class="col-lg-7">
                
                <div class="mb-3" style="color: #8b949e; font-weight: 600;">
                    📜 Histórico de Scans (últimos 10)
                </div>

                <?php if (empty($scans)): ?>
                    <div class="empty-state">
                        <i>🔍</i>
                        <h5>Nenhum scan realizado ainda</h5>
                        <p>Inicie seu primeiro scan usando o formulário ao lado</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($scans as $scan): ?>
                        <div class="scan-card">
                            <div class="scan-header">
                                <div style="flex: 1;">
                                    <div class="scan-id">Scan #<?= $scan['id'] ?></div>
                                    <div class="scan-url">
                                        <?= htmlspecialchars($scan['url_ip']) ?>
                                        <?php if ($scan['target_nome']): ?>
                                            <small style="color: #8b949e;">
                                                (<?= htmlspecialchars($scan['target_nome']) ?>)
                                            </small>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div>
                                    <?php
                                        $statusClass = [
                                            'Pendente' => 'status-pendente',
                                            'Em execução' => 'status-execucao',
                                            'Concluído' => 'status-concluido',
                                            'Erro' => 'status-erro'
                                        ];
                                        $class = $statusClass[$scan['status']] ?? 'status-pendente';
                                    ?>
                                    <span class="status-badge <?= $class ?>">
                                        <?= $scan['status'] ?>
                                    </span>
                                </div>
                            </div>

                            <div class="scan-meta">
                                <div class="meta-item">
                                    <span style="color: #8b949e;">Scanner:</span>
                                    <span style="color: #f0f6fc;"><?= htmlspecialchars($scan['scanner']) ?></span>
                                </div>
                                
                                <div class="meta-item">
                                    <span style="color: #8b949e;">Iniciado:</span>
                                    <span style="color: #f0f6fc;">
                                        <?= date('d/m/Y H:i', strtotime($scan['iniciado_em'])) ?>
                                    </span>
                                </div>
                                
                                <?php if ($scan['finalizado_em']): ?>
                                    <div class="meta-item">
                                        <span style="color: #8b949e;">Finalizado:</span>
                                        <span style="color: #f0f6fc;">
                                            <?= date('d/m/Y H:i', strtotime($scan['finalizado_em'])) ?>
                                        </span>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if ($scan['total_vulns'] > 0): ?>
                                    <div class="meta-item" style="background: rgba(248, 81, 73, 0.2);">
                                        <span style="color: #f85149;">⚠️ <?= $scan['total_vulns'] ?> vulnerabilidade(s)</span>
                                    </div>
                                <?php endif; ?>
                                
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
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>

            </div>
        </div>

    </div>

    <!-- MODAL DE LOADING -->
    <div class="modal fade modal-loading" id="modalScan" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-body p-5">
                    <div class="text-center mb-4">
                        <h3 style="color: #3fb950; font-weight: bold; margin-bottom: 10px;">
                            🔍 Scan em Andamento
                        </h3>
                        <p style="color: #8b949e;">
                            Analisando <span id="targetUrl" style="color: #58a6ff; font-weight: 600;">...</span>
                        </p>
                    </div>

                    <div class="spinner-container">
                        <div class="spinner"></div>
                    </div>


                    <div class="scan-info-item mt-4">
                        <div class="scan-info-label">⏱️ Tempo Decorrido</div>
                        <div class="scan-info-value timer" id="timer">00:00</div>
                    </div>

                    <div class="text-center mt-4">
                        <div style="color: #f0883e; font-weight: 600; margin-bottom: 10px;">
                            🔥 Processando em background...
                        </div>
                        <small style="color: #8b949e;">
                            💡 Este processo pode levar até 15 minutos.<br>
                            A página recarregará automaticamente quando concluir.
                        </small>
                    </div>

                    <div class="alert alert-info mt-4" style="background: rgba(88, 166, 255, 0.1); border: 1px solid #58a6ff; color: #58a6ff;">
                        <strong>📌 Importante:</strong> Não feche esta aba durante o scan!
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let timerInterval;
        let segundos = 0;

        // Intercepta o submit do formulário
        document.getElementById('formScan')?.addEventListener('submit', function(e) {
            // Pega a URL do target selecionado
            const select = document.getElementById('target_id');
            const targetUrl = select.options[select.selectedIndex].getAttribute('data-url');
            
            // Atualiza o modal com a URL
            document.getElementById('targetUrl').textContent = targetUrl;

            // Abre o modal
            const modal = new bootstrap.Modal(document.getElementById('modalScan'));
            modal.show();

            // Inicia o timer
            segundos = 0;
            timerInterval = setInterval(function() {
                segundos++;
                const minutos = Math.floor(segundos / 60);
                const segs = segundos % 60;
                document.getElementById('timer').textContent = 
                    String(minutos).padStart(2, '0') + ':' + String(segs).padStart(2, '0');
            }, 1000);

            // Inicia verificação automática (a cada 10 segundos verifica se terminou)
            const checkInterval = setInterval(function() {
                fetch(window.location.href, {
                    method: 'HEAD'
                }).then(() => {
                    // Após 60 segundos, começa a verificar se o scan terminou
                    if (segundos > 60) {
                        // Força reload para ver se apareceu na lista
                        clearInterval(checkInterval);
                        clearInterval(timerInterval);
                        window.location.reload();
                    }
                });
            }, 10000); // Verifica a cada 10 segundos

            // Após 15 minutos, força reload
            setTimeout(function() {
                clearInterval(timerInterval);
                clearInterval(checkInterval);
                window.location.reload();
            }, 900000); // 15 minutos = 900000ms

            // NÃO previne o submit - deixa o formulário ser enviado normalmente
            // O PHP vai processar em background
        });
    </script>

</body>
</html>