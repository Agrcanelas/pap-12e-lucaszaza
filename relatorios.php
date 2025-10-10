<?php
session_start();
<<<<<<< HEAD
include 'conexao.php';

// Verifica login
if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit();
=======
require 'conexao.php';

if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit;
>>>>>>> 16e81cb (CODIGO FUNCIONANDOOOOO)
}

$usuario_id = $_SESSION['usuario_id'];

<<<<<<< HEAD
// Buscar todos os scans do usuário
$stmt = $pdo->prepare("SELECT s.*, t.target FROM scans s JOIN targets t ON s.target_id = t.id WHERE s.usuario_id = ? ORDER BY s.data_executado DESC");
$stmt->execute([$usuario_id]);
$scans = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Buscar relatório detalhado se for solicitado
$scanDetalhe = null;
if (isset($_GET['id'])) {
    $scan_id = $_GET['id'];
    $stmt = $pdo->prepare("SELECT s.*, t.target FROM scans s JOIN targets t ON s.target_id = t.id WHERE s.id = ? AND s.usuario_id = ?");
    $stmt->execute([$scan_id, $usuario_id]);
    $scanDetalhe = $stmt->fetch(PDO::FETCH_ASSOC);
=======
// Obter lista de scans do usuário
$sql = "SELECT s.id, s.scanner, s.status, s.score_risco, s.iniciado_em, s.finalizado_em, t.url_ip
        FROM scans s
        JOIN targets t ON s.target_id = t.id
        WHERE s.usuario_id = ?
        ORDER BY s.iniciado_em DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute([$usuario_id]);
$scans = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Verificar se o usuário clicou em um scan específico
$vulnerabilidades = [];
$scanSelecionado = null;

if (isset($_GET['scan_id'])) {
    $scan_id = intval($_GET['scan_id']);

    // Pegar info do scan
    $stmt = $pdo->prepare("SELECT s.*, t.url_ip 
                           FROM scans s 
                           JOIN targets t ON s.target_id = t.id 
                           WHERE s.id = ? AND s.usuario_id = ?");
    $stmt->execute([$scan_id, $usuario_id]);
    $scanSelecionado = $stmt->fetch(PDO::FETCH_ASSOC);

    // Buscar vulnerabilidades desse scan
    $stmtVuln = $pdo->prepare("SELECT * FROM vulnerabilidades WHERE scan_id = ? ORDER BY severidade DESC");
    $stmtVuln->execute([$scan_id]);
    $vulnerabilidades = $stmtVuln->fetchAll(PDO::FETCH_ASSOC);
>>>>>>> 16e81cb (CODIGO FUNCIONANDOOOOO)
}
?>

<!DOCTYPE html>
<html lang="pt">
<<<<<<< HEAD

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Relatórios | Secure Systems</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <style>
    body {
      background-color: #0d1117;
      color: #f0f6fc;
      font-family: 'Segoe UI', sans-serif;
      margin: 0;
      padding: 0;
    }

    .container {
      max-width: 1000px;
      margin: 4rem auto;
      padding: 2rem;
      background: #161b22;
      border-radius: 12px;
      border: 1px solid #30363d;
      box-shadow: 0 0 20px rgba(88, 166, 255, 0.1);
    }

    h1 {
      text-align: center;
      color: #58a6ff;
      margin-bottom: 1.5rem;
    }

    table {
      width: 100%;
      border-collapse: collapse;
      margin-top: 1rem;
    }

    th, td {
      padding: 0.9rem;
      border-bottom: 1px solid #30363d;
      text-align: left;
    }

    th {
      background-color: #21262d;
      color: #58a6ff;
    }

    tr:hover {
      background-color: #1c2128;
    }

    a {
      color: #58a6ff;
      text-decoration: none;
      transition: 0.3s;
    }

    a:hover {
      text-decoration: underline;
    }

    .detail-box {
      margin-top: 2rem;
      padding: 1.5rem;
      background: #0d1117;
      border: 1px solid #30363d;
      border-radius: 10px;
    }

    .detail-box h2 {
      color: #3fb950;
      margin-bottom: 1rem;
    }

    .detail-box pre {
      background: #161b22;
      padding: 1rem;
      border-radius: 8px;
      overflow-x: auto;
      color: #c9d1d9;
      font-size: 0.95rem;
    }

    .back-link {
      margin-top: 1.5rem;
      text-align: center;
    }

    .back-link a {
      color: #8b949e;
    }

    .back-link a:hover {
      color: #58a6ff;
    }

    .export-buttons {
      margin-top: 1rem;
      text-align: right;
    }

    .btn {
      display: inline-block;
      padding: 0.6rem 1.2rem;
      margin-left: 0.5rem;
      border: none;
      border-radius: 6px;
      background: #58a6ff;
      color: #fff;
      font-weight: bold;
      cursor: pointer;
      transition: 0.3s;
    }

    .btn:hover {
      background: #1f6feb;
      box-shadow: 0 0 10px rgba(88, 166, 255, 0.5);
    }

  </style>
</head>

<body>
  <div class="container">
    <h1><i class="fas fa-file-alt"></i> Relatórios de Scans</h1>

    <!-- Lista de scans -->
    <table>
      <thead>
        <tr>
          <th>ID</th>
          <th>Target</th>
          <th>Scanner</th>
          <th>Status</th>
          <th>Data</th>
          <th>Ações</th>
        </tr>
      </thead>
      <tbody>
        <?php if (count($scans) > 0): ?>
          <?php foreach ($scans as $s): ?>
            <tr>
              <td><?= $s['id'] ?></td>
              <td><?= htmlspecialchars($s['target']) ?></td>
              <td><?= htmlspecialchars($s['tipo_scan']) ?></td>
              <td><?= $s['status'] ?></td>
              <td><?= $s['data_executado'] ?></td>
              <td><a href="relatorios.php?id=<?= $s['id'] ?>">📄 Ver Detalhes</a></td>
            </tr>
          <?php endforeach; ?>
        <?php else: ?>
          <tr>
            <td colspan="6" style="text-align:center; color:#8b949e;">Nenhum relatório disponível.</td>
          </tr>
        <?php endif; ?>
      </tbody>
    </table>

    <!-- Relatório detalhado -->
    <?php if ($scanDetalhe): ?>
      <div class="detail-box">
        <h2>📌 Relatório Detalhado - Scan #<?= $scanDetalhe['id'] ?></h2>
        <p><strong>Target:</strong> <?= htmlspecialchars($scanDetalhe['target']) ?></p>
        <p><strong>Scanner:</strong> <?= htmlspecialchars($scanDetalhe['tipo_scan']) ?></p>
        <p><strong>Status:</strong> <?= $scanDetalhe['status'] ?></p>
        <p><strong>Data:</strong> <?= $scanDetalhe['data_executado'] ?></p>

        <h3>Resultado:</h3>
        <pre><?= htmlspecialchars($scanDetalhe['resultado']) ?></pre>

        <div class="export-buttons">
          <button class="btn">⬇ Exportar PDF</button>
          <button class="btn">⬇ Exportar CSV</button>
        </div>
      </div>
    <?php endif; ?>

    <div class="back-link">
      <p><a href="home.php">⬅ Voltar ao painel</a></p>
    </div>
  </div>
=======
<head>
    <meta charset="UTF-8">
    <title>Relatórios - Secure Systems</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #0d1117; color: #f0f6fc; font-family: 'Segoe UI', sans-serif; }
        .navbar { background-color: #161b22; border-bottom: 1px solid #30363d; }
        .navbar-brand { font-weight: bold; color: #58a6ff !important; }
        .report-card { background: #161b22; border: 1px solid #30363d; border-radius: 12px; padding: 20px; margin-bottom: 20px; }
        .status-success { color: #3fb950; }
        .status-warning { color: #f1c40f; }
        .status-danger { color: #e74c3c; }
        .vuln-card { background: #1c2128; border: 1px solid #30363d; border-radius: 10px; padding: 15px; margin-bottom: 10px; }
        .badge-baixa { background-color: #2ea043; }
        .badge-média { background-color: #d29922; }
        .badge-alta { background-color: #f85149; }
        .badge-crítica { background-color: #b62324; }
        .btn-voltar { background-color: #21262d; color: #58a6ff; border: 1px solid #30363d; }
        .btn-voltar:hover { background-color: #30363d; }
    </style>
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark px-4 py-3">
    <div class="container-fluid">
        <a class="navbar-brand" href="home.php">🔒 Secure Systems</a>
        <div class="ms-auto">
            <a href="scans.php" class="btn btn-outline-light btn-sm me-2">Voltar aos Scans</a>
            <a href="logout.php" class="btn btn-outline-danger btn-sm">Sair</a>
        </div>
    </div>
</nav>

<div class="container py-5">

    <?php if (!$scanSelecionado): ?>
        <h3>📊 Relatórios de Scans</h3>
        <p class="text-secondary">Selecione um scan abaixo para visualizar as vulnerabilidades detectadas.</p>

        <div class="row">
            <?php foreach ($scans as $scan): ?>
                <div class="col-md-6">
                    <div class="report-card">
                        <h5>🎯 <?= htmlspecialchars($scan['url_ip']) ?></h5>
                        <p><strong>Scanner:</strong> <?= htmlspecialchars($scan['scanner']) ?></p>
                        <p>
                            <strong>Status:</strong>
                            <span class="<?= $scan['status'] == 'Concluído' ? 'status-success' : 'status-warning' ?>">
                                <?= htmlspecialchars($scan['status']) ?>
                            </span>
                        </p>
                        <p><strong>Score de Risco:</strong> <?= htmlspecialchars($scan['score_risco'] ?? 'N/A') ?></p>
                        <p><strong>Data:</strong> <?= date('d/m/Y H:i', strtotime($scan['iniciado_em'])) ?></p>
                        <a href="relatorios.php?scan_id=<?= $scan['id'] ?>" class="btn btn-primary btn-sm">📑 Ver Relatório</a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

    <?php else: ?>
        <a href="relatorios.php" class="btn btn-voltar mb-3">⬅️ Voltar aos Relatórios</a>
        <h3>📋 Relatório de Vulnerabilidades</h3>
        <p><strong>Target:</strong> <?= htmlspecialchars($scanSelecionado['url_ip']) ?></p>
        <p><strong>Scanner:</strong> <?= htmlspecialchars($scanSelecionado['scanner']) ?></p>
        <p><strong>Score de Risco:</strong> <?= htmlspecialchars($scanSelecionado['score_risco']) ?></p>
        <p><strong>Executado em:</strong> <?= date('d/m/Y H:i', strtotime($scanSelecionado['iniciado_em'])) ?></p>

        <hr>

        <?php if (empty($vulnerabilidades)): ?>
            <div class="alert alert-success">✅ Nenhuma vulnerabilidade encontrada neste scan.</div>
        <?php else: ?>
            <?php foreach ($vulnerabilidades as $v): ?>
                <div class="vuln-card">
                    <h5>
                        ⚠️ <?= htmlspecialchars($v['titulo']) ?>
                        <span class="badge badge-<?= strtolower($v['severidade']) ?>">
                            <?= htmlspecialchars($v['severidade']) ?>
                        </span>
                    </h5>
                    <p><strong>Descrição:</strong> <?= htmlspecialchars($v['descricao']) ?></p>
                    <?php if ($v['cve']): ?><p><strong>CVE:</strong> <?= htmlspecialchars($v['cve']) ?></p><?php endif; ?>
                    <?php if ($v['cwe']): ?><p><strong>CWE:</strong> <?= htmlspecialchars($v['cwe']) ?></p><?php endif; ?>
                    <?php if ($v['cvss']): ?><p><strong>CVSS:</strong> <?= htmlspecialchars($v['cvss']) ?></p><?php endif; ?>
                    <?php if ($v['prova']): ?><p><strong>Evidência:</strong> <?= htmlspecialchars($v['prova']) ?></p><?php endif; ?>
                    <p><strong>Detectado em:</strong> <?= date('d/m/Y H:i', strtotime($v['criado_em'])) ?></p>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    <?php endif; ?>

</div>
>>>>>>> 16e81cb (CODIGO FUNCIONANDOOOOO)
</body>
</html>
