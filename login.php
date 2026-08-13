<?php
/**
 * Page de Connexion Admin - Blooming FTTH
 */

require 'config/session.php';
require 'config/helpers.php';

// Redirection si déjà connecté
if (isLoggedIn()) {
    header('Location: index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require 'config/database.php';

    $username = sanitize($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username && $password) {
        $stmt = $pdo->prepare('SELECT id, password FROM admin_users WHERE username = ?');
        $stmt->execute([$username]);
        $admin = $stmt->fetch();

        if ($admin && password_verify($password, $admin['password'])) {
            $_SESSION['admin_id'] = $admin['id'];
            $_SESSION['admin_username'] = $username;
            header('Location: index.php');
            exit;
        } else {
            $error = 'Identifiant ou mot de passe incorrect.';
        }
    } else {
        $error = 'Veuillez remplir tous les champs.';
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion - Blooming FTTH</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/modern-dashboard.css">
    <style>
        body {
            margin: 0;
            padding: 0;
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            overflow: hidden;
            font-family: 'Inter', sans-serif;
        }

        .login-bg-shapes {
            position: absolute;
            width: 100%;
            height: 100%;
            z-index: 0;
            overflow: hidden;
        }

        .shape {
            position: absolute;
            border-radius: 50%;
            filter: blur(100px);
            opacity: 0.15;
        }

        .shape-1 {
            width: 500px;
            height: 500px;
            background: var(--primary);
            top: -100px;
            left: -100px;
        }

        .shape-2 {
            width: 400px;
            height: 400px;
            background: var(--success);
            bottom: -50px;
            right: -50px;
        }

        .login-card {
            width: 100%;
            max-width: 400px;
            background: rgba(255, 255, 255, 0.03);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 24px;
            padding: 2.5rem;
            position: relative;
            z-index: 10;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
            animation: slideUp 0.6s ease-out;
        }

        @keyframes slideUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .login-logo {
            text-align: center;
            margin-bottom: 2rem;
        }

        .login-logo i {
            font-size: 3rem;
            background: linear-gradient(135deg, var(--primary), var(--success));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 1rem;
        }

        .login-title {
            color: #fff;
            font-size: 1.75rem;
            font-weight: 800;
            margin: 0;
            letter-spacing: -0.025em;
        }

        .login-subtitle {
            color: rgba(255, 255, 255, 0.5);
            font-size: 0.875rem;
            margin-top: 0.5rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            color: rgba(255, 255, 255, 0.7);
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 0.5rem;
        }

        .input-group {
            position: relative;
        }

        .input-group i {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: rgba(255, 255, 255, 0.3);
            font-size: 1rem;
        }

        .input-group input {
            width: 100%;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            padding: 0.75rem 1rem 0.75rem 2.75rem;
            color: #fff;
            font-size: 1rem;
            transition: all 0.2s;
        }

        .input-group input:focus {
            outline: none;
            background: rgba(255, 255, 255, 0.1);
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.1);
        }

        .btn-login {
            width: 100%;
            padding: 0.875rem;
            background: linear-gradient(135deg, var(--primary), #2563eb);
            color: #fff;
            border: none;
            border-radius: 12px;
            font-size: 1rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            margin-top: 2rem;
            box-shadow: 0 10px 15px -3px rgba(59, 130, 246, 0.3);
        }

        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 20px 25px -5px rgba(59, 130, 246, 0.4);
            background: linear-gradient(135deg, #2563eb, var(--primary));
        }

        .alert-error {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.2);
            color: #ef4444;
            padding: 0.75rem;
            border-radius: 12px;
            font-size: 0.875rem;
            margin-bottom: 1.5rem;
            text-align: center;
        }

        .login-footer {
            margin-top: 2rem;
            text-align: center;
            color: rgba(255, 255, 255, 0.3);
            font-size: 0.75rem;
        }
    </style>
</head>
<body>
    <div class="login-bg-shapes">
        <div class="shape shape-1"></div>
        <div class="shape shape-2"></div>
    </div>

    <div class="login-card">
        <div class="login-logo">
            <i class="fa-solid fa-satellite-dish"></i>
            <h1 class="login-title">Blooming FTTH</h1>
            <p class="login-subtitle">Console de Gestion</p>
        </div>

        <?php if ($error): ?>
            <div class="alert-error">
                <i class="fa-solid fa-circle-exclamation mr-2"></i>
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label for="username">Identifiant</label>
                <div class="input-group">
                    <i class="fa-solid fa-user"></i>
                    <input type="text" id="username" name="username" placeholder="Entrez votre identifiant" required autofocus>
                </div>
            </div>

            <div class="form-group">
                <label for="password">Mot de passe</label>
                <div class="input-group">
                    <i class="fa-solid fa-lock"></i>
                    <input type="password" id="password" name="password" placeholder="••••••••" required>
                </div>
            </div>

            <button type="submit" class="btn-login">
                Se Connecter
                <i class="fa-solid fa-arrow-right"></i>
            </button>
        </form>

        <div class="login-footer">
            &copy; <?php echo date('Y'); ?> Blooming Telecom &bull; FTTH Dashboard
        </div>
    </div>
</body>
</html>