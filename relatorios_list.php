<?php
session_start();
include 'conexao.php';

if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit();
}

$usuario_id = $_SESSION['usuario_id'];

$sql = "SELECT r.id, r.arquivo_path, r.gerado_em, s.scanner, t.url_ip AS target
        FROM relatorios r
        JOIN scans s ON r.scan_id = s.id
        JOIN targets t ON s.target_id = t.id
        WHERE s.usuario_id = ?
        ORDER BY r.gerado_em DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute([$usuario_id]);
$relatorios = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="pt">
<head>
  <meta charset="UTF-8">
  <title>Relatórios - Secure Systems</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-dark text-light p-4">
  <div class="container">
    <h2 class="mb-4 text-info">📑 Relatórios Gerados</h2>

    <?php if (count($relatorios) > 0): ?>
      <table class="table table-dark table-bordered">
        <thead>
          <tr>
            <th>ID</th>
            <th>Target</th>
            <th>Scanner</th>
            <th>Data</th>
            <th>Ação</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($relatorios as $r): ?>
            <tr>
              <td><?= htmlspecialchars($r['id']) ?></td>
              <td><?= htmlspecialchars($r['target']) ?></td>
              <td><?= htmlspecialchars($r['scanner']) ?></td>
              <td><?= date('d/m/Y H:i', strtotime($r['gerado_em'])) ?></td>
              <td>
                <a href="<?= htmlspecialchars($r['arquivo_path']) ?>" class="btn btn-sm btn-success" target="_blank">📄 Ver</a>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php else: ?>
      <p>Nenhum relatório disponível.</p>
    <?php endif; ?>

    <a href="home.php" class="btn btn-outline-info mt-3">⬅ Voltar</a>
  </div>
</body>
</html>
