<?php
/**
 * Consultado por main.js mientras espera para aplicar una actualización
 * forzada: mientras haya una mesa/comanda abierta, instalar ahora podría
 * llevarse por delante un pedido que el negocio todavía no ha cobrado.
 * (El carrito de una venta normal sin mesa vive solo en el navegador, ese
 * lado lo revisa main.js directamente contra la ventana, no aquí.)
 * Sin guard de sesión: solo alcanzable desde 127.0.0.1 (ver heartbeat_licencia.php).
 */
require_once "../modelo/bootstrap.php";
require_once "../modelo/comandas.php";

header('Content-Type: application/json; charset=utf-8');

try {
    $hayMesasAbiertas = Configuracion::moduloActivo('mesas_comandas')
        ? (new comandas())->hayAbiertas()
        : false;

    echo json_encode(['ok' => true, 'hay_mesas_abiertas' => $hayMesasAbiertas]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
