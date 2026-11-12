<?php
require_once "../modelo/bootstrap.php";
require_once "../modelo/vencimientos.php";

Auth::requerirModuloApi('vencimientos');
Auth::requerirPermisoApi('productos.ver');

header('Content-Type: application/json');

echo json_encode((new vencimientos())->todos());
