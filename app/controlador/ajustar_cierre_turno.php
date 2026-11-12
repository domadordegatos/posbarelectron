<?php
/**
 * Corrige el monto contado al cerrar un turno YA CERRADO (cualquiera, de cualquier fecha) — por
 * ejemplo cuando se digitó 450 en vez de 450.000. Acción delicada (cambia la diferencia del
 * cuadre): un administrador la hace directamente; un cajero solo puede si un administrador la
 * autoriza en ese momento con su usuario y clave. Siempre con motivo, y queda registrada (tabla
 * turno_ajustes + auditoría). A diferencia de la apertura, el efectivo esperado NO cambia (no
 * depende de lo contado).
 */
require_once "../modelo/bootstrap.php";
require_once "../modelo/turnos.php";

Auth::requerirModuloApi('caja_turnos');
$usuario = Auth::requerirPermisoApi('caja.gestionar');
Csrf::validarApi();

header('Content-Type: application/json');

$idTurno = filter_input(INPUT_POST, 'id_turno', FILTER_VALIDATE_INT);
$montoNuevo = filter_input(INPUT_POST, 'monto_nuevo', FILTER_VALIDATE_FLOAT);
$motivo = trim($_POST['motivo'] ?? '');

if ($idTurno === false || $idTurno === null || $idTurno <= 0) {
    echo json_encode(['error' => 'Falta indicar qué turno corregir.']);
    exit;
}
if ($montoNuevo === false || $montoNuevo === null) {
    echo json_encode(['error' => 'Escribe el nuevo monto contado (un número).']);
    exit;
}

// Quién autoriza: el propio administrador, o el administrador cuyas credenciales se digitaron.
if ($usuario['rol'] === 'admin') {
    $autorizadoPor = (int) $usuario['id'];
} else {
    $verificacion = Auth::verificarAdministrador((string) ($_POST['admin_usuario'] ?? ''), (string) ($_POST['admin_password'] ?? ''));
    if ($verificacion['admin'] === null) {
        echo json_encode(['error' => 'Para corregir el monto contado se necesita la autorización de un administrador. ' . $verificacion['error'], 'requiere_admin' => true]);
        exit;
    }
    $autorizadoPor = $verificacion['admin']['id'];
}

$obj = new turnos();
try {
    $resultado = $obj->ajustarCierre($idTurno, $montoNuevo, (int) $usuario['id'], $autorizadoPor, $motivo);
    echo json_encode(['ok' => true] + $resultado);
} catch (TurnoException $e) {
    echo json_encode(['error' => $e->getMessage()]);
} catch (Throwable $e) {
    error_log('Error al ajustar el monto contado del turno: ' . $e->getMessage());
    echo json_encode(['error' => 'No se pudo ajustar el monto contado.']);
}
