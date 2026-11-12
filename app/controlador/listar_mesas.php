<?php
require_once "../modelo/bootstrap.php";
require_once "../modelo/comandas.php";

Auth::requerirModuloApi('mesas_comandas');

header('Content-Type: application/json');

echo json_encode((new comandas())->listarConEstado());
