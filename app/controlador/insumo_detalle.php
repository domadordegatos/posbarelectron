<?php
require_once "../modelo/bootstrap.php";
require_once "../modelo/movimientos.php";

Auth::requerirPermisoApi('movimientos.ver');

header('Content-Type: application/json');

$idInsumo = filter_input(INPUT_GET, 'id_insumo', FILTER_VALIDATE_INT);

if ($idInsumo === false || $idInsumo === null) {
    echo json_encode(null);
    exit;
}

$obj = new movimientos();
echo json_encode($obj->insumoDetalle($idInsumo));
