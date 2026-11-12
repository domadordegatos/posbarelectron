<?php
require_once "../modelo/bootstrap.php";
require_once "../modelo/clientes.php";

Auth::requerirModuloApi('clientes_credito');
Auth::requerirPermisoApi('clientes.gestionar');

header('Content-Type: application/json');

echo json_encode((new clientes())->listarConSaldo());
