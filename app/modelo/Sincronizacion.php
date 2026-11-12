<?php
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Licencia.php';
require_once __DIR__ . '/Auditoria.php';

/**
 * Sincronización entre cajas: varias instalaciones (registradoras) que comparten la misma
 * clave de licencia — mismo local o sedes distintas — viendo las mismas ventas, el mismo
 * inventario, el mismo catálogo y los mismos clientes entre sí, relayado por el servidor
 * central del proveedor. Ver docs/sincronizacion_cajas.md para el contrato completo con el
 * servidor.
 *
 * GARANTÍA: en una instalación de UN SOLO equipo (la inmensa mayoría) nada de esto hace nada
 * — sincronizacionActiva() es falso hasta que el servidor asigna un caja_codigo, así que
 * registrarEventoSaliente() es un no-op, las colas quedan vacías y el comportamiento es
 * idéntico al de siempre.
 *
 * Identidad: cada fila que puede viajar entre cajas (facturas, movimiento, productos,
 * categorias, clientes) tiene un par (origen_caja_codigo, origen_id_x) — la identidad de
 * nacimiento de esa fila, puesta una sola vez y para siempre. Sirve para "es la misma fila
 * real" sin necesitar una tabla de mapeo aparte.
 */
class Sincronizacion
{
    /** Un tipo de entidad por cada evento que puede viajar — ver docs/sincronizacion_cajas.md. */
    private const TIPOS_EN_ORDEN = [
        'categoria', 'producto', 'cliente',
        'venta', 'compra', 'perdida', 'devolucion_linea', 'venta_anulada', 'abono',
    ];

    /** Usuario "sistema" (ver migración 042) dueño de las facturas/movimiento que llegan de otra caja. */
    public const USUARIO_SISTEMA_ID = -1;

    // ------------------------------------------------------------------ identidad de esta caja

    public static function obtenerCajaCodigo(): ?string
    {
        $codigo = Licencia::datos()['caja_codigo'] ?? null;
        return is_string($codigo) && $codigo !== '' ? $codigo : null;
    }

    public static function sincronizacionActiva(): bool
    {
        return self::obtenerCajaCodigo() !== null;
    }

    /**
     * Si esta caja ya tiene código, lo devuelve sin tocar la red (es permanente, igual que
     * install_id). Si no, y la licencia está activada, se lo pide al servidor — es el único
     * que conoce todos los equipos de esa licencia y puede garantizar que no se repita, sin
     * que las cajas tengan que coordinarse entre sí. "Mejor esfuerzo": nunca lanza, en
     * cualquier fallo (red, licencia no activada, respuesta rara) devuelve null y se
     * reintenta solo en el próximo ciclo.
     */
    public static function asegurarCajaCodigo(): ?string
    {
        $actual = self::obtenerCajaCodigo();
        if ($actual !== null) {
            return $actual;
        }
        if (!Licencia::estaActivada()) {
            return null;
        }

        try {
            $respuesta = Licencia::llamarAutenticado('POST', 'sync_registrar_caja.php', [], 10);
        } catch (Throwable $e) {
            return null;
        }
        $codigo = $respuesta['caja_codigo'] ?? null;
        if (empty($respuesta['ok']) || !is_string($codigo) || $codigo === '') {
            return null;
        }

        Licencia::fusionarDatos(['caja_codigo' => $codigo]);
        self::backfillOrigenPropio($codigo);

        return $codigo;
    }

    /**
     * Todo lo que esta caja ya tenía creado ANTES de sincronizar (origen_caja_codigo NULL)
     * queda con identidad de origen propia — una sola vez, justo al recibir el código por
     * primera vez, antes de que se genere el primer evento saliente.
     */
    private static function backfillOrigenPropio(string $codigo): void
    {
        $pdo = Database::conexion();
        $pdo->beginTransaction();
        try {
            $pdo->prepare('UPDATE productos SET origen_caja_codigo = :c, origen_id_producto = id_producto WHERE origen_caja_codigo IS NULL')->execute(['c' => $codigo]);
            $pdo->prepare('UPDATE categorias SET origen_caja_codigo = :c, origen_id_categoria = id_categoria WHERE origen_caja_codigo IS NULL')->execute(['c' => $codigo]);
            $pdo->prepare('UPDATE clientes SET origen_caja_codigo = :c, origen_id_cliente = id_cliente WHERE origen_caja_codigo IS NULL')->execute(['c' => $codigo]);
            $pdo->prepare('UPDATE facturas SET origen_caja_codigo = :c, origen_id_factura = id_factura WHERE origen_caja_codigo IS NULL')->execute(['c' => $codigo]);
            $pdo->prepare('UPDATE movimiento SET origen_caja_codigo = :c, origen_id_movimiento = id_movimiento WHERE origen_caja_codigo IS NULL')->execute(['c' => $codigo]);
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Marca el origen propio de una fila recién creada/editada localmente — idempotente
     * (COALESCE: nunca pisa un origen que ya tuviera). No-op si esta caja no está
     * sincronizando (instalación de un solo equipo).
     */
    public static function marcarOrigenPropio(PDO $pdo, string $tabla, string $columnaId, string $columnaOrigenId, int $id): void
    {
        $codigo = self::obtenerCajaCodigo();
        if ($codigo === null) {
            return;
        }
        $pdo->prepare(
            "UPDATE $tabla SET origen_caja_codigo = COALESCE(origen_caja_codigo, :codigo),
                                $columnaOrigenId = COALESCE($columnaOrigenId, $columnaId)
             WHERE $columnaId = :id"
        )->execute(['codigo' => $codigo, 'id' => $id]);
    }

    /** El origen (caja, id local) de una fila ya marcada — para construir el payload de un evento saliente. */
    public static function origenDe(PDO $pdo, string $tabla, string $columnaId, string $columnaOrigenId, int $id): ?array
    {
        $stmt = $pdo->prepare("SELECT origen_caja_codigo, $columnaOrigenId AS origen_id FROM $tabla WHERE $columnaId = :id");
        $stmt->execute(['id' => $id]);
        $fila = $stmt->fetch();
        if (!$fila || $fila['origen_caja_codigo'] === null || $fila['origen_id'] === null) {
            return null;
        }
        return ['caja' => $fila['origen_caja_codigo'], 'id' => (int) $fila['origen_id']];
    }

    // ------------------------------------------------------------------ número de factura

    /** "Factura #" que se muestra — el id crudo en instalaciones de un solo equipo, "C2-000042" cuando hay más de una caja. */
    public static function numeroFactura(?string $cajaCodigo, ?int $origenId, int $idFacturaLocal): string
    {
        if ($cajaCodigo === null || $origenId === null) {
            return (string) $idFacturaLocal;
        }
        return $cajaCodigo . '-' . str_pad((string) $origenId, 6, '0', STR_PAD_LEFT);
    }

    // ------------------------------------------------------------------ outbox

    /**
     * Encola un evento para las demás cajas — se llama DENTRO de la misma transacción del
     * llamador, justo antes de su commit (si la operación hace rollback, el evento se va con
     * ella). No-op si esta caja no está sincronizando: costo cero en instalaciones normales.
     */
    public static function registrarEventoSaliente(PDO $pdo, string $tipo, int $entidadLocalId, array $payload): void
    {
        if (!self::sincronizacionActiva()) {
            return;
        }
        $pdo->prepare('INSERT INTO sync_eventos_salientes (tipo_entidad, entidad_local_id, payload) VALUES (:tipo, :id, :payload)')
            ->execute(['tipo' => $tipo, 'id' => $entidadLocalId, 'payload' => json_encode(['tipo' => $tipo, 'emitido_por' => self::obtenerCajaCodigo()] + $payload, JSON_UNESCAPED_UNICODE)]);
    }

    public static function eventosPendientes(int $limite = 200): array
    {
        $pdo = Database::conexion();
        $stmt = $pdo->prepare('SELECT id_evento, tipo_entidad, payload FROM sync_eventos_salientes WHERE enviado = 0 ORDER BY id_evento LIMIT :limite');
        $stmt->bindValue(':limite', max(1, $limite), PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public static function marcarEnviados(array $idsEventos): void
    {
        if (empty($idsEventos)) {
            return;
        }
        $pdo = Database::conexion();
        $marcador = implode(',', array_fill(0, count($idsEventos), '?'));
        $stmt = $pdo->prepare("UPDATE sync_eventos_salientes SET enviado = 1, enviado_en = datetime('now','localtime') WHERE id_evento IN ($marcador)");
        $stmt->execute(array_values($idsEventos));
    }

    public static function marcarFalloEnvio(array $idsEventos, string $error): void
    {
        if (empty($idsEventos)) {
            return;
        }
        $pdo = Database::conexion();
        $marcador = implode(',', array_fill(0, count($idsEventos), '?'));
        $stmt = $pdo->prepare("UPDATE sync_eventos_salientes SET intentos = intentos + 1, ultimo_error = ? WHERE id_evento IN ($marcador)");
        $stmt->execute(array_merge([$error], array_values($idsEventos)));
    }

    /** Lo que esta caja reporta de sí misma en cada ciclo (para diagnóstico del lado del servidor). */
    public static function estadoParaServidor(): array
    {
        $pdo = Database::conexion();
        return [
            'caja_codigo' => self::obtenerCajaCodigo(),
            'eventos_pendientes' => (int) $pdo->query('SELECT COUNT(*) FROM sync_eventos_salientes WHERE enviado = 0')->fetchColumn(),
            'eventos_diferidos' => (int) $pdo->query('SELECT COUNT(*) FROM sync_eventos_entrantes WHERE aplicado = 0')->fetchColumn(),
        ];
    }

    // ------------------------------------------------------------------ inbox / aplicar

    /**
     * Aplica eventos que llegaron de otras cajas. Acepta un array YA DECODIFICADO (mismo
     * contrato que ConfiguracionRemota::procesar()) — así se prueba sin HTTP ni servidor real.
     * Nunca lanza: un evento que no se puede aplicar todavía (ej. depende de un producto que
     * aún no llegó) queda diferido para reintentarlo en la próxima llamada, nunca se descarta.
     *
     * @return array{aplicados: int, diferidos: int, descartados: int, advertencias: string[]}
     */
    public static function aplicarEventosEntrantes(array $eventosCrudos): array
    {
        $pdo = Database::conexion();
        $resultado = ['aplicados' => 0, 'diferidos' => 0, 'descartados' => 0, 'advertencias' => []];

        $stmtInsertar = $pdo->prepare(
            'INSERT OR IGNORE INTO sync_eventos_entrantes (caja_origen, tipo_entidad, entidad_local_id, payload)
             VALUES (:caja, :tipo, :id, :payload)'
        );
        foreach ($eventosCrudos as $evento) {
            if (!is_array($evento) || !isset($evento['tipo'], $evento['caja_origen']) || !is_string($evento['tipo']) || !is_string($evento['caja_origen'])) {
                $resultado['descartados']++;
                $resultado['advertencias'][] = 'evento sin "tipo"/"caja_origen" válidos: se descarta.';
                continue;
            }
            $idLocal = self::entidadLocalIdDe($evento);
            if ($idLocal === null) {
                $resultado['descartados']++;
                $resultado['advertencias'][] = "evento tipo \"{$evento['tipo']}\" sin id de origen reconocible: se descarta.";
                continue;
            }
            // Nunca aplicar un evento que ESTA misma caja generó (si el servidor
            // alguna vez lo hiciera eco por error, se ignora silenciosamente).
            // "emitido_por" = caja que generó el evento; "caja_origen" puede ser otra (una anulación hecha
            // en C2 sobre una factura de C1 lleva caja_origen C1 y debe llegar a C1).
            if (($evento['emitido_por'] ?? $evento['caja_origen']) === self::obtenerCajaCodigo()) {
                continue;
            }
            $payloadJson = json_encode($evento, JSON_UNESCAPED_UNICODE);
            $stmtInsertar->execute([
                'caja' => $evento['caja_origen'], 'tipo' => $evento['tipo'], 'id' => $idLocal, 'payload' => $payloadJson,
            ]);
            // Catálogo/clientes se editan muchas veces: la clave (caja, tipo, id) ya la ocupa el evento
            // anterior de esa misma fila (por ej. su creación), así que el INSERT OR IGNORE de arriba
            // descartaría toda edición posterior. Si llega una versión MÁS NUEVA de la misma fila, reemplaza
            // a la anterior y vuelve a quedar pendiente de aplicar.
            if ($stmtInsertar->rowCount() === 0 && in_array($evento['tipo'], ['producto', 'categoria', 'cliente'], true)) {
                $previo = $pdo->prepare('SELECT id, payload FROM sync_eventos_entrantes WHERE caja_origen = :c AND tipo_entidad = :t AND entidad_local_id = :i');
                $previo->execute(['c' => $evento['caja_origen'], 't' => $evento['tipo'], 'i' => $idLocal]);
                $fila = $previo->fetch();
                $versionPrevia = $fila ? (json_decode($fila['payload'], true)['actualizado_en'] ?? null) : null;
                if ($fila && $fila['payload'] !== $payloadJson && self::masReciente($evento['actualizado_en'] ?? null, $versionPrevia)) {
                    $pdo->prepare('UPDATE sync_eventos_entrantes SET payload = :p, aplicado = 0, aplicado_en = NULL, intentos = 0 WHERE id = :id')
                        ->execute(['p' => $payloadJson, 'id' => $fila['id']]);
                }
            }
        }

        foreach (self::TIPOS_EN_ORDEN as $tipo) {
            $stmt = $pdo->prepare('SELECT id, payload FROM sync_eventos_entrantes WHERE aplicado = 0 AND tipo_entidad = :tipo ORDER BY id');
            $stmt->execute(['tipo' => $tipo]);
            foreach ($stmt->fetchAll() as $fila) {
                $evento = json_decode($fila['payload'], true);
                try {
                    $ok = self::despachar($tipo, $evento);
                } catch (Throwable $e) {
                    $ok = false;
                    $resultado['advertencias'][] = "error aplicando evento #{$fila['id']} ($tipo): " . $e->getMessage();
                }
                if ($ok) {
                    $pdo->prepare("UPDATE sync_eventos_entrantes SET aplicado = 1, aplicado_en = datetime('now','localtime') WHERE id = :id")
                        ->execute(['id' => $fila['id']]);
                    $resultado['aplicados']++;
                } else {
                    $pdo->prepare('UPDATE sync_eventos_entrantes SET intentos = intentos + 1 WHERE id = :id')->execute(['id' => $fila['id']]);
                    $resultado['diferidos']++;
                }
            }
        }

        return $resultado;
    }

    private static function entidadLocalIdDe(array $evento): ?int
    {
        $campo = [
            'categoria' => 'id_categoria_origen', 'producto' => 'id_producto_origen', 'cliente' => 'id_cliente_origen',
            'venta' => 'id_factura_origen', 'compra' => 'id_factura_origen', 'perdida' => 'id_factura_origen',
            'venta_anulada' => 'id_factura_origen', 'abono' => 'id_factura_origen',
            'devolucion_linea' => 'id_factura_devolucion_origen',
        ][$evento['tipo']] ?? null;
        if ($campo === null || !isset($evento[$campo]) || !is_int($evento[$campo]) && !ctype_digit((string) $evento[$campo])) {
            return null;
        }

        return (int) $evento[$campo];
    }

    private static function despachar(string $tipo, array $e): bool
    {
        switch ($tipo) {
            case 'categoria': return self::aplicarCategoria($e);
            case 'producto': return self::aplicarProducto($e);
            case 'cliente': return self::aplicarCliente($e);
            case 'venta': return self::aplicarVenta($e);
            case 'venta_anulada': return self::aplicarVentaAnulada($e);
            case 'devolucion_linea': return self::aplicarDevolucionLinea($e);
            case 'abono': return self::aplicarAbono($e);
            case 'compra': return self::aplicarCompra($e);
            case 'perdida': return self::aplicarPerdida($e);
            default: return false;
        }
    }

    /** La fila local (o null) cuyo origen sea exactamente ese — o null si $origen mismo es null (sin referencia). */
    private static function resolverLocal(string $tabla, string $columnaId, string $columnaOrigenId, ?array $origen): ?int
    {
        if ($origen === null || !isset($origen['caja'], $origen['id'])) {
            return null;
        }
        $pdo = Database::conexion();
        $stmt = $pdo->prepare("SELECT $columnaId FROM $tabla WHERE origen_caja_codigo = :c AND $columnaOrigenId = :i");
        $stmt->execute(['c' => $origen['caja'], 'i' => (int) $origen['id']]);
        $valor = $stmt->fetchColumn();

        return $valor !== false ? (int) $valor : null;
    }

    /** true si $ref no es null (se pidió resolver algo) pero no se pudo resolver todavía — el evento debe diferirse. */
    private static function faltaResolver(?array $ref, ?int $resuelto): bool
    {
        return $ref !== null && $resuelto === null;
    }

    // ---------------------------------------------------------------- catálogo (last-write-wins)

    private static function aplicarCategoria(array $e): bool
    {
        $pdo = Database::conexion();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('SELECT id_categoria, actualizado_en FROM categorias WHERE origen_caja_codigo = :c AND origen_id_categoria = :i');
            $stmt->execute(['c' => $e['caja_origen'], 'i' => (int) $e['id_categoria_origen']]);
            $local = $stmt->fetch();

            if ($local && !self::masReciente($e['actualizado_en'] ?? null, $local['actualizado_en'])) {
                $pdo->commit();
                return true; // lo que hay local ya es igual o más nuevo: no hace falta hacer nada, no es un error
            }

            if ($local) {
                $pdo->prepare('UPDATE categorias SET descripcion = :d, estado = :e, actualizado_en = :a WHERE id_categoria = :id')
                    ->execute(['d' => $e['descripcion'], 'e' => (int) $e['estado'], 'a' => $e['actualizado_en'] ?? null, 'id' => $local['id_categoria']]);
            } else {
                $pdo->prepare(
                    'INSERT INTO categorias (descripcion, estado, origen_caja_codigo, origen_id_categoria, actualizado_en)
                     VALUES (:d, :e, :caja, :oid, :a)'
                )->execute([
                    'd' => $e['descripcion'], 'e' => (int) $e['estado'],
                    'caja' => $e['caja_origen'], 'oid' => (int) $e['id_categoria_origen'], 'a' => $e['actualizado_en'] ?? null,
                ]);
            }
            $pdo->commit();
            return true;
        } catch (Throwable $ex) {
            $pdo->rollBack();
            throw $ex;
        }
    }

    private static function aplicarProducto(array $e): bool
    {
        $categoriaLocal = self::resolverLocal('categorias', 'id_categoria', 'origen_id_categoria', $e['categoria'] ?? null);
        if (self::faltaResolver($e['categoria'] ?? null, $categoriaLocal)) {
            return false;
        }

        $pdo = Database::conexion();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('SELECT id_producto, actualizado_en FROM productos WHERE origen_caja_codigo = :c AND origen_id_producto = :i');
            $stmt->execute(['c' => $e['caja_origen'], 'i' => (int) $e['id_producto_origen']]);
            $local = $stmt->fetch();

            if ($local && !self::masReciente($e['actualizado_en'] ?? null, $local['actualizado_en'])) {
                $pdo->commit();
                return true;
            }

            $campos = [
                'nombre' => $e['nombre_producto'], 'codigo' => $e['codigo'] ?? null,
                'categoria' => $categoriaLocal, 'precio' => (float) $e['precio_unitario'],
                'minimo' => (int) ($e['minimo_stock'] ?? 0), 'controla' => !empty($e['controla_stock']) ? 1 : 0,
                'estado' => (int) ($e['estado'] ?? 1), 'upp' => max(1, (int) ($e['unidades_por_paquete'] ?? 1)),
                'pp' => $e['precio_paquete'] ?? null, 'costo' => (float) ($e['costo_unitario'] ?? 0),
                'iva' => (float) ($e['tasa_iva'] ?? 0), 'combo' => !empty($e['es_combo']) ? 1 : 0,
                'peso' => !empty($e['se_vende_por_peso']) ? 1 : 0, 'a' => $e['actualizado_en'] ?? null,
            ];

            if ($local) {
                $pdo->prepare(
                    'UPDATE productos SET nombre_producto=:nombre, codigo=:codigo, categoria=:categoria,
                        precio_unitario=:precio, minimo_stock=:minimo, controla_stock=:controla, estado=:estado,
                        unidades_por_paquete=:upp, precio_paquete=:pp, costo_unitario=:costo, tasa_iva=:iva,
                        es_combo=:combo, se_vende_por_peso=:peso, actualizado_en=:a
                     WHERE id_producto = :id'
                )->execute($campos + ['id' => $local['id_producto']]);
            } else {
                // El stock inicial solo viene en el evento de CREACIÓN (para que todas las cajas
                // arranquen con el mismo número); desde ahí solo se mueve por deltas (venta/compra/
                // pérdida/devolución), nunca por un snapshot de catálogo.
                $pdo->prepare(
                    'INSERT INTO productos
                        (nombre_producto, codigo, categoria, precio_unitario, cantidad_existente, minimo_stock,
                         controla_stock, estado, unidades_por_paquete, precio_paquete, costo_unitario, tasa_iva,
                         es_combo, se_vende_por_peso, origen_caja_codigo, origen_id_producto, actualizado_en)
                     VALUES
                        (:nombre, :codigo, :categoria, :precio, :stock, :minimo,
                         :controla, :estado, :upp, :pp, :costo, :iva,
                         :combo, :peso, :caja, :oid, :a)'
                )->execute($campos + ['caja' => $e['caja_origen'], 'oid' => (int) $e['id_producto_origen'], 'stock' => (int) ($e['stock_inicial'] ?? 0)]);
            }
            $pdo->commit();
            return true;
        } catch (Throwable $ex) {
            $pdo->rollBack();
            throw $ex;
        }
    }

    private static function aplicarCliente(array $e): bool
    {
        $pdo = Database::conexion();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('SELECT id_cliente, actualizado_en FROM clientes WHERE origen_caja_codigo = :c AND origen_id_cliente = :i');
            $stmt->execute(['c' => $e['caja_origen'], 'i' => (int) $e['id_cliente_origen']]);
            $local = $stmt->fetch();

            if ($local && !self::masReciente($e['actualizado_en'] ?? null, $local['actualizado_en'])) {
                $pdo->commit();
                return true;
            }

            $campos = [
                'nombre' => $e['nombre'], 'identificacion' => $e['identificacion'] ?? null,
                'telefono' => $e['telefono'] ?? null, 'direccion' => $e['direccion'] ?? null,
                'limite' => (float) ($e['limite_credito'] ?? 0), 'estado' => (int) ($e['estado'] ?? 1),
                'a' => $e['actualizado_en'] ?? null,
            ];

            if ($local) {
                $pdo->prepare(
                    'UPDATE clientes SET nombre=:nombre, identificacion=:identificacion, telefono=:telefono,
                        direccion=:direccion, limite_credito=:limite, estado=:estado, actualizado_en=:a
                     WHERE id_cliente = :id'
                )->execute($campos + ['id' => $local['id_cliente']]);
            } else {
                $pdo->prepare(
                    'INSERT INTO clientes (nombre, identificacion, telefono, direccion, limite_credito, estado,
                        origen_caja_codigo, origen_id_cliente, actualizado_en)
                     VALUES (:nombre, :identificacion, :telefono, :direccion, :limite, :estado, :caja, :oid, :a)'
                )->execute($campos + ['caja' => $e['caja_origen'], 'oid' => (int) $e['id_cliente_origen']]);
            }
            $pdo->commit();
            return true;
        } catch (Throwable $ex) {
            $pdo->rollBack();
            throw $ex;
        }
    }

    private static function masReciente(?string $entrante, ?string $local): bool
    {
        if ($entrante === null) {
            return false;
        }
        if ($local === null) {
            return true;
        }
        // Empate (mismo segundo): gana el que llega. Es el caso normal de "creé y edité enseguida" en
        // la misma caja (los eventos de una caja llegan en orden); el empate exacto entre DOS cajas
        // distintas editando la misma fila en el mismo segundo es una limitación aceptada de la v1.
        return strtotime($entrante) >= strtotime($local);
    }

    // ---------------------------------------------------------------- ventas / inventario (solo se agregan)

    private static function aplicarVenta(array $e): bool
    {
        if (!isset($e['fecha'], $e['hora'], $e['total'], $e['id_factura_origen']) || !is_array($e['lineas'] ?? null)) {
            return false;
        }
        $clienteLocal = self::resolverLocal('clientes', 'id_cliente', 'origen_id_cliente', $e['cliente'] ?? null);
        if (self::faltaResolver($e['cliente'] ?? null, $clienteLocal)) {
            return false;
        }
        foreach ($e['lineas'] as $linea) {
            $prodLocal = self::resolverLocal('productos', 'id_producto', 'origen_id_producto', $linea['producto'] ?? null);
            if (self::faltaResolver($linea['producto'] ?? null, $prodLocal)) {
                return false;
            }
            foreach ($linea['combo_detalle'] ?? [] as $comp) {
                if (self::faltaResolver($comp['producto'] ?? null, self::resolverLocal('productos', 'id_producto', 'origen_id_producto', $comp['producto'] ?? null))) {
                    return false;
                }
            }
            foreach ($linea['modificadores'] ?? [] as $mod) {
                if (self::faltaResolver($mod['producto'] ?? null, self::resolverLocal('productos', 'id_producto', 'origen_id_producto', $mod['producto'] ?? null))) {
                    return false;
                }
            }
        }

        $pdo = Database::conexion();
        $pdo->beginTransaction();
        try {
            $pdo->prepare(
                'INSERT INTO facturas (fecha, hora, usuario_id, tipo_transaccion, total, ajuste, propina, metodo_pago,
                    cliente_id, origen_caja_codigo, origen_id_factura, usuario_origen_nombre)
                 VALUES (:fecha, :hora, :usuario, 1, :total, :ajuste, :propina, :metodo, :cliente, :caja, :oid, :nombre)'
            )->execute([
                'fecha' => $e['fecha'], 'hora' => $e['hora'], 'usuario' => self::USUARIO_SISTEMA_ID,
                'total' => (float) $e['total'], 'ajuste' => (float) ($e['ajuste'] ?? 0), 'propina' => (float) ($e['propina'] ?? 0),
                'metodo' => $e['metodo_pago'] ?? 'efectivo', 'cliente' => $clienteLocal,
                'caja' => $e['caja_origen'], 'oid' => (int) $e['id_factura_origen'], 'nombre' => $e['usuario_origen_nombre'] ?? null,
            ]);
            $idFactura = (int) $pdo->lastInsertId();

            foreach ($e['pagos'] ?? [] as $pago) {
                $pdo->prepare('INSERT INTO factura_pagos (id_factura, metodo_pago, valor) VALUES (:f, :m, :v)')
                    ->execute(['f' => $idFactura, 'm' => $pago['metodo_pago'], 'v' => (float) $pago['valor']]);
            }

            foreach ($e['lineas'] as $linea) {
                $prodLocal = self::resolverLocal('productos', 'id_producto', 'origen_id_producto', $linea['producto'] ?? null);
                $pdo->prepare(
                    'INSERT INTO movimiento
                        (id_factura, usuario_id, tipo_transaccion, fecha, hora, producto_id, presentacion,
                         unidades_por_paquete, cantidad, unidades_totales, afecto_stock, precio_unitario,
                         costo_unitario, tasa_iva, valor_iva, total, descuento_linea, peso_kg, descripcion,
                         profesional_origen_nombre, comision_porcentaje, comision_valor,
                         origen_caja_codigo, origen_id_movimiento)
                     VALUES
                        (:idf, :usuario, 1, :fecha, :hora, :producto, :presentacion,
                         :upp, :cantidad, :unidades_totales, :afecto, :precio,
                         :costo, :iva, :valor_iva, :total, :descuento, :peso, :descripcion,
                         :prof_nombre, :com_pct, :com_val,
                         :caja, :oid)'
                )->execute([
                    'idf' => $idFactura, 'usuario' => self::USUARIO_SISTEMA_ID, 'fecha' => $e['fecha'], 'hora' => $e['hora'],
                    'producto' => $prodLocal, 'presentacion' => $linea['presentacion'], 'upp' => (int) $linea['unidades_por_paquete'],
                    'cantidad' => $linea['cantidad'], 'unidades_totales' => $linea['unidades_totales'],
                    'afecto' => !empty($linea['afecto_stock']) ? 1 : 0, 'precio' => (float) $linea['precio_unitario'],
                    'costo' => (float) ($linea['costo_unitario'] ?? 0), 'iva' => (float) ($linea['tasa_iva'] ?? 0),
                    'valor_iva' => (float) ($linea['valor_iva'] ?? 0), 'total' => (float) $linea['total'],
                    'descuento' => (float) ($linea['descuento_linea'] ?? 0), 'peso' => $linea['peso_kg'] ?? null,
                    'descripcion' => $linea['descripcion'] ?? '', 'prof_nombre' => $linea['profesional_origen_nombre'] ?? null,
                    'com_pct' => (float) ($linea['comision_porcentaje'] ?? 0), 'com_val' => (float) ($linea['comision_valor'] ?? 0),
                    'caja' => $e['caja_origen'], 'oid' => (int) $linea['id_movimiento_origen'],
                ]);
                $idMovimiento = (int) $pdo->lastInsertId();

                if (!empty($linea['afecto_stock']) && $prodLocal !== null) {
                    self::aplicarDeltaStock($pdo, $prodLocal, -(float) $linea['unidades_totales'], 'venta', $linea);
                }

                foreach ($linea['combo_detalle'] ?? [] as $comp) {
                    $compLocal = self::resolverLocal('productos', 'id_producto', 'origen_id_producto', $comp['producto']);
                    $pdo->prepare('INSERT INTO movimiento_combo_detalle (id_movimiento, producto_id, unidades_descontadas) VALUES (:m, :p, :u)')
                        ->execute(['m' => $idMovimiento, 'p' => $compLocal, 'u' => (int) $comp['unidades_descontadas']]);
                    self::aplicarDeltaStock($pdo, $compLocal, -(float) $comp['unidades_descontadas'], 'venta_combo', $comp);
                }

                foreach ($linea['modificadores'] ?? [] as $mod) {
                    $modProdLocal = self::resolverLocal('productos', 'id_producto', 'origen_id_producto', $mod['producto'] ?? null);
                    $pdo->prepare(
                        'INSERT INTO movimiento_modificadores
                            (id_movimiento, producto_id, nombre_snapshot, precio_cobrado, incluido, afecto_stock, unidades_descontadas)
                         VALUES (:m, :p, :n, :precio, :incluido, :afecto, :u)'
                    )->execute([
                        'm' => $idMovimiento, 'p' => $modProdLocal, 'n' => $mod['nombre_snapshot'],
                        'precio' => (float) $mod['precio_cobrado'], 'incluido' => !empty($mod['incluido']) ? 1 : 0,
                        'afecto' => !empty($mod['afecto_stock']) ? 1 : 0, 'u' => (int) ($mod['unidades_descontadas'] ?? 0),
                    ]);
                    if (!empty($mod['afecto_stock']) && $modProdLocal !== null) {
                        self::aplicarDeltaStock($pdo, $modProdLocal, -(float) $mod['unidades_descontadas'], 'venta_modificador', $mod);
                    }
                }
            }

            $pdo->commit();
            return true;
        } catch (Throwable $ex) {
            $pdo->rollBack();
            throw $ex;
        }
    }

    /**
     * Aplica un cambio de stock por DELTA (nunca valor absoluto) — así dos cajas vendiendo
     * casi al mismo tiempo, sin haber sincronizado entre medio, no se pisan entre sí. Si el
     * resultado queda negativo se aplica igual (la venta ya pasó físicamente en la otra caja;
     * rechazarla perdería el dato de la venta, que es peor) y queda un rastro de auditoría.
     */
    private static function aplicarDeltaStock(PDO $pdo, ?int $idProductoLocal, float $delta, string $contexto, array $detalleEvento): void
    {
        if ($idProductoLocal === null || $delta == 0.0) {
            return;
        }
        $pdo->prepare('UPDATE productos SET cantidad_existente = cantidad_existente + :delta WHERE id_producto = :id')
            ->execute(['delta' => $delta, 'id' => $idProductoLocal]);

        $resultante = $pdo->query('SELECT cantidad_existente FROM productos WHERE id_producto = ' . (int) $idProductoLocal)->fetchColumn();
        if ((float) $resultante < 0) {
            try {
                Auditoria::registrar(null, 'sync.stock_negativo', 'productos', $idProductoLocal, [
                    'contexto' => $contexto, 'delta' => $delta, 'resultante' => (float) $resultante, 'evento' => $detalleEvento,
                ]);
            } catch (Throwable $e) {
                // una auditoría que no se pudo guardar nunca debe tumbar la sincronización.
            }
        }
    }

    private static function aplicarVentaAnulada(array $e): bool
    {
        $idFacturaLocal = self::resolverLocal('facturas', 'id_factura', 'origen_id_factura', ['caja' => $e['caja_origen'], 'id' => $e['id_factura_origen']]);
        if ($idFacturaLocal === null) {
            return false; // la venta original todavía no llegó: se difiere
        }

        $pdo = Database::conexion();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('SELECT anulada FROM facturas WHERE id_factura = :id');
            $stmt->execute(['id' => $idFacturaLocal]);
            if ((int) $stmt->fetchColumn() === 1) {
                $pdo->commit();
                return true; // ya estaba anulada (evento repetido o aplicado por otra vía): no-op
            }

            // Líneas que la caja que anuló decidió descartar (no vuelven a existencias): se resuelven por origen.
            $descartadas = [];
            foreach ($e['lineas_descartadas'] ?? [] as $ref) {
                $idLocal = self::resolverLocal('movimiento', 'id_movimiento', 'origen_id_movimiento', $ref);
                if ($idLocal !== null) {
                    $descartadas[$idLocal] = true;
                }
            }

            $lineas = $pdo->prepare('SELECT id_movimiento, producto_id, cantidad, cantidad_devuelta, unidades_totales, afecto_stock FROM movimiento WHERE id_factura = :id');
            $lineas->execute(['id' => $idFacturaLocal]);
            $marcarDestino = $pdo->prepare('UPDATE movimiento SET anulacion_destino = :d WHERE id_movimiento = :id');
            foreach ($lineas->fetchAll() as $linea) {
                $idLinea = (int) $linea['id_movimiento'];
                $cantidadLinea = (float) $linea['cantidad'];
                $factor = $cantidadLinea > 0 ? max(0.0, ($cantidadLinea - (float) $linea['cantidad_devuelta']) / $cantidadLinea) : 1.0;
                $afecta = (int) $linea['afecto_stock'] === 1 && $linea['producto_id'] !== null;

                if ($afecta && $factor > 0) {
                    if (isset($descartadas[$idLinea])) {
                        $marcarDestino->execute(['d' => 'descartado', 'id' => $idLinea]);
                        continue; // la pérdida llega como su propio evento "perdida"
                    }
                    $marcarDestino->execute(['d' => 'inventario', 'id' => $idLinea]);
                    self::aplicarDeltaStock($pdo, (int) $linea['producto_id'], round((float) $linea['unidades_totales'] * $factor, 3), 'venta_anulada', $e);
                }
                $combo = $pdo->prepare('SELECT producto_id, unidades_descontadas FROM movimiento_combo_detalle WHERE id_movimiento = :id');
                $combo->execute(['id' => $idLinea]);
                foreach ($combo->fetchAll() as $c) {
                    self::aplicarDeltaStock($pdo, (int) $c['producto_id'], round((float) $c['unidades_descontadas'] * $factor, 3), 'venta_anulada_combo', $e);
                }
                $mods = $pdo->prepare('SELECT producto_id, unidades_descontadas FROM movimiento_modificadores WHERE id_movimiento = :id AND afecto_stock = 1');
                $mods->execute(['id' => $idLinea]);
                foreach ($mods->fetchAll() as $m) {
                    self::aplicarDeltaStock($pdo, (int) $m['producto_id'], round((float) $m['unidades_descontadas'] * $factor, 3), 'venta_anulada_modificador', $e);
                }
            }

            $pdo->prepare(
                "UPDATE facturas SET anulada = 1, anulada_motivo = :motivo, anulada_en = datetime('now','localtime')
                 WHERE id_factura = :id"
            )->execute(['motivo' => $e['motivo'] ?? '', 'id' => $idFacturaLocal]);

            $pdo->commit();
            return true;
        } catch (Throwable $ex) {
            $pdo->rollBack();
            throw $ex;
        }
    }

    private static function aplicarDevolucionLinea(array $e): bool
    {
        // OJO: la línea original devuelta puede ser de OTRA caja distinta a la que hace la
        // devolución (ej. venden en la caja A, devuelven en la caja B) — por eso viaja como su
        // propia referencia {caja,id} en "movimiento_devuelto", nunca asumiendo caja_origen.
        $movimientoLocal = self::resolverLocal('movimiento', 'id_movimiento', 'origen_id_movimiento', $e['movimiento_devuelto'] ?? null);
        if ($movimientoLocal === null) {
            return false; // la línea original todavía no llegó: se difiere
        }
        $prodLocal = self::resolverLocal('productos', 'id_producto', 'origen_id_producto', $e['producto'] ?? null);
        if (self::faltaResolver($e['producto'] ?? null, $prodLocal)) {
            return false;
        }

        $pdo = Database::conexion();
        $pdo->beginTransaction();
        try {
            $pdo->prepare(
                'INSERT INTO facturas (fecha, hora, usuario_id, tipo_transaccion, total, origen_caja_codigo, origen_id_factura)
                 VALUES (:fecha, :hora, :usuario, 6, :total, :caja, :oid)'
            )->execute([
                'fecha' => $e['fecha'], 'hora' => $e['hora'], 'usuario' => self::USUARIO_SISTEMA_ID, 'total' => (float) $e['total'],
                'caja' => $e['caja_origen'], 'oid' => (int) $e['id_factura_devolucion_origen'],
            ]);
            $idFacturaDevolucion = (int) $pdo->lastInsertId();

            $pdo->prepare(
                'INSERT INTO movimiento
                    (id_factura, usuario_id, tipo_transaccion, fecha, hora, producto_id, presentacion,
                     unidades_por_paquete, cantidad, unidades_totales, afecto_stock, precio_unitario,
                     costo_unitario, tasa_iva, valor_iva, total, peso_kg, descripcion, movimiento_origen_id)
                 VALUES (:idf, :usuario, 6, :fecha, :hora, :producto, :presentacion,
                         :upp, :cantidad, :unidades_totales, :afecto, :precio,
                         :costo, :iva, :valor_iva, :total, :peso, :descripcion, :origen)'
            )->execute([
                'idf' => $idFacturaDevolucion, 'usuario' => self::USUARIO_SISTEMA_ID, 'fecha' => $e['fecha'], 'hora' => $e['hora'],
                'producto' => $prodLocal, 'presentacion' => $e['presentacion'], 'upp' => (int) $e['unidades_por_paquete'],
                'cantidad' => $e['cantidad'], 'unidades_totales' => $e['unidades_totales'], 'afecto' => !empty($e['afecto_stock']) ? 1 : 0,
                'precio' => (float) $e['precio_unitario'], 'costo' => (float) ($e['costo_unitario'] ?? 0),
                'iva' => (float) ($e['tasa_iva'] ?? 0), 'valor_iva' => (float) ($e['valor_iva'] ?? 0), 'total' => (float) $e['total'],
                'peso' => $e['peso_kg'] ?? null, 'descripcion' => $e['motivo'] ?? '', 'origen' => $movimientoLocal,
            ]);

            if (!empty($e['afecto_stock']) && $prodLocal !== null) {
                self::aplicarDeltaStock($pdo, $prodLocal, (float) $e['unidades_totales'], 'devolucion', $e);
            }

            $pdo->prepare('UPDATE movimiento SET cantidad_devuelta = cantidad_devuelta + :cantidad WHERE id_movimiento = :id')
                ->execute(['cantidad' => $e['cantidad'], 'id' => $movimientoLocal]);

            $pdo->commit();
            return true;
        } catch (Throwable $ex) {
            $pdo->rollBack();
            throw $ex;
        }
    }

    private static function aplicarAbono(array $e): bool
    {
        $clienteLocal = self::resolverLocal('clientes', 'id_cliente', 'origen_id_cliente', $e['cliente'] ?? null);
        if (self::faltaResolver($e['cliente'] ?? null, $clienteLocal) || $clienteLocal === null) {
            return false; // un abono siempre necesita cliente
        }

        $pdo = Database::conexion();
        $pdo->beginTransaction();
        try {
            $pdo->prepare(
                'INSERT INTO facturas (fecha, hora, usuario_id, tipo_transaccion, total, cliente_id, origen_caja_codigo, origen_id_factura)
                 VALUES (:fecha, :hora, :usuario, 5, :total, :cliente, :caja, :oid)'
            )->execute([
                'fecha' => $e['fecha'], 'hora' => $e['hora'], 'usuario' => self::USUARIO_SISTEMA_ID, 'total' => (float) $e['valor'],
                'cliente' => $clienteLocal, 'caja' => $e['caja_origen'], 'oid' => (int) $e['id_factura_origen'],
            ]);
            $idFactura = (int) $pdo->lastInsertId();

            $pdo->prepare(
                'INSERT INTO movimiento (id_factura, usuario_id, tipo_transaccion, fecha, hora, producto_id, cantidad, precio_unitario, total, descripcion)
                 VALUES (:idf, :usuario, 5, :fecha, :hora, NULL, 0, 0, :total, :descripcion)'
            )->execute([
                'idf' => $idFactura, 'usuario' => self::USUARIO_SISTEMA_ID, 'fecha' => $e['fecha'], 'hora' => $e['hora'],
                'total' => (float) $e['valor'], 'descripcion' => $e['descripcion'] ?? '',
            ]);

            $pdo->commit();
            return true;
        } catch (Throwable $ex) {
            $pdo->rollBack();
            throw $ex;
        }
    }

    private static function aplicarCompra(array $e): bool
    {
        return self::aplicarMovimientoInventario($e, 3, 'compra', (float) $e['unidades_totales'], (float) $e['costo_total']);
    }

    private static function aplicarPerdida(array $e): bool
    {
        // Un descarte por anulación de venta viaja con afecto_stock=false: es solo el registro de la pérdida.
        $mueveStock = ($e['afecto_stock'] ?? true) !== false;
        return self::aplicarMovimientoInventario($e, 4, 'perdida', $mueveStock ? -(float) $e['unidades_totales'] : 0.0, (float) $e['valor_estimado'], $mueveStock);
    }

    private static function aplicarMovimientoInventario(array $e, int $tipoTransaccion, string $contexto, float $deltaUnidades, float $totalFactura, bool $afectaStock = true): bool
    {
        $prodLocal = self::resolverLocal('productos', 'id_producto', 'origen_id_producto', $e['producto'] ?? null);
        if (self::faltaResolver($e['producto'] ?? null, $prodLocal) || $prodLocal === null) {
            return false;
        }

        $pdo = Database::conexion();
        $pdo->beginTransaction();
        try {
            $pdo->prepare(
                'INSERT INTO facturas (fecha, hora, usuario_id, tipo_transaccion, total, origen_caja_codigo, origen_id_factura)
                 VALUES (:fecha, :hora, :usuario, :tipo, :total, :caja, :oid)'
            )->execute([
                'fecha' => $e['fecha'], 'hora' => $e['hora'], 'usuario' => self::USUARIO_SISTEMA_ID, 'tipo' => $tipoTransaccion,
                'total' => $totalFactura, 'caja' => $e['caja_origen'], 'oid' => (int) $e['id_factura_origen'],
            ]);
            $idFactura = (int) $pdo->lastInsertId();

            $pdo->prepare(
                'INSERT INTO movimiento
                    (id_factura, usuario_id, tipo_transaccion, fecha, hora, producto_id, presentacion,
                     cantidad, unidades_totales, precio_unitario, total, descripcion, afecto_stock)
                 VALUES (:idf, :usuario, :tipo, :fecha, :hora, :producto, :presentacion,
                         :cantidad, :unidades_totales, 0, :total, :descripcion, :afecta)'
            )->execute([
                'afecta' => $afectaStock ? 1 : 0,
                'idf' => $idFactura, 'usuario' => self::USUARIO_SISTEMA_ID, 'tipo' => $tipoTransaccion, 'fecha' => $e['fecha'], 'hora' => $e['hora'],
                'producto' => $prodLocal, 'presentacion' => $e['presentacion'], 'cantidad' => $e['cantidad'],
                'unidades_totales' => $e['unidades_totales'], 'total' => $totalFactura, 'descripcion' => $e['descripcion'] ?? "Sincronizado ($contexto)",
            ]);

            if ($afectaStock) {
                self::aplicarDeltaStock($pdo, $prodLocal, $deltaUnidades, $contexto, $e);
            }

            $pdo->commit();
            return true;
        } catch (Throwable $ex) {
            $pdo->rollBack();
            throw $ex;
        }
    }
}
