<?php
session_start();
include 'conexao.php'; // já conecta com PDO

$erro = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $_POST['username'];
    $senha = $_POST['senha'];

    $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE username = ?");
    $stmt->execute([$username]);

    if ($stmt->rowCount() > 0) {
        $usuario = $stmt->fetch();

        if (password_verify($senha, $usuario['senha'])) {
            $_SESSION['usuario_id'] = $usuario['id'];
            $_SESSION['username'] = $usuario['username'];
            $_SESSION['nome_completo'] = $usuario['nome_completo'];

            header('Location: home.php');
            exit();
        } else {
            $erro = "⚠️ Senha incorreta!";
        }
    } else {
        $erro = "⚠️ Usuário não encontrado!";
    }
}
?>

<!DOCTYPE html>
<html lang="pt">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login | Secure Systems</title>
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

    .login-container {
      background: #161b22;
      border: 1px solid #30363d;
      border-radius: 12px;
      padding: 2.5rem;
      width: 100%;
      max-width: 400px;
      text-align: center;
      box-shadow: 0 0 20px rgba(88, 166, 255, 0.1);
    }

    .login-container h1 {
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

    input[type="text"]:focus,
    input[type="password"]:focus {
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

    .error-message {
      color: #ff7b72;
      font-weight: bold;
      margin-top: 1rem;
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
  <div class="login-container">
    <h1><i class="fas fa-lock"></i> Login</h1>
    <form action="login.php" method="POST">
      <div class="form-group">
        <label for="username">Nome de usuário</label>
        <input type="text" id="username" name="username" required>
      </div>

      <div class="form-group">
        <label for="password">Senha</label>
        <input type="password" id="password" name="senha" required>
      </div>

      <button type="submit" name="login" class="btn">Entrar</button>

      <?php if (!empty($erro)): ?>
        <div class="error-message"><?= $erro ?></div>
      <?php endif; ?>
    </form>

    <div class="links">
      <p><a href="registro.php">Criar conta</a> | <a href="index.html">Voltar</a></p>
    </div>
  </div>
</body>
</html>
