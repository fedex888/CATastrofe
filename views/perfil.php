<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/funciones.php';
requerir_login(); // cualquier rol: cada persona edita solo su propia cuenta

$stmt = $pdo->prepare('SELECT nombre, usuario, rol, fecha_creacion FROM usuarios WHERE id = ?');
$stmt->execute([(int)$_SESSION['usuario_id']]);
$yo = $stmt->fetch();

$titulo = 'Mi perfil';
require __DIR__ . '/../includes/header.php';
?>
<div class="mb-3">
    <h1>Mi perfil</h1>
    <p class="muted">Tus datos de acceso. Rol actual: <strong><?= e($yo['rol']) ?></strong>. El rol y el alta o baja de cuentas los gestiona Gerencia.</p>
</div>

<div class="card perfil-card">
    <form class="form" action="<?= BASE_URL ?>api/accion_perfil.php" method="post" autocomplete="off">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

        <h3>Datos de la cuenta</h3>
        <div class="form-row">
            <label>Nombre completo <input type="text" name="nombre" required maxlength="100" value="<?= e($yo['nombre']) ?>"></label>
            <label>Usuario de login <input type="text" name="usuario" required maxlength="50" pattern="[a-zA-Z0-9_]+" value="<?= e($yo['usuario']) ?>"></label>
        </div>

        <hr>
        <h3>Cambiar contraseña</h3>
        <p class="muted">Dejá estos campos vacíos si no querés cambiarla.</p>
        <div class="form-row">
            <label>Nueva contraseña <input type="password" name="password_nueva" minlength="6" autocomplete="new-password"></label>
            <label>Repetir nueva contraseña <input type="password" name="password_repetir" minlength="6" autocomplete="new-password"></label>
        </div>

        <hr>
        <label>Contraseña actual
            <input type="password" name="password_actual" autocomplete="current-password">
            <span class="hint">Es obligatoria solo si cambiás el usuario o la contraseña.</span>
        </label>

        <div class="form-actions">
            <a class="btn btn-ghost" href="<?= BASE_URL ?>views/dashboard.php">Cancelar</a>
            <button type="submit" class="btn btn-primary">Guardar cambios</button>
        </div>
    </form>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
