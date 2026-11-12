<?php
/**
 * Fuerza un heartbeat de licencia ahora mismo (en vez de esperar al
 * automático de main.js) — útil cuando el proveedor está diagnosticando
 * en vivo el computador de un cliente desde el panel de superadmin.
 */
require_once "../modelo/bootstrap.php";
require_once "../modelo/Licencia.php";

Auth::requerirSuperAdminApi();
Csrf::validarApi();

header('Content-Type: application/json; charset=utf-8');

echo json_encode(Licencia::heartbeat());
