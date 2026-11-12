<?php
require_once "../modelo/bootstrap.php";
require_once "../modelo/facturas.php";

$usuario = Auth::requerirPermisoApi('ventas.anular');
Csrf::validarApi();

header('Content-Type: application/json');

$idMovimiento = filter_input(INPUT_POST, 'id_movimiento', FILTER_VALIDATE_INT);
// FLOAT y no INT — una línea vendida por peso (báscula) se puede devolver
// en kilos con decimales (ej. 1.5 kg), no solo en unidades enteras.
$cantidad = filter_input(INPUT_POST, 'cantidad', FILTER_VALIDATE_FLOAT);
$motivo = trim($_POST['motivo'] ?? '');

if ($idMovimiento === false || $idMovimiento === null || $cantidad === false || $cantidad === null || $motivo === '') {
    echo json_encode(['error' => 'Indica la cantidad y el motivo de la devolución.']);
    exit;
}

$obj = new facturas();

try {
    $idFacturaDevolucion = $obj->devolverLinea($idMovimiento, (int) $usuario['id'], $cantidad, $motivo);
    echo json_encode(['ok' => true, 'id_factura' => $idFacturaDevolucion]);
} catch (InvalidArgumentException $e) {
    echo json_encode(['error' => $e->getMessage()]);
} catch (Throwable $e) {
    error_log('Error al devolver línea: ' . $e->getMessage());
    echo json_encode(['error' => 'Error al procesar la devolución.']);
}
