<?php
require_once "../modelo/bootstrap.php";
require_once "../modelo/turnos.php";

Auth::requerirModuloApi('caja_turnos');
$usuario = Auth::requerirPermisoApi('caja.gestionar');
Csrf::validarApi();

header('Content-Type: application/json');

$montoApertura = filter_input(INPUT_POST, 'monto_apertura', FILTER_VALIDATE_FLOAT);
$montoApertura = ($montoApertura === false || $montoApertura === null) ? 0.0 : $montoApertura;

$obj = new turnos();

try {
    $idTurno = $obj->abrirTurno((int) $usuario['id'], $montoApertura);
    echo json_encode(['ok' => true, 'id_turno' => $idTurno]);
} catch (TurnoException $e) {
    echo json_encode(['error' => $e->getMessage()]);
} catch (Throwable $e) {
    error_log('Error al abrir turno: ' . $e->getMessage());
    echo json_encode(['error' => 'Error al abrir el turno.']);
}
