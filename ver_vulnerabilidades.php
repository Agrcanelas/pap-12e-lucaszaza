<?php
include 'config.php';
session_start();

// Verifica se o usuário está logado
if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit();
}

// Pega o ID do relatório via GET
if (!isset($_GET['relatorio_id'])) {
    die("Relatório não especificado.");
}

$relatorio_id = $_GET['relatorio_id'];

// Busca informações do relatório
$stmt = $pdo->prepare("
    SELECT r.id, r.data_geracao, t.url_ip
    FROM relatorios r
    JOIN targets t ON r.target_id = t.id
    WHERE r.id = ?
");
$stmt->execute([$relatorio_id]);
$relatorio = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$relatorio) {
    die("Relatório não encontrado.");
}

// Busca vulnerabilidades associadas
$stmt = $pdo->prepare("
    SELECT id, nome_vulnerabilidade, risco, descricao, recomendacao
    FROM vulnerabilidades
    WHERE relatorio_id = ?
");
$stmt->execute([$relatorio_id]);
$vulnerabilidades = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Vulnerabilidades - Relatório <?= htmlspecialchars($relatorio_id) ?></title>
    <link rel="stylesheet" href="style.css">
    <style>
        body { font-family: Arial, sans-serif; background: #f4f6f8; margin: 0; padding: 0; }
        .container { max-width: 900px; margin: 40px auto; background: white; padding: 20px; border-radius: 10px; box-shadow: 0 0 8px rgba(0,0,0,0.1); }
        h1 { margin-bottom: 20px; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        table, th, td { border: 1px solid #ddd; }
        th, td { padding: 10px; text-align: left; }
        th { background: #222; color: #fff; }
        tr:nth-child(even) { background: #f9f9f9; }
        a.btn { display: inline-block; background: #222; color: white; padding: 8px 15px; border-radius: 6px; text-decoration: none; margin-top: 20px; }
        a.btn:hover { background: #444; }
    </style>
</head>
<body>
<div class="container">
    <h1>Relatório de Vulnerabilidades</h1>

    <p><strong>Target:</strong> <?= htmlspecialchars($relatorio['url_ip']) ?></p>
    <p><strong>Data de geração:</strong> <?= htmlspecialchars($relatorio['data_geracao']) ?></p>

    <h2>Vulnerabilidades Encontradas</h2>

    <?php if (count($vulnerabilidades) > 0): ?>
        <table>
            <thead>
                <tr>
                    <th>Nome</th>
                    <th>Risco</th>
                    <th>Descrição</th>
                    <th>Recomendação</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($vulnerabilidades as $v): ?>
                    <tr>
                        <td><?= htmlspecialchars($v['nome_vulnerabilidade']) ?></td>
                        <td><?= htmlspecialchars($v['risco']) ?></td>
                        <td><?= htmlspecialchars($v['descricao']) ?></td>
                        <td><?= htmlspecialchars($v['recomendacao']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p><em>Nenhuma vulnerabilidade registrada para este relatório.</em></p>
    <?php endif; ?>

    <a href="relatorios.php" class="btn">⬅ Voltar</a>
</div>
</body>
</html>
