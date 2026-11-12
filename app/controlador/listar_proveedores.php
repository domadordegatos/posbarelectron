<?php
require_once "../modelo/bootstrap.php";
require_once "../modelo/proveedores.php";

Auth::requerirModuloApi('proveedores');
Auth::requerirPermisoApi('proveedores.gestionar');

header('Content-Type: application/json');

echo json_encode((new proveedores())->listar());
