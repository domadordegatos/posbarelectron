<?php
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Configuracion.php';

class Auth
{
    public static function iniciarSesion(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_set_cookie_params([
                'lifetime' => 0,
                'path' => '/',
                'httponly' => true,
                'samesite' => 'Strict',
            ]);
            session_start();
        }
    }

    public static function intentarLogin(string $usuario, string $password): ?array
    {
        $pdo = Database::conexion();
        $stmt = $pdo->prepare(
            'SELECT id_usuario, nombre, usuario, password_hash, rol, estado
             FROM usuarios WHERE usuario = :usuario LIMIT 1'
        );
        $stmt->execute(['usuario' => $usuario]);
        $fila = $stmt->fetch();

        if (!$fila || (int) $fila['estado'] !== 1 || !password_verify($password, $fila['password_hash'])) {
            return null;
        }

        self::iniciarSesion();
        session_regenerate_id(true);

        $_SESSION['usuario'] = [
            'id' => (int) $fila['id_usuario'],
            'nombre' => $fila['nombre'],
            'usuario' => $fila['usuario'],
            'rol' => $fila['rol'],
        ];

        return $_SESSION['usuario'];
    }

    /**
     * Comprueba el usuario y la clave de un ADMINISTRADOR activo sin iniciar ni cambiar la sesión
     * (para que un cajero pueda pedir la autorización de un administrador en una acción delicada,
     * ej. corregir el monto de apertura de la caja). Devuelve al administrador o null.
     * Se bloquea 5 minutos después de 5 intentos fallidos seguidos en esta sesión, para que no se
     * pueda adivinar una clave a fuerza de intentos.
     *
     * @return array{admin: ?array, error: ?string}
     */
    public static function verificarAdministrador(string $usuario, string $password): array
    {
        self::iniciarSesion();
        $ahora = time();
        if (($_SESSION['aut_admin_bloqueado_hasta'] ?? 0) > $ahora) {
            return ['admin' => null, 'error' => 'Demasiados intentos fallidos. Espera unos minutos e inténtalo de nuevo.'];
        }

        $pdo = Database::conexion();
        $stmt = $pdo->prepare("SELECT id_usuario, nombre, usuario, password_hash FROM usuarios WHERE usuario = :usuario AND rol = 'admin' AND estado = 1 LIMIT 1");
        $stmt->execute(['usuario' => trim($usuario)]);
        $fila = $stmt->fetch();

        if ($fila && $password !== '' && password_verify($password, $fila['password_hash'])) {
            unset($_SESSION['aut_admin_fallos'], $_SESSION['aut_admin_bloqueado_hasta']);
            return ['admin' => ['id' => (int) $fila['id_usuario'], 'nombre' => $fila['nombre'], 'usuario' => $fila['usuario']], 'error' => null];
        }

        $_SESSION['aut_admin_fallos'] = ($_SESSION['aut_admin_fallos'] ?? 0) + 1;
        if ($_SESSION['aut_admin_fallos'] >= 5) {
            $_SESSION['aut_admin_bloqueado_hasta'] = $ahora + 300;
            $_SESSION['aut_admin_fallos'] = 0;
        }

        return ['admin' => null, 'error' => 'Usuario o clave de administrador incorrectos.'];
    }

    public static function cerrarSesion(): void
    {
        self::iniciarSesion();
        $_SESSION = [];
        session_destroy();
    }

    public static function usuarioActual(): ?array
    {
        self::iniciarSesion();
        return $_SESSION['usuario'] ?? null;
    }

    public static function estaAutenticado(): bool
    {
        return self::usuarioActual() !== null;
    }

    public static function tieneRol(string ...$roles): bool
    {
        $usuario = self::usuarioActual();
        return $usuario !== null && in_array($usuario['rol'], $roles, true);
    }

    public static function requerirLogin(): array
    {
        self::iniciarSesion();
        $usuario = self::usuarioActual();
        if (!$usuario) {
            header('Location: /vista/login/index.php');
            exit;
        }
        return $usuario;
    }

    public static function requerirRol(string ...$roles): array
    {
        $usuario = self::requerirLogin();
        if (!in_array($usuario['rol'], $roles, true)) {
            http_response_code(403);
            exit('No tienes permisos para acceder a esta sección.');
        }
        return $usuario;
    }

    public static function requerirLoginApi(): array
    {
        self::iniciarSesion();
        $usuario = self::usuarioActual();
        if (!$usuario) {
            http_response_code(401);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'No autenticado']);
            exit;
        }
        return $usuario;
    }

    public static function requerirRolApi(string ...$roles): array
    {
        $usuario = self::requerirLoginApi();
        if (!in_array($usuario['rol'], $roles, true)) {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'No tienes permisos para esta acción']);
            exit;
        }
        return $usuario;
    }

    /** Algunos permisos solo tienen sentido si su módulo está activo — aplica por igual a admin y cajero. */
    private const MODULO_POR_PERMISO = [
        'caja.gestionar' => 'caja_turnos',
        'clientes.gestionar' => 'clientes_credito',
        'proveedores.gestionar' => 'proveedores',
    ];

    /**
     * true si el usuario en sesión puede realizar la acción $clave. Admin
     * siempre tiene acceso total dentro de lo que el módulo correspondiente
     * permita (no se consulta rol_permisos, para no poder auto-bloquearse).
     * No se cachea en sesión: un cambio de permisos hecho por el admin
     * aplica de inmediato a un cajero ya logueado.
     */
    public static function tienePermiso(string $clave): bool
    {
        $usuario = self::usuarioActual();
        if (!$usuario) {
            return false;
        }

        $modulo = self::MODULO_POR_PERMISO[$clave] ?? null;
        if ($modulo !== null && !Configuracion::moduloActivo($modulo)) {
            return false;
        }

        if ($usuario['rol'] === 'admin') {
            return true;
        }

        $pdo = Database::conexion();
        $stmt = $pdo->prepare(
            'SELECT 1 FROM rol_permisos rp
             JOIN permisos p ON rp.id_permiso = p.id_permiso
             WHERE rp.rol = :rol AND p.clave = :clave'
        );
        $stmt->execute(['rol' => $usuario['rol'], 'clave' => $clave]);

        return (bool) $stmt->fetch();
    }

    public static function requerirPermiso(string $clave): array
    {
        $usuario = self::requerirLogin();
        if (!self::tienePermiso($clave)) {
            http_response_code(403);
            exit('No tienes permisos para acceder a esta sección.');
        }
        return $usuario;
    }

    public static function requerirPermisoApi(string $clave): array
    {
        $usuario = self::requerirLoginApi();
        if (!self::tienePermiso($clave)) {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'No tienes permisos para esta acción']);
            exit;
        }
        return $usuario;
    }

    /** Para vistas/endpoints de un módulo activable (Caja, Clientes, IVA, Combos, Mesas...). */
    public static function requerirModulo(string $modulo): array
    {
        $usuario = self::requerirLogin();
        if (!Configuracion::moduloActivo($modulo)) {
            http_response_code(403);
            exit('Esta función no está activada para esta instalación.');
        }
        return $usuario;
    }

    public static function requerirModuloApi(string $modulo): array
    {
        $usuario = self::requerirLoginApi();
        if (!Configuracion::moduloActivo($modulo)) {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Esta función no está activada para esta instalación.']);
            exit;
        }
        return $usuario;
    }

    /**
     * Sesión del panel de superadministrador (proveedor del software):
     * completamente independiente de la sesión del negocio.
     */
    public static function esSuperAdmin(): bool
    {
        self::iniciarSesion();
        return $_SESSION['superadmin'] ?? false;
    }

    public static function requerirSuperAdmin(): void
    {
        self::iniciarSesion();
        if (!self::esSuperAdmin()) {
            header('Location: /vista/superadmin/login.php');
            exit;
        }
    }

    public static function requerirSuperAdminApi(): void
    {
        self::iniciarSesion();
        if (!self::esSuperAdmin()) {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'No autenticado como superadministrador.']);
            exit;
        }
    }
}
