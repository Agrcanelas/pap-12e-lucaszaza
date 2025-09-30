<?php
session_start();
require_once "conexao.php";

if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit;
}

$id = $_SESSION['usuario_id'];

// Atualiza os dados se o formulário foi enviado
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = $_POST['nome_completo'];
    $email = $_POST['email'];
    $data_nasc = $_POST['data_nascimento'];
    $cidade = $_POST['cidade'];

    $sql = "UPDATE usuarios SET nome_completo = :nome, email = :email, data_nascimento = :data, cidade = :cidade WHERE id = :id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':nome' => $nome,
        ':email' => $email,
        ':data' => $data_nasc,
        ':cidade' => $cidade,
        ':id' => $id
    ]);

    header("Location: perfil.php");
    exit;
}

// Busca os dados atuais
$stmt = $pdo->prepare("SELECT * FROM usuarios WHERE id = :id");
$stmt->execute([':id' => $id]);
$usuario = $stmt->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="pt">
<head>
  <meta charset="UTF-8">
  <title>Editar Perfil</title>
  <style>
    body {
      background-color: #121212;
      color: #e0e0e0;
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
      display: flex;
      justify-content: center;
      align-items: center;
      height: 100vh;
      margin: 0;
    }

    .card {
      background-color: #1e1e1e;
      padding: 30px 40px;
      border-radius: 12px;
      box-shadow: 0 0 20px rgba(0, 0, 0, 0.6);
      width: 100%;
      max-width: 400px;
      box-sizing: border-box;
    }

    h2 {
      color: #ffffff;
      padding-bottom: 10px;
      margin-bottom: 20px;
      text-align: center;
      font-size: 1.5em;
    }

    label {
      display: block;
      margin: 15px 0 5px;
      font-weight: bold;
      color: #90caf9;
    }

    input {
      width: 100%;
      padding: 10px;
      border: none;
      border-radius: 6px;
      background-color: #2c2c2c;
      color: #e0e0e0;
      font-size: 1em;
      box-sizing: border-box;
    }

    input:focus {
      outline: none;
      border: 1px solid #64b5f6;
      background-color: #333;
    }

    .btn {
      display: block;
      width: 100%;
      padding: 12px;
      margin-top: 15px;
      border: none;
      border-radius: 8px;
      font-size: 1em;
      text-align: center;
      cursor: pointer;
      transition: background-color 0.3s;
      text-decoration: none;
      box-sizing: border-box;
    }

    .btn-save {
      background-color: #43a047;
      color: white;
    }

    .btn-save:hover {
      background-color: #388e3c;
    }

    .btn-back {
      background-color: #2c2c2c;
      color: #e0e0e0;
    }

    .btn-back:hover {
      background-color: #1b1b1b;
    }
  </style>
</head>
<body>
  <div class="card">
    <h2>Editar Perfil</h2>
    <form method="POST">
      <label>Nome completo:</label>
      <input type="text" name="nome_completo" value="<?= htmlspecialchars($usuario['nome_completo']) ?>">

      <label>Email:</label>
      <input type="email" name="email" value="<?= htmlspecialchars($usuario['email']) ?>">

      <label>Data de nascimento:</label>
      <input type="date" name="data_nascimento" value="<?= htmlspecialchars($usuario['data_nascimento']) ?>">

      <label>Cidade:</label>
      <input type="text" name="cidade" value="<?= htmlspecialchars($usuario['cidade']) ?>">

      <button type="submit" class="btn btn-save">Salvar Alterações</button>
      <a href="perfil.php" class="btn btn-back">Voltar</a>
    </form>
  </div>
</body>
</html>

