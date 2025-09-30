<?php
session_start();

// Verifica se o usuário está logado
if (!isset($_SESSION['usuario_id'])) {
  header("Location: login.php");
  exit;
}
?>

<!DOCTYPE html>
<html lang="pt">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Secure Systems - Dashboard</title>
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
    .dashboard-title {
      margin-bottom: 10px;
      font-size: 1.8rem;
      font-weight: bold;
    }
    .subtitle {
      color: #8b949e;
    }
    .stats-box {
      background: #161b22;
      border: 1px solid #30363d;
      border-radius: 10px;
      padding: 25px;
      text-align: center;
      transition: transform 0.2s;
    }
    .stats-box:hover {
      transform: scale(1.03);
    }
    .stats-box h4 {
      color: #58d68d;
      font-size: 2rem;
      margin-bottom: 5px;
    }
    .stats-box p {
      margin: 0;
      color: #8b949e;
    }
    .dashboard-card {
      background-color: #161b22;
      border: 1px solid #30363d;
      border-radius: 12px;
      padding: 30px;
      text-align: center;
      transition: transform 0.2s ease, box-shadow 0.2s ease;
      cursor: pointer;
      height: 100%;
    }
    .dashboard-card:hover {
      transform: scale(1.05);
      box-shadow: 0 0 20px rgba(88, 166, 255, 0.2);
    }
    .dashboard-card h5 {
      margin-top: 15px;
      font-weight: bold;
    }
    .dashboard-card p {
      color: #8b949e;
    }
    .icon {
      font-size: 2.5rem;
      color: #58a6ff;
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
        <a href="perfil.php" class="btn btn-outline-info btn-sm">Perfil</a>
        <a href="logout.php" class="btn btn-outline-danger btn-sm">Sair</a>
      </div>
    </div>
  </nav>

  <!-- Dashboard -->
  <div class="container py-5">
    <h3 class="dashboard-title">📊 Painel de Segurança</h3>
    <p class="subtitle">Gerencie seus targets, execute testes e acompanhe os relatórios em um só lugar.</p>

    <!-- Estatísticas rápidas -->
    <div class="row mb-5 g-4">
      <div class="col-md-3">
        <div class="stats-box">
          <h4>5</h4>
          <p>Targets Monitorados</p>
        </div>
      </div>
      <div class="col-md-3">
        <div class="stats-box">
          <h4>12</h4>
          <p>Scans Executados</p>
        </div>
      </div>
      <div class="col-md-3">
        <div class="stats-box">
          <h4>3</h4>
          <p>Vulnerabilidades Críticas</p>
        </div>
      </div>
      <div class="col-md-3">
        <div class="stats-box">
          <h4>8</h4>
          <p>Relatórios Gerados</p>
        </div>
      </div>
    </div>

    <!-- Funcionalidades principais -->
    <div class="row g-4">
      <div class="col-md-4">
        <a href="targets.php" style="text-decoration: none; color: inherit;">
          <div class="dashboard-card">
            <div class="icon">🎯</div>
            <h5>Gestão de Targets</h5>
            <p>Adicione e gerencie os sistemas (URLs/IPs) a serem analisados.</p>
          </div>
        </a>
      </div>

      <div class="col-md-4">
        <a href="scans.php" style="text-decoration: none; color: inherit;">
          <div class="dashboard-card">
            <div class="icon">🛠️</div>
            <h5>Executar Scans</h5>
            <p>Rode testes de segurança usando scanners integrados.</p>
          </div>
        </a>
      </div>

      <div class="col-md-4">
        <a href="relatorios.php" style="text-decoration: none; color: inherit;">
          <div class="dashboard-card">
            <div class="icon">📑</div>
            <h5>Relatórios</h5>
            <p>Acesse relatórios detalhados e dashboards de vulnerabilidades.</p>
          </div>
        </a>
      </div>

      <div class="col-md-6">
        <a href="logs.php" style="text-decoration: none; color: inherit;">
          <div class="dashboard-card">
            <div class="icon">📜</div>
            <h5>Auditoria & Logs</h5>
            <p>Veja o histórico de execuções, parâmetros utilizados e resultados registrados.</p>
          </div>
        </a>
      </div>

      <div class="col-md-6">
        <a href="perfil.php" style="text-decoration: none; color: inherit;">
          <div class="dashboard-card">
            <div class="icon">⚙️</div>
            <h5>Configurações da Conta</h5>
            <p>Atualize suas informações e personalize sua experiência.</p>
          </div>
        </a>
      </div>
    </div>
  </div>

</body>
</html>
