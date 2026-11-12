<?php
require_once "../modelo/bootstrap.php";
require_once "../modelo/proveedores.php";

Auth::requerirModuloApi('proveedores');
Auth::requerirPermisoApi('proveedores.gestionar');
Csrf::validarApi();

header('Content-Type: application/json');

$nombre = trim($_POST['nombre'] ?? '');
$nit = trim($_POST['nit'] ?? '');
$telefono = trim($_POST['telefono'] ?? '');
$direccion = trim($_POST['direccion'] ?? '');

if ($nombre === '') {
    echo json_encode(['error' => 'El nombre es obligatorio.']);
    exit;
}

echo json_encode((new proveedores())->crear($nombre, $nit, $telefono, $direccion));
