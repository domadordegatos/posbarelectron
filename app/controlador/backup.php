<?php
/**
 * Botón "Hacer respaldo ahora" en Configuración — dispara un respaldo
 * remoto de inmediato, con sesión de admin. La lógica real vive en
 * modelo/Respaldo.php (compartida con el disparo automático de main.js).
 */
require_once "../modelo/bootstrap.php";
require_once "../modelo/Respaldo.php";

Auth::requerirRolApi('admin');
Csrf::validarApi();

header('Content-Type: application/json; charset=utf-8');

try {
    echo json_encode(Respaldo::realizar());
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
