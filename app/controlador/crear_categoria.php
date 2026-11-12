<?php
require_once "../modelo/bootstrap.php";
require_once "../modelo/productos.php";

Auth::requerirPermisoApi('productos.editar');
Csrf::validarApi();

header('Content-Type: application/json');

$descripcion = trim($_POST['descripcion'] ?? '');

if ($descripcion === '') {
    echo json_encode(['error' => 'El nombre de la categoría es obligatorio.']);
    exit;
}

$obj = new productos();
$id = $obj->crearCategoria($descripcion);

echo json_encode(['ok' => true, 'id_categoria' => $id, 'descripcion' => $descripcion]);
