<?php
require_once "../modelo/bootstrap.php";
require_once "../modelo/productos.php";

Auth::requerirRolApi('admin');

header('Content-Type: application/json');

echo json_encode((new productos())->listarActivosSimple());
