<?php
/**
 * Cambia la clave de licencia de esta instalación — para cuando la
 * licencia actual se revocó sin forma de reactivarla, o el proveedor
 * quiere pasar este equipo a otra licencia. Reutiliza el mismo install_id
 * (Licencia::activar ya lo maneja), solo pide un token nuevo al servidor
 * ligado a la clave nueva. Requiere internet, igual que la activación
 * inicial.
 */
require_once "../modelo/bootstrap.php";
require_once "../modelo/Licencia.php";

Auth::requerirSuperAdminApi();
Csrf::validarApi();

header('Content-Type: application/json; charset=utf-8');

$claveLicencia = trim($_POST['clave_licencia'] ?? '');
if ($claveLicencia === '') {
    echo json_encode(['ok' => false, 'error' => 'Escribe la clave de licencia nueva.']);
    exit;
}

try {
    $datos = Licencia::activar($claveLicencia, Licencia::datos()['nombre_dispositivo'] ?? '');
    // Refresca de una vez las banderas (respaldo/actualizaciones/pagada_hasta)
    // de la nueva licencia, para no dejar datos de la licencia anterior
    // mostrándose hasta el próximo heartbeat programado.
    Licencia::heartbeat();
    echo json_encode(['ok' => true]);
} catch (Throwable $e) {
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
