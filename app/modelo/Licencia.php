<?php
require_once __DIR__ . '/Database.php';

/**
 * Fallo de CONEXIÓN de verdad (sin internet, DNS, timeout, servidor
 * caído) — nunca un rechazo real del servidor. Los addons que sí
 * necesitan internet en el momento (ej. generar un QR de fidelización)
 * la atrapan aparte para mostrar "sin conexión" en vez del texto crudo
 * del error de cURL.
 */
class LicenciaSinConexionException extends RuntimeException
{
}

/**
 * Activación/licenciamiento del dispositivo (ver prompt_servidor_licencias.md
 * para el contrato exacto de los endpoints).
 *
 * Todo el estado vive en un archivo licencia.json dentro de la carpeta de
 * datos del usuario (la misma donde vive posbar.db) — así sobrevive a cada
 * actualización de la app, igual que la base de datos. Internet solo hace
 * falta para activar() la primera vez; heartbeat() es siempre "best effort":
 * si falla (sin internet, servidor caído, lo que sea) el POS debe seguir
 * funcionando exactamente igual, nunca bloquea nada.
 */
class Licencia
{
    public const URL_BASE = 'https://pos.nncoms.com/api';

    private const TIMEOUT_ACTIVACION_SEGUNDOS = 15;
    private const TIMEOUT_HEARTBEAT_SEGUNDOS = 8;
    private const TIMEOUT_PING_SEGUNDOS = 5;

    /** Días después de pagada_hasta antes de bloquear el acceso (licencias tipo suscripción). */
    private const DIAS_GRACIA = 5;

    private static ?array $datos = null;

    /**
     * Solo para pruebas automáticas: si una prueba (vía reflexión) le asigna un
     * callable(metodo, url, campos), las llamadas al servidor lo usan en vez de
     * cURL. En producción SIEMPRE es null — no hay variable de entorno ni
     * archivo que lo active, así que no es una puerta para desviar la licencia.
     */
    private static $transportePrueba = null;

    /** Momento (timestamp) del último heartbeat adelantado por el ping al ver una revisión nueva de configuración. */
    private static int $ultimoHeartbeatPorConfig = 0;

    private static function rutaArchivo(): string
    {
        return Database::directorioDatos() . '/licencia.json';
    }

    public static function datos(): array
    {
        if (self::$datos === null) {
            $ruta = self::rutaArchivo();
            $contenido = is_file($ruta) ? file_get_contents($ruta) : false;
            $decodificado = $contenido !== false ? json_decode($contenido, true) : null;
            self::$datos = is_array($decodificado) ? $decodificado : [];
        }

        return self::$datos;
    }

    private static function guardar(array $datos): void
    {
        self::$datos = $datos;
        file_put_contents(self::rutaArchivo(), json_encode($datos, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    /** Suma/cambia solo esas claves en licencia.json (lo usa ConfiguracionRemota; el resto del archivo no se toca). */
    public static function fusionarDatos(array $cambios): void
    {
        self::guardar(array_merge(self::datos(), $cambios));
    }

    /** true si el proveedor administra la configuración de este equipo a distancia (ver ConfiguracionRemota). */
    public static function gestionRemota(): bool
    {
        return !empty(self::datos()['gestion_remota']);
    }

    public static function estaActivada(): bool
    {
        $datos = self::datos();
        return !empty($datos['token']) && !empty($datos['install_id']);
    }

    public static function registrarRespaldoExitoso(): void
    {
        $datos = self::datos();
        $datos['ultimo_respaldo_en'] = date('c');
        self::guardar($datos);
    }

    /**
     * "Hoy", pero nunca puede retroceder respecto a la última vez que la
     * app lo calculó — evita que atrasar el reloj de la PC sirva para
     * esquivar el bloqueo por falta de pago (ver debeBloquearPorPago). Se
     * ancla a la fecha real del servidor en cada heartbeat exitoso (ver
     * heartbeat()), que es la fuente más confiable; entre heartbeats, solo
     * avanza con el reloj local, nunca retrocede.
     */
    private static function fechaEfectivaHoy(): DateTimeImmutable
    {
        $datos = self::datos();
        $actual = new DateTimeImmutable('today');
        $guardada = !empty($datos['ultima_fecha_vista'])
            ? new DateTimeImmutable($datos['ultima_fecha_vista'])
            : $actual;
        $efectiva = $actual > $guardada ? $actual : $guardada;

        if ($efectiva->format('Y-m-d') !== ($datos['ultima_fecha_vista'] ?? null)) {
            $datos['ultima_fecha_vista'] = $efectiva->format('Y-m-d');
            self::guardar($datos);
        }

        return $efectiva;
    }

    /**
     * true solo para licencias tipo suscripción (pagada_hasta no nulo) que
     * llevan más de DIAS_GRACIA días vencidas. Las perpetuas (pagada_hasta
     * nulo) nunca bloquean nada.
     */
    public static function debeBloquearPorPago(): bool
    {
        $datos = self::datos();
        if (empty($datos['pagada_hasta'])) {
            return false;
        }

        $vencePlusGracia = (new DateTimeImmutable($datos['pagada_hasta']))->modify('+' . self::DIAS_GRACIA . ' days');

        return self::fechaEfectivaHoy() > $vencePlusGracia;
    }

    /**
     * Días de gracia que quedan antes del bloqueo — null si la licencia es
     * perpetua o si todavía no llegó la fecha de vencimiento (nada que
     * avisar). Para el aviso pequeño en la esquina superior derecha.
     */
    public static function diasGraciaRestantes(): ?int
    {
        $datos = self::datos();
        if (empty($datos['pagada_hasta'])) {
            return null;
        }

        $vence = new DateTimeImmutable($datos['pagada_hasta']);
        $hoy = self::fechaEfectivaHoy();
        if ($hoy <= $vence) {
            return null;
        }

        $vencePlusGracia = $vence->modify('+' . self::DIAS_GRACIA . ' days');
        $restantes = (int) floor(($vencePlusGracia->getTimestamp() - $hoy->getTimestamp()) / 86400);

        return max(0, $restantes);
    }

    /** Se genera una sola vez y se conserva siempre, incluso si la activación falla. */
    public static function installId(): string
    {
        $datos = self::datos();
        if (!empty($datos['install_id'])) {
            return $datos['install_id'];
        }

        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        $hex = bin2hex($bytes);
        $installId = sprintf(
            '%s-%s-%s-%s-%s',
            substr($hex, 0, 8),
            substr($hex, 8, 4),
            substr($hex, 12, 4),
            substr($hex, 16, 4),
            substr($hex, 20, 12)
        );

        $datos['install_id'] = $installId;
        self::guardar($datos);

        return $installId;
    }

    private static function versionInstalada(): string
    {
        $ruta = __DIR__ . '/../version.txt';
        return is_file($ruta) ? trim((string) file_get_contents($ruta)) : '0.0.0';
    }

    /**
     * Único punto que arma cURL para todo el archivo — activar/heartbeat/ping
     * (vía llamar(), sin cambios de comportamiento) y cualquier addon nuevo
     * (vía llamarAutenticado(), ver más abajo) comparten aquí el manejo de
     * certificados, así una sola vez es correcto en vez de repetido en cada
     * método nuevo que se agregue.
     */
    private static function ejecutarPeticionCurl(string $metodo, string $url, array $campos, int $timeoutSegundos): array
    {
        if (self::$transportePrueba !== null) {
            return (self::$transportePrueba)($metodo, $url, $campos);
        }
        if (!function_exists('curl_init')) {
            throw new RuntimeException('Esta build de PHP no tiene cURL habilitado.');
        }

        $ch = curl_init();
        if ($metodo === 'GET') {
            $separador = strpos($url, '?') === false ? '?' : '&';
            curl_setopt($ch, CURLOPT_URL, $campos ? $url . $separador . http_build_query($campos) : $url);
        } else {
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $campos);
        }
        $opciones = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $timeoutSegundos,
            CURLOPT_CONNECTTIMEOUT => $timeoutSegundos,
            CURLOPT_SSL_VERIFYPEER => true,
        ];
        // El PHP portable de Windows no trae paquete de certificados raíz
        // del sistema operativo — sin esto, cURL rechaza CUALQUIER HTTPS
        // con "unable to get local issuer certificate". cacert.pem viaja
        // junto al binario (ver scripts/download-php.js); se resuelve vía
        // PHP_BINARY (ruta absoluta real) en vez de una relativa, porque
        // curl.cainfo no se resuelve relativo al php.ini como sí hace
        // extension_dir — se resuelve relativo al cwd del proceso, que
        // Electron fija en /app, no en /php. En un PHP normal (hosting,
        // Linux) ese archivo no existe junto al binario — ahí se deja que
        // cURL use el paquete de certificados del propio sistema.
        $cacert = dirname(PHP_BINARY) . DIRECTORY_SEPARATOR . 'cacert.pem';
        if (is_file($cacert)) {
            $opciones[CURLOPT_CAINFO] = $cacert;
        }
        curl_setopt_array($ch, $opciones);
        $cuerpo = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);

        // Solo esto es un fallo de CONEXIÓN de verdad (sin internet, DNS,
        // timeout, servidor caído) — el llamador nunca debe bloquear nada
        // por esto, el POS sigue funcionando offline como siempre.
        if ($cuerpo === false) {
            throw new LicenciaSinConexionException("No se pudo contactar el servidor: $error");
        }

        $json = json_decode($cuerpo, true);
        if (!is_array($json)) {
            throw new RuntimeException('Respuesta inválida del servidor.');
        }

        // A partir de aquí SÍ hubo respuesta real del servidor — puede ser
        // ok:true o ok:false (ej. "token inválido o licencia suspendida"),
        // pero ya no es un problema de conectividad. Se devuelve tal cual
        // y cada llamador decide qué hacer con un rechazo explícito
        // (activar() lo trata como error; heartbeat() lo usa para saber
        // que el proveedor revocó la licencia — ver debeBloquearPorRevocacion).
        return $json;
    }

    private static function llamar(string $endpoint, array $campos, int $timeoutSegundos): array
    {
        return self::ejecutarPeticionCurl('POST', rtrim(self::URL_BASE, '/') . '/' . ltrim($endpoint, '/'), $campos, $timeoutSegundos);
    }

    /**
     * Llamada autenticada genérica a un endpoint de un addon (ej. el
     * programa de fidelización — ver modelo/Fidelizacion.php) — reusa el
     * mismo token de licencia que ya usan activar()/heartbeat()/ping(), sin
     * pedir ni guardar ninguna credencial nueva. Lanza si la licencia no
     * está activada o si falla la conexión/respuesta — el llamador decide
     * cómo mostrarlo (los controladores de cada addon lo atrapan y
     * responden {"error": "..."} sin tumbar el resto del POS).
     */
    public static function llamarAutenticado(string $metodo, string $endpoint, array $campos = [], int $timeoutSegundos = 15): array
    {
        $token = self::datos()['token'] ?? null;
        if (!$token) {
            throw new RuntimeException('La licencia no está activada.');
        }

        $url = rtrim(self::URL_BASE, '/') . '/' . ltrim($endpoint, '/');

        return self::ejecutarPeticionCurl(strtoupper($metodo), $url, ['token' => $token] + $campos, $timeoutSegundos);
    }

    /**
     * Subida de archivo autenticada (multipart) — mismo token, mismo
     * criterio que llamarAutenticado(). $tipoMime: pásale
     * $_FILES['campo']['type'] (el navegador ya lo manda en cada subida) —
     * el PHP portátil que viaja empaquetado con la app NO trae la
     * extensión fileinfo habilitada, así que mime_content_type() no existe
     * ahí; solo se usa como último recurso si no llega nada.
     */
    public static function subirArchivoAutenticado(
        string $endpoint,
        array $campos,
        string $nombreCampoArchivo,
        string $rutaArchivoTemporal,
        string $nombreArchivoOriginal,
        ?string $tipoMime = null,
        int $timeoutSegundos = 30
    ): array {
        if (!function_exists('curl_init')) {
            throw new RuntimeException('Esta build de PHP no tiene cURL habilitado.');
        }
        $token = self::datos()['token'] ?? null;
        if (!$token) {
            throw new RuntimeException('La licencia no está activada.');
        }

        if (!$tipoMime && function_exists('mime_content_type')) {
            $tipoMime = mime_content_type($rutaArchivoTemporal) ?: null;
        }

        $ch = curl_init(rtrim(self::URL_BASE, '/') . '/' . ltrim($endpoint, '/'));
        $campos['token'] = $token;
        $campos[$nombreCampoArchivo] = new CURLFile(
            $rutaArchivoTemporal,
            $tipoMime ?: 'application/octet-stream',
            $nombreArchivoOriginal
        );

        $opciones = [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $campos,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $timeoutSegundos,
            CURLOPT_CONNECTTIMEOUT => $timeoutSegundos,
            CURLOPT_SSL_VERIFYPEER => true,
        ];
        $cacert = dirname(PHP_BINARY) . DIRECTORY_SEPARATOR . 'cacert.pem';
        if (is_file($cacert)) {
            $opciones[CURLOPT_CAINFO] = $cacert;
        }
        curl_setopt_array($ch, $opciones);
        $cuerpo = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);

        if ($cuerpo === false) {
            throw new LicenciaSinConexionException("No se pudo contactar el servidor: $error");
        }
        $json = json_decode($cuerpo, true);
        if (!is_array($json)) {
            throw new RuntimeException('Respuesta inválida del servidor.');
        }

        return $json;
    }

    /**
     * Única llamada que SÍ requiere internet. Se hace una vez, de forma
     * explícita (el usuario la dispara desde la pantalla de activación).
     */
    public static function activar(string $claveLicencia, string $nombreDispositivo): array
    {
        $nombreDispositivo = $nombreDispositivo !== '' ? $nombreDispositivo : gethostname();

        $respuesta = self::llamar('activar.php', [
            'clave_licencia' => trim($claveLicencia),
            'install_id' => self::installId(),
            'nombre_dispositivo' => $nombreDispositivo,
        ], self::TIMEOUT_ACTIVACION_SEGUNDOS);

        if (empty($respuesta['ok'])) {
            throw new RuntimeException($respuesta['error'] ?? 'No se pudo activar la licencia.');
        }

        $datos = self::datos();
        // Limpia lo que haya quedado de una licencia anterior (ej. al
        // cambiar de clave desde el panel de superadmin) — nunca debe
        // mostrarse pagada_hasta/revocada/etc. de la licencia vieja hasta
        // que un heartbeat confirme los datos reales de la nueva.
        $datos['token'] = $respuesta['token'];
        $datos['nombre_dispositivo'] = $nombreDispositivo;
        $datos['nombre_negocio'] = $respuesta['nombre_negocio'] ?? null;
        $datos['activada_en'] = date('c');
        $datos['revocada'] = false;
        $datos['motivo_revocacion'] = null;
        $datos['motivo_liberacion'] = null;
        $datos['pagada_hasta'] = null;
        $datos['respaldo_habilitado'] = false;
        $datos['actualizaciones_habilitadas'] = false;
        $datos['fidelizacion_habilitada'] = false;
        $datos['actualizacion_disponible'] = false;
        $datos['version_disponible'] = null;
        $datos['notas_version'] = null;
        $datos['url_descarga'] = null;
        $datos['ultima_conexion_exitosa'] = null;
        $datos['gestion_remota'] = false;
        $datos['config_revision_aplicada'] = 0;
        self::guardar($datos);

        return $datos;
    }

    /** true si el proveedor revocó/suspendió esta licencia (ver heartbeat()) — bloquea sin importar el tipo de licencia. */
    public static function debeBloquearPorRevocacion(): bool
    {
        return !empty(self::datos()['revocada']);
    }

    public static function motivoRevocacion(): string
    {
        return self::datos()['motivo_revocacion'] ?? 'Esta licencia fue revocada o suspendida por el proveedor.';
    }

    /** Mensaje a mostrar en la pantalla de activación tras liberarLocalmente() — null si no aplica (activación normal). */
    public static function motivoLiberacion(): ?string
    {
        return self::datos()['motivo_liberacion'] ?? null;
    }

    /**
     * El servidor respondió explícitamente que este token ya no sirve —
     * cupo liberado por el admin (equipo reemplazado), dispositivo
     * revocado permanentemente, o licencia suspendida. A diferencia de un
     * fallo de red (ver llamar()), esto SÍ es autoritativo: hay que sacar
     * al usuario de inmediato y pedirle una clave nueva. install_id NUNCA
     * se toca — se conserva siempre (ver installId()), reactivar con una
     * clave nueva reusa el mismo identificador de equipo.
     */
    private static function liberarLocalmente(?string $motivo): void
    {
        $datos = self::datos();
        $datos['token'] = null;
        $datos['revocada'] = false; // ya no aplica el flujo viejo de "revocada, reintentar" — ahora se pide activar de nuevo
        $datos['motivo_revocacion'] = null;
        $datos['motivo_liberacion'] = $motivo ?: 'Tu licencia ya no está disponible en este equipo. Contacta al administrador o ingresa una nueva clave.';
        $datos['pagada_hasta'] = null;
        $datos['gestion_remota'] = false;
        self::guardar($datos);
    }

    /**
     * $forzarActualizacion puede venir como `true` (revisar YA, sin
     * horario — comportamiento de siempre) o como
     * `{"instalar_despues_de": "2026-09-04T03:00:00-05:00"}` (revisar YA,
     * pero main.js no instala hasta esa hora aunque ya no haya ventas en
     * curso — programar la hora de una actualización forzada). Un servidor
     * viejo que solo mande `true` sigue funcionando exactamente igual.
     */
    private static function escribirSenalForzarActualizacion($forzarActualizacion): void
    {
        if (empty($forzarActualizacion)) {
            return;
        }
        $ruta = Database::directorioDatos() . '/forzar_actualizacion.flag';
        if (is_array($forzarActualizacion) && !empty($forzarActualizacion['instalar_despues_de'])) {
            @file_put_contents($ruta, json_encode(['instalar_despues_de' => $forzarActualizacion['instalar_despues_de']]));
        } else {
            @touch($ruta);
        }
    }

    /**
     * Best-effort: nunca lanza hacia afuera. Devuelve ['ok' => false] si no
     * hay conexión o el servidor no respondió — el llamador simplemente
     * ignora el resultado y sigue.
     */
    public static function heartbeat(): array
    {
        $datos = self::datos();
        if (empty($datos['token'])) {
            return ['ok' => false, 'error' => 'no activada'];
        }

        try {
            $respuesta = self::llamar('heartbeat.php', [
                'token' => $datos['token'],
                'version_instalada' => self::versionInstalada(),
                'nombre_dispositivo' => $datos['nombre_dispositivo'] ?? gethostname(),
            ] + self::estadoConfiguracionParaServidor(), self::TIMEOUT_HEARTBEAT_SEGUNDOS);

            if (empty($respuesta['ok'])) {
                // El servidor SÍ respondió, y dijo explícitamente que este
                // token ya no es válido — cupo liberado, dispositivo
                // revocado, o licencia suspendida. A diferencia de un fallo
                // de conexión, esta es una respuesta autoritativa: si se
                // ignorara, revocar una licencia nunca tendría efecto real
                // en el equipo del cliente. Aplica sin importar el tipo de
                // licencia (perpetua o suscripción).
                self::liberarLocalmente($respuesta['error'] ?? null);
                return $respuesta + ['liberada_localmente' => true];
            }

            $datos['revocada'] = false;
            $datos['ultima_conexion_exitosa'] = date('c');
            $datos['nombre_negocio'] = $respuesta['nombre_negocio'] ?? ($datos['nombre_negocio'] ?? null);
            $datos['respaldo_habilitado'] = !empty($respuesta['respaldo_habilitado']);
            $datos['actualizaciones_habilitadas'] = !empty($respuesta['actualizaciones_habilitadas']);
            $datos['fidelizacion_habilitada'] = !empty($respuesta['fidelizacion_habilitada']);
            $datos['actualizacion_disponible'] = !empty($respuesta['actualizacion_disponible']);
            $datos['version_disponible'] = $respuesta['version_disponible'] ?? null;
            $datos['notas_version'] = $respuesta['notas'] ?? null;
            $datos['url_descarga'] = $respuesta['url_descarga'] ?? null;
            $datos['pagada_hasta'] = $respuesta['pagada_hasta'] ?? null;
            // Rollout escalonado: qué canal de actualizaciones sigue este
            // equipo — 'canary' solo si el proveedor lo marcó explícitamente
            // para probar una versión antes de soltarla a todos.
            $datos['canal_actualizacion'] = ($respuesta['canal'] ?? '') === 'canary' ? 'canary' : 'estable';

            // Ancla la fecha efectiva a la del servidor (fuente confiable) —
            // nunca la retrocede, solo la adelanta si el servidor va más
            // avanzado que lo último que se había visto localmente.
            if (!empty($respuesta['fecha_servidor'])) {
                $fechaServidor = new DateTimeImmutable($respuesta['fecha_servidor']);
                $guardada = !empty($datos['ultima_fecha_vista'])
                    ? new DateTimeImmutable($datos['ultima_fecha_vista'])
                    : $fechaServidor;
                $datos['ultima_fecha_vista'] = ($fechaServidor > $guardada ? $fechaServidor : $guardada)->format('Y-m-d');
            }

            self::guardar($datos);

            // Control a distancia: el servidor puede mandar la configuración del
            // negocio (tipo, módulos, parámetros). Best-effort: un fallo aquí
            // nunca debe afectar el resto del latido ni la operación del POS.
            self::aplicarConfiguracionRemota($respuesta);

            // Señal para main.js: soporte remoto pidió forzar una revisión
            // de actualización YA, sin esperar el ciclo normal de 6h — se
            // consume una sola vez (main.js borra el archivo al leerlo).
            // Un archivo en disco es más simple que IPC real entre el
            // proceso de PHP y el proceso principal de Electron.
            self::escribirSenalForzarActualizacion($respuesta['forzar_actualizacion'] ?? null);
            // Instalación dirigida (rollback a una versión anterior, o
            // reinstalar una versión puntual) — a diferencia de
            // forzar_actualizacion (que solo dice "revisa ya"), esto trae
            // la versión y la URL exactas del instalador a bajar, porque
            // electron-updater nunca ofrecería una versión igual o más
            // vieja que la instalada por su cuenta.
            if (!empty($respuesta['forzar_version']) && !empty($respuesta['forzar_version']['url']) && !empty($respuesta['forzar_version']['version'])) {
                @file_put_contents(
                    Database::directorioDatos() . '/forzar_version.flag',
                    json_encode($respuesta['forzar_version'])
                );
            }

            return ['ok' => true] + $respuesta;
        } catch (Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    /** Lo que este equipo informa del estado de su configuración en cada latido (ver ConfiguracionRemota::estadoParaServidor). */
    private static function estadoConfiguracionParaServidor(): array
    {
        try {
            require_once __DIR__ . '/ConfiguracionRemota.php';

            return ConfiguracionRemota::estadoParaServidor();
        } catch (Throwable $e) {
            return [];
        }
    }

    private static function aplicarConfiguracionRemota(array $respuesta): void
    {
        try {
            require_once __DIR__ . '/ConfiguracionRemota.php';
            ConfiguracionRemota::procesar($respuesta);
        } catch (Throwable $e) {
            error_log('Configuración remota: no se pudo aplicar — ' . $e->getMessage());
        }
    }

    /**
     * El ping (cada minuto) trae "config_revision" cuando el proveedor administra la
     * configuración a distancia. Si es distinta de la que este equipo ya aplicó, se pide
     * el latido completo YA (que trae el bloque) en vez de esperar hasta 6 horas. Con un
     * tope de una vez cada 5 minutos, para que una revisión que no se pudiera aplicar no
     * genere un latido por minuto.
     */
    private static function adelantarLatidoSiHayConfiguracionNueva(array $respuesta): void
    {
        if (!array_key_exists('config_revision', $respuesta) || !is_numeric($respuesta['config_revision'])) {
            return;
        }
        if ((int) $respuesta['config_revision'] < 1) {
            return; // 0 o negativo = el proveedor no la administra a distancia
        }
        $datos = self::datos();
        $aplicada = (int) ($datos['config_revision_aplicada'] ?? 0);
        if ((int) $respuesta['config_revision'] === $aplicada && !empty($datos['gestion_remota'])) {
            return;
        }
        if (time() - self::$ultimoHeartbeatPorConfig < 300) {
            return;
        }
        self::$ultimoHeartbeatPorConfig = time();
        self::heartbeat();
    }

    /**
     * Presencia liviana: a diferencia de heartbeat() (cada 6h, recalcula
     * todo el estado de la licencia), esto lo llama main.js cada ~60
     * segundos solo para que el panel del proveedor pueda mostrar "en
     * línea" y para que "forzar actualización" llegue en minutos y no
     * hasta el próximo heartbeat de 6h. Nunca debe bloquear ni cambiar el
     * estado de la licencia — un fallo aquí simplemente no hace nada.
     */
    public static function ping(): array
    {
        $datos = self::datos();
        if (empty($datos['token'])) {
            return ['ok' => false];
        }

        try {
            $respuesta = self::llamar('ping.php', [
                'token' => $datos['token'],
            ], self::TIMEOUT_PING_SEGUNDOS);
        } catch (Throwable $e) {
            // Fallo de RED (sin internet, timeout, servidor caído) — nunca
            // es una respuesta real del servidor, así que nunca debe
            // liberar/desactivar nada localmente. Se reintenta solo en el
            // próximo ciclo de 60s (ver programarPingLicencia en main.js).
            return ['ok' => false, 'fallo_red' => true, 'error' => $e->getMessage()];
        }

        if (empty($respuesta['ok'])) {
            // El servidor SÍ respondió, y dijo explícitamente que este
            // token ya no sirve — mismo caso que en heartbeat(), pero el
            // ping lo detecta en segundos en vez de hasta 6 horas.
            self::liberarLocalmente($respuesta['error'] ?? null);
            return $respuesta + ['liberada_localmente' => true];
        }

        self::escribirSenalForzarActualizacion($respuesta['forzar_actualizacion'] ?? null);
        self::adelantarLatidoSiHayConfiguracionNueva($respuesta);
        if (!empty($respuesta['forzar_respaldo'])) {
            @touch(Database::directorioDatos() . '/forzar_respaldo.flag');
        }
        if (!empty($respuesta['forzar_version']) && !empty($respuesta['forzar_version']['url']) && !empty($respuesta['forzar_version']['version'])) {
            @file_put_contents(
                Database::directorioDatos() . '/forzar_version.flag',
                json_encode($respuesta['forzar_version'])
            );
        }

        return $respuesta;
    }
}
