<?php
require_once __DIR__ . '/includes/auth.php';
if (isLoggedIn()) { header('Location: index.php'); exit; }
$erro = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    if (login($username, $password)) {
        header('Location: index.php'); exit;
    }
    $erro = 'Credenciais inválidas.';
}
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login — Gestor de Projeto CCTV</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body class="login-body">
    <div class="login-container">
        <div class="login-box">
            <div class="login-icon">📹</div>
            <h1>Gestor de Projeto CCTV</h1>
            <p class="login-subtitle">Aceda ao sistema de gestão</p>
            <?php if ($erro): ?>
                <div class="alert alert-danger"><?= e($erro) ?></div>
            <?php endif; ?>
            <form method="POST">
                <div class="form-group">
                    <label for="username">Utilizador</label>
                    <input type="text" id="username" name="username" required autofocus>
                </div>
                <div class="form-group">
                    <label for="password">Palavra-passe</label>
                    <input type="password" id="password" name="password" required>
                </div>
                <button type="submit" class="btn btn-primary btn-block">Entrar</button>
            </form>
            <p class="login-footer">Gestor de Projeto CCTV v1.0</p>
        </div>
    </div>
</body>
</html>
