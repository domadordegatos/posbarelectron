<?php
require_once "../modelo/bootstrap.php";
require_once "../modelo/combos.php";

Auth::requerirModuloApi('combos');

header('Content-Type: application/json');

$idProducto = filter_input(INPUT_POST, 'id_producto', FILTER_VALIDATE_INT);

if ($idProducto === false || $idProducto === null) {
    echo json_encode([]);
    exit;
}

echo json_encode((new combos())->obtenerComponentes($idProducto));
