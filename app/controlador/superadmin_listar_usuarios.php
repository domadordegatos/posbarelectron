<?php
require_once "../modelo/bootstrap.php";
require_once "../modelo/usuarios.php";

Auth::requerirSuperAdminApi();

header('Content-Type: application/json');

echo json_encode((new usuarios())->listar());
