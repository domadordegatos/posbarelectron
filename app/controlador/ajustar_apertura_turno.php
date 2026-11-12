<?php
/**
 * Corrige el monto de apertura del turno de caja ABIERTO. Acción delicada (cambia la base del
 * cuadre): un administrador la hace directamente; un cajero solo puede si un administrador la
 * autoriza en ese momento con su usuario y clave. Siempre con motivo, y queda registrada (tabla
 * turno_ajustes + auditoría). Sin "id_turno" corrige el turno abierto ahora; con "id_turno" corrige
 * ese turno, incluso uno ya cerrado (días anteriores: se recalculan su efectivo esperado y su diferencia).
 */
require_once "../modelo/bootstrap.php";
require_once "../modelo/turnos.php";

Auth::requerirModuloApi('caja_turnos');
$usuario = Auth::requerirPermisoApi('caja.gestionar');
Csrf::validarApi();

header('Content-Type: application/json');

$montoNuevo = filter_input(INPUT_POST, 'monto_nuevo', FILTER_VALIDATE_FLOAT);
$motivo = trim($_POST['motivo'] ?? '');

if ($montoNuevo === false || $montoNuevo === null) {
    echo json_encode(['error' => 'Escribe el nuevo monto de apertura (un número).']);
    exit;
}

// Quién autoriza: el propio administrador, o el administrador cuyas credenciales se digitaron.
if ($usuario['rol'] === 'admin') {
    $autorizadoPor = (int) $usuario['id'];
} else {
    $verificacion = Auth::verificarAdministrador((string) ($_POST['admin_usuario'] ?? ''), (string) ($_POST['admin_password'] ?? ''));
    if ($verificacion['admin'] === null) {
        echo json_encode(['error' => 'Para corregir el monto de apertura se necesita la autorización de un administrador. ' . $verificacion['error'], 'requiere_admin' => true]);
        exit;
    }
    $autorizadoPor = $verificacion['admin']['id'];
}

$obj = new turnos();
$idTurnoPedido = filter_input(INPUT_POST, 'id_turno', FILTER_VALIDATE_INT);
if ($idTurnoPedido !== false && $idTurnoPedido !== null && $idTurnoPedido > 0) {
    $idTurno = $idTurnoPedido;
} else {
    $turno = $obj->turnoAbierto();
    if (!$turno) {
        echo json_encode(['error' => 'No hay un turno abierto.']);
        exit;
    }
    $idTurno = (int) $turno['id_turno'];
}

try {
    $resultado = $obj->ajustarApertura($idTurno, $montoNuevo, (int) $usuario['id'], $autorizadoPor, $motivo);
    echo json_encode(['ok' => true] + $resultado);
} catch (TurnoException $e) {
    echo json_encode(['error' => $e->getMessage()]);
} catch (Throwable $e) {
    error_log('Error al ajustar la apertura del turno: ' . $e->getMessage());
    echo json_encode(['error' => 'No se pudo ajustar el monto de apertura.']);
}
