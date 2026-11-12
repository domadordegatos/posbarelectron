<?php
/**
 * Conexión única a SQLite, con migraciones versionadas automáticas.
 *
 * Una instalación nueva no tiene archivo .db todavía: se crea y se le
 * aplica schema.sql completo (versión 1). Una instalación que ya existía
 * (por ejemplo, tras actualizar la app a una versión con columnas/tablas
 * nuevas) solo recibe los archivos de modelo/migraciones/ que todavía no
 * se le hayan aplicado — se sabe cuáles faltan comparando SCHEMA_VERSION
 * contra PRAGMA user_version, que SQLite guarda en el propio archivo .db
 * para exactamente este uso (no hace falta una tabla aparte).
 */
class Database
{
    private static ?PDO $conexion = null;

    /** Súbelo cada vez que agregues un archivo nuevo en modelo/migraciones/. */
    private const SCHEMA_VERSION = 53;

    public static function conexion(): PDO
    {
        if (self::$conexion === null) {
            $rutaBd = self::rutaArchivoBd();
            $existia = file_exists($rutaBd);

            if (!$existia && file_exists($rutaBd . '.enc')) {
                // No hay posbar.db en texto plano, pero SÍ hay un respaldo
                // cifrado (posbar.db.enc). Esto NUNCA debería pasar en un
                // arranque normal: cifrado-bd.js siempre descifra el .enc
                // a texto plano ANTES de que este código se ejecute (ver
                // prepararBaseDeDatosCifrada() en cifrado-bd.js). Si llegamos
                // aquí es porque algo se saltó ese paso — lo seguro es
                // fallar ruidosamente en vez de crear un esquema vacío y
                // arriesgarnos a que luego se cifre por encima del respaldo
                // real, destruyéndolo sin posibilidad de recuperación.
                throw new RuntimeException(
                    "Se encontró posbar.db.enc pero no posbar.db en texto plano en " . dirname($rutaBd) . ". " .
                    'Esto indica que el paso de descifrado no se ejecutó antes de abrir la base de datos. ' .
                    'Para evitar pérdida de datos, no se creará una base de datos nueva automáticamente.'
                );
            }

            $pdo = new PDO('sqlite:' . $rutaBd);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            $pdo->exec('PRAGMA foreign_keys = ON');
            // WAL permite que una lectura no bloquee una escritura en curso;
            // busy_timeout hace que una escritura concurrente espere en vez
            // de fallar de inmediato con "database is locked".
            $pdo->exec('PRAGMA journal_mode = WAL');
            $pdo->exec('PRAGMA busy_timeout = 5000');

            if (!$existia) {
                self::aplicarEsquema($pdo);
                $pdo->exec('PRAGMA user_version = 1');
            }

            self::aplicarMigracionesPendientes($pdo);

            self::$conexion = $pdo;
        }

        return self::$conexion;
    }

    private static function aplicarEsquema(PDO $pdo): void
    {
        $rutaEsquema = __DIR__ . '/schema.sql';
        $sql = file_get_contents($rutaEsquema);
        if ($sql === false) {
            throw new RuntimeException("No se pudo leer el esquema en $rutaEsquema");
        }
        $pdo->exec($sql);
    }

    private static function aplicarMigracionesPendientes(PDO $pdo): void
    {
        $version = (int) $pdo->query('PRAGMA user_version')->fetchColumn();

        if ($version >= self::SCHEMA_VERSION) {
            return;
        }

        $carpeta = __DIR__ . '/migraciones';
        for ($siguiente = $version + 1; $siguiente <= self::SCHEMA_VERSION; $siguiente++) {
            $archivos = glob(sprintf('%s/%03d_*.sql', $carpeta, $siguiente));
            if (empty($archivos)) {
                throw new RuntimeException("Falta el archivo de migración para la versión $siguiente en $carpeta");
            }

            $sql = file_get_contents($archivos[0]);
            if ($sql === false) {
                throw new RuntimeException("No se pudo leer la migración {$archivos[0]}");
            }

            $pdo->exec('BEGIN');
            try {
                $pdo->exec($sql);
                $pdo->exec("PRAGMA user_version = $siguiente");
                $pdo->exec('COMMIT');
            } catch (Throwable $e) {
                $pdo->exec('ROLLBACK');
                throw new RuntimeException("Error aplicando la migración {$archivos[0]}: " . $e->getMessage(), 0, $e);
            }
        }
    }

    /**
     * Electron pasa POSBAR_DATA_DIR apuntando a app.getPath('userData')
     * (ver main.js) para que la base de datos sobreviva a cada
     * actualización. Si se abre fuera de Electron (ej. "php -S" a mano
     * para probar en el navegador), se usa una carpeta local de respaldo.
     */
    public static function directorioDatos(): string
    {
        $directorio = getenv('POSBAR_DATA_DIR');
        if ($directorio === false || $directorio === '') {
            $directorio = __DIR__ . '/../../datos-dev';
        }
        if (!is_dir($directorio)) {
            mkdir($directorio, 0777, true);
        }

        return $directorio;
    }

    private static function rutaArchivoBd(): string
    {
        return self::directorioDatos() . '/posbar.db';
    }
}
