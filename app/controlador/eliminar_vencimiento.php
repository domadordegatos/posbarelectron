<?php
/** Elimina el vencimiento completo (lote/fecha) con todos sus avisos. */
require_once "../modelo/bootstrap.php";
require_once "../modelo/vencimientos.php";

Auth::requerirModuloApi('vencimientos');
Auth::requerirPermisoApi('productos.editar');
Csrf::validarApi();

header('Content-Type: application/json');

$idVencimiento = filter_input(INPUT_POST, 'vencimiento_id', FILTER_VALIDATE_INT);
if (!$idVencimiento) {
    echo json_encode(['error' => 'Falta indicar el vencimiento.']);
    exit;
}

echo json_encode(['ok' => (new vencimientos())->eliminarVencimiento($idVencimiento)]);
