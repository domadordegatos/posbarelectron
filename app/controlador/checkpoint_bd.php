<?php
/**
 * Fuerza un WAL checkpoint completo antes de que main.js mate el proceso
 * de PHP al cerrar la app — sin esto, transacciones recién confirmadas
 * podrían vivir solo en posbar.db-wal y perderse cuando main.js cifra y
 * borra los archivos en texto plano al apagar (ver cifrarYLimpiarAlCerrar
 * en main.js). Sin guard de sesión: solo alcanzable desde 127.0.0.1, el
 * mismo proceso que Electron levanta para sí mismo (igual que
 * heartbeat_licencia.php).
 */
require_once "../modelo/Database.php";

header('Content-Type: application/json; charset=utf-8');

try {
    Database::conexion()->exec('PRAGMA wal_checkpoint(FULL)');
    echo json_encode(['ok' => true]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
