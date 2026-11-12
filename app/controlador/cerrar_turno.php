<?php
require_once "../modelo/bootstrap.php";
require_once "../modelo/turnos.php";

Auth::requerirModuloApi('caja_turnos');
$usuario = Auth::requerirPermisoApi('caja.gestionar');
Csrf::validarApi();

header('Content-Type: application/json');

$idTurno = filter_input(INPUT_POST, 'id_turno', FILTER_VALIDATE_INT);
$montoContado = filter_input(INPUT_POST, 'monto_contado', FILTER_VALIDATE_FLOAT);
$observaciones = trim($_POST['observaciones'] ?? '');

if ($idTurno === false || $idTurno === null || $montoContado === false || $montoContado === null) {
    echo json_encode(['error' => 'Indica el monto contado.']);
    exit;
}

$obj = new turnos();

try {
    $resultado = $obj->cerrarTurno($idTurno, (int) $usuario['id'], $montoContado, $observaciones);
    $resultado['reporte'] = $obj->reporteCierre($idTurno);
    echo json_encode(['ok' => true] + $resultado);
} catch (TurnoException $e) {
    echo json_encode(['error' => $e->getMessage()]);
} catch (Throwable $e) {
    error_log('Error al cerrar turno: ' . $e->getMessage());
    echo json_encode(['error' => 'Error al cerrar el turno.']);
}
