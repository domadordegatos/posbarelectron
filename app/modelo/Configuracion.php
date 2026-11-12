<?php
require_once __DIR__ . '/Database.php';

class Configuracion
{
    private static ?array $cache = null;

    private const EXTENSIONES_PERMITIDAS = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'];
    private const TAMANO_MAXIMO_LOGO = 2 * 1024 * 1024; // 2 MB

    /**
     * Módulos avanzados que el proveedor activa/desactiva desde el panel de
     * superadministrador. Los permisos "núcleo" (vender, ver/editar
     * productos, registrar compras/pérdidas, anular ventas) están siempre
     * disponibles, sin importar los módulos.
     */
    public const MODULOS = [
        'paquetes', 'costeo', 'permisos_rol', 'caja_turnos',
        'clientes_credito', 'proveedores', 'iva', 'combos', 'mesas_comandas', 'promociones', 'modificadores',
        'existencias', 'propina', 'profesionales', 'vencimientos',
    ];

    /**
     * 'servicios' = peluquería, consultorio, estética… (se vende trabajo, no
     * mercancía). 'taller' = mano de obra + repuestos. Un tipo es solo un
     * punto de partida: el proveedor puede ajustar cada módulo después.
     */
    public const TIPOS_NEGOCIO = ['tienda', 'supermercado', 'bar_restaurante', 'servicios', 'taller', 'personalizado'];

    public const PRESETS_TIPO_NEGOCIO = [
        'tienda' => [
            'paquetes' => true, 'costeo' => true, 'permisos_rol' => true, 'caja_turnos' => true,
            'clientes_credito' => false, 'proveedores' => true, 'iva' => false, 'combos' => false, 'mesas_comandas' => false,
            'promociones' => false, 'modificadores' => false, 'existencias' => true, 'propina' => false, 'profesionales' => false,
            'vencimientos' => false,
        ],
        'supermercado' => [
            'paquetes' => true, 'costeo' => true, 'permisos_rol' => true, 'caja_turnos' => true,
            'clientes_credito' => true, 'proveedores' => true, 'iva' => true, 'combos' => false, 'mesas_comandas' => false,
            'promociones' => false, 'modificadores' => false, 'existencias' => true, 'propina' => false, 'profesionales' => false,
            'vencimientos' => true,
        ],
        'bar_restaurante' => [
            'paquetes' => true, 'costeo' => true, 'permisos_rol' => true, 'caja_turnos' => true,
            'clientes_credito' => true, 'proveedores' => true, 'iva' => true, 'combos' => true, 'mesas_comandas' => true,
            'promociones' => true, 'modificadores' => true, 'existencias' => true, 'propina' => true, 'profesionales' => false,
            'vencimientos' => true,
        ],
        // Servicios: sin inventario ni costeo por producto; con propina; combos = "paquetes" de servicios (corte + barba).
        'servicios' => [
            'paquetes' => false, 'costeo' => false, 'permisos_rol' => true, 'caja_turnos' => true,
            'clientes_credito' => true, 'proveedores' => false, 'iva' => false, 'combos' => true, 'mesas_comandas' => false,
            'promociones' => true, 'modificadores' => false, 'existencias' => false, 'propina' => true, 'profesionales' => true,
            'vencimientos' => false,
        ],
        // Taller: repuestos con existencias y costo, mano de obra como servicio, cuentas por cobrar y IVA; sin propina.
        'taller' => [
            'paquetes' => false, 'costeo' => true, 'permisos_rol' => true, 'caja_turnos' => true,
            'clientes_credito' => true, 'proveedores' => true, 'iva' => true, 'combos' => true, 'mesas_comandas' => false,
            'promociones' => false, 'modificadores' => false, 'existencias' => true, 'propina' => false, 'profesionales' => true,
            'vencimientos' => true,
        ],
    ];

    public static function obtener(): array
    {
        if (self::$cache === null) {
            $pdo = Database::conexion();
            $fila = $pdo->query('SELECT * FROM configuracion_negocio WHERE id = 1')->fetch();
            self::$cache = $fila ?: [
                'nombre_negocio' => 'Mi Negocio',
                'color_primario' => '#4f46e5',
                'color_secundario' => '#f97316',
                'moneda' => 'COP',
                'zona_horaria' => 'America/Bogota',
                'logo_path' => null,
                'formato_ticket' => 'a4',
                'tipo_negocio' => 'personalizado',
                'nit' => null,
                'telefono' => null,
                'direccion' => null,
                'lema' => null,
                'ancho_ticket_mm' => null,
                'margen_ticket_mm' => null,
                'ancho_ticket_58_mm' => null,
                'margen_ticket_58_mm' => null,
                'ancho_ticket_80_mm' => null,
                'margen_ticket_80_mm' => null,
                'margen_ticket_a4_mm' => null,
                'impresora_recibo' => null,
                'impresora_cocina' => null,
                'impresora_etiquetas' => null,
                'ancho_etiqueta_mm' => null,
                'alto_etiqueta_mm' => null,
                'fidelizacion_qr_auto_venta' => 0,
                'ventas_vista_cuadricula' => 0,
                'ventas_cuadricula_modo' => 'productos',
                'mesas_mostrar_cliente' => 0,
                'periodo_balance_tipo' => 'mensual',
                'periodo_balance_dia' => 1,
            ];
        }

        return self::$cache;
    }

    public static function moduloActivo(string $modulo): bool
    {
        if (!in_array($modulo, self::MODULOS, true)) {
            return false;
        }

        return (bool) (self::obtener()['mod_' . $modulo] ?? false);
    }

    public static function modulosActivos(): array
    {
        $config = self::obtener();
        $activos = [];
        foreach (self::MODULOS as $modulo) {
            $activos[$modulo] = (bool) ($config['mod_' . $modulo] ?? false);
        }

        return $activos;
    }

    /**
     * Ciclo de corte del "balance" (ventas - gastos) que el Panel y Compras y gastos calculan solos, sin
     * tener que elegir fechas a mano cada vez — cada negocio tiene su propio ritmo (reporte real: un
     * cliente cobra y paga cada 15 días). 'mensual' con día 1 (el valor de fábrica) es el mes calendario
     * de siempre: un negocio que no configura nada ve lo mismo que vería hoy.
     */
    public const PERIODOS_BALANCE = ['semanal', 'quincenal', 'mensual'];

    public static function periodoBalance(): array
    {
        $config = self::obtener();
        $tipo = in_array($config['periodo_balance_tipo'] ?? null, self::PERIODOS_BALANCE, true)
            ? $config['periodo_balance_tipo'] : 'mensual';
        $dia = (int) ($config['periodo_balance_dia'] ?? 1);
        if ($tipo === 'semanal' && ($dia < 1 || $dia > 7)) {
            $dia = 1;
        }
        if ($tipo === 'mensual' && ($dia < 1 || $dia > 28)) {
            $dia = 1;
        }

        return ['tipo' => $tipo, 'dia' => $dia];
    }

    public static function actualizarPeriodoBalance(string $tipo, int $dia): bool
    {
        if (!in_array($tipo, self::PERIODOS_BALANCE, true)) {
            throw new InvalidArgumentException('Elige un tipo de período válido (semanal, quincenal o mensual).');
        }
        if ($tipo === 'semanal') {
            if ($dia < 1 || $dia > 7) {
                throw new InvalidArgumentException('Elige un día de la semana válido.');
            }
        } elseif ($tipo === 'mensual') {
            // 1-28 (no 1-31): así "+1 mes" siempre cae en un día que existe en TODOS los meses — sin
            // esto, empezar un período el 30 o 31 se rompería en febrero.
            if ($dia < 1 || $dia > 28) {
                throw new InvalidArgumentException('El día del mes debe estar entre 1 y 28 (para que exista en todos los meses).');
            }
        } else {
            $dia = 1; // quincenal es fijo: 1–15 y 16–fin de mes, no usa día.
        }
        $pdo = Database::conexion();
        $resultado = $pdo->prepare('UPDATE configuracion_negocio SET periodo_balance_tipo = :tipo, periodo_balance_dia = :dia WHERE id = 1')
            ->execute(['tipo' => $tipo, 'dia' => $dia]);
        self::$cache = null;

        return $resultado;
    }

    /**
     * Rango [inicio, fin] (Y-m-d, ambos inclusive) del período que contiene $hoy, según el tipo/día
     * configurados. Pura y testable sin tocar la base — no depende de más estado que sus parámetros.
     */
    public static function rangoPeriodo(string $tipo, int $dia, ?string $hoy = null): array
    {
        $hoy = $hoy !== null ? new DateTimeImmutable($hoy) : new DateTimeImmutable('today');

        if ($tipo === 'semanal') {
            $diaSemanaHoy = (int) $hoy->format('N'); // 1 = lunes … 7 = domingo
            $retroceder = ($diaSemanaHoy - $dia + 7) % 7;
            $inicio = $hoy->modify("-{$retroceder} days");
            $fin = $inicio->modify('+6 days');
        } elseif ($tipo === 'quincenal') {
            $diaMes = (int) $hoy->format('j');
            if ($diaMes <= 15) {
                $inicio = $hoy->modify('first day of this month');
                $fin = $inicio->modify('+14 days');
            } else {
                $inicio = $hoy->modify('first day of this month')->modify('+15 days');
                $fin = $hoy->modify('last day of this month');
            }
        } else { // mensual
            $diaMes = (int) $hoy->format('j');
            if ($diaMes >= $dia) {
                $inicio = $hoy->modify('first day of this month')->modify('+' . ($dia - 1) . ' days');
            } else {
                $inicio = $hoy->modify('first day of last month')->modify('+' . ($dia - 1) . ' days');
            }
            $fin = $inicio->modify('+1 month')->modify('-1 day');
        }

        return ['inicio' => $inicio->format('Y-m-d'), 'fin' => $fin->format('Y-m-d')];
    }

    /** Porcentaje de propina sugerido (0–30). Solo tiene efecto con el módulo "propina" activo. */
    public static function propinaPorcentaje(): float
    {
        $valor = (float) (self::obtener()['propina_porcentaje'] ?? 10);

        return max(0.0, min(30.0, $valor));
    }

    /** true si al cobrar la propina sugerida se aplica sola (la persona puede quitarla). */
    public static function propinaPorDefecto(): bool
    {
        return (bool) (self::obtener()['propina_por_defecto'] ?? false);
    }

    /** Decisión del negocio (Configuración), no del proveedor — pero solo se ofrece si el módulo "propina" está activo. */
    public static function guardarParametrosPropina(float $porcentaje, bool $porDefecto): bool
    {
        $pdo = Database::conexion();
        $resultado = $pdo->prepare('UPDATE configuracion_negocio SET propina_porcentaje = :p, propina_por_defecto = :d WHERE id = 1')
            ->execute(['p' => max(0.0, min(30.0, $porcentaje)), 'd' => $porDefecto ? 1 : 0]);
        self::$cache = null;

        return $resultado;
    }

    public static function tipoNegocio(): string
    {
        return self::obtener()['tipo_negocio'] ?? 'personalizado';
    }

    public static function guardarModulos(string $tipoNegocio, array $modulos): bool
    {
        $tipoNegocio = in_array($tipoNegocio, self::TIPOS_NEGOCIO, true) ? $tipoNegocio : 'personalizado';

        $pdo = Database::conexion();
        $asignaciones = ['tipo_negocio = :tipo'];
        $params = ['tipo' => $tipoNegocio];
        foreach (self::MODULOS as $modulo) {
            $asignaciones[] = "mod_$modulo = :mod_$modulo";
            $params["mod_$modulo"] = !empty($modulos[$modulo]) ? 1 : 0;
        }

        $stmt = $pdo->prepare('UPDATE configuracion_negocio SET ' . implode(', ', $asignaciones) . ' WHERE id = 1');
        $resultado = $stmt->execute($params);
        self::$cache = null;

        // Cada tipo de negocio trae sus propios conceptos de gastos (gas, bolsas, desechables...).
        if ($resultado) {
            require_once __DIR__ . '/conceptos_gasto.php';
            (new conceptos_gasto())->sembrarParaTipo($tipoNegocio);
        }

        return $resultado;
    }

    /** true si Fidelización debe generar y añadir un QR al recibo de cada venta — decisión del negocio, no del proveedor (ver fidelizacion_habilitada en Licencia). */
    public static function fidelizacionQrAutoHabilitado(): bool
    {
        return (bool) (self::obtener()['fidelizacion_qr_auto_venta'] ?? false);
    }

    public static function actualizarFidelizacionQrAuto(bool $habilitado): bool
    {
        $pdo = Database::conexion();
        $resultado = $pdo->prepare('UPDATE configuracion_negocio SET fidelizacion_qr_auto_venta = :v WHERE id = 1')
            ->execute(['v' => $habilitado ? 1 : 0]);
        self::$cache = null;

        return $resultado;
    }

    /** true si Ventas debe mostrar la cuadrícula de productos destacados además del buscador de siempre. */
    public static function ventasVistaCuadriculaHabilitada(): bool
    {
        return (bool) (self::obtener()['ventas_vista_cuadricula'] ?? false);
    }

    public static function actualizarVentasVistaCuadricula(bool $habilitado): bool
    {
        $pdo = Database::conexion();
        $resultado = $pdo->prepare('UPDATE configuracion_negocio SET ventas_vista_cuadricula = :v WHERE id = 1')
            ->execute(['v' => $habilitado ? 1 : 0]);
        self::$cache = null;

        return $resultado;
    }

    /** 'productos' (elegidos a mano) o 'categorias' (agrupados por categoría). */
    public static function cuadriculaModo(): string
    {
        $modo = self::obtener()['ventas_cuadricula_modo'] ?? 'productos';

        return $modo === 'categorias' ? 'categorias' : 'productos';
    }

    public static function actualizarCuadriculaModo(string $modo): bool
    {
        if (!in_array($modo, ['productos', 'categorias'], true)) {
            return false;
        }

        $pdo = Database::conexion();
        $resultado = $pdo->prepare('UPDATE configuracion_negocio SET ventas_cuadricula_modo = :v WHERE id = 1')
            ->execute(['v' => $modo]);
        self::$cache = null;

        return $resultado;
    }

    /** true si la cuadrícula de mesas debe mostrar el nombre del cliente asociado (cuando la mesa tiene uno). */
    public static function mesasMostrarCliente(): bool
    {
        return (bool) (self::obtener()['mesas_mostrar_cliente'] ?? false);
    }

    public static function actualizarMesasMostrarCliente(bool $mostrar): bool
    {
        $pdo = Database::conexion();
        $resultado = $pdo->prepare('UPDATE configuracion_negocio SET mesas_mostrar_cliente = :v WHERE id = 1')
            ->execute(['v' => $mostrar ? 1 : 0]);
        self::$cache = null;

        return $resultado;
    }

    /**
     * ancho/margen van por formato (58mm, 80mm y el margen de A4) desde que un cliente reportó que
     * probar 58mm y volver a 80mm le borraba el milimetraje fino que ya tenía ajustado en 80mm — antes
     * era un solo valor compartido entre los tres. Cada uno se valida y se guarda por separado.
     */
    public static function actualizar(
        string $nombreNegocio,
        string $colorPrimario,
        string $moneda,
        string $zonaHoraria,
        string $formatoTicket,
        string $nit = '',
        string $telefono = '',
        string $direccion = '',
        string $lema = '',
        ?float $anchoTicket58Mm = null,
        ?float $margenTicket58Mm = null,
        ?string $colorSecundario = null,
        ?string $impresoraRecibo = null,
        ?string $impresoraCocina = null,
        ?float $anchoTicket80Mm = null,
        ?float $margenTicket80Mm = null,
        ?float $margenTicketA4Mm = null,
        ?string $impresoraEtiquetas = null,
        ?float $anchoEtiquetaMm = null,
        ?float $altoEtiquetaMm = null
    ): bool {
        $formatoTicket = in_array($formatoTicket, ['a4', 'termica_58', 'termica_80'], true) ? $formatoTicket : 'a4';
        // Rango razonable de impresoras térmicas reales (mini 25mm a las de 112mm) — evita valores absurdos.
        $limitarAncho = function (?float $v): ?float {
            return $v !== null && ($v < 25 || $v > 120) ? null : $v;
        };
        // 0 = sin margen (algunas térmicas lo necesitan); 50mm ya es un margen absurdo para un ticket.
        $limitarMargen = function (?float $v): ?float {
            return $v !== null && ($v < 0 || $v > 50) ? null : $v;
        };
        // Etiquetas reales de rollo térmico van de ~20mm (joyería) a ~100mm (cajas) de lado.
        $limitarLadoEtiqueta = function (?float $v): ?float {
            return $v !== null && ($v < 15 || $v > 150) ? null : $v;
        };
        $anchoTicket58Mm = $limitarAncho($anchoTicket58Mm);
        $anchoTicket80Mm = $limitarAncho($anchoTicket80Mm);
        $margenTicket58Mm = $limitarMargen($margenTicket58Mm);
        $margenTicket80Mm = $limitarMargen($margenTicket80Mm);
        $margenTicketA4Mm = $limitarMargen($margenTicketA4Mm);
        $anchoEtiquetaMm = $limitarLadoEtiqueta($anchoEtiquetaMm);
        $altoEtiquetaMm = $limitarLadoEtiqueta($altoEtiquetaMm);
        if ($colorSecundario === null || !preg_match('/^#[0-9a-fA-F]{6}$/', $colorSecundario)) {
            $colorSecundario = '#f97316';
        }
        $pdo = Database::conexion();
        $stmt = $pdo->prepare(
            'UPDATE configuracion_negocio
             SET nombre_negocio = :nombre, color_primario = :color, color_secundario = :color_secundario,
                 moneda = :moneda, zona_horaria = :zona,
                 formato_ticket = :formato, nit = :nit, telefono = :telefono, direccion = :direccion, lema = :lema,
                 ancho_ticket_58_mm = :ancho58, margen_ticket_58_mm = :margen58,
                 ancho_ticket_80_mm = :ancho80, margen_ticket_80_mm = :margen80,
                 margen_ticket_a4_mm = :margena4,
                 impresora_recibo = :impresora_recibo, impresora_cocina = :impresora_cocina,
                 impresora_etiquetas = :impresora_etiquetas, ancho_etiqueta_mm = :ancho_etiqueta, alto_etiqueta_mm = :alto_etiqueta
             WHERE id = 1'
        );
        $resultado = $stmt->execute([
            'nombre' => $nombreNegocio,
            'color' => $colorPrimario,
            'color_secundario' => $colorSecundario,
            'moneda' => $moneda,
            'zona' => $zonaHoraria,
            'formato' => $formatoTicket,
            'nit' => $nit !== '' ? $nit : null,
            'telefono' => $telefono !== '' ? $telefono : null,
            'direccion' => $direccion !== '' ? $direccion : null,
            'lema' => $lema !== '' ? $lema : null,
            'ancho58' => $anchoTicket58Mm,
            'margen58' => $margenTicket58Mm,
            'ancho80' => $anchoTicket80Mm,
            'margen80' => $margenTicket80Mm,
            'margena4' => $margenTicketA4Mm,
            'impresora_recibo' => $impresoraRecibo !== '' ? $impresoraRecibo : null,
            'impresora_cocina' => $impresoraCocina !== '' ? $impresoraCocina : null,
            'impresora_etiquetas' => $impresoraEtiquetas !== null && $impresoraEtiquetas !== '' ? $impresoraEtiquetas : null,
            'ancho_etiqueta' => $anchoEtiquetaMm,
            'alto_etiqueta' => $altoEtiquetaMm,
        ]);
        self::$cache = null;

        return $resultado;
    }

    /** Logo de la APLICACIÓN (barra lateral, sobre el color del negocio). */
    public static function guardarLogo(array $archivo): string|false
    {
        return self::guardarImagenSubida($archivo, 'logo', 'logo_path', false);
    }

    /**
     * Logo de la FACTURA (lo que se imprime en papel). Aparte del de la aplicación porque
     * una térmica necesita otra imagen (sin fondo de color / en blanco y negro). Se le agrega
     * un número de versión a la ruta para que ni la pantalla ni la impresora reutilicen la
     * imagen anterior cuando se cambia por otra con el mismo nombre.
     */
    public static function guardarLogoFactura(array $archivo): string|false
    {
        return self::guardarImagenSubida($archivo, 'logo_factura', 'logo_factura_path', true);
    }

    /** Quita el logo propio de la factura: vuelve a usar el de la aplicación (o ninguno si se apagó "imprimir logo"). */
    public static function quitarLogoFactura(): void
    {
        foreach (glob(Database::directorioDatos() . '/uploads/logo_factura.*') ?: [] as $archivo) {
            @unlink($archivo);
        }
        Database::conexion()->exec('UPDATE configuracion_negocio SET logo_factura_path = NULL WHERE id = 1');
        self::$cache = null;
    }

    public static function actualizarImprimirLogoFactura(bool $imprimir): void
    {
        Database::conexion()->prepare('UPDATE configuracion_negocio SET imprimir_logo_factura = :v WHERE id = 1')
            ->execute(['v' => $imprimir ? 1 : 0]);
        self::$cache = null;
    }

    /** Ruta del logo que va en la factura: el propio de la factura, si no el de la aplicación; null si se pidió no imprimir logo. */
    public static function logoParaFactura(): ?string
    {
        $config = self::obtener();
        if (array_key_exists('imprimir_logo_factura', $config) && !(int) $config['imprimir_logo_factura']) {
            return null;
        }

        return ($config['logo_factura_path'] ?? null) ?: ($config['logo_path'] ?? null) ?: null;
    }

    private static function guardarImagenSubida(array $archivo, string $nombreBase, string $columna, bool $conVersion): string|false
    {
        if (($archivo['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return false;
        }
        if ($archivo['size'] > self::TAMANO_MAXIMO_LOGO) {
            return false;
        }

        $extension = strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION));
        if (!isset(self::EXTENSIONES_PERMITIDAS[$extension])) {
            return false;
        }

        $info = @getimagesize($archivo['tmp_name']);
        if ($info === false || $info['mime'] !== self::EXTENSIONES_PERMITIDAS[$extension]) {
            return false;
        }

        // El logo también va en la carpeta de datos del usuario (no en /app,
        // que se reemplaza en cada actualización).
        $directorio = Database::directorioDatos() . '/uploads';
        if (!is_dir($directorio)) {
            mkdir($directorio, 0755, true);
        }

        // Un solo archivo por logo: si antes había otro con distinta extensión, se retira.
        if ($conVersion) {
            foreach (glob("$directorio/$nombreBase.*") ?: [] as $anterior) {
                @unlink($anterior);
            }
        }
        $nombreArchivo = $nombreBase . '.' . $extension;
        if (!move_uploaded_file($archivo['tmp_name'], "$directorio/$nombreArchivo")) {
            return false;
        }

        $ruta = "/uploads/$nombreArchivo" . ($conVersion ? '?v=' . time() : '');
        $pdo = Database::conexion();
        $stmt = $pdo->prepare("UPDATE configuracion_negocio SET $columna = :ruta WHERE id = 1");
        $stmt->execute(['ruta' => $ruta]);
        self::$cache = null;

        return $ruta;
    }

    public static function ajustarColor(string $hex, float $porcentaje): string
    {
        $hex = ltrim($hex, '#');
        if (strlen($hex) !== 6 || !ctype_xdigit($hex)) {
            return '#4f46e5';
        }

        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));

        $objetivo = $porcentaje > 0 ? 255 : 0;
        $factor = abs($porcentaje);

        $r = (int) round($r + ($objetivo - $r) * $factor);
        $g = (int) round($g + ($objetivo - $g) * $factor);
        $b = (int) round($b + ($objetivo - $b) * $factor);

        return sprintf('#%02x%02x%02x', max(0, min(255, $r)), max(0, min(255, $g)), max(0, min(255, $b)));
    }
}
