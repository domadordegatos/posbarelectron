<?php
require_once "../modelo/bootstrap.php";
require_once "../modelo/movimientos.php";

Auth::requerirPermisoApi('movimientos.ver');

header('Content-Type: application/json');

$fechaInicio = trim($_GET['fecha_inicio'] ?? '');
$fechaFin = trim($_GET['fecha_fin'] ?? '');

if ($fechaInicio === '' || $fechaFin === '') {
    echo json_encode(['error' => 'Indica el rango de fechas.']);
    exit;
}

$obj = new movimientos();
echo json_encode($obj->insumosPorFecha($fechaInicio, $fechaFin));
