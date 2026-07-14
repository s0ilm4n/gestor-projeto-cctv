<?php
/**
 * Autenticação e Segurança
 * Gestor de Projeto CCTV
 */
session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'domain'   => '',
    'secure'   => false,       // true em produção com HTTPS
    'httponly' => true,
    'samesite' => 'Lax'
]);
ini_set('session.use_only_cookies', 1);
ini_set('session.use_strict_mode', 1);
session_start();

require_once __DIR__ . '/../config/database.php';

// ===== TOKENS CSRF =====
function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}
function csrf_field(): string {
    return '<input type="hidden" name="csrf_token" value="' . csrf_token() . '">';
}
function verify_csrf(string $token = null): void {
    if ($token === null) $token = $_POST['csrf_token'] ?? '';
    if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        die('Erro de segurança: token CSRF inválido.');
    }
}

// ===== AUTENTICAÇÃO =====
function login(string $username, string $password): bool {
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM users WHERE username = ? AND ativo = 1");
    $stmt->execute([$username]);
    $user = $stmt->fetch();
    if ($user && password_verify($password, $user['password_hash'])) {
        session_regenerate_id(true);
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_nome'] = $user['nome'];
        $_SESSION['user_role'] = $user['role'];
        $_SESSION['user_username'] = $user['username'];
        return true;
    }
    return false;
}
function isLoggedIn(): bool { return isset($_SESSION['user_id']); }
function requireLogin(): void {
    if (!isLoggedIn()) { header('Location: login.php'); exit; }
}
function isAdmin(): bool { return $_SESSION['user_role'] === 'admin'; }
function requireAdmin(): void {
    requireLogin();
    if (!isAdmin()) { header('Location: index.php'); exit; }
}
function logout(): void {
    $_SESSION = [];
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params['path'], $params['domain'],
        $params['secure'], $params['httponly']
    );
    session_destroy();
    header('Location: login.php'); exit;
}
function getUserName(): string { return $_SESSION['user_nome'] ?? 'Técnico'; }
function getUserRole(): string { return $_SESSION['user_role'] ?? ''; }

// ===== VALIDAÇÃO DE INPUT =====
function validar_texto($valor, int $max = 255): string {
    return substr(trim($valor), 0, $max);
}
function validar_email($email): string {
    return filter_var(trim($email), FILTER_VALIDATE_EMAIL) ? trim($email) : '';
}
function validar_telefone($tel): string {
    $tel = preg_replace('/[^0-9+]/', '', trim($tel));
    return strlen($tel) <= 20 ? $tel : '';
}
function validar_data($data): string {
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $data)) {
        return $data;
    }
    return date('Y-m-d');
}
function validar_nif($nif): string {
    $nif = preg_replace('/[^0-9]/', '', trim($nif));
    return strlen($nif) === 9 ? $nif : '';
}

// ===== XSS HELPER =====
function e(string $str): string {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

// ===== FORMATAÇÃO =====
function fmtData(?string $data): string {
    if (!$data) return '-';
    $ts = strtotime($data);
    return $ts ? date('d/m/Y', $ts) : '-';
}
function fmtNumero($valor, int $decimais = 2): string {
    return number_format((float)$valor, $decimais, ',', '.');
}

// ===== DORI =====
function doriLabel(string $nivel): string {
    $labels = [
        'D' => 'Detecção',
        'O' => 'Observação',
        'R' => 'Reconhecimento',
        'I' => 'Identificação'
    ];
    return $labels[$nivel] ?? $nivel;
}
function doriPPM(string $nivel): int {
    $ppm = ['D' => 25, 'O' => 62, 'R' => 125, 'I' => 250];
    return $ppm[$nivel] ?? 0;
}
function doriBadge($ppm, int $objetivo = 125): string {
    $ppm = (float)$ppm;
    $conforme = $ppm >= $objetivo;
    $cls = $conforme ? 'badge-success' : 'badge-danger';
    $ico = $conforme ? '✅' : '❌';
    return "<span class=\"badge {$cls}\">{$ico} {$ppm} ppm</span>";
}
