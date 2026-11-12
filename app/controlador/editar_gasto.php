<?php
/**
 * Corrige el valor/cantidad/nota de un gasto o una pérdida de concepto ya registrados. Mismo nivel de
 * confianza que anularlo ('ventas.anular') — ver inventario::editarGasto().
 */
require_once "../modelo/bootstrap.php";
require_once "../modelo/inventario.php";

$usuario = Auth::requerirPermisoApi('ventas.anular');
Csrf::validarApi();

header('Content-Type: application/json');

$idFactura = filter_input(INPUT_POST, 'id_factura', FILTER_VALIDATE_INT);
$valor = filter_input(INPUT_POST, 'valor', FILTER_VALIDATE_FLOAT);
$descripcion = trim($_POST['descripcion'] ?? '');
$motivo = trim($_POST['motivo'] ?? '');
$cantidadRaw = trim($_POST['cantidad'] ?? '');
$cantidad = $cantidadRaw !== '' ? filter_var($cantidadRaw, FILTER_VALIDATE_INT) : null;

if ($idFactura === false || $idFactura === null || $valor === false || $valor === null || $motivo === '') {
    echo json_encode(['error' => 'Indica el valor y el motivo de la edición.']);
    exit;
}

try {
    (new inventario())->editarGasto($idFactura, (int) $usuario['id'], $valor, $descripcion, $motivo, $cantidad === false ? null : $cantidad);
    echo json_encode(['ok' => true]);
} catch (InvalidArgumentException $e) {
    echo json_encode(['error' => $e->getMessage()]);
} catch (Throwable $e) {
    error_log('Error al editar gasto: ' . $e->getMessage());
    echo json_encode(['error' => 'Error al editar el movimiento.']);
}
