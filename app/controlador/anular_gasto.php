<?php
/**
 * Anula un gasto (egreso) o una pérdida de un concepto. Mismo nivel de
 * confianza que anular una venta ('ventas.anular'). Las compras y pérdidas de
 * productos no se anulan aquí (ver inventario::anularGasto).
 */
require_once "../modelo/bootstrap.php";
require_once "../modelo/inventario.php";

$usuario = Auth::requerirPermisoApi('ventas.anular');
Csrf::validarApi();

header('Content-Type: application/json');

$idFactura = filter_input(INPUT_POST, 'id_factura', FILTER_VALIDATE_INT);
$motivo = trim($_POST['motivo'] ?? '');

if ($idFactura === false || $idFactura === null || $motivo === '') {
    echo json_encode(['error' => 'Indica el motivo de la anulación.']);
    exit;
}

try {
    (new inventario())->anularGasto($idFactura, (int) $usuario['id'], $motivo);
    echo json_encode(['ok' => true]);
} catch (InvalidArgumentException $e) {
    echo json_encode(['error' => $e->getMessage()]);
} catch (Throwable $e) {
    error_log('Error al anular gasto: ' . $e->getMessage());
    echo json_encode(['error' => 'Error al anular el movimiento.']);
}
