<?php
include 'conexao.php';

$mensagem = "";

if (isset($_POST['registrar'])) {
    $email = $_POST['email'];
    $nome_completo = $_POST['nome_completo'];
    $data_nascimento = $_POST['data_nascimento'];
    $cidade = $_POST['cidade'];
    $username = $_POST['username'];
    $senha = password_hash($_POST['senha'], PASSWORD_DEFAULT);

    $stmt = $pdo->prepare("INSERT INTO usuarios (email, nome_completo, data_nascimento, cidade, username, senha) VALUES (?, ?, ?, ?, ?, ?)");

    if ($stmt->execute([$email, $nome_completo, $data_nascimento, $cidade, $username, $senha])) {
        $mensagem = "<p class='success'>✅ Registrado com sucesso! <a href='login.php'>Fazer Login</a></p>";
    } else {
        $mensagem = "<p class='error'>❌ Erro ao registrar!</p>";
    }
}
?>

<!DOCTYPE html>
<html lang="pt">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Registro | Secure Systems</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <style>
    body {
      background-color: #0d1117;
      color: #f0f6fc;
      font-family: 'Segoe UI', sans-serif;
      display: flex;
      justify-content: center;
      align-items: center;
      min-height: 100vh;
      margin: 0;
    }

    .register-container {
      background: #161b22;
      border: 1px solid #30363d;
      border-radius: 12px;
      padding: 2.5rem;
      width: 100%;
      max-width: 500px;
      text-align: center;
      box-shadow: 0 0 20px rgba(88, 166, 255, 0.1);
    }

    .register-container h1 {
      margin-bottom: 1.5rem;
      color: #58a6ff;
    }

    .form-group {
      margin-bottom: 1.2rem;
      text-align: left;
    }

    label {
      display: block;
      margin-bottom: 0.5rem;
      font-weight: 500;
      color: #c9d1d9;
    }

    input[type="text"],
    input[type="email"],
    input[type="date"],
    input[type="password"] {
      width: 100%;
      padding: 0.8rem;
      border-radius: 8px;
      border: 1px solid #30363d;
      background-color: #0d1117;
      color: #f0f6fc;
      font-size: 1rem;
      transition: border 0.3s ease;
    }

    input:focus {
      border-color: #58a6ff;
      outline: none;
    }

    .btn {
      background-color: #58a6ff;
      color: #fff;
      padding: 0.9rem;
      border: none;
      border-radius: 8px;
      width: 100%;
      font-size: 1rem;
      font-weight: bold;
      cursor: pointer;
      transition: all 0.3s ease;
      margin-top: 1rem;
      box-shadow: 0 0 10px rgba(88, 166, 255, 0.2);
    }

    .btn:hover {
      background-color: #1f6feb;
      box-shadow: 0 0 20px rgba(88, 166, 255, 0.5);
      transform: translateY(-2px);
    }

    .success {
      color: #3fb950;
      margin-top: 1rem;
      font-weight: bold;
    }

    .error {
      color: #ff7b72;
      margin-top: 1rem;
      font-weight: bold;
    }

    .links {
      margin-top: 1.5rem;
      font-size: 0.9rem;
    }

    .links a {
      color: #8b949e;
      text-decoration: none;
      transition: 0.3s;
    }

    .links a:hover {
      color: #58a6ff;
      text-decoration: underline;
    }
  </style>
</head>

<body>
  <div class="register-container">
    <h1><i class="fas fa-user-plus"></i> Registro</h1>
    <form action="registro.php" method="POST">
      <div class="form-group">
        <label for="email">Email</label>
        <input type="email" id="email" name="email" required>
      </div>

      <div class="form-group">
        <label for="name">Nome completo</label>
        <input type="text" id="name" name="nome_completo" required>
      </div>

      <div class="form-group">
        <label for="birthdate">Data de nascimento</label>
        <input type="date" id="birthdate" name="data_nascimento" required>
      </div>

      <div class="form-group">
        <label for="city">Cidade</label>
        <input type="text" id="city" name="cidade" required>
      </div>

      <div class="form-group">
        <label for="username">Nome de usuário</label>
        <input type="text" id="username" name="username" required>
      </div>

      <div class="form-group">
        <label for="password">Senha</label>
        <input type="password" id="password" name="senha" required>
      </div>

      <button type="submit" name="registrar" class="btn">Criar Conta</button>

      <?= $mensagem ?>
    </form>

    <div class="links">
      <p><a href="login.php">Já tenho conta</a> | <a href="index.html">Voltar</a></p>
    </div>
  </div>
</body>
</html>
