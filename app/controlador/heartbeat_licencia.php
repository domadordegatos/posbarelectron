<?php
/**
 * Llamado periódicamente por main.js (nunca por la interfaz) para avisarle
 * al servidor "sigo viva" y enterarse de actualizaciones/permisos de
 * respaldo. Sin guard de sesión: solo es alcanzable desde 127.0.0.1, el
 * mismo proceso PHP que Electron levanta para sí mismo.
 */
require_once "../modelo/Licencia.php";

header('Content-Type: application/json; charset=utf-8');

echo json_encode(Licencia::heartbeat());
