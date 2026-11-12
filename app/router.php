<?php
/**
 * Router para el servidor embebido de PHP (php -S ... router.php).
 *
 * El servidor integrado de PHP solo sirve archivos que estén físicamente
 * dentro de su docroot (/app) — pero el logo del negocio se guarda en la
 * carpeta de datos del usuario (fuera de /app, para sobrevivir cada
 * actualización de la app). Este router intercepta esas rutas y las sirve
 * a mano; todo lo demás lo deja pasar al comportamiento normal.
 */

require_once __DIR__ . '/modelo/Database.php';

$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));

/**
 * Sirve un archivo a mano con Last-Modified/ETag y responde 304 si el navegador ya lo tiene — el
 * servidor embebido de PHP (php -S) NO manda ningún encabezado de caché por su cuenta, así que sin
 * esto cada clic a otra pantalla vuelve a descargar entero el CSS, el JS, la fuente de los íconos y
 * el logo del negocio: eso es lo que hacía que los íconos desaparecieran un instante en cada clic y
 * la pantalla se sintiera "recargar completa" en vez de solo cambiar.
 * $maxAgeSegundos: cuánto puede el navegador usar la copia SIN preguntar de nuevo — 0 = siempre
 * pregunta (con 304 si no cambió), útil para archivos que el usuario puede reemplazar (el logo).
 */
function servirConCache(string $rutaReal, string $tipoContenido, int $maxAgeSegundos): void
{
    $mtime = filemtime($rutaReal);
    $etag = '"' . md5($mtime . '|' . filesize($rutaReal)) . '"';
    $desde = $_SERVER['HTTP_IF_MODIFIED_SINCE'] ?? null;
    $etagCliente = $_SERVER['HTTP_IF_NONE_MATCH'] ?? null;

    header('Content-Type: ' . $tipoContenido);
    header('Cache-Control: ' . ($maxAgeSegundos > 0 ? "public, max-age=$maxAgeSegundos, immutable" : 'no-cache'));
    header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $mtime) . ' GMT');
    header('ETag: ' . $etag);

    if ($etagCliente === $etag || ($desde !== null && strtotime($desde) >= $mtime)) {
        http_response_code(304);
        return;
    }
    readfile($rutaReal);
}

if (strpos($uri, '/uploads/') === 0) {
    $ruta = Database::directorioDatos() . $uri;
    $rutaReal = realpath($ruta);
    $carpetaUploads = realpath(Database::directorioDatos() . '/uploads');

    // Evita salir de /uploads con un "../" en la URL.
    if ($rutaReal === false || $carpetaUploads === false || strpos($rutaReal, $carpetaUploads) !== 0) {
        http_response_code(404);
        return true;
    }

    $tipos = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'];
    $extension = strtolower(pathinfo($rutaReal, PATHINFO_EXTENSION));
    // El logo lo puede reemplazar el administrador en cualquier momento: se revalida en cada carga
    // (sin max-age), pero con ETag/Last-Modified evita volver a bajar el archivo si no cambió.
    servirConCache($rutaReal, $tipos[$extension] ?? 'application/octet-stream', 0);
    return true;
}

// CSS, JS y fuentes/imágenes de la app: librerías de terceros con caché larga, lo propio con revalidación.
if (strpos($uri, '/assets/') === 0) {
    $tiposEstaticos = [
        'css' => 'text/css', 'js' => 'application/javascript', 'map' => 'application/json',
        'woff' => 'font/woff', 'woff2' => 'font/woff2', 'ttf' => 'font/ttf', 'eot' => 'application/vnd.ms-fontobject',
        'svg' => 'image/svg+xml', 'png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg',
        'gif' => 'image/gif', 'ico' => 'image/x-icon', 'json' => 'application/json',
    ];
    $extension = strtolower(pathinfo($uri, PATHINFO_EXTENSION));
    if (isset($tiposEstaticos[$extension])) {
        $rutaReal = realpath(__DIR__ . $uri);
        $carpetaAssets = realpath(__DIR__ . '/assets');
        if ($rutaReal !== false && $carpetaAssets !== false && strpos($rutaReal, $carpetaAssets) === 0) {
            // Solo las librerías de terceros (vendor/) son inmutables. app.js, app.css e imágenes propias
            // cambian con cada actualización y la caché de Electron persiste entre versiones (puerto fijo
            // 8080): se revalidan con ETag (304 si no cambió) para no quedarse con una versión vieja.
            $carpetaVendor = realpath(__DIR__ . '/assets/vendor');
            $inmutable = $carpetaVendor !== false && strpos($rutaReal, $carpetaVendor) === 0;
            servirConCache($rutaReal, $tiposEstaticos[$extension], $inmutable ? 31536000 : 0);
            return true;
        }
    }
}

return false; // deja que el servidor integrado sirva /app normalmente
