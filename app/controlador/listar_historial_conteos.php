<?php
require_once "../modelo/bootstrap.php";
require_once "../modelo/ConteoInventario.php";

Auth::requerirPermisoApi('conteo_inventario.registrar');

header('Content-Type: application/json');

echo json_encode((new ConteoInventario())->historial());
