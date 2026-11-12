<?php
/**
 * Crear cliente al vuelo desde la pantalla de Ventas (para relacionarlo
 * con la factura, no necesariamente para venderle a crédito). A
 * propósito NO exige el permiso 'clientes.gestionar' — cualquiera que
 * pueda vender puede anotar rápido quién compró, sin poder tocar cupos
 * de crédito ni el resto de la ficha del cliente (eso sigue siendo
 * exclusivo de la pantalla de Clientes). Siempre crea con
 * límite_credito = 0.
 */
require_once "../modelo/bootstrap.php";
require_once "../modelo/clientes.php";

Auth::requerirModuloApi('clientes_credito');
Auth::requerirLoginApi();
Csrf::validarApi();

header('Content-Type: application/json');

$nombre = trim($_POST['nombre'] ?? '');
$telefono = trim($_POST['telefono'] ?? '');

if ($nombre === '') {
    echo json_encode(['error' => 'El nombre es obligatorio.']);
    exit;
}

// El id REAL del cliente recién creado (no el "1 = salió bien" de crear()): con ese id se asocia
// la mesa, la venta y el crédito, así que debe ser el de este cliente y no el de otro.
$id = (new clientes())->crearConId($nombre, null, $telefono !== '' ? $telefono : null, null, 0);

if ($id <= 0) {
    echo json_encode(['error' => 'No se pudo crear el cliente.']);
    exit;
}

echo json_encode(['ok' => true, 'id_cliente' => $id, 'nombre' => $nombre, 'saldo' => 0, 'limite_credito' => 0]);
