<?php
session_start();

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit();
}

// Handle logout messages
$message = '';
if (isset($_GET['msg'])) {
    $messages = [
        'logged_out' => '✅ You have been logged out.',
        'session_expired' => '⚠️ Session expired. Please log in again.'
    ];
    $message = $messages[$_GET['msg']] ?? '';
}

// Handle login
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!$username || !$password) {
        $error = "Please enter username and password.";
    } else {
        // For demo: admin/pass123
        // In production, use database
        if ($username === 'admin' && $password === 'pass123') {
            $_SESSION['user_id'] = 1;
            $_SESSION['username'] = 'Admin';
            $_SESSION['role'] = 'admin';
            $_SESSION['login_time'] = time();
            header('Location: dashboard.php');
            exit();
        } else {
            $error = "Invalid username or password.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pharmacy Login</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: #f8f9fa;
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: Arial, sans-serif;
        }
        .login-box {
            width: 100%;
            max-width: 400px;
            padding: 20px;
        }
        .logo {
            text-align: center;
            margin-bottom: 30px;
        }
        .logo i {
            font-size: 3rem;
            color: #0d6efd;
            margin-bottom: 10px;
        }
        .logo h2 {
            color: #333;
            margin: 0;
        }
        .logo p {
            color: #666;
            margin: 0;
        }
        .form-control {
            border-radius: 8px;
            padding: 12px;
            font-size: 16px;
        }
        .btn-login {
            background: #0d6efd;
            color: white;
            border: none;
            padding: 12px;
            border-radius: 8px;
            font-size: 16px;
            width: 100%;
            margin-top: 20px;
        }
        .btn-login:hover {
            background: #0b5ed7;
        }
        .alert {
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .demo-note {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 10px;
            margin-top: 20px;
            font-size: 14px;
            color: #666;
            text-align: center;
        }
    </style>
</head>
<body>
<div class="login-box">
    <div class="logo">
        <i class="fas fa-capsules"></i>
        <h2>Pharmacy</h2>
        <p>Management System</p>
    </div>

    <?php if ($message): ?>
    <div class="alert alert-info">
        <?= htmlspecialchars($message) ?>
    </div>
    <?php endif; ?>

    <?php if ($error): ?>
    <div class="alert alert-danger">
        <?= htmlspecialchars($error) ?>
    </div>
    <?php endif; ?>

    <form method="POST">
        <div class="mb-3">
            <input type="text" name="username" class="form-control" 
                   placeholder="Username" value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" 
                   required autofocus>
        </div>
        
        <div class="mb-3">
            <input type="password" name="password" class="form-control" 
                   placeholder="Password" required>
        </div>
        
        <button type="submit" class="btn-login">
            Sign In
        </button>
    </form>

    
    <div class="demo-note">
        Demo: admin / pass123
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/js/all.min.js"></script>
</body>
</html>