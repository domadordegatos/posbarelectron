<?php
/**
 * Reporte Z (cierre) de un turno YA CERRADO, para reimprimirlo después — por ejemplo tras corregir
 * su monto de apertura o su monto contado, cuando el ticket que se entregó ese día quedó con el
 * número viejo. Es de solo lectura: no cambia nada, solo trae los mismos datos que ya se ven en el
 * historial de Caja, listos para el mismo ticket que arma mostrarReporteZ() en la vista.
 */
require_once "../modelo/bootstrap.php";
require_once "../modelo/turnos.php";

Auth::requerirModuloApi('caja_turnos');
Auth::requerirPermisoApi('caja.gestionar');

header('Content-Type: application/json');

$idTurno = filter_input(INPUT_GET, 'id_turno', FILTER_VALIDATE_INT);
if ($idTurno === false || $idTurno === null || $idTurno <= 0) {
    echo json_encode(['error' => 'Falta indicar qué turno.']);
    exit;
}

$obj = new turnos();

try {
    $turno = $obj->obtener($idTurno);
    if (!$turno) {
        echo json_encode(['error' => 'Ese turno no existe.']);
        exit;
    }
    if ($turno['estado'] !== 'cerrado') {
        echo json_encode(['error' => 'Ese turno todavía está abierto: no tiene reporte de cierre.']);
        exit;
    }

    echo json_encode(['ok' => true, 'reporte' => $obj->reporteCierre($idTurno)]);
} catch (TurnoException $e) {
    echo json_encode(['error' => $e->getMessage()]);
} catch (Throwable $e) {
    error_log('Error al obtener el reporte de cierre: ' . $e->getMessage());
    echo json_encode(['error' => 'No se pudo obtener el reporte de cierre.']);
}
