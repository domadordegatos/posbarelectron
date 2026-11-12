<?php
require_once "../modelo/bootstrap.php";
require_once "../modelo/clientes.php";

Auth::requerirModuloApi('clientes_credito');
Auth::requerirPermisoApi('clientes.gestionar');
Csrf::validarApi();

header('Content-Type: application/json');

$nombre = trim($_POST['nombre'] ?? '');
$identificacion = trim($_POST['identificacion'] ?? '');
$telefono = trim($_POST['telefono'] ?? '');
$direccion = trim($_POST['direccion'] ?? '');
$limiteCredito = filter_input(INPUT_POST, 'limite_credito', FILTER_VALIDATE_FLOAT) ?: 0;

if ($nombre === '') {
    echo json_encode(['error' => 'El nombre es obligatorio.']);
    exit;
}

echo json_encode((new clientes())->crear($nombre, $identificacion, $telefono, $direccion, $limiteCredito));
