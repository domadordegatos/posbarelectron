<?php
require_once "../modelo/bootstrap.php";
require_once "../modelo/inventario.php";

$usuario = Auth::requerirPermisoApi('ajustes_inventario.registrar');
Csrf::validarApi();

header('Content-Type: application/json');

$idProducto = filter_input(INPUT_POST, 'id_producto', FILTER_VALIDATE_INT);
$direccion = ($_POST['direccion'] ?? 'salida') === 'entrada' ? 'entrada' : 'salida';
$presentacion = ($_POST['presentacion'] ?? 'unidad') === 'paquete' ? 'paquete' : 'unidad';
$cantidad = filter_input(INPUT_POST, 'cantidad', FILTER_VALIDATE_INT);
$idMotivo = filter_input(INPUT_POST, 'id_motivo', FILTER_VALIDATE_INT);
$descripcion = trim($_POST['descripcion'] ?? '');
$fecha = trim($_POST['fecha'] ?? '');

if ($idProducto === false || $idProducto === null || $cantidad === false || $cantidad === null || $cantidad <= 0
    || $idMotivo === false || $idMotivo === null) {
    echo json_encode(['error' => 'Datos inválidos.']);
    exit;
}

$obj = new inventario();

try {
    $idFactura = $obj->registrarAjusteInventario(
        (int) $usuario['id'],
        $idProducto,
        $direccion,
        $presentacion,
        $cantidad,
        $idMotivo,
        $descripcion,
        $fecha !== '' ? $fecha : null
    );
    echo json_encode(['ok' => true, 'id_factura' => $idFactura]);
} catch (InvalidArgumentException $e) {
    echo json_encode(['error' => $e->getMessage()]);
} catch (Throwable $e) {
    error_log('Error al registrar ajuste de inventario: ' . $e->getMessage());
    echo json_encode(['error' => 'Error al registrar el movimiento.']);
}
