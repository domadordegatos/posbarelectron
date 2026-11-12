<?php
require_once "../modelo/bootstrap.php";
require_once "../modelo/ConteoInventario.php";

Auth::requerirPermisoApi('conteo_inventario.registrar');

header('Content-Type: application/json');

$obj = new ConteoInventario();
$conteo = $obj->conteoAbierto();
if ($conteo === null) {
    echo json_encode(['conteo' => null]);
    exit;
}

echo json_encode([
    'conteo' => $conteo,
    'detalle' => $obj->detalle((int) $conteo['id_conteo']),
    'resumen' => $obj->resumen((int) $conteo['id_conteo']),
]);
