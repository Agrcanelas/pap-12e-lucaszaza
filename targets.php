<?php
session_start();
include 'conexao.php';

<<<<<<< HEAD
// Verifica se usuário está logado
=======
// Verifica se o usuário está logado
>>>>>>> 16e81cb (CODIGO FUNCIONANDOOOOO)
if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit();
}

$usuario_id = $_SESSION['usuario_id'];
$mensagem = "";

<<<<<<< HEAD
// Descobre automaticamente qual coluna da tabela targets é URL/IP
=======
// Descobre a coluna de URL/IP da tabela targets
>>>>>>> 16e81cb (CODIGO FUNCIONANDOOOOO)
$colUrl = null;
$stmt = $pdo->query("DESCRIBE targets");
$columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
foreach ($columns as $col) {
    if (stripos($col, 'url') !== false || stripos($col, 'target') !== false) {
        $colUrl = $col;
        break;
    }
}
if (!$colUrl) {
    die("❌ Não foi possível identificar a coluna de URL/IP na tabela targets.");
}

// Adicionar target
if (isset($_POST['adicionar'])) {
    $novo_target = trim($_POST['novo_target']);
    if (!empty($novo_target)) {
<<<<<<< HEAD
        $stmtInsert = $pdo->prepare("INSERT INTO targets (usuario_id, `$colUrl`, data_adicionado) VALUES (?, ?, NOW())");
        if ($stmtInsert->execute([$usuario_id, $novo_target])) {
            // Pega o ID do target recém-criado
            $novo_id = $pdo->lastInsertId();

            // Redireciona para scans.php com o target já selecionado
            header("Location: targets.php?target_id=" . $novo_id);
=======
        $stmtInsert = $pdo->prepare("INSERT INTO targets (usuario_id, `$colUrl`, nome, endereco, data_adicionado) VALUES (?, ?, '', '', NOW())");
        if ($stmtInsert->execute([$usuario_id, $novo_target])) {
            $novo_id = $pdo->lastInsertId();
            header("Location: scans.php?target_id=" . $novo_id);
>>>>>>> 16e81cb (CODIGO FUNCIONANDOOOOO)
            exit();
        } else {
            $mensagem = "<p class='error'>❌ Erro ao adicionar target.</p>";
        }
    } else {
        $mensagem = "<p class='error'>❌ O campo não pode ficar vazio.</p>";
    }
}

<<<<<<< HEAD

=======
>>>>>>> 16e81cb (CODIGO FUNCIONANDOOOOO)
// Remover target
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    $stmtDelete = $pdo->prepare("DELETE FROM targets WHERE id = ? AND usuario_id = ?");
    if ($stmtDelete->execute([$id, $usuario_id])) {
        $mensagem = "<p class='success'>🗑️ Target removido.</p>";
    } else {
        $mensagem = "<p class='error'>❌ Erro ao remover target.</p>";
    }
}

<<<<<<< HEAD
// Buscar targets do usuário
=======
// Buscar targets
>>>>>>> 16e81cb (CODIGO FUNCIONANDOOOOO)
$stmtTargets = $pdo->prepare("SELECT id, `$colUrl` AS target, data_adicionado FROM targets WHERE usuario_id = ? ORDER BY data_adicionado DESC");
$stmtTargets->execute([$usuario_id]);
$targets = $stmtTargets->fetchAll(PDO::FETCH_ASSOC);
?>
<<<<<<< HEAD
=======
<!-- (restante do HTML idêntico ao seu, pode manter o mesmo) -->

>>>>>>> 16e81cb (CODIGO FUNCIONANDOOOOO)


<!DOCTYPE html>
<html lang="pt">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestão de Targets | Secure Systems</title>
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
            max-width: 900px;
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

        form {
            display: flex;
            gap: 1rem;
            margin-bottom: 2rem;
        }

        input[type="text"] {
            flex: 1;
            padding: 0.8rem;
            border-radius: 8px;
            border: 1px solid #30363d;
            background-color: #0d1117;
            color: #f0f6fc;
            font-size: 1rem;
            transition: border 0.3s;
        }

        input:focus {
            border-color: #58a6ff;
            outline: none;
        }

        .btn {
            background-color: #58a6ff;
            color: #fff;
            padding: 0.8rem 1.5rem;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: bold;
            transition: all 0.3s;
        }

        .btn:hover {
            background-color: #1f6feb;
            box-shadow: 0 0 15px rgba(88, 166, 255, 0.4);
            transform: translateY(-2px);
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
        }

        th,
        td {
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

        .actions a {
            color: #ff7b72;
            text-decoration: none;
            margin-left: 0.5rem;
        }

        .actions a:hover {
            text-decoration: underline;
        }

        .success {
            color: #3fb950;
            font-weight: bold;
            margin-bottom: 1rem;
        }

        .error {
            color: #ff7b72;
            font-weight: bold;
            margin-bottom: 1rem;
        }

        .back-link {
            margin-top: 1.5rem;
            display: block;
            text-align: center;
        }

        .back-link a {
            color: #8b949e;
            text-decoration: none;
        }

        .back-link a:hover {
            color: #58a6ff;
            text-decoration: underline;
        }
    </style>
</head>

<body>
    <div class="container">
        <h1><i class="fas fa-bullseye"></i> Gestão de Targets</h1>
        <?= $mensagem ?>

        <!-- Formulário de adicionar -->
        <form method="POST" action="targets.php">
            <input type="text" name="novo_target" placeholder="Ex: https://site.com ou 192.168.0.1" required>

            <button type="submit" name="adicionar" class="btn">Adicionar</button>
        </form>

        <!-- Tabela de targets -->
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Target</th>
                    <th>Adicionado em</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($targets) > 0): ?>
                    <?php foreach ($targets as $t): ?>
                        <tr>
                            <td><?= htmlspecialchars($t['id']) ?></td>
                            <td><?= htmlspecialchars($t['target']) ?></td>
                            <td><?= $t['data_adicionado'] ?></td>
                            <td class="actions">
                                <a href="targets.php?delete=<?= $t['id'] ?>"
                                    onclick="return confirm('Tem certeza que deseja remover este target?')">
                                    <i class="fas fa-trash"></i> Remover
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="4" style="text-align:center; color:#8b949e;">Nenhum target adicionado ainda.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>

        <div class="back-link">
            <p><a href="home.php">⬅ Voltar ao painel</a></p>
        </div>
    </div>
</body>

</html>