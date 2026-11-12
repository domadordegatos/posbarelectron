<?php
/**
 * Corrige el cliente de una venta ya registrada (ej. ventas que quedaron a nombre de otra
 * persona por el error del "Crear cliente nuevo" de Ventas). Mismo nivel de confianza que
 * editar una factura ('facturas.editar'); queda en la auditoría.
 */
require_once "../modelo/bootstrap.php";
require_once "../modelo/facturas.php";

$usuario = Auth::requerirPermisoApi('facturas.editar');
Auth::requerirModuloApi('clientes_credito');
Csrf::validarApi();

header('Content-Type: application/json');

$idFactura = filter_input(INPUT_POST, 'id_factura', FILTER_VALIDATE_INT);
$clienteRaw = trim((string) ($_POST['cliente_id'] ?? ''));
$idCliente = $clienteRaw === '' ? null : filter_var($clienteRaw, FILTER_VALIDATE_INT);
$motivo = trim($_POST['motivo'] ?? '');

if ($idFactura === false || $idFactura === null || $idCliente === false) {
    echo json_encode(['error' => 'Datos inválidos.']);
    exit;
}

try {
    (new facturas())->cambiarClienteDeVenta($idFactura, $idCliente, (int) $usuario['id'], $motivo);
    echo json_encode(['ok' => true]);
} catch (InvalidArgumentException $e) {
    echo json_encode(['error' => $e->getMessage()]);
} catch (Throwable $e) {
    error_log('Error al cambiar el cliente de una venta: ' . $e->getMessage());
    echo json_encode(['error' => 'No se pudo cambiar el cliente.']);
}
