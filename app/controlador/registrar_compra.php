<?php
require_once "../modelo/bootstrap.php";
require_once "../modelo/inventario.php";

$usuario = Auth::requerirPermisoApi('compras.registrar');
Csrf::validarApi();

header('Content-Type: application/json');

$idProducto = filter_input(INPUT_POST, 'id_producto', FILTER_VALIDATE_INT);
$presentacion = ($_POST['presentacion'] ?? 'unidad') === 'paquete' ? 'paquete' : 'unidad';
$cantidad = filter_input(INPUT_POST, 'cantidad', FILTER_VALIDATE_INT);
$costoTotal = filter_input(INPUT_POST, 'costo_total', FILTER_VALIDATE_FLOAT);
$proveedorId = filter_input(INPUT_POST, 'proveedor_id', FILTER_VALIDATE_INT);
$proveedorId = ($proveedorId === false || $proveedorId === null) ? null : $proveedorId;
$fecha = trim($_POST['fecha'] ?? '');

if ($idProducto === false || $idProducto === null || $cantidad === false || $cantidad === null || $cantidad <= 0
    || $costoTotal === false || $costoTotal === null || $costoTotal < 0
) {
    echo json_encode(['error' => 'Datos inválidos.']);
    exit;
}

$obj = new inventario();

try {
    $idFactura = $obj->registrarCompra((int) $usuario['id'], $idProducto, $presentacion, $cantidad, $costoTotal, $proveedorId, $fecha !== '' ? $fecha : null);
    echo json_encode(['ok' => true, 'id_factura' => $idFactura]);
} catch (InvalidArgumentException $e) {
    echo json_encode(['error' => $e->getMessage()]);
} catch (Throwable $e) {
    error_log('Error al registrar compra: ' . $e->getMessage());
    echo json_encode(['error' => 'Error al registrar la compra.']);
}
