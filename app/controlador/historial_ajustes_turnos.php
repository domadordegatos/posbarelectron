<?php
require_once "../modelo/bootstrap.php";
require_once "../modelo/turnos.php";

// Historial de TODOS los ajustes del monto de apertura de caja (de cualquier turno).
Auth::requerirModuloApi('caja_turnos');
Auth::requerirPermisoApi('caja.gestionar');

header('Content-Type: application/json');

$formatoFecha = '/^\d{4}-\d{2}-\d{2}$/';
$inicio = trim($_GET['fecha_inicio'] ?? '');
$fin = trim($_GET['fecha_fin'] ?? '');
if (($inicio !== '' && !preg_match($formatoFecha, $inicio)) || ($fin !== '' && !preg_match($formatoFecha, $fin))) {
    http_response_code(400);
    echo json_encode(['error' => 'Fechas inválidas.']);
    exit;
}

echo json_encode((new turnos())->historialAjustes($inicio ?: null, $fin ?: null));
