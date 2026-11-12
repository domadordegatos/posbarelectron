<?php
require_once "../modelo/bootstrap.php";
require_once "../modelo/productos.php";

Auth::requerirLoginApi();

header('Content-Type: application/json');

$id = filter_input(INPUT_POST, 'id_producto', FILTER_VALIDATE_INT);

if ($id === false || $id === null) {
    echo json_encode(['error' => 'Producto inválido.']);
    exit;
}

$obj = new productos();
$producto = $obj->obtenerProductoPorId($id);

echo json_encode($producto ?: ['error' => 'Producto no encontrado.']);
