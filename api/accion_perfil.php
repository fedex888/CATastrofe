<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/funciones.php';

// Cualquier rol, pero SIEMPRE sobre la propia cuenta: el id sale de la sesión, nunca del formulario,
// y el rol y el estado (activo) no se tocan desde acá.
requerir_login();

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    redirigir(BASE_URL . 'views/perfil.php');
}
verificar_csrf();

try {
    $id       = (int)$_SESSION['usuario_id'];
    $nombre   = trim((string)($_POST['nombre'] ?? ''));
    $usuario  = trim((string)($_POST['usuario'] ?? ''));
    $actual   = (string)($_POST['password_actual'] ?? '');
    $nueva    = (string)($_POST['password_nueva'] ?? '');
    $repetida = (string)($_POST['password_repetir'] ?? '');

    validar_nombre_usuario($nombre, $usuario);

    $stmt = $pdo->prepare('SELECT usuario, password_hash FROM usuarios WHERE id = ?');
    $stmt->execute([$id]);
    $fila = $stmt->fetch();
    if (!$fila) throw new RuntimeException('Usuario inválido.');

    $cambia_usuario = ($usuario !== $fila['usuario']);
    $cambia_pass    = ($nueva !== '' || $repetida !== '');

    if ($cambia_pass) {
        validar_password_nueva($nueva);
        if ($nueva !== $repetida) throw new RuntimeException('Las contraseñas nuevas no coinciden.');
    }

    // Cambiar las credenciales exige confirmar la contraseña actual.
    if (($cambia_usuario || $cambia_pass) && !password_verify($actual, (string)$fila['password_hash'])) {
        throw new RuntimeException('La contraseña actual no es correcta.');
    }

    $stmt = $pdo->prepare('SELECT id FROM usuarios WHERE usuario = ? AND id != ?');
    $stmt->execute([$usuario, $id]);
    if ($stmt->fetch()) throw new RuntimeException('Ese nombre de usuario ya está en uso por otra persona.');

    $pdo->prepare('UPDATE usuarios SET nombre = ?, usuario = ? WHERE id = ?')->execute([$nombre, $usuario, $id]);
    if ($cambia_pass) {
        $pdo->prepare('UPDATE usuarios SET password_hash = ? WHERE id = ?')
            ->execute([password_hash($nueva, PASSWORD_DEFAULT), $id]);
        session_regenerate_id(true);
    }

    $_SESSION['nombre'] = $nombre;
    set_mensaje('ok', 'Tu perfil se actualizó correctamente.');
    redirigir(BASE_URL . 'views/perfil.php');
} catch (Throwable $t) {
    set_mensaje('error', mensaje_error($t));
    redirigir(BASE_URL . 'views/perfil.php');
}
