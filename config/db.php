<?php
// =====================================================================
// Configuración central + conexión PDO
// =====================================================================
declare(strict_types=1);

date_default_timezone_set('America/Argentina/Buenos_Aires');

// --- Detección automática de BASE_URL --------------------------------
$doc_root = rtrim(str_replace('\\', '/', (string)realpath($_SERVER['DOCUMENT_ROOT'] ?? '')), '/');
$app_root = rtrim(str_replace('\\', '/', (string)realpath(__DIR__ . '/..')), '/');

if ($doc_root !== '' && strpos($app_root, $doc_root) === 0) {
    $base = substr($app_root, strlen($doc_root));
} else {
    $base = '/catastrofe';
}
define('BASE_URL', ($base === '' ? '' : $base) . '/');

// --- Modo depuración ------------------------------------------------
// Poner en true solo mientras se depura. Para la entrega: false.
define('APP_DEBUG', false);

// --- Conexión PDO ---------------------------------------------------
$db_host = '127.0.0.1';
$db_name = 'catastrofe_db';
$db_user = 'root';
$db_pass = '';

try {
    $pdo = new PDO(
        "mysql:host={$db_host};dbname={$db_name};charset=utf8mb4",
        $db_user,
        $db_pass,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
    // La hora de MySQL (NOW, CURDATE) debe coincidir con la de PHP (Argentina, sin horario de verano).
    $pdo->exec("SET time_zone = '-03:00'");
} catch (PDOException $e) {
    error_log('DB connection error: ' . $e->getMessage());
    if (APP_DEBUG) {
        die('Error de conexión: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'));
    }
    die('No se pudo conectar a la base de datos.');
}