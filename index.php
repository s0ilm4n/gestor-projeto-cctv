<?php
/**
 * Router principal — Gestor de Projeto CCTV
 */
require_once __DIR__ . '/includes/auth.php';
requireLogin();

$page = $_GET['p'] ?? 'dashboard';
$allowed_pages = [
    'dashboard', 'projetos', 'clientes', 'equipamentos',
    'dori', 'plantas',
    'config', 'users'
];
// Admin-only
$admin_pages = ['config', 'users'];
if (!in_array($page, $allowed_pages)) $page = 'dashboard';
if (in_array($page, $admin_pages) && !isAdmin()) $page = 'dashboard';

$pageTitle = 'Gestor de Projeto CCTV';

// 🟢 Processar POST primeiro (pode fazer redirects)
ob_start();
include __DIR__ . '/pages/' . $page . '.php';
$pageContent = ob_get_clean();

// Flash message
$flash = $_SESSION['flash'] ?? '';
unset($_SESSION['flash']);

// Agora sim, renderizar header + conteúdo
include __DIR__ . '/includes/header.php';
if ($flash): ?>
<div class="flash-message"><?= e($flash) ?></div>
<?php endif;
echo $pageContent;
include __DIR__ . '/includes/footer.php';
