<?php
require_once "../modelo/bootstrap.php";
require_once "../modelo/movimientos.php";

Auth::requerirPermisoApi('movimientos.ver');

header('Content-Type: application/json');

$idFactura = filter_input(INPUT_GET, 'id_factura', FILTER_VALIDATE_INT);

if ($idFactura === false || $idFactura === null) {
    echo json_encode(null);
    exit;
}

$obj = new movimientos();
echo json_encode($obj->facturaDetalle($idFactura));
