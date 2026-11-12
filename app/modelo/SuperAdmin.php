<?php
require_once __DIR__ . '/Database.php';

/**
 * Acceso al panel de superadministrador (proveedor del software): un
 * código de 6 dígitos tipo TOTP (RFC 6238, el mismo estándar de los
 * tokens de banco / Google Authenticator), calculado a partir de un
 * secreto y la hora actual — funciona sin internet.
 */
class SuperAdmin
{
    private const PASO_SEGUNDOS = 30;
    private const DIGITOS = 6;
    private const TOLERANCIA_PASOS = 1;

    private const MAX_INTENTOS = 5;
    private const VENTANA_BLOQUEO_MINUTOS = 15;

    private static ?array $config = null;

    private static function config(): array
    {
        if (self::$config === null) {
            // Primero se busca fuera de la carpeta pública (carpeta de datos
            // — así debe vivir en un hosting web, ver comentario en
            // superadmin_secreto.php); si no está ahí, se usa la copia
            // empaquetada junto al código (caso del instalador de escritorio).
            $rutaExterna = Database::directorioDatos() . '/superadmin_secreto.php';
            $ruta = is_file($rutaExterna) ? $rutaExterna : __DIR__ . '/superadmin_secreto.php';
            self::$config = is_file($ruta) ? require $ruta : [];
        }

        return self::$config;
    }

    public static function estaConfigurado(): bool
    {
        return !empty(self::config()['secreto_totp']);
    }

    public static function usuarioRoot(): string
    {
        return self::config()['root_usuario'] ?? 'root';
    }

    private static function secretoBinario(): string
    {
        $hex = self::config()['secreto_totp'] ?? '';
        return $hex !== '' ? hex2bin($hex) : '';
    }

    private static function codigoParaPaso(int $paso): string
    {
        $contador = pack('J', $paso);
        $hash = hash_hmac('sha1', $contador, self::secretoBinario(), true);
        $offset = ord($hash[19]) & 0x0f;
        $binario = ((ord($hash[$offset]) & 0x7f) << 24)
            | (ord($hash[$offset + 1]) << 16)
            | (ord($hash[$offset + 2]) << 8)
            | ord($hash[$offset + 3]);
        $codigo = $binario % (10 ** self::DIGITOS);

        return str_pad((string) $codigo, self::DIGITOS, '0', STR_PAD_LEFT);
    }

    public static function validarCodigo(string $codigo): bool
    {
        if (!self::estaConfigurado() || !preg_match('/^\d{6}$/', $codigo)) {
            return false;
        }

        $pasoActual = intdiv(time(), self::PASO_SEGUNDOS);
        for ($delta = -self::TOLERANCIA_PASOS; $delta <= self::TOLERANCIA_PASOS; $delta++) {
            if (hash_equals(self::codigoParaPaso($pasoActual + $delta), $codigo)) {
                return true;
            }
        }

        return false;
    }

    public static function estaBloqueado(string $ip): bool
    {
        $pdo = Database::conexion();
        $stmt = $pdo->prepare(
            "SELECT COUNT(*) AS fallidos FROM superadmin_intentos
             WHERE ip = :ip AND exitoso = 0 AND creado_en >= datetime('now', :ventana)"
        );
        $stmt->execute(['ip' => $ip, 'ventana' => '-' . self::VENTANA_BLOQUEO_MINUTOS . ' minutes']);

        return (int) $stmt->fetch()['fallidos'] >= self::MAX_INTENTOS;
    }

    public static function registrarIntento(string $ip, string $usuario, bool $exitoso): void
    {
        $pdo = Database::conexion();
        $stmt = $pdo->prepare('INSERT INTO superadmin_intentos (ip, usuario, exitoso) VALUES (:ip, :usuario, :exitoso)');
        $stmt->execute(['ip' => $ip, 'usuario' => $usuario, 'exitoso' => $exitoso ? 1 : 0]);
    }

    public static function historialIntentos(int $limite = 30): array
    {
        $pdo = Database::conexion();
        $stmt = $pdo->prepare('SELECT * FROM superadmin_intentos ORDER BY id_intento DESC LIMIT :limite');
        $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }
}
