<?php
session_start();
require_once "conexao.php";

if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit;
}

$id = $_SESSION['usuario_id'];
$sql = "SELECT * FROM usuarios WHERE id = :id";
$stmt = $pdo->prepare($sql);
$stmt->bindParam(':id', $id);
$stmt->execute();
$usuario = $stmt->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="pt">
<head>
  <meta charset="UTF-8">
  <title>Perfil</title>
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
      border-radius: 10px;
      box-shadow: 0 4px 20px rgba(0, 0, 0, 0.5);
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

    p {
      margin: 12px 0;
      font-size: 1em;
    }

    strong {
      color: #90caf9;
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

    .btn-edit {
      background-color: #43a047;
      color: white;
    }

    .btn-edit:hover {
      background-color: #388e3c;
    }

    .btn-back {
      background-color: #2c2c2c;
      color: #e0e0e0;
    }
    .btn-red {
      background-color: #e53935;
      color: white;
    }
    .btn-back:hover {
      background-color: #1b1b1b;
    }
  </style>
</head>
<body>

  <div class="card">
    <h2>Meu Perfil</h2>
    <p><strong>Nome completo:</strong> <?= htmlspecialchars($usuario['nome_completo']) ?></p>
    <p><strong>Email:</strong> <?= htmlspecialchars($usuario['email']) ?></p>
    <p><strong>Data de nascimento:</strong> <?= htmlspecialchars($usuario['data_nascimento']) ?></p>
    <p><strong>Cidade:</strong> <?= htmlspecialchars($usuario['cidade']) ?></p>
    <p><strong>Username:</strong> <?= htmlspecialchars($usuario['username']) ?></p>
    <a href="editar_perfil.php" class="btn btn-edit">Editar Perfil</a>
    <a href="logout.php" class="btn btn-red">Logout</a>
    <a href="home.php" class="btn btn-back">Voltar</a>
  </div>

</body>
</html>


