<?php
session_start();
include 'conexao.php';

// Verifica login
if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit();
}

$usuario_id = $_SESSION['usuario_id'];

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
}
?>

<!DOCTYPE html>
<html lang="pt">

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
</body>
</html>
