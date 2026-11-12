<?php
require_once "../modelo/bootstrap.php";
require_once "../modelo/inventario.php";

$usuario = Auth::requerirPermisoApi('perdidas.registrar');
Csrf::validarApi();

header('Content-Type: application/json');

$idProducto = filter_input(INPUT_POST, 'id_producto', FILTER_VALIDATE_INT);
$presentacion = ($_POST['presentacion'] ?? 'unidad') === 'paquete' ? 'paquete' : 'unidad';
$cantidad = filter_input(INPUT_POST, 'cantidad', FILTER_VALIDATE_INT);
$valorEstimado = filter_input(INPUT_POST, 'valor_estimado', FILTER_VALIDATE_FLOAT) ?: 0;
$descripcion = trim($_POST['descripcion'] ?? '');
$fecha = trim($_POST['fecha'] ?? '');

if ($idProducto === false || $idProducto === null || $cantidad === false || $cantidad === null || $cantidad <= 0) {
    echo json_encode(['error' => 'Datos inválidos.']);
    exit;
}

$obj = new inventario();

try {
    $idFactura = $obj->registrarPerdida((int) $usuario['id'], $idProducto, $presentacion, $cantidad, $valorEstimado, $descripcion, $fecha !== '' ? $fecha : null);
    echo json_encode(['ok' => true, 'id_factura' => $idFactura]);
} catch (InvalidArgumentException $e) {
    echo json_encode(['error' => $e->getMessage()]);
} catch (Throwable $e) {
    error_log('Error al registrar pérdida: ' . $e->getMessage());
    echo json_encode(['error' => 'Error al registrar la pérdida.']);
}
