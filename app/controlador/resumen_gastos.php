<?php
require_once "../modelo/bootstrap.php";
require_once "../modelo/movimientos.php";

Auth::requerirPermisoApi('movimientos.ver');

header('Content-Type: application/json');

$inicio = trim($_GET['fecha_inicio'] ?? '');
$fin = trim($_GET['fecha_fin'] ?? '');
$formatoFecha = '/^\d{4}-\d{2}-\d{2}$/';

if (!preg_match($formatoFecha, $inicio) || !preg_match($formatoFecha, $fin)) {
    http_response_code(400);
    echo json_encode(['error' => 'Fechas inválidas.']);
    exit;
}

echo json_encode((new movimientos())->resumenGastos($inicio, $fin));
