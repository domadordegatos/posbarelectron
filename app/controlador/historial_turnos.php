<?php
require_once "../modelo/bootstrap.php";
require_once "../modelo/turnos.php";

Auth::requerirModuloApi('caja_turnos');
Auth::requerirPermisoApi('caja.gestionar');

header('Content-Type: application/json');

echo json_encode((new turnos())->historial(30));
