<?php
// =====================================================================
// Sesión, roles y CSRF
// =====================================================================
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax',
        'path'     => '/',
    ]);
    session_start();
}

function requerir_login(): void
{
    global $pdo;

    if (empty($_SESSION['usuario_id'])) {
        header('Location: ' . BASE_URL . 'views/login.php');
        exit;
    }

    // Se revalida contra la base en cada request: si el usuario fue dado de baja
    // o le cambiaron el rol, el cambio rige de inmediato (no recién al volver a ingresar).
    $stmt = $pdo->prepare('SELECT nombre, rol, activo FROM usuarios WHERE id = ?');
    $stmt->execute([(int)$_SESSION['usuario_id']]);
    $u = $stmt->fetch();

    if (!$u || (int)$u['activo'] !== 1) {
        $_SESSION = [];
        session_regenerate_id(true);
        $_SESSION['mensaje'] = [
            'tipo'  => 'error',
            'texto' => 'Tu cuenta ya no está habilitada. Consultá con gerencia.',
        ];
        header('Location: ' . BASE_URL . 'views/login.php');
        exit;
    }

    $_SESSION['nombre'] = $u['nombre'];
    $_SESSION['rol']    = $u['rol'];
}

function requerir_rol(string $rol): void
{
    requerir_login();
    if (($_SESSION['rol'] ?? '') !== $rol) {
        $_SESSION['mensaje'] = [
            'tipo'  => 'error',
            'texto' => 'No tenés permisos para realizar esa acción.',
        ];
        header('Location: ' . BASE_URL . 'views/dashboard.php');
        exit;
    }
}

/** Operaciones del depósito: stock, proveedores, órdenes y movimientos. */
function requerir_encargado(): void
{
    requerir_rol('encargado');
}

/** Gestión de usuarios: solo gerencia. */
function requerir_gerencia(): void
{
    requerir_rol('gerencia');
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verificar_csrf(): void
{
    $token = (string)($_POST['csrf_token'] ?? '');
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        http_response_code(403);
        die('Token CSRF inválido. Recargá la página e intentá de nuevo.');
    }
}