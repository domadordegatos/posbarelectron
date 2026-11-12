<?php
require_once "../modelo/bootstrap.php";
require_once "../modelo/vencimientos.php";

Auth::requerirModuloApi('vencimientos');
Auth::requerirPermisoApi('productos.ver');

header('Content-Type: application/json');

$productoId = filter_input(INPUT_GET, 'producto_id', FILTER_VALIDATE_INT);
if (!$productoId) {
    echo json_encode([]);
    exit;
}

echo json_encode((new vencimientos())->porProducto($productoId));
