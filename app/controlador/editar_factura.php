<?php
require_once "../modelo/bootstrap.php";
require_once "../modelo/facturas.php";

$usuario = Auth::requerirPermisoApi('facturas.editar');
Csrf::validarApi();

header('Content-Type: application/json');

$idFactura = filter_input(INPUT_POST, 'id_factura', FILTER_VALIDATE_INT);
$nuevoAjuste = filter_input(INPUT_POST, 'ajuste', FILTER_VALIDATE_FLOAT);
$motivo = trim($_POST['motivo'] ?? '');

if ($idFactura === false || $idFactura === null || $nuevoAjuste === false || $nuevoAjuste === null || $motivo === '') {
    echo json_encode(['error' => 'Indica el nuevo ajuste y el motivo de la edición.']);
    exit;
}

$obj = new facturas();

try {
    $resultado = $obj->editarFactura($idFactura, (int) $usuario['id'], $nuevoAjuste, $motivo);
    echo json_encode(['ok' => true] + $resultado);
} catch (InvalidArgumentException $e) {
    echo json_encode(['error' => $e->getMessage()]);
} catch (Throwable $e) {
    error_log('Error al editar factura: ' . $e->getMessage());
    echo json_encode(['error' => 'Error al editar la factura.']);
}
