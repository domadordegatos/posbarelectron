<?php
require_once "../modelo/bootstrap.php";
require_once "../modelo/usuarios.php";

header('Content-Type: application/json');

$obj = new usuarios();
echo json_encode(['hay_usuarios' => count($obj->listar()) > 0]);
