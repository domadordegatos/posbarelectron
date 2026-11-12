<?php
/**
 * Llamado cada ~60 segundos por main.js (nunca por la interfaz) — presencia
 * liviana para el panel del proveedor ("en línea") y para que "forzar
 * actualización" llegue en minutos en vez de esperar el heartbeat de 6h.
 * Sin guard de sesión: solo alcanzable desde 127.0.0.1 (ver heartbeat_licencia.php).
 */
require_once "../modelo/Licencia.php";

header('Content-Type: application/json; charset=utf-8');

echo json_encode(Licencia::ping());
