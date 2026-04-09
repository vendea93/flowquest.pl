<?php
require_once '../includes/config.php';
require_once '../includes/cms.php';

// Sprawdź czy już zalogowany
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header('Location: index.php');
    exit;
}

// Obsługa logowania
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if ($username === ADMIN_USERNAME && password_verify($password, ADMIN_PASSWORD_HASH)) {
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_username'] = $username;
        $_SESSION['login_time'] = time();
        
        // Zapisz log logowania
        $db = getDB();
        $stmt = $db->prepare('INSERT INTO form_submissions (form_type, email, source_url, ip_address, user_agent, notes) 
                             VALUES (?, ?, ?, ?, ?, ?)');
        $stmt->execute([
            'admin_login',
            $username,
            $_SERVER['HTTP_REFERER'] ?? '',
            $_SERVER['REMOTE_ADDR'] ?? '',
            $_SERVER['HTTP_USER_AGENT'] ?? '',
            'Successful login'
        ]);
        
        header('Location: index.php');
        exit;
    } else {
        $error = 'Nieprawidłowy login lub hasło';
        
        // Zapisz próbę nieudanego logowania
        $db = getDB();
        $stmt = $db->prepare('INSERT INTO form_submissions (form_type, email, source_url, ip_address, user_agent, notes) 
                             VALUES (?, ?, ?, ?, ?, ?)');
        $stmt->execute([
            'admin_login_failed',
            $username,
            $_SERVER['HTTP_REFERER'] ?? '',
            $_SERVER['REMOTE_ADDR'] ?? '',
            $_SERVER['HTTP_USER_AGENT'] ?? '',
            'Failed login attempt'
        ]);
    }
}
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FlowQuest Admin - Logowanie</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
        }
        .login-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
        }
        .brand-logo {
            text-align: center;
            margin-bottom: 30px;
        }
        .brand-logo h2 {
            color: #667eea;
            font-weight: bold;
        }
        .btn-login {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            color: white;
            padding: 12px;
            font-weight: 600;
        }
        .btn-login:hover {
            background: linear-gradient(135deg, #5a6fd8 0%, #6a4290 100%);
            color: white;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-5">
                <div class="login-card p-5">
                    <div class="brand-logo">
                        <h2>FlowQuest</h2>
                        <p class="text-muted">Panel Administracyjny</p>
                    </div>
                    
                    <?php if ($error): ?>
                        <div class="alert alert-danger"><?= h($error) ?></div>
                    <?php endif; ?>
                    
                    <form method="POST">
                        <div class="mb-3">
                            <label class="form-label">Login</label>
                            <input type="text" name="username" class="form-control" required 
                                   placeholder="Wprowadź login" autofocus>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Hasło</label>
                            <input type="password" name="password" class="form-control" required 
                                   placeholder="Wprowadź hasło">
                        </div>
                        
                        <div class="d-grid gap-2">
                            <button type="submit" name="login" class="btn btn-login">
                                Zaloguj się
                            </button>
                        </div>
                    </form>
                    
                    <div class="mt-4 text-center">
                        <p class="text-muted small">
                            © <?= date('Y') ?> FlowQuest<br>
                            Wersja 1.0
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>