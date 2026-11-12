<?php
require_once "../modelo/bootstrap.php";
require_once "../modelo/mesas.php";

Auth::requerirModuloApi('mesas_comandas');
Auth::requerirRolApi('admin');

header('Content-Type: application/json');

echo json_encode((new mesas())->listar());
