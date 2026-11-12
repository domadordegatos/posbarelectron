<?php
require_once "../modelo/bootstrap.php";
require_once "../modelo/facturas.php";

$usuario = Auth::requerirPermisoApi('ventas.anular');
Csrf::validarApi();

header('Content-Type: application/json');

$idFactura = filter_input(INPUT_POST, 'id_factura', FILTER_VALIDATE_INT);
$motivo = trim($_POST['motivo'] ?? '');

if ($idFactura === false || $idFactura === null || $motivo === '') {
    echo json_encode(['error' => 'Indica el motivo de la anulación.']);
    exit;
}

$descartar = json_decode($_POST['descartar'] ?? '[]', true);
$descartar = is_array($descartar) ? array_values(array_filter($descartar, 'is_numeric')) : [];

$obj = new facturas();

try {
    $obj->anularVenta($idFactura, (int) $usuario['id'], $motivo, $descartar);
    echo json_encode(['ok' => true]);
} catch (InvalidArgumentException $e) {
    echo json_encode(['error' => $e->getMessage()]);
} catch (Throwable $e) {
    error_log('Error al anular venta: ' . $e->getMessage());
    echo json_encode(['error' => 'Error al anular la venta.']);
}
