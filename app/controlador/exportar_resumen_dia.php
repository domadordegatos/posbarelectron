<?php
/**
 * Exporta el resumen de ventas de esta caja en un rango de fechas, como un
 * archivo .json descargable — pensado para negocios con más de una caja
 * (cada una es una instalación/licencia independiente, sin sincronizar en
 * vivo entre ellas): al final del día, cada caja exporta el suyo y se
 * combinan en "Consolidar cajas" (ver vista/reportes/index.php).
 */
require_once "../modelo/bootstrap.php";
require_once "../modelo/movimientos.php";
require_once "../modelo/Licencia.php";

Auth::requerirPermiso('movimientos.ver');

$fechaInicio = trim($_GET['fecha_inicio'] ?? '');
$fechaFin = trim($_GET['fecha_fin'] ?? '');
$cajaNombre = trim($_GET['caja_nombre'] ?? '');

if ($fechaInicio === '' || $fechaFin === '') {
    http_response_code(400);
    exit('Indica el rango de fechas.');
}

$config = Configuracion::obtener();
$licencia = Licencia::datos();
$m = new movimientos();

if ($cajaNombre === '') {
    $cajaNombre = ($config['nombre_negocio'] ?? 'Negocio') . ' — ' . ($licencia['install_id'] ?? 'equipo sin identificar');
}

$payload = [
    'caja_nombre' => $cajaNombre,
    'negocio' => $config['nombre_negocio'] ?? null,
    'install_id' => $licencia['install_id'] ?? null,
    'fecha_inicio' => $fechaInicio,
    'fecha_fin' => $fechaFin,
    'generado_en' => date('Y-m-d H:i:s'),
    'resumen' => $m->resumenPeriodo($fechaInicio, $fechaFin),
    'facturas' => $m->ventasPorFecha($fechaInicio, $fechaFin),
];

$nombreArchivo = preg_replace('/[^a-zA-Z0-9_-]+/', '_', $cajaNombre) . '_' . $fechaInicio . '_a_' . $fechaFin . '.json';

header('Content-Type: application/json; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $nombreArchivo . '"');
echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
