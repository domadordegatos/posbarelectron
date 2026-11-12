<?php
require_once "../modelo/bootstrap.php";
require_once "../modelo/facturas.php";

$usuario = Auth::requerirModuloApi('clientes_credito');
Auth::requerirPermisoApi('clientes.gestionar');
Csrf::validarApi();

header('Content-Type: application/json');

$clienteId = filter_input(INPUT_POST, 'cliente_id', FILTER_VALIDATE_INT);
$valor = filter_input(INPUT_POST, 'valor', FILTER_VALIDATE_FLOAT);
$descripcion = trim($_POST['descripcion'] ?? '');

if ($clienteId === false || $clienteId === null || $valor === false || $valor === null || $valor <= 0) {
    echo json_encode(['error' => 'Datos inválidos.']);
    exit;
}

$obj = new facturas();

try {
    $idFactura = $obj->registrarAbono((int) $usuario['id'], $clienteId, $valor, $descripcion);
    echo json_encode(['ok' => true, 'id_factura' => $idFactura]);
} catch (InvalidArgumentException $e) {
    echo json_encode(['error' => $e->getMessage()]);
} catch (Throwable $e) {
    error_log('Error al registrar abono: ' . $e->getMessage());
    echo json_encode(['error' => 'Error al registrar el abono.']);
}
