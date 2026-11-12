<?php
require_once "../modelo/bootstrap.php";
require_once "../modelo/profesionales.php";

Auth::requerirPermisoApi('movimientos.ver');
Auth::requerirModuloApi('profesionales');

header('Content-Type: application/json');

$inicio = trim($_GET['fecha_inicio'] ?? '');
$fin = trim($_GET['fecha_fin'] ?? '');
$formatoFecha = '/^\d{4}-\d{2}-\d{2}$/';

if (!preg_match($formatoFecha, $inicio) || !preg_match($formatoFecha, $fin)) {
    http_response_code(400);
    echo json_encode(['error' => 'Fechas inválidas.']);
    exit;
}

$idProfesional = filter_input(INPUT_GET, 'profesional', FILTER_VALIDATE_INT);
$idProfesional = ($idProfesional === false || $idProfesional === null || $idProfesional <= 0) ? null : $idProfesional;

echo json_encode((new profesionales())->comisionesPorFecha($inicio, $fin, $idProfesional));
