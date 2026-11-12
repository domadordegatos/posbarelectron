<?php
require_once "../modelo/bootstrap.php";
require_once "../modelo/ConteoInventario.php";

Auth::requerirPermisoApi('conteo_inventario.registrar');

header('Content-Type: application/json');

$idConteo = filter_input(INPUT_GET, 'conteo_id', FILTER_VALIDATE_INT);
if (!$idConteo) {
    echo json_encode(['error' => 'Conteo inválido.']);
    exit;
}

$obj = new ConteoInventario();
echo json_encode([
    'detalle' => $obj->detalle($idConteo),
    'resumen' => $obj->resumen($idConteo),
]);
