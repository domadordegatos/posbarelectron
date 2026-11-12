<?php
/**
 * Respaldo de emergencia pedido por el proveedor desde el servidor (ver
 * Licencia::ping() y programarRevisionDeSenalForzarRespaldo en main.js) —
 * salta el límite de horas entre respaldos manuales, pero sigue exigiendo
 * que la licencia tenga el respaldo habilitado.
 * Sin guard de sesión: solo alcanzable desde 127.0.0.1 (ver heartbeat_licencia.php).
 */
require_once "../modelo/Database.php";
require_once "../modelo/Licencia.php";
require_once "../modelo/Respaldo.php";

header('Content-Type: application/json; charset=utf-8');

try {
    echo json_encode(Respaldo::realizar(true));
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
