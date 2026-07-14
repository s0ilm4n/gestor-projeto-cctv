<?php
/**
 * Configuração da Base de Dados
 * Gestor de Projeto CCTV
 */
define('DB_HOST', 'localhost');
define('DB_NAME', 'gestor_cctv');
define('DB_USER', 'root');
define('DB_PASS', 'jarbas');
define('DB_CHARSET', 'utf8mb4');

/**
 * Obtém ligação PDO à base de dados
 */
function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            error_log('DB Error: ' . $e->getMessage());
            die('Erro de ligação à base de dados.');
        }
    }
    return $pdo;
}

/**
 * Obtém valor de config da BD
 */
function getConfig(string $chave, string $default = ''): string {
    try {
        $db = getDB();
        $stmt = $db->prepare("SELECT valor FROM config WHERE chave = ?");
        $stmt->execute([$chave]);
        $row = $stmt->fetch();
        return $row ? $row['valor'] : $default;
    } catch (PDOException $e) {
        return $default;
    }
}
