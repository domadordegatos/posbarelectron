<?php
require_once "../modelo/bootstrap.php";
require_once "../modelo/productos.php";

Auth::requerirPermisoApi('productos.editar');
Csrf::validarApi();

header('Content-Type: application/json');

$idCategoria = filter_input(INPUT_POST, 'id_categoria', FILTER_VALIDATE_INT);
$descripcion = trim($_POST['descripcion'] ?? '');

if (!$idCategoria || $descripcion === '') {
    echo json_encode(['error' => 'Datos inválidos.']);
    exit;
}

$obj = new productos();
$obj->editarCategoria($idCategoria, $descripcion);

echo json_encode(['ok' => true, 'id_categoria' => $idCategoria, 'descripcion' => $descripcion]);
