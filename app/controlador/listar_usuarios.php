<?php
require_once "../modelo/bootstrap.php";
require_once "../modelo/usuarios.php";

Auth::requerirRolApi('admin');

header('Content-Type: application/json');

$obj = new usuarios();
echo json_encode($obj->listar());
