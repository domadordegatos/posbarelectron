<?php
require_once "../modelo/bootstrap.php";
require_once "../modelo/movimientos.php";

Auth::requerirPermisoApi('movimientos.ver');

header('Content-Type: application/json');

$dias = filter_input(INPUT_GET, 'dias', FILTER_VALIDATE_INT) ?: 14;
$dias = max(2, min(90, $dias));
$fechaFin = date('Y-m-d');
$fechaInicio = date('Y-m-d', strtotime('-' . ($dias - 1) . ' days'));

$obj = new movimientos();
echo json_encode([
    'ventas_por_dia' => $obj->ventasPorDia($fechaInicio, $fechaFin),
    'top_productos' => $obj->topProductosPeriodo($fechaInicio, $fechaFin, 8),
    'comparativa' => $obj->comparativaPeriodos($fechaInicio, $fechaFin),
    'rotacion_lenta' => Configuracion::moduloActivo('existencias') ? $obj->productosRotacionLenta($dias, 10) : [],
]);
