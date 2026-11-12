<?php
require_once "../modelo/bootstrap.php";
require_once "../modelo/clientes.php";

Auth::requerirModuloApi('clientes_credito');
Auth::requerirPermisoApi('clientes.gestionar');
Csrf::validarApi();

header('Content-Type: application/json');

$id = filter_input(INPUT_POST, 'id_cliente', FILTER_VALIDATE_INT);
$nombre = trim($_POST['nombre'] ?? '');
$identificacion = trim($_POST['identificacion'] ?? '');
$telefono = trim($_POST['telefono'] ?? '');
$direccion = trim($_POST['direccion'] ?? '');
$limiteCredito = filter_input(INPUT_POST, 'limite_credito', FILTER_VALIDATE_FLOAT) ?: 0;
$estado = filter_input(INPUT_POST, 'estado', FILTER_VALIDATE_INT);

if ($id === false || $id === null || $nombre === '' || $estado === false || $estado === null) {
    echo json_encode(['error' => 'Datos inválidos.']);
    exit;
}

echo json_encode((new clientes())->editar($id, $nombre, $identificacion, $telefono, $direccion, $limiteCredito, $estado));
