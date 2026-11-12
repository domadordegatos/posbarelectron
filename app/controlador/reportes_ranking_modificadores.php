<?php
require_once "../modelo/bootstrap.php";
require_once "../modelo/Modificadores.php";

Auth::requerirPermisoApi('movimientos.ver');

header('Content-Type: application/json');

$fechaInicio = trim($_POST['fecha_inicio'] ?? '');
$fechaFin = trim($_POST['fecha_fin'] ?? '');

if ($fechaInicio === '' || $fechaFin === '') {
    echo json_encode(['error' => 'Indica el rango de fechas.']);
    exit;
}

echo json_encode((new Modificadores())->rankingVentas($fechaInicio, $fechaFin));
