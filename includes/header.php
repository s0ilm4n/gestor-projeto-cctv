<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle ?? 'Gestor de Projeto CCTV') ?></title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
<?php if (isLoggedIn()): ?>
<div class="app-layout">
    <aside class="sidebar">
        <div class="sidebar-header">
            <h2>📹 CCTV</h2>
            <small><?= e(getConfig('empresa_nome', 'Gestor de Projeto')) ?></small>
        </div>
        <nav class="sidebar-nav">
            <ul>
                <li><a href="index.php" class="<?= $page === 'dashboard' ? 'active' : '' ?>">📊 Dashboard</a></li>
                <li><a href="index.php?p=projetos" class="<?= $page === 'projetos' ? 'active' : '' ?>">📋 Projetos</a></li>
                <li><a href="index.php?p=clientes" class="<?= $page === 'clientes' ? 'active' : '' ?>">👥 Clientes</a></li>
                <li><a href="index.php?p=equipamentos" class="<?= $page === 'equipamentos' ? 'active' : '' ?>">📹 Equipamentos</a></li>
                <li><hr class="sidebar-divider"></li>
                <li><a href="index.php?p=dori" class="<?= $page === 'dori' ? 'active' : '' ?>">🔬 Cálculos DORI</a></li>
                <li><a href="index.php?p=plantas" class="<?= $page === 'plantas' ? 'active' : '' ?>">🗺️ Editor de Plantas</a></li>
                <li><hr class="sidebar-divider"></li>
                <?php if (isAdmin()): ?>
                <li><a href="index.php?p=config" class="<?= $page === 'config' ? 'active' : '' ?>">⚙️ Configurações</a></li>
                <li><a href="index.php?p=users" class="<?= $page === 'users' ? 'active' : '' ?>">👤 Utilizadores</a></li>
                <?php endif; ?>
            </ul>
        </nav>
        <div class="sidebar-footer">
            <span><?= e(getUserName()) ?> (<?= e(getUserRole()) ?>)</span>
            <a href="logout.php" class="btn-logout">🚪 Sair</a>
        </div>
    </aside>
    <main class="main-content">
<?php endif; ?>
