<?php
/**
 * Llamado cada ~20 segundos por main.js (nunca por la interfaz): empuja los
 * eventos pendientes de esta caja al servidor y trae los de las demás cajas
 * de la misma licencia. Mejor esfuerzo: nunca lanza ni bloquea una venta.
 * Sin guard de sesión: solo alcanzable desde 127.0.0.1 (ver ping_licencia.php).
 * Contrato con el servidor: docs/sincronizacion_cajas.md
 */
require_once "../modelo/Licencia.php";
require_once "../modelo/Sincronizacion.php";

header('Content-Type: application/json; charset=utf-8');

$resultado = ['ok' => true, 'sincronizando' => false, 'enviados' => 0, 'aplicados' => 0, 'diferidos' => 0];

try {
    if (!Licencia::estaActivada() || Sincronizacion::asegurarCajaCodigo() === null) {
        echo json_encode($resultado);
        exit;
    }
    $resultado['sincronizando'] = true;
    $caja = Sincronizacion::obtenerCajaCodigo();

    $pendientes = Sincronizacion::eventosPendientes(200);
    if (!empty($pendientes)) {
        $ids = array_map(fn($f) => (int) $f['id_evento'], $pendientes);
        $eventos = array_map(fn($f) => ['id_evento_origen' => (int) $f['id_evento']] + json_decode($f['payload'], true), $pendientes);
        try {
            $resp = Licencia::llamarAutenticado('POST', 'sync_push.php', [
                'caja_codigo' => $caja,
                'eventos' => json_encode($eventos, JSON_UNESCAPED_UNICODE),
            ], 15);
            if (!empty($resp['ok'])) {
                Sincronizacion::marcarEnviados($ids);
                $resultado['enviados'] = count($ids);
            } else {
                Sincronizacion::marcarFalloEnvio($ids, (string) ($resp['error'] ?? 'respuesta sin ok'));
                $resultado['error_push'] = (string) ($resp['error'] ?? 'respuesta sin ok');
            }
        } catch (Throwable $e) {
            Sincronizacion::marcarFalloEnvio($ids, $e->getMessage());
            $resultado['error_push'] = $e->getMessage();
        }
    }

    try {
        $cursor = (int) (Licencia::datos()['sync_cursor'] ?? 0);
        $resp = Licencia::llamarAutenticado('POST', 'sync_pull.php', ['caja_codigo' => $caja, 'desde' => $cursor], 15);
        if (!empty($resp['ok']) && is_array($resp['eventos'] ?? null)) {
            $aplicacion = Sincronizacion::aplicarEventosEntrantes($resp['eventos']);
            $resultado['aplicados'] = $aplicacion['aplicados'];
            $resultado['diferidos'] = $aplicacion['diferidos'];
            if (isset($resp['cursor_nuevo']) && (int) $resp['cursor_nuevo'] >= $cursor) {
                Licencia::fusionarDatos(['sync_cursor' => (int) $resp['cursor_nuevo']]);
            }
        } elseif (empty($resp['ok'])) {
            $resultado['error_pull'] = (string) ($resp['error'] ?? 'respuesta sin ok');
        }
    } catch (Throwable $e) {
        $resultado['error_pull'] = $e->getMessage();
    }
} catch (Throwable $e) {
    $resultado['ok'] = false;
    $resultado['error'] = $e->getMessage();
}

echo json_encode($resultado);
