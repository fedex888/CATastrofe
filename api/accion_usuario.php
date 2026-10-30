<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/funciones.php';

requerir_encargado();

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    redirigir(BASE_URL . 'views/usuarios.php');
}
verificar_csrf();

$accion = (string)($_POST['accion'] ?? '');

const PASSWORD_MIN = 6;

/** Validaciones comunes de alta y edición (el HTML solo las repite por comodidad). */
function validar_datos_usuario(string $nombre, string $usuario, string $rol): void
{
    if ($nombre === '' || $usuario === '') throw new RuntimeException('Todos los campos son obligatorios.');
    if (largo_texto($nombre) > 100) throw new RuntimeException('El nombre es demasiado largo (máximo 100 caracteres).');
    if (!preg_match('/^[A-Za-z0-9_]{1,50}$/', $usuario)) {
        throw new RuntimeException('El usuario solo puede tener letras, números y guion bajo (máximo 50).');
    }
    if (!in_array($rol, ['encargado', 'gerencia'], true)) throw new RuntimeException('Rol inválido.');
}

try {
    if ($accion === 'crear') {
        $nombre   = trim((string)($_POST['nombre'] ?? ''));
        $usuario  = trim((string)($_POST['usuario'] ?? ''));
        $password = (string)($_POST['password'] ?? '');
        $rol      = (string)($_POST['rol'] ?? 'encargado');

        validar_datos_usuario($nombre, $usuario, $rol);
        if ($password === '') throw new RuntimeException('Todos los campos son obligatorios.');
        if (largo_texto($password) < PASSWORD_MIN) throw new RuntimeException('La contraseña debe tener al menos ' . PASSWORD_MIN . ' caracteres.');

        $stmt = $pdo->prepare('SELECT id FROM usuarios WHERE usuario = ?');
        $stmt->execute([$usuario]);
        if ($stmt->fetch()) throw new RuntimeException('Ese nombre de usuario ya está en uso.');

        $hash = password_hash($password, PASSWORD_DEFAULT);

        $pdo->prepare('INSERT INTO usuarios (nombre, usuario, password_hash, rol, activo) VALUES (?, ?, ?, ?, 1)')
            ->execute([$nombre, $usuario, $hash, $rol]);

        set_mensaje('ok', 'Usuario creado correctamente.');
        redirigir(BASE_URL . 'views/usuarios.php');
    }

    if ($accion === 'editar') {
        $id      = (int)($_POST['id'] ?? 0);
        $nombre  = trim((string)($_POST['nombre'] ?? ''));
        $usuario = trim((string)($_POST['usuario'] ?? ''));
        $rol     = (string)($_POST['rol'] ?? 'encargado');

        if ($id <= 0) throw new RuntimeException('Usuario inválido.');
        validar_datos_usuario($nombre, $usuario, $rol);

        $password = (string)($_POST['password'] ?? '');
        if ($password !== '' && largo_texto($password) < PASSWORD_MIN) {
            throw new RuntimeException('La contraseña debe tener al menos ' . PASSWORD_MIN . ' caracteres.');
        }

        // Nadie se cambia el rol a sí mismo: así siempre queda al menos un encargado activo.
        if ($id === (int)$_SESSION['usuario_id'] && $rol !== $_SESSION['rol']) {
            throw new RuntimeException('No podés cambiar tu propio rol. Pedile a otro encargado que lo haga.');
        }

        $stmt = $pdo->prepare('SELECT id FROM usuarios WHERE usuario = ? AND id != ?');
        $stmt->execute([$usuario, $id]);
        if ($stmt->fetch()) throw new RuntimeException('Ese nombre de usuario ya está en uso por otra persona.');

        $stmt = $pdo->prepare('UPDATE usuarios SET nombre = ?, usuario = ?, rol = ? WHERE id = ?');
        $stmt->execute([$nombre, $usuario, $rol, $id]);

        if ($password !== '') {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $pdo->prepare('UPDATE usuarios SET password_hash = ? WHERE id = ?')->execute([$hash, $id]);
        }

        set_mensaje('ok', 'Usuario actualizado.');
        redirigir(BASE_URL . 'views/usuarios.php');
    }

    if ($accion === 'baja' || $accion === 'alta') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) throw new RuntimeException('Usuario inválido.');
        if ($id === (int)$_SESSION['usuario_id']) throw new RuntimeException('No podés cambiarte el estado a vos mismo.');

        $nuevo_estado = ($accion === 'baja') ? 0 : 1;
        $pdo->prepare('UPDATE usuarios SET activo = ? WHERE id = ?')->execute([$nuevo_estado, $id]);

        set_mensaje('ok', $accion === 'baja' ? 'Usuario dado de baja.' : 'Usuario reactivado.');
        redirigir(BASE_URL . 'views/usuarios.php');
    }

    throw new RuntimeException('Acción no reconocida.');
} catch (Throwable $t) {
    // Errores de validación: se muestran; errores técnicos: se registran y se oculta el detalle.
    $msg = mensaje_error($t);
    set_mensaje('error', $msg);
    redirigir(BASE_URL . 'views/usuarios.php');
}