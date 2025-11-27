<?php
session_start();
require_once "conexao.php";

// Verifica se o usuário está logado
if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit;
}

$usuario_id = $_SESSION['usuario_id'];
$mensagem = '';
$mensagem_tipo = '';

// Processar ações
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? '';
    
    try {
        switch ($acao) {
            case 'alterar_tema':
                $tema = $_POST['tema'] ?? 'dark';
                // Salvar preferência de tema (você pode criar uma coluna na tabela usuarios)
                $stmt = $pdo->prepare("UPDATE usuarios SET tema = ? WHERE id = ?");
                // Se não tiver coluna tema, usa sessão
                $_SESSION['tema'] = $tema;
                $mensagem = "✅ Tema alterado com sucesso!";
                $mensagem_tipo = "success";
                break;
                
            case 'limpar_scans_antigos':
                // Remove scans com mais de 30 dias
                $stmt = $pdo->prepare("DELETE FROM scans WHERE usuario_id = ? AND iniciado_em < DATE_SUB(NOW(), INTERVAL 30 DAY)");
                $stmt->execute([$usuario_id]);
                $removidos = $stmt->rowCount();
                $mensagem = "✅ $removidos scans antigos removidos!";
                $mensagem_tipo = "success";
                break;
                
            case 'limpar_targets_inativos':
                // Remove targets sem scans há mais de 60 dias
                $stmt = $pdo->prepare("
                    DELETE FROM targets 
                    WHERE usuario_id = ? 
                    AND id NOT IN (
                        SELECT DISTINCT target_id FROM scans 
                        WHERE usuario_id = ? AND iniciado_em > DATE_SUB(NOW(), INTERVAL 60 DAY)
                    )
                ");
                $stmt->execute([$usuario_id, $usuario_id]);
                $removidos = $stmt->rowCount();
                $mensagem = "✅ $removidos targets inativos removidos!";
                $mensagem_tipo = "success";
                break;
                
            case 'resetar_sistema':
                // CUIDADO: Remove TUDO do usuário
                $confirmacao = $_POST['confirmacao'] ?? '';
                if ($confirmacao === 'RESETAR') {
                    $pdo->beginTransaction();
                    
                    // Remove na ordem correta (por causa de foreign keys)
                    $pdo->prepare("DELETE FROM logs WHERE usuario_id = ?")->execute([$usuario_id]);
                    $pdo->prepare("DELETE FROM relatorios WHERE scan_id IN (SELECT id FROM scans WHERE usuario_id = ?)")->execute([$usuario_id]);
                    $pdo->prepare("DELETE FROM vulnerabilidades WHERE scan_id IN (SELECT id FROM scans WHERE usuario_id = ?)")->execute([$usuario_id]);
                    $pdo->prepare("DELETE FROM scans WHERE usuario_id = ?")->execute([$usuario_id]);
                    $pdo->prepare("DELETE FROM targets WHERE usuario_id = ?")->execute([$usuario_id]);
                    
                    $pdo->commit();
                    $mensagem = "✅ Sistema resetado completamente!";
                    $mensagem_tipo = "success";
                } else {
                    $mensagem = "❌ Confirmação incorreta! Digite RESETAR para confirmar.";
                    $mensagem_tipo = "danger";
                }
                break;
                
            case 'testar_zap':
                $zapHost = $_POST['zap_host'] ?? 'http://127.0.0.1:8090';
                $zapKey = $_POST['zap_key'] ?? '12345';
                
                $url = $zapHost . "/JSON/core/view/version/?apikey=" . urlencode($zapKey);
                $response = @file_get_contents($url);
                
                if ($response) {
                    $data = json_decode($response, true);
                    $versao = $data['version'] ?? 'desconhecida';
                    $mensagem = "✅ Conexão OK! ZAP versão: $versao";
                    $mensagem_tipo = "success";
                } else {
                    $mensagem = "❌ Falha ao conectar com ZAP. Verifique se está rodando.";
                    $mensagem_tipo = "danger";
                }
                break;
                
            case 'exportar_dados':
                // Exportar dados do usuário em JSON
                $dados = [];
                
                // Targets
                $stmt = $pdo->prepare("SELECT * FROM targets WHERE usuario_id = ?");
                $stmt->execute([$usuario_id]);
                $dados['targets'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Scans
                $stmt = $pdo->prepare("SELECT * FROM scans WHERE usuario_id = ?");
                $stmt->execute([$usuario_id]);
                $dados['scans'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Criar arquivo
                $filename = "backup_usuario_{$usuario_id}_" . date('Y-m-d_His') . ".json";
                $filepath = __DIR__ . "/reports/" . $filename;
                file_put_contents($filepath, json_encode($dados, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                
                $mensagem = "✅ Backup criado: <a href='reports/$filename' class='text-light' download><u>$filename</u></a>";
                $mensagem_tipo = "success";
                break;
                
            case 'alterar_senha':
                $senha_atual = $_POST['senha_atual'] ?? '';
                $senha_nova = $_POST['senha_nova'] ?? '';
                $senha_confirma = $_POST['senha_confirma'] ?? '';
                
                // Busca senha atual
                $stmt = $pdo->prepare("SELECT senha FROM usuarios WHERE id = ?");
                $stmt->execute([$usuario_id]);
                $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!password_verify($senha_atual, $usuario['senha'])) {
                    $mensagem = "❌ Senha atual incorreta!";
                    $mensagem_tipo = "danger";
                } elseif ($senha_nova !== $senha_confirma) {
                    $mensagem = "❌ As senhas não coincidem!";
                    $mensagem_tipo = "danger";
                } elseif (strlen($senha_nova) < 6) {
                    $mensagem = "❌ A senha deve ter no mínimo 6 caracteres!";
                    $mensagem_tipo = "danger";
                } else {
                    $hash = password_hash($senha_nova, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("UPDATE usuarios SET senha = ? WHERE id = ?");
                    $stmt->execute([$hash, $usuario_id]);
                    $mensagem = "✅ Senha alterada com sucesso!";
                    $mensagem_tipo = "success";
                }
                break;
        }
    } catch (Exception $e) {
        $mensagem = "❌ Erro: " . $e->getMessage();
        $mensagem_tipo = "danger";
    }
}

// Buscar informações para exibir
$stmt = $pdo->prepare("SELECT COUNT(*) FROM targets WHERE usuario_id = ?");
$stmt->execute([$usuario_id]);
$totalTargets = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM scans WHERE usuario_id = ?");
$stmt->execute([$usuario_id]);
$totalScans = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM logs WHERE usuario_id = ?");
$stmt->execute([$usuario_id]);
$totalLogs = $stmt->fetchColumn();

$tema_atual = $_SESSION['tema'] ?? 'dark';
?>

<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Configurações do Sistema - Secure Systems</title>
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
        .config-section {
            background: #161b22;
            border: 1px solid #30363d;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 20px;
            transition: transform 0.2s;
        }
        .config-section:hover {
            transform: translateY(-2px);
            border-color: #58a6ff;
        }
        .config-section h5 {
            color: #58a6ff;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .config-section p {
            color: #8b949e;
            font-size: 0.9rem;
            margin-bottom: 15px;
        }
        .btn-custom {
            background-color: #238636;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 6px;
            transition: all 0.2s;
        }
        .btn-custom:hover {
            background-color: #2ea043;
            transform: scale(1.05);
        }
        .btn-danger-custom {
            background-color: #da3633;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 6px;
        }
        .btn-danger-custom:hover {
            background-color: #ff5555;
        }
        .btn-info-custom {
            background-color: #1f6feb;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 6px;
        }
        .btn-info-custom:hover {
            background-color: #58a6ff;
        }
        .stats-mini {
            background: #0d1117;
            border: 1px solid #30363d;
            border-radius: 8px;
            padding: 15px;
            text-align: center;
        }
        .stats-mini h6 {
            color: #58d68d;
            font-size: 1.5rem;
            margin-bottom: 5px;
        }
        .stats-mini p {
            color: #8b949e;
            font-size: 0.85rem;
            margin: 0;
        }
        .form-control, .form-select {
            background-color: #0d1117;
            border: 1px solid #30363d;
            color: #f0f6fc;
        }
        .form-control:focus, .form-select:focus {
            background-color: #0d1117;
            border-color: #58a6ff;
            color: #f0f6fc;
            box-shadow: 0 0 0 0.25rem rgba(88, 166, 255, 0.25);
        }
        .theme-toggle {
            display: flex;
            gap: 10px;
        }
        .theme-btn {
            flex: 1;
            padding: 15px;
            border: 2px solid #30363d;
            border-radius: 8px;
            background: #0d1117;
            cursor: pointer;
            transition: all 0.3s;
            text-align: center;
        }
        .theme-btn.active {
            border-color: #58a6ff;
            background: #161b22;
        }
        .theme-btn:hover {
            border-color: #58a6ff;
        }
        .icon-large {
            font-size: 2rem;
        }
        .alert-custom {
            border-radius: 8px;
            border: 1px solid;
        }
        .modal-content {
            background-color: #161b22;
            border: 1px solid #30363d;
        }
        .modal-header {
            border-bottom: 1px solid #30363d;
        }
        .modal-footer {
            border-top: 1px solid #30363d;
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
                <a href="home.php" class="btn btn-outline-info btn-sm">← Voltar</a>
                <a href="logout.php" class="btn btn-outline-danger btn-sm">Sair</a>
            </div>
        </div>
    </nav>

    <div class="container py-5">
        <h3 class="mb-2" style="font-weight: bold; color: #58a6ff;">⚙️ Configurações do Sistema</h3>
        <p class="mb-4" style="color: #8b949e;">Personalize sua experiência e gerencie configurações avançadas</p>

        <?php if ($mensagem): ?>
            <div class="alert alert-<?= $mensagem_tipo ?> alert-custom">
                <?= $mensagem ?>
            </div>
        <?php endif; ?>


        <div class="row">
            <!-- Coluna Esquerda -->
            <div class="col-lg-6">
                
                <!-- Aparência -->
                <div class="config-section">
                    <h5><span class="icon-large">🎨</span> Aparência</h5>
                    <p>Escolha o tema da interface que mais combina com você</p>
                    
                    <form method="POST">
                        <input type="hidden" name="acao" value="alterar_tema">
                        <div class="theme-toggle">
                            <label class="theme-btn <?= $tema_atual === 'dark' ? 'active' : '' ?>">
                                <input type="radio" name="tema" value="dark" style="display:none;" <?= $tema_atual === 'dark' ? 'checked' : '' ?>>
                                <div>🌙 Dark Mode</div>
                            </label>
                            <label class="theme-btn <?= $tema_atual === 'light' ? 'active' : '' ?>">
                                <input type="radio" name="tema" value="light" style="display:none;" <?= $tema_atual === 'light' ? 'checked' : '' ?>>
                                <div>☀️ Light Mode</div>
                            </label>
                        </div>
                        <button type="submit" class="btn btn-custom w-100 mt-3">Aplicar Tema</button>
                    </form>
                </div>

                <!-- Segurança -->
                <div class="config-section">
                    <h5><span class="icon-large">🔐</span> Segurança</h5>
                    <p>Altere sua senha para manter sua conta segura</p>
                    
                    <form method="POST">
                        <input type="hidden" name="acao" value="alterar_senha">
                        <div class="mb-3">
                            <label class="form-label">Senha Atual</label>
                            <input type="password" name="senha_atual" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Nova Senha</label>
                            <input type="password" name="senha_nova" class="form-control" required minlength="6">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Confirmar Nova Senha</label>
                            <input type="password" name="senha_confirma" class="form-control" required minlength="6">
                        </div>
                        <button type="submit" class="btn btn-custom w-100">Alterar Senha</button>
                    </form>
                </div>

                <!-- Conexão ZAP -->
                <div class="config-section">
                    <h5><span class="icon-large">🔌</span> Conexão OWASP ZAP</h5>
                    <p>Teste a conectividade com o servidor ZAP</p>
                    
                    <form method="POST">
                        <input type="hidden" name="acao" value="testar_zap">
                        <div class="mb-3">
                            <label class="form-label">Host do ZAP</label>
                            <input type="text" name="zap_host" class="form-control" value="http://127.0.0.1:8080" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">API Key</label>
                            <input type="text" name="zap_key" class="form-control" value="12345" required>
                        </div>
                        <button type="submit" class="btn btn-info-custom w-100">🔍 Testar Conexão</button>
                    </form>
                </div>

            </div>

            <!-- Coluna Direita -->
            <div class="col-lg-6">

                <!-- Backup -->
                <div class="config-section">
                    <h5><span class="icon-large">💾</span> Backup de Dados</h5>
                    <p>Exporte todos os seus dados em formato JSON</p>
                    
                    <form method="POST">
                        <input type="hidden" name="acao" value="exportar_dados">
                        <button type="submit" class="btn btn-info-custom w-100">📥 Exportar Dados</button>
                    </form>
                </div>

                <!-- Limpeza -->
                <div class="config-section">
                    <h5><span class="icon-large">🧹</span> Limpeza de Dados</h5>
                    <p>Remova dados antigos para liberar espaço</p>
                    
                    <form method="POST" class="mb-3">
                        <input type="hidden" name="acao" value="limpar_scans_antigos">
                        <button type="submit" class="btn btn-custom w-100" onclick="return confirm('Remover scans com mais de 30 dias?')">
                            🗑️ Limpar Scans Antigos (30+ dias)
                        </button>
                    </form>
                    
                    <form method="POST">
                        <input type="hidden" name="acao" value="limpar_targets_inativos">
                        <button type="submit" class="btn btn-custom w-100" onclick="return confirm('Remover targets sem scans há 60+ dias?')">
                            🗑️ Limpar Targets Inativos (60+ dias)
                        </button>
                    </form>
                </div>

                <!-- Reset PERIGOSO -->
                <div class="config-section" style="border-color: #da3633;">
                    <h5 style="color: #da3633;"><span class="icon-large">⚠️</span> Zona de Perigo</h5>
                    <p style="color: #ff5555;">Atenção! Esta ação é IRREVERSÍVEL e removerá todos os seus dados.</p>
                    
                    <button type="button" class="btn btn-danger-custom w-100" data-bs-toggle="modal" data-bs-target="#resetModal">
                        💣 Resetar Sistema Completamente
                    </button>
                </div>

                <!-- Informações do Sistema -->
                <div class="config-section">
                    <h5><span class="icon-large">ℹ️</span> Informações do Sistema</h5>
                    <table class="table table-dark table-sm">
                        <tr>
                            <td>Versão:</td>
                            <td><strong>1.0.0</strong></td>
                        </tr>
                        <tr>
                            <td>PHP:</td>
                            <td><strong><?= phpversion() ?></strong></td>
                        </tr>
                        <tr>
                            <td>Banco de Dados:</td>
                            <td><strong>MySQL <?= $pdo->query('SELECT VERSION()')->fetchColumn() ?></strong></td>
                        </tr>
                        <tr>
                            <td>Usuário Logado:</td>
                            <td><strong><?= htmlspecialchars($_SESSION['username']) ?></strong></td>
                        </tr>
                    </table>
                </div>

            </div>
        </div>
    </div>

    <!-- Modal de Confirmação Reset -->
    <div class="modal fade" id="resetModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" style="color: #da3633;">⚠️ Confirmar Reset Total</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="acao" value="resetar_sistema">
                        <p style="color: #ff5555;">Esta ação irá <strong>DELETAR PERMANENTEMENTE</strong>:</p>
                        <ul style="color: #f0f6fc;">
                            <li>Todos os targets</li>
                            <li>Todos os scans</li>
                            <li>Todas as vulnerabilidades</li>
                            <li>Todos os relatórios</li>
                        </ul>
                        <p style="color: #8b949e;">Digite <strong style="color: #fff;">RESETAR</strong> para confirmar:</p>
                        <input type="text" name="confirmacao" class="form-control" placeholder="Digite RESETAR" required>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-danger-custom">Resetar Tudo</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-submit ao trocar tema
        document.querySelectorAll('.theme-btn input').forEach(input => {
            input.addEventListener('change', function() {
                this.closest('form').submit();
            });
        });
    </script>
</body>
</html>