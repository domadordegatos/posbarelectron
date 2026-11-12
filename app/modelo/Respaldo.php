<?php
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Licencia.php';

/**
 * Lógica compartida del respaldo remoto — la usan tanto el botón manual
 * (controlador/backup.php, con sesión de admin) como el disparo automático
 * programado por main.js (controlador/backup_automatico.php, sin sesión).
 */
class Respaldo
{
    private const TIMEOUT_SEGUNDOS = 30;
    // El automático corre cada 24h — este mínimo solo limita al botón manual
    // ("Hacer respaldo ahora"), para que alguien no pueda mandar decenas de
    // copias al día a mano sin ninguna razón real.
    private const HORAS_MINIMAS_ENTRE_RESPALDOS = 12;

    /** Para que la vista pueda desactivar el botón antes de que alguien haga clic y se lleve el error. */
    public static function proximoRespaldoManualDisponibleEn(): ?DateTimeImmutable
    {
        $ultimoRespaldoEn = Licencia::datos()['ultimo_respaldo_en'] ?? null;
        if (!$ultimoRespaldoEn) {
            return null;
        }
        $disponibleDesde = (new DateTimeImmutable($ultimoRespaldoEn))->modify('+' . self::HORAS_MINIMAS_ENTRE_RESPALDOS . ' hours');
        return $disponibleDesde > new DateTimeImmutable() ? $disponibleDesde : null;
    }

    private static function rutaBaseDatos(): string
    {
        return Database::directorioDatos() . '/posbar.db';
    }

    /**
     * SQLite puede tener escrituras pendientes en el journal — copiar el
     * archivo mientras hay una conexión abierta puede llevarse una copia a
     * medias. Se abre una conexión propia y se fuerza un checkpoint antes
     * de copiar, para asegurar que todo lo pendiente ya quedó escrito en
     * el archivo principal.
     */
    private static function prepararCopiaConsistente(string $origen, string $destinoTemporal): void
    {
        if (!file_exists($origen)) {
            throw new RuntimeException('No existe una base de datos para respaldar todavía.');
        }

        $pdo = new PDO('sqlite:' . $origen);
        $pdo->exec('PRAGMA wal_checkpoint(FULL)');
        $pdo = null; // cierra la conexión antes de copiar

        if (!copy($origen, $destinoTemporal)) {
            throw new RuntimeException('No se pudo copiar el archivo de base de datos.');
        }
    }

    private static function enviarArchivo(string $rutaArchivo, string $token): array
    {
        if (!function_exists('curl_init')) {
            throw new RuntimeException('Esta build de PHP no tiene cURL habilitado; no se puede enviar el respaldo.');
        }

        $nombreArchivo = 'posbar-' . date('Y-m-d_His') . '.db';
        $archivoCurl = new CURLFile($rutaArchivo, 'application/octet-stream', $nombreArchivo);

        $ch = curl_init(rtrim(Licencia::URL_BASE, '/') . '/respaldo.php');
        $opciones = [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => ['archivo' => $archivoCurl, 'token' => $token],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => self::TIMEOUT_SEGUNDOS,
            CURLOPT_CONNECTTIMEOUT => self::TIMEOUT_SEGUNDOS,
            CURLOPT_SSL_VERIFYPEER => true,
        ];
        // Ver comentario equivalente en Licencia.php::llamar().
        $cacert = dirname(PHP_BINARY) . DIRECTORY_SEPARATOR . 'cacert.pem';
        if (is_file($cacert)) {
            $opciones[CURLOPT_CAINFO] = $cacert;
        }
        curl_setopt_array($ch, $opciones);
        $respuesta = curl_exec($ch);
        $error = curl_error($ch);
        $codigoHttp = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($respuesta === false) {
            throw new RuntimeException("No se pudo enviar el respaldo: $error");
        }
        if ($codigoHttp < 200 || $codigoHttp >= 300) {
            throw new RuntimeException("El servidor de respaldos respondió HTTP $codigoHttp");
        }

        // HTTP 200 solo confirma que el servidor respondió — no que haya
        // recibido/guardado el archivo. Sin esto, un error de aplicación del
        // lado del servidor (ej. el archivo nunca llegó a $_FILES) se
        // reportaría acá como respaldo exitoso.
        $cuerpo = json_decode($respuesta, true);
        if (!is_array($cuerpo) || empty($cuerpo['ok'])) {
            $motivo = is_array($cuerpo) && !empty($cuerpo['error']) ? $cuerpo['error'] : 'respuesta inesperada del servidor';
            throw new RuntimeException("El servidor de respaldos no confirmó el envío: $motivo");
        }

        return ['nombre_archivo' => $nombreArchivo];
    }

    /**
     * Lanza RuntimeException con un mensaje claro si algo falla en cualquier
     * paso. $forzado = true salta el límite de horas entre respaldos (lo usa
     * el respaldo de emergencia que pide el proveedor desde el servidor,
     * ver controlador/backup_forzado.php) — sigue exigiendo que la licencia
     * tenga el respaldo habilitado, eso no se salta.
     */
    public static function realizar(bool $forzado = false): array
    {
        $datosLicencia = Licencia::datos();
        if (empty($datosLicencia['token'])) {
            throw new RuntimeException('Esta instalación todavía no está activada.');
        }
        if (empty($datosLicencia['respaldo_habilitado'])) {
            throw new RuntimeException('El respaldo automático no está habilitado para esta licencia.');
        }

        if (!$forzado && !empty($datosLicencia['ultimo_respaldo_en'])) {
            $ultimo = new DateTimeImmutable($datosLicencia['ultimo_respaldo_en']);
            $horasTranscurridas = (time() - $ultimo->getTimestamp()) / 3600;
            if ($horasTranscurridas < self::HORAS_MINIMAS_ENTRE_RESPALDOS) {
                $faltan = (int) ceil(self::HORAS_MINIMAS_ENTRE_RESPALDOS - $horasTranscurridas);
                throw new RuntimeException("Ya se hizo un respaldo hace poco — vuelve a intentarlo en aproximadamente $faltan hora(s).");
            }
        }

        $archivoTemporal = sys_get_temp_dir() . '/posbar-respaldo-' . uniqid() . '.db';
        try {
            self::prepararCopiaConsistente(self::rutaBaseDatos(), $archivoTemporal);
            $resultado = self::enviarArchivo($archivoTemporal, $datosLicencia['token']);
            Licencia::registrarRespaldoExitoso();

            return [
                'ok' => true,
                'archivo' => $resultado['nombre_archivo'],
                'tamano_bytes' => filesize($archivoTemporal),
                'fecha' => date('c'),
            ];
        } finally {
            if (file_exists($archivoTemporal)) {
                unlink($archivoTemporal);
            }
        }
    }
}
