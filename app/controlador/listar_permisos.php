<?php
require_once "../modelo/bootstrap.php";
require_once "../modelo/permisos.php";

Auth::requerirRolApi('admin');

header('Content-Type: application/json');

$obj = new permisos();
echo json_encode([
    'permisos' => $obj->listar(),
    'concedidos_cajero' => $obj->obtenerParaRol('cajero'),
]);
