<?php
require_once "../modelo/bootstrap.php";
require_once "../modelo/Promociones.php";

Auth::requerirModuloApi('promociones');
Auth::requerirRolApi('admin');

header('Content-Type: application/json');
echo json_encode(Promociones::listar());
