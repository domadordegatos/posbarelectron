<?php
require_once "../modelo/bootstrap.php";
require_once "../modelo/proveedores.php";

Auth::requerirModuloApi('proveedores');
Auth::requerirPermisoApi('proveedores.gestionar');
Csrf::validarApi();

header('Content-Type: application/json');

$id = filter_input(INPUT_POST, 'id_proveedor', FILTER_VALIDATE_INT);
$nombre = trim($_POST['nombre'] ?? '');
$nit = trim($_POST['nit'] ?? '');
$telefono = trim($_POST['telefono'] ?? '');
$direccion = trim($_POST['direccion'] ?? '');
$estado = filter_input(INPUT_POST, 'estado', FILTER_VALIDATE_INT);

if ($id === false || $id === null || $nombre === '' || $estado === false || $estado === null) {
    echo json_encode(['error' => 'Datos inválidos.']);
    exit;
}

echo json_encode((new proveedores())->editar($id, $nombre, $nit, $telefono, $direccion, $estado));
