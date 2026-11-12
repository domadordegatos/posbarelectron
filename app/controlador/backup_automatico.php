<?php
/**
 * Disparado periódicamente por main.js (cada 24h, ver
 * INTERVALO_RESPALDO_AUTOMATICO_MS) — nunca por la interfaz. Sin guard de
 * sesión: solo alcanzable desde 127.0.0.1, el mismo proceso PHP que
 * Electron levanta para sí mismo (igual que heartbeat_licencia.php).
 *
 * Distingue "esta licencia no tiene el servicio" (no_aplica=true, no vale
 * la pena reintentar pronto) de "se intentó y falló" (ok=false sin
 * no_aplica — típicamente sin internet en ese momento, main.js reintenta
 * más pronto en ese caso, ver programarRespaldoAutomatico en main.js).
 */
require_once "../modelo/Respaldo.php";

header('Content-Type: application/json; charset=utf-8');

$datosLicencia = Licencia::datos();
if (empty($datosLicencia['respaldo_habilitado'])) {
    echo json_encode(['ok' => false, 'no_aplica' => true, 'error' => 'Respaldo no incluido en esta licencia.']);
    exit;
}

try {
    echo json_encode(Respaldo::realizar());
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
