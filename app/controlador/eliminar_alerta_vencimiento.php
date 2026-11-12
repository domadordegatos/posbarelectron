<?php
require_once "../modelo/bootstrap.php";
require_once "../modelo/vencimientos.php";

Auth::requerirModuloApi('vencimientos');
Auth::requerirPermisoApi('productos.editar');
Csrf::validarApi();

header('Content-Type: application/json');

$idAlerta = filter_input(INPUT_POST, 'id_alerta', FILTER_VALIDATE_INT);
if (!$idAlerta) {
    echo json_encode(['error' => 'Falta indicar la alerta.']);
    exit;
}

echo json_encode(['ok' => (new vencimientos())->eliminar($idAlerta)]);
