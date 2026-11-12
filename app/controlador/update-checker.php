<?php
/**
 * Verifica si hay una versión nueva disponible consultando un servidor
 * remoto propio. Esto es independiente de electron-updater (que se encarga
 * de descargar e instalar el .exe nuevo) — este endpoint es para que la
 * propia interfaz PHP pueda mostrar un aviso ("hay una versión nueva")
 * sin depender del proceso de Electron.
 *
 * Reemplaza URL_SERVIDOR_VERSION por tu propio endpoint antes de producción.
 * Debe responder JSON como: {"version": "1.1.0", "download_url": "https://..."}
 */

header('Content-Type: application/json; charset=utf-8');

const URL_SERVIDOR_VERSION = 'https://actualizaciones.enesoft.example.com/posbar-desktop/version.json';
const TIMEOUT_SEGUNDOS = 5;

function versionActual(): string
{
    $ruta = __DIR__ . '/../version.txt';
    if (!file_exists($ruta)) {
        return '0.0.0';
    }

    return trim((string) file_get_contents($ruta));
}

/**
 * Compara versiones tipo "1.2.3" con version_compare de PHP (entiende
 * semver lo suficientemente bien para esto: 1.10.0 > 1.9.0, etc.).
 */
function hayVersionMasNueva(string $actual, string $remota): bool
{
    return version_compare($remota, $actual, '>');
}

function consultarServidorRemoto(string $url): array
{
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => TIMEOUT_SEGUNDOS,
            CURLOPT_CONNECTTIMEOUT => TIMEOUT_SEGUNDOS,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $cuerpo = curl_exec($ch);
        $error = curl_error($ch);
        $codigoHttp = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($cuerpo === false) {
            throw new RuntimeException("No se pudo contactar el servidor de actualizaciones: $error");
        }
        if ($codigoHttp < 200 || $codigoHttp >= 300) {
            throw new RuntimeException("El servidor de actualizaciones respondió HTTP $codigoHttp");
        }
    } else {
        // Respaldo si el build de PHP portable no trae cURL habilitado.
        $contexto = stream_context_create([
            'http' => ['timeout' => TIMEOUT_SEGUNDOS, 'ignore_errors' => true],
        ]);
        $cuerpo = @file_get_contents($url, false, $contexto);
        if ($cuerpo === false) {
            throw new RuntimeException('No se pudo contactar el servidor de actualizaciones.');
        }
    }

    $datos = json_decode($cuerpo, true);
    if (!is_array($datos) || !isset($datos['version'])) {
        throw new RuntimeException('Respuesta del servidor de actualizaciones inválida.');
    }

    return $datos;
}

try {
    $actual = versionActual();
    $remoto = consultarServidorRemoto(URL_SERVIDOR_VERSION);
    $versionRemota = (string) $remoto['version'];

    echo json_encode([
        'update_available' => hayVersionMasNueva($actual, $versionRemota),
        'version' => $versionRemota,
        'download_url' => $remoto['download_url'] ?? null,
        'version_actual' => $actual,
    ]);
} catch (Throwable $e) {
    http_response_code(200); // no queremos que un fallo de red rompa la UI que consulta esto
    echo json_encode([
        'update_available' => false,
        'version' => null,
        'download_url' => null,
        'version_actual' => versionActual(),
        'error' => $e->getMessage(),
    ]);
}
