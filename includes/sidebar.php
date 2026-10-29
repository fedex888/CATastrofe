<?php
$archivo_actual = basename($_SERVER['SCRIPT_NAME'] ?? '');
$es_gerencia    = ($_SESSION['rol'] ?? '') === 'gerencia';

$menu = [
    ['archivo' => 'dashboard.php',   'url' => BASE_URL . 'views/dashboard.php',   'label' => 'Dashboard',          'icon' => '📊'],
    ['archivo' => 'stock.php',       'url' => BASE_URL . 'views/stock.php',       'label' => 'Stock',              'icon' => '📦'],
    ['archivo' => 'proveedores.php', 'url' => BASE_URL . 'views/proveedores.php', 'label' => 'Proveedores',        'icon' => '🏢'],
    ['archivo' => 'ordenes.php',     'url' => BASE_URL . 'views/ordenes.php',     'label' => 'Órdenes de compra',  'icon' => '🧾'],
    ['archivo' => 'movimientos.php', 'url' => BASE_URL . 'views/movimientos.php', 'label' => 'Movimientos',        'icon' => '🔄'],
    ['archivo' => 'usuarios.php',    'url' => BASE_URL . 'views/usuarios.php',    'label' => 'Usuarios',           'icon' => '👥'],
];
$reportes = ['archivo' => 'reportes.php', 'url' => BASE_URL . 'views/reportes.php', 'label' => 'Reportes', 'icon' => '📈'];

$menu_final = $es_gerencia ? array_merge([$reportes], $menu) : array_merge($menu, [$reportes]);
?>
<aside class="sidebar">
    <nav>
        <?php foreach ($menu_final as $item):
            $active = ($archivo_actual === $item['archivo']);
            if (!$active && $archivo_actual === 'orden_nueva.php' && $item['archivo'] === 'ordenes.php') {
                $active = true;
            }
        ?>
        <a href="<?= e($item['url']) ?>" class="nav-item<?= $active ? ' active' : '' ?>">
            <span class="nav-icon"><?= $item['icon'] ?></span>
            <span><?= e($item['label']) ?></span>
        </a>
        <?php endforeach; ?>
    </nav>
    <div class="sidebar-foot">
        <small>TP · Compras y Depósito</small>
    </div>
</aside>