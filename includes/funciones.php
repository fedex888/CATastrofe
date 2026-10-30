<?php
// =====================================================================
// Helpers generales y operaciones de stock
// =====================================================================
declare(strict_types=1);

function e($v): string
{
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

/**
 * Valida una fecha en formato Y-m-d (incluye que exista en el calendario).
 */
function fecha_valida(string $fecha): bool
{
    $d = DateTime::createFromFormat('!Y-m-d', $fecha);
    return $d !== false && $d->format('Y-m-d') === $fecha;
}

/**
 * Cantidad de caracteres de un texto UTF-8 (sin depender de la extensión mbstring).
 */
function largo_texto(string $s): int
{
    return (int)preg_match_all('/./su', $s);
}

/**
 * Valida una fecha de vencimiento: debe existir en el calendario y no ser pasada.
 * Lanza RuntimeException (mensaje apto para mostrar al usuario).
 */
function validar_vencimiento(string $fecha): void
{
    if (!fecha_valida($fecha)) {
        throw new RuntimeException('La fecha de vencimiento no es válida.');
    }
    if ($fecha < date('Y-m-d')) {
        throw new RuntimeException('La fecha de vencimiento no puede ser una fecha pasada.');
    }
}

/**
 * Valida la unidad de medida contra la lista permitida.
 */
function validar_unidad(string $unidad): string
{
    if (!in_array($unidad, ['unidad', 'kg', 'litro'], true)) {
        throw new RuntimeException('Unidad de medida inválida.');
    }
    return $unidad;
}

/**
 * Devuelve el mensaje que se muestra al usuario.
 * Los errores de validación (RuntimeException) se muestran tal cual;
 * los errores técnicos de base de datos se registran y se ocultan.
 */
function mensaje_error(Throwable $t): string
{
    $tecnico = $t instanceof PDOException || !($t instanceof RuntimeException);

    if ($tecnico) {
        error_log('Error interno: ' . $t->getMessage());
        if (defined('APP_DEBUG') && APP_DEBUG) {
            return $t->getMessage();
        }
        return 'Ocurrió un error interno. Intentá nuevamente.';
    }

    return $t->getMessage();
}

function redirigir(string $url): void
{
    header('Location: ' . $url);
    exit;
}

function set_mensaje(string $tipo, string $texto): void
{
    $_SESSION['mensaje'] = ['tipo' => $tipo, 'texto' => $texto];
}

function mensaje_flash(): ?array
{
    if (empty($_SESSION['mensaje'])) {
        return null;
    }
    $m = $_SESSION['mensaje'];
    unset($_SESSION['mensaje']);
    return $m;
}

function estado_stock($actual, $minimo): string
{
    $actual = (float)$actual;
    $minimo = (float)$minimo;
    if ($actual <= $minimo) {
        return 'critico';
    }
    if ($actual <= $minimo * 1.2) {
        return 'proximo';
    }
    return 'normal';
}

function etiqueta_estado(string $estado): string
{
    return match ($estado) {
        'critico' => 'Crítico',
        'proximo' => 'Próximo a agotarse',
        default   => 'Normal',
    };
}

function cantidad_sugerida($stock_actual, $stock_maximo, $en_pedido): float
{
    $sug = (float)$stock_maximo - (float)$stock_actual - (float)$en_pedido;
    return $sug > 0 ? $sug : 0.0;
}

function motivo_id(PDO $pdo, string $codigo): int
{
    $stmt = $pdo->prepare('SELECT id FROM motivos_movimiento WHERE codigo = ?');
    $stmt->execute([$codigo]);
    return (int)$stmt->fetchColumn();
}

/**
 * Registra una entrada y suma al stock. Debe llamarse dentro de una transacción.
 */
function registrar_entrada(
    PDO $pdo,
    int $producto_id,
    int $usuario_id,
    int $motivo_id,
    float $cantidad,
    ?string $fecha_vencimiento = null,
    ?int $orden_id = null
): void {
    $stmt = $pdo->prepare(
        "INSERT INTO movimientos_deposito
            (producto_id, usuario_id, motivo_id, orden_id, tipo, cantidad, fecha_vencimiento)
         VALUES (?, ?, ?, ?, 'entrada', ?, ?)"
    );
    $stmt->execute([$producto_id, $usuario_id, $motivo_id, $orden_id, $cantidad, $fecha_vencimiento]);

    $pdo->prepare('UPDATE productos SET stock_actual = stock_actual + ? WHERE id = ?')
        ->execute([$cantidad, $producto_id]);
}

/**
 * Registra una salida controlando que no deje el stock negativo.
 * Debe llamarse dentro de una transacción.
 */
function registrar_salida(
    PDO $pdo,
    int $producto_id,
    int $usuario_id,
    int $motivo_id,
    float $cantidad
): void {
    $stmt = $pdo->prepare(
        'UPDATE productos SET stock_actual = stock_actual - ?
         WHERE id = ? AND stock_actual >= ?'
    );
    $stmt->execute([$cantidad, $producto_id, $cantidad]);
    if ($stmt->rowCount() === 0) {
        throw new RuntimeException('Stock insuficiente para la salida solicitada.');
    }

    $pdo->prepare(
        "INSERT INTO movimientos_deposito
            (producto_id, usuario_id, motivo_id, tipo, cantidad)
         VALUES (?, ?, ?, 'salida', ?)"
    )->execute([$producto_id, $usuario_id, $motivo_id, $cantidad]);
}

/**
 * Calcula, para los productos activos que controlan vencimiento,
 * la cantidad estimada en depósito de cada lote (PEPS).
 * Los lotes más antiguos se consumen primero, por lo que el stock
 * remanente se asigna a los más nuevos.
 * Devuelve un array de lotes con cantidad > 0.
 */
function obtener_lotes_peps(PDO $pdo): array
{
    $sql = "SELECT m.id, m.producto_id, p.nombre AS producto, c.nombre AS categoria,
                   m.cantidad, m.fecha, m.fecha_vencimiento, p.stock_actual
            FROM movimientos_deposito m
            JOIN productos p   ON p.id = m.producto_id
            JOIN categorias c  ON c.id = p.categoria_id
            WHERE m.tipo = 'entrada' AND m.fecha_vencimiento IS NOT NULL
              AND p.activo = 1 AND c.controla_vencimiento = 1
            ORDER BY m.producto_id, m.fecha DESC, m.id DESC";
    $rows = $pdo->query($sql)->fetchAll();

    $por_producto = [];
    foreach ($rows as $r) {
        $por_producto[(int)$r['producto_id']][] = $r;
    }

    $hoy_ts = strtotime('today');
    $lotes = [];
    foreach ($por_producto as $pid => $lista) {
        $stock = (float)$lista[0]['stock_actual'];
        foreach ($lista as $lot) {
            if ($stock <= 0) {
                break;
            }
            $asignado = min($stock, (float)$lot['cantidad']);
            $stock -= $asignado;
            if ($asignado <= 0) {
                continue;
            }
            $venc_ts = strtotime((string)$lot['fecha_vencimiento']);
            $dias = (int)ceil(($venc_ts - $hoy_ts) / 86400);

            if ($dias < 0) {
                $ventana = 'vencido';
            } elseif ($dias <= 30) {
                $ventana = '30';
            } elseif ($dias <= 60) {
                $ventana = '60';
            } elseif ($dias <= 90) {
                $ventana = '90';
            } else {
                $ventana = 'normal';
            }

            $lotes[] = [
                'movimiento_id'     => (int)$lot['id'],
                'producto_id'       => $pid,
                'producto'          => $lot['producto'],
                'categoria'         => $lot['categoria'],
                'cantidad'          => $asignado,
                'fecha_entrada'     => $lot['fecha'],
                'fecha_vencimiento' => $lot['fecha_vencimiento'],
                'dias_restantes'    => $dias,
                'ventana'           => $ventana,
            ];
        }
    }
    return $lotes;
}