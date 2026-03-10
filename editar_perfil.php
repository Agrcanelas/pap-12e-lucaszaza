<?php
session_start();
require_once "conexao.php";

if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit;
}

$id = $_SESSION['usuario_id'];
$mensagem = '';
$mensagem_tipo = '';

// Atualiza os dados se o formulário foi enviado
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = trim($_POST['nome_completo']);
    $email = trim($_POST['email']);
    $data_nasc = $_POST['data_nascimento'];
    $cidade = trim($_POST['cidade']);
    
    // Validações
    if (empty($nome) || empty($email) || empty($cidade)) {
        $mensagem = "❌ Todos os campos são obrigatórios!";
        $mensagem_tipo = "danger";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $mensagem = "❌ Email inválido!";
        $mensagem_tipo = "danger";
    } else {
        try {
            // Verifica se o email já existe para outro usuário
            $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE email = ? AND id != ?");
            $stmt->execute([$email, $id]);
            
            if ($stmt->fetch()) {
                $mensagem = "❌ Este email já está em uso por outro usuário!";
                $mensagem_tipo = "danger";
            } else {
                // Atualiza os dados
                $sql = "UPDATE usuarios SET nome_completo = ?, email = ?, data_nascimento = ?, cidade = ? WHERE id = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$nome, $email, $data_nasc, $cidade, $id]);
                
                // Registra log
                $stmtLog = $pdo->prepare("INSERT INTO logs (usuario_id, acao, detalhes, criado_em) VALUES (?, 'perfil_atualizado', ?, NOW())");
                $stmtLog->execute([$id, json_encode(['nome' => $nome, 'email' => $email])]);
                
                $mensagem = "✅ Perfil atualizado com sucesso!";
                $mensagem_tipo = "success";
                
                // Aguarda 1 segundo e redireciona
                header("refresh:1;url=perfil.php");
            }
        } catch (Exception $e) {
            $mensagem = "❌ Erro ao atualizar: " . $e->getMessage();
            $mensagem_tipo = "danger";
        }
    }
}

// Busca os dados atuais
$stmt = $pdo->prepare("SELECT * FROM usuarios WHERE id = ?");
$stmt->execute([$id]);
$usuario = $stmt->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="pt">
<head>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Editar Perfil - Secure Systems</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #0d1117;
            color: #f0f6fc;
            font-family: 'Segoe UI', sans-serif;
            min-height: 100vh;
        }
        /* --- NOVA NAVBAR PROFISSIONAL (IGUAL À HOME) --- */
        .navbar {
            background-color: rgba(22, 27, 34, 0.8) !important;
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border-bottom: 1px solid #30363d;
            padding: 0.75rem 1.5rem;
            position: sticky;
            top: 0;
            z-index: 1000;
        }

        .navbar-brand {
            font-weight: 700;
            letter-spacing: -0.5px;
            color: #f0f6fc !important;
            display: flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
        }

        .navbar-brand span {
            color: #58a6ff;
        }

        .user-dropdown-toggle {
            background: #21262d;
            border: 1px solid #30363d;
            color: #f0f6fc !important;
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 0.85rem;
            font-weight: 500;
            transition: all 0.2s;
            text-decoration: none;
        }

        .user-dropdown-toggle:hover {
            background: #30363d;
            border-color: #8b949e;
        }

        .dropdown-menu {
            background-color: #161b22;
            border: 1px solid #30363d;
            box-shadow: 0 8px 24px rgba(0,0,0,0.5);
            margin-top: 10px !important;
        }

        .dropdown-item {
            color: #c9d1d9;
            font-size: 0.9rem;
            padding: 8px 20px;
        }

        .dropdown-item:hover {
            background-color: #1f242c;
            color: #58a6ff;
        }

        .dropdown-divider { 
            border-top: 1px solid #30363d; 
        }
        .edit-container {
            max-width: 700px;
            margin: 50px auto;
        }
        .edit-card {
            background: #161b22;
            border: 1px solid #30363d;
            border-radius: 12px;
            padding: 40px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
        }
        .edit-header {
            text-align: center;
            margin-bottom: 30px;
        }
        .edit-header h3 {
            color: #58a6ff;
            font-weight: bold;
            margin-bottom: 10px;
        }
        .edit-header p {
            color: #8b949e;
            font-size: 0.95rem;
        }
        .avatar-edit {
            width: 100px;
            height: 100px;
            background: linear-gradient(135deg, #1f6feb 0%, #58a6ff 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5rem;
            margin: 0 auto 20px;
            border: 3px solid #30363d;
        }
        .form-label {
            color: #8b949e;
            font-weight: 600;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .form-control {
            background-color: #0d1117;
            border: 2px solid #30363d;
            color: #f0f6fc;
            padding: 12px;
            border-radius: 8px;
            transition: all 0.3s;
        }
        .form-control:focus {
            background-color: #0d1117;
            border-color: #58a6ff;
            color: #f0f6fc;
            box-shadow: 0 0 0 0.25rem rgba(88, 166, 255, 0.25);
        }
        .form-control::placeholder {
            color: #484f58;
        }
        .btn-custom {
            padding: 14px 30px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 1rem;
            transition: all 0.3s;
            border: none;
        }
        .btn-save {
            background: linear-gradient(135deg, #238636 0%, #2ea043 100%);
            color: white;
        }
        .btn-save:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(46, 160, 67, 0.4);
        }
        .btn-cancel {
            background-color: #30363d;
            color: #f0f6fc;
        }
        .btn-cancel:hover {
            background-color: #484f58;
            transform: translateY(-2px);
        }
        .alert-custom {
            border-radius: 8px;
            border: none;
            padding: 15px 20px;
            margin-bottom: 25px;
        }
        .info-box {
            background: #0d1117;
            border: 1px solid #30363d;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
        }
        .info-box-title {
            color: #58a6ff;
            font-weight: 600;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .info-box-text {
            color: #8b949e;
            font-size: 0.9rem;
            line-height: 1.6;
        }
        .input-icon {
            font-size: 1.2rem;
        }
        .required {
            color: #ff5555;
        }
    </style>
</head>
<body>

    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg">
        <div class="container-fluid">
            <a class="navbar-brand" href="home.php">
                <i class="bi bi-shield-lock-fill"></i> 
                SECURE<span>SYSTEMS</span>
            </a>

            <div class="ms-auto d-flex align-items-center gap-3">
                <div class="dropdown">
                    <a class="nav-link dropdown-toggle user-dropdown-toggle d-flex align-items-center gap-2" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="bi bi-person-circle"></i>
                        <span><?= htmlspecialchars($_SESSION['username'] ?? 'Usuário') ?></span>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="perfil.php"><i class="bi bi-person me-2"></i> Meu Perfil</a></li>
                        <li><a class="dropdown-item" href="site_config.php"><i class="bi bi-gear me-2"></i> Configurações</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item text-danger" href="logout.php"><i class="bi bi-box-arrow-right me-2"></i> Sair</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </nav>

    <div class="edit-container">
        <div class="edit-card">
            
            <!-- Header -->
            <div class="edit-header">
                <div class="avatar-edit">
                    <?= strtoupper(substr($usuario['nome_completo'], 0, 1)) ?>
                </div>
                <h3>✏️ Editar Perfil</h3>
                <p>Atualize suas informações pessoais</p>
            </div>

            <!-- Mensagem -->
            <?php if ($mensagem): ?>
                <div class="alert alert-<?= $mensagem_tipo ?> alert-custom">
                    <?= $mensagem ?>
                </div>
            <?php endif; ?>

            <!-- Info Box -->
            <div class="info-box">
                <div class="info-box-title">
                    ℹ️ Informações Importantes
                </div>
                <div class="info-box-text">
                    • Seu <strong>username</strong> não pode ser alterado<br>
                    • Use um email válido para recuperação de conta<br>
                    • Todos os campos marcados com <span class="required">*</span> são obrigatórios
                </div>
            </div>

            <!-- Formulário -->
            <form method="POST">
                
                <!-- Nome Completo -->
                <div class="mb-4">
                    <label class="form-label">
                        <span class="input-icon">👤</span>
                        Nome Completo <span class="required">*</span>
                    </label>
                    <input 
                        type="text" 
                        name="nome_completo" 
                        class="form-control" 
                        value="<?= htmlspecialchars($usuario['nome_completo']) ?>"
                        placeholder="Digite seu nome completo"
                        required
                    >
                </div>

                <!-- Email -->
                <div class="mb-4">
                    <label class="form-label">
                        <span class="input-icon">📧</span>
                        Email <span class="required">*</span>
                    </label>
                    <input 
                        type="email" 
                        name="email" 
                        class="form-control" 
                        value="<?= htmlspecialchars($usuario['email']) ?>"
                        placeholder="seu@email.com"
                        required
                    >
                </div>

                <!-- Data de Nascimento -->
                <div class="mb-4">
                    <label class="form-label">
                        <span class="input-icon">🎂</span>
                        Data de Nascimento <span class="required">*</span>
                    </label>
                    <input 
                        type="date" 
                        name="data_nascimento" 
                        class="form-control" 
                        value="<?= htmlspecialchars($usuario['data_nascimento']) ?>"
                        required
                    >
                </div>

                <!-- Cidade -->
                <div class="mb-4">
                    <label class="form-label">
                        <span class="input-icon">📍</span>
                        Cidade <span class="required">*</span>
                    </label>
                    <input 
                        type="text" 
                        name="cidade" 
                        class="form-control" 
                        value="<?= htmlspecialchars($usuario['cidade']) ?>"
                        placeholder="Digite sua cidade"
                        required
                    >
                </div>

                <!-- Username (Somente Leitura) -->
                <div class="mb-4">
                    <label class="form-label">
                        <span class="input-icon">🔑</span>
                        Username (não editável)
                    </label>
                    <input 
                        type="text" 
                        class="form-control" 
                        value="@<?= htmlspecialchars($usuario['username']) ?>"
                        disabled
                        style="cursor: not-allowed; opacity: 0.6;"
                    >
                </div>

                <!-- Botões -->
                <div class="row g-3 mt-4">
                    <div class="col-md-6">
                        <button type="submit" class="btn btn-custom btn-save w-100">
                            💾 Salvar Alterações
                        </button>
                    </div>
                    <div class="col-md-6">
                        <a href="perfil.php" class="btn btn-custom btn-cancel w-100">
                            ← Cancelar
                        </a>
                    </div>
                </div>

            </form>

            <!-- Informações Adicionais -->
            <div class="mt-4 pt-4" style="border-top: 1px solid #30363d;">
                <div class="row g-3">
                    <div class="col-md-6">
                        <div style="text-align: center; padding: 15px; background: #0d1117; border-radius: 8px;">
                            <div style="font-size: 0.85rem; color: #8b949e; margin-bottom: 5px;">Membro desde</div>
                            <div style="font-weight: 600; color: #58a6ff;">
                                <?php
                                // Você pode adicionar uma coluna created_at na tabela usuarios
                                // Por enquanto, vamos usar a data de nascimento como exemplo
                                echo date('d/m/Y');
                                ?>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div style="text-align: center; padding: 15px; background: #0d1117; border-radius: 8px;">
                            <div style="font-size: 0.85rem; color: #8b949e; margin-bottom: 5px;">Última atualização</div>
                            <div style="font-weight: 600; color: #58a6ff;">Agora</div>
                        </div>
                    </div>
                </div>
            </div>

        </div>

        <!-- Links Adicionais -->
        <div class="text-center mt-4">
            <p style="color: #8b949e; font-size: 0.9rem;">
                Precisa alterar sua senha? 
                <a href="site_config.php" style="color: #58a6ff; text-decoration: none; font-weight: 600;">
                    Acesse as Configurações →
                </a>
            </p>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Validação adicional no cliente
        document.querySelector('form').addEventListener('submit', function(e) {
            const nome = document.querySelector('[name="nome_completo"]').value.trim();
            const email = document.querySelector('[name="email"]').value.trim();
            const cidade = document.querySelector('[name="cidade"]').value.trim();
            
            if (nome.length < 3) {
                e.preventDefault();
                alert('❌ O nome deve ter pelo menos 3 caracteres!');
                return;
            }
            
            if (!email.includes('@') || !email.includes('.')) {
                e.preventDefault();
                alert('❌ Email inválido!');
                return;
            }
            
            if (cidade.length < 2) {
                e.preventDefault();
                alert('❌ Nome da cidade muito curto!');
                return;
            }
        });
        
        // Confirmação antes de sair se houver alterações
        let formModificado = false;
        document.querySelectorAll('input[type="text"], input[type="email"], input[type="date"]').forEach(input => {
            input.addEventListener('input', function() {
                formModificado = true;
            });
        });
        
        document.querySelector('.btn-cancel').addEventListener('click', function(e) {
            if (formModificado) {
                if (!confirm('Você tem alterações não salvas. Deseja realmente sair?')) {
                    e.preventDefault();
                }
            }
        });
    </script>

</body>
</html>