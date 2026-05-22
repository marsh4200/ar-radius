<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';

Auth::start();
if (Auth::check()) {
    header('Location: /index.php');
    exit;
}

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Throttle: simple delay on failed attempts via session counter
    $username = trim((string)($_POST['username'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    if ($username === '' || $password === '') {
        $error = 'Please enter a username and password.';
    } elseif (Auth::login($username, $password)) {
        header('Location: /index.php');
        exit;
    } else {
        // small delay to slow brute-force
        usleep(800000);
        $error = 'Invalid username or password.';
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="dark">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Sign in · AR Radius</title>
<link rel="stylesheet" href="/assets/css/bootstrap.min.css">
<link rel="stylesheet" href="/assets/css/app.css">
</head>
<body class="ar-login-page">
<div class="ar-login-card">
    <div class="ar-login-brand">
        <div class="ar-brand-mark">AR</div>
        <h2>AR Radius</h2>
        <p>FreeRADIUS Management Console</p>
    </div>

    <?php if ($error): ?>
        <div class="ar-alert"><?= e($error) ?></div>
    <?php endif ?>

    <form method="post" autocomplete="off" novalidate>
        <div class="ar-form-group">
            <label class="ar-label" for="username">Username</label>
            <input type="text" id="username" name="username" class="ar-input"
                   required autofocus autocomplete="username"
                   value="<?= e($_POST['username'] ?? '') ?>">
        </div>
        <div class="ar-form-group">
            <label class="ar-label" for="password">Password</label>
            <input type="password" id="password" name="password" class="ar-input"
                   required autocomplete="current-password">
        </div>
        <button type="submit" class="ar-btn ar-btn-primary w-100" style="justify-content:center;">
            Sign in
        </button>
    </form>
    <div class="text-center small text-muted mt-4">
        v<?= e(ar_version()) ?>
    </div>
</div>
</body>
</html>
