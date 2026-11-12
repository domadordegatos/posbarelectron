<?php
require_once "../modelo/bootstrap.php";
require_once "../modelo/turnos.php";

Auth::requerirModuloApi('caja_turnos');

header('Content-Type: application/json');

$turno = (new turnos())->turnoAbierto();
$resumen = $turno ? (new turnos())->resumenTurno((int) $turno['id_turno']) : null;

$ajustes = $turno ? (new turnos())->ajustesDeTurno((int) $turno['id_turno']) : [];

echo json_encode(['turno' => $turno, 'resumen' => $resumen, 'ajustes' => $ajustes]);
