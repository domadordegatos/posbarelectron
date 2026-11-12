<?php
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Configuracion.php';
require_once __DIR__ . '/Sincronizacion.php';

class productos
{
    private const CAMPOS_BASE = 'p.id_producto, p.nombre_producto, p.codigo, p.precio_unitario,
        p.cantidad_existente, p.minimo_stock, p.controla_stock,
        p.unidades_por_paquete, p.precio_paquete, p.costo_unitario, p.tasa_iva, p.es_combo,
        p.se_vende_por_peso';

    public function buscar(string $termino, int $limite = 10): array
    {
        $pdo = Database::conexion();
        $termino = trim($termino);

        // Si el término es puramente numérico, primero se intenta una
        // coincidencia exacta de código de barras (lo típico al escanear).
        if ($termino !== '' && ctype_digit($termino)) {
            $stmt = $pdo->prepare(
                'SELECT ' . self::CAMPOS_BASE . ', c.descripcion AS categoria
                 FROM productos p
                 JOIN categorias c ON p.categoria = c.id_categoria
                 WHERE p.codigo = :codigo AND p.estado = 1
                 LIMIT 1'
            );
            $stmt->execute(['codigo' => $termino]);
            $exacto = $stmt->fetchAll();
            if (!empty($exacto)) {
                return $exacto;
            }
        }

        $like = '%' . $termino . '%';
        $stmt = $pdo->prepare(
            'SELECT ' . self::CAMPOS_BASE . ', c.descripcion AS categoria
             FROM productos p
             JOIN categorias c ON p.categoria = c.id_categoria
             WHERE (p.nombre_producto LIKE :like1 OR p.codigo LIKE :like2)
               AND p.estado = 1
             ORDER BY p.nombre_producto ASC
             LIMIT :limite'
        );
        $stmt->bindValue(':like1', $like, PDO::PARAM_STR);
        $stmt->bindValue(':like2', $like, PDO::PARAM_STR);
        $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /** Catálogo completo (activos), sin paginar — para pickers de admin como la cuadrícula de ventas. */
    public function listarActivosSimple(): array
    {
        $pdo = Database::conexion();
        $stmt = $pdo->query(
            'SELECT p.id_producto, p.nombre_producto, p.precio_unitario
             FROM productos p
             WHERE p.estado = 1
             ORDER BY p.nombre_producto ASC'
        );

        return $stmt->fetchAll();
    }

    public function listarPaginado(int $pagina, int $porPagina, string $termino = '', string $orden = 'asc'): array
    {
        $pdo = Database::conexion();
        $offset = max(0, ($pagina - 1) * $porPagina);
        $like = '%' . $termino . '%';
        $direccion = strtolower($orden) === 'desc' ? 'DESC' : 'ASC';

        $filtro = $termino !== '' ? 'AND (p.nombre_producto LIKE :like1 OR p.codigo LIKE :like2)' : '';

        $stmt = $pdo->prepare(
            "SELECT " . self::CAMPOS_BASE . ", p.estado, c.descripcion AS categoria
             FROM productos p
             JOIN categorias c ON p.categoria = c.id_categoria
             WHERE 1 = 1 $filtro
             ORDER BY p.nombre_producto $direccion
             LIMIT :porPagina OFFSET :offset"
        );
        if ($termino !== '') {
            $stmt->bindValue(':like1', $like, PDO::PARAM_STR);
            $stmt->bindValue(':like2', $like, PDO::PARAM_STR);
        }
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->bindValue(':porPagina', $porPagina, PDO::PARAM_INT);
        $stmt->execute();
        $filas = $stmt->fetchAll();

        $stmtTotal = $pdo->prepare(
            "SELECT COUNT(*) AS total
             FROM productos p
             WHERE 1 = 1 $filtro"
        );
        if ($termino !== '') {
            $stmtTotal->bindValue(':like1', $like, PDO::PARAM_STR);
            $stmtTotal->bindValue(':like2', $like, PDO::PARAM_STR);
        }
        $stmtTotal->execute();
        $total = (int) $stmtTotal->fetch()['total'];

        return ['filas' => $filas, 'total' => $total];
    }

    /** Todos los productos, sin paginar — para exportar el catálogo completo a Excel. */
    public function listarTodos(): array
    {
        $pdo = Database::conexion();

        return $pdo->query(
            "SELECT " . self::CAMPOS_BASE . ", p.estado, c.descripcion AS categoria
             FROM productos p
             JOIN categorias c ON p.categoria = c.id_categoria
             ORDER BY p.nombre_producto ASC"
        )->fetchAll();
    }

    public function crearProducto(
        string $nombre_producto,
        ?string $codigo_producto,
        int $categoria,
        float $precio_unitario,
        int $cantidad_existente,
        int $minimo_stock,
        int $estado,
        bool $controla_stock = true,
        int $unidades_por_paquete = 1,
        ?float $precio_paquete = null,
        float $costo_unitario = 0,
        float $tasa_iva = 0,
        bool $es_combo = false,
        bool $se_vende_por_peso = false
    ): int {
        $pdo = Database::conexion();
        $codigo = ($codigo_producto === '' ? null : $codigo_producto);
        // Sin el módulo "existencias" (negocio de servicios) un producto nuevo nace sin inventario.
        if (!Configuracion::moduloActivo('existencias')) {
            $controla_stock = false;
        }
        // La venta por peso no soporta inventario en esta versión (se
        // sirve/pesa a ojo, igual que un plato preparado al momento).
        if (!$controla_stock || $se_vende_por_peso) {
            $cantidad_existente = 0;
            $minimo_stock = 0;
        }
        [$unidades_por_paquete, $precio_paquete] = self::normalizarPaquete($unidades_por_paquete, $precio_paquete);

        // Igual que en crearCategoria(): si ya hay una transacción abierta (carga masiva),
        // nos sumamos a esa en vez de abrir otra anidada.
        $transaccionPropia = !$pdo->inTransaction();
        if ($transaccionPropia) {
            $pdo->beginTransaction();
        }
        try {
            $stmt = $pdo->prepare(
                'INSERT INTO productos
                    (nombre_producto, codigo, categoria, precio_unitario, cantidad_existente, minimo_stock, estado,
                     controla_stock, unidades_por_paquete, precio_paquete, costo_unitario, tasa_iva, es_combo, se_vende_por_peso,
                     actualizado_en)
                 VALUES (:nombre, :codigo, :categoria, :precio, :cantidad, :minimo, :estado,
                         :controla_stock, :upp, :precio_paquete, :costo, :tasa_iva, :es_combo, :se_vende_por_peso,
                         datetime(\'now\',\'localtime\'))'
            );
            $ok = $stmt->execute([
                'nombre' => $nombre_producto,
                'codigo' => $codigo,
                'categoria' => $categoria,
                'precio' => $precio_unitario,
                'cantidad' => $cantidad_existente,
                'minimo' => $minimo_stock,
                'estado' => $estado,
                'controla_stock' => ($controla_stock && !$se_vende_por_peso) ? 1 : 0,
                'upp' => $unidades_por_paquete,
                'precio_paquete' => $precio_paquete,
                'costo' => max(0, $costo_unitario),
                'tasa_iva' => max(0, $tasa_iva),
                'es_combo' => $es_combo ? 1 : 0,
                'se_vende_por_peso' => $se_vende_por_peso ? 1 : 0,
            ]);
            $idProducto = (int) $pdo->lastInsertId();
            // Ojo: encolar el evento hace otro INSERT, así que después de esto lastInsertId() ya
            // NO es el id del producto — quien lo necesite debe usar ultimoIdCreado().
            $this->ultimoIdCreado = $idProducto;
            Sincronizacion::marcarOrigenPropio($pdo, 'productos', 'id_producto', 'origen_id_producto', $idProducto);
            $this->encolarEventoProducto($pdo, $idProducto, true);
            if ($transaccionPropia) {
                $pdo->commit();
            }

            return $ok ? 1 : 0;
        } catch (Throwable $e) {
            if ($transaccionPropia) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Arma y encola el evento "producto" para las demás cajas — usado tanto al crear como al
     * editar. No-op si esta caja no está sincronizando.
     */
    private int $ultimoIdCreado = 0;

    /** id_producto del último producto creado con crearProducto() en este objeto (lastInsertId() ya no sirve para eso). */
    public function ultimoIdCreado(): int
    {
        return $this->ultimoIdCreado;
    }

    /**
     * $esCreacion: solo en la creación viaja el stock inicial — para que las demás cajas
     * arranquen con el mismo número. Después el stock solo se mueve por deltas (ventas,
     * compras, pérdidas), nunca por un snapshot de catálogo.
     */
    private function encolarEventoProducto(PDO $pdo, int $idProducto, bool $esCreacion = false): void
    {
        if (!Sincronizacion::sincronizacionActiva()) {
            return;
        }
        $stmt = $pdo->prepare('SELECT ' . self::CAMPOS_BASE . ', p.categoria, p.estado, p.actualizado_en FROM productos p WHERE p.id_producto = :id');
        $stmt->execute(['id' => $idProducto]);
        $p = $stmt->fetch();
        if (!$p) {
            return;
        }
        $origenProducto = Sincronizacion::origenDe($pdo, 'productos', 'id_producto', 'origen_id_producto', $idProducto);
        if ($origenProducto === null) {
            return;
        }
        $origenCategoria = Sincronizacion::origenDe($pdo, 'categorias', 'id_categoria', 'origen_id_categoria', (int) $p['categoria']);

        Sincronizacion::registrarEventoSaliente($pdo, 'producto', $idProducto, [
            'caja_origen' => $origenProducto['caja'],
            'id_producto_origen' => $origenProducto['id'],
            'actualizado_en' => $p['actualizado_en'],
            'nombre_producto' => $p['nombre_producto'],
            'codigo' => $p['codigo'],
            'categoria' => $origenCategoria,
            'precio_unitario' => (float) $p['precio_unitario'],
            'minimo_stock' => (int) $p['minimo_stock'],
            'controla_stock' => (bool) $p['controla_stock'],
            'estado' => (int) $p['estado'],
            'unidades_por_paquete' => (int) $p['unidades_por_paquete'],
            'precio_paquete' => $p['precio_paquete'] !== null ? (float) $p['precio_paquete'] : null,
            'costo_unitario' => (float) $p['costo_unitario'],
            'tasa_iva' => (float) $p['tasa_iva'],
            'es_combo' => (bool) $p['es_combo'],
            'se_vende_por_peso' => (bool) $p['se_vende_por_peso'],
            'stock_inicial' => $esCreacion ? (int) $p['cantidad_existente'] : null,
        ]);
    }

    private static function normalizarPaquete(int $unidades_por_paquete, ?float $precio_paquete): array
    {
        $unidades_por_paquete = max(1, $unidades_por_paquete);
        // Si no se maneja por paquete (1 unidad = 1 paquete), no tiene sentido guardar un precio de paquete.
        if ($unidades_por_paquete <= 1) {
            $precio_paquete = null;
        }

        return [$unidades_por_paquete, $precio_paquete];
    }

    public function obtenerProductoPorId(int $id_producto): array|false
    {
        $pdo = Database::conexion();
        $stmt = $pdo->prepare(
            'SELECT ' . self::CAMPOS_BASE . ', p.categoria, p.estado
             FROM productos p
             WHERE p.id_producto = :id'
        );
        $stmt->execute(['id' => $id_producto]);
        $fila = $stmt->fetch();

        return $fila ?: false;
    }

    public function editarProducto(
        int $id_producto,
        string $nombre_producto,
        ?string $codigo_producto,
        int $categoria,
        float $precio_unitario,
        int $cantidad_existente,
        int $minimo_stock,
        int $estado,
        bool $controla_stock = true,
        int $unidades_por_paquete = 1,
        ?float $precio_paquete = null,
        ?float $costo_unitario = null,
        float $tasa_iva = 0,
        bool $es_combo = false,
        bool $se_vende_por_peso = false
    ): int {
        $pdo = Database::conexion();
        $codigo = ($codigo_producto === '' ? null : $codigo_producto);
        // Sin el módulo "existencias" la pantalla no muestra ni envía nada de inventario: se CONSERVA lo que
        // el producto ya tenía guardado (para que, si el módulo se vuelve a activar, no se haya perdido).
        if (!Configuracion::moduloActivo('existencias')) {
            $actual = $pdo->prepare('SELECT controla_stock, cantidad_existente, minimo_stock FROM productos WHERE id_producto = :id');
            $actual->execute(['id' => $id_producto]);
            if ($fila = $actual->fetch()) {
                $controla_stock = (bool) $fila['controla_stock'];
                $cantidad_existente = (int) $fila['cantidad_existente'];
                $minimo_stock = (int) $fila['minimo_stock'];
            }
        }
        if (!$controla_stock || $se_vende_por_peso) {
            $cantidad_existente = 0;
            $minimo_stock = 0;
        }
        [$unidades_por_paquete, $precio_paquete] = self::normalizarPaquete($unidades_por_paquete, $precio_paquete);

        $sql = 'UPDATE productos
             SET nombre_producto = :nombre, codigo = :codigo, categoria = :categoria,
                 precio_unitario = :precio, cantidad_existente = :cantidad, minimo_stock = :minimo,
                 estado = :estado, controla_stock = :controla_stock,
                 unidades_por_paquete = :upp, precio_paquete = :precio_paquete,
                 tasa_iva = :tasa_iva, es_combo = :es_combo, se_vende_por_peso = :se_vende_por_peso,
                 actualizado_en = datetime(\'now\',\'localtime\')';
        $params = [
            'nombre' => $nombre_producto,
            'codigo' => $codigo,
            'categoria' => $categoria,
            'precio' => $precio_unitario,
            'cantidad' => $cantidad_existente,
            'minimo' => $minimo_stock,
            'estado' => $estado,
            'controla_stock' => ($controla_stock && !$se_vende_por_peso) ? 1 : 0,
            'upp' => $unidades_por_paquete,
            'precio_paquete' => $precio_paquete,
            'tasa_iva' => max(0, $tasa_iva),
            'es_combo' => $es_combo ? 1 : 0,
            'se_vende_por_peso' => $se_vende_por_peso ? 1 : 0,
            'id' => $id_producto,
        ];

        // El costo unitario solo se toca si se manda explícitamente (evita
        // pisar el costo promedio ponderado que ya calculan las compras).
        if ($costo_unitario !== null) {
            $sql .= ', costo_unitario = :costo';
            $params['costo'] = max(0, $costo_unitario);
        }

        $sql .= ' WHERE id_producto = :id';

        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare($sql);
            $ok = $stmt->execute($params);
            Sincronizacion::marcarOrigenPropio($pdo, 'productos', 'id_producto', 'origen_id_producto', $id_producto);
            $this->encolarEventoProducto($pdo, $id_producto);
            $pdo->commit();

            return $ok ? 1 : 0;
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public function existeCodigo(string $codigo, ?int $excluirId = null): bool
    {
        $pdo = Database::conexion();
        $sql = 'SELECT id_producto FROM productos WHERE codigo = :codigo';
        $params = ['codigo' => $codigo];
        if ($excluirId !== null) {
            $sql .= ' AND id_producto != :id';
            $params['id'] = $excluirId;
        }
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        return (bool) $stmt->fetch();
    }

    public function productosStockBajo(): array
    {
        if (!Configuracion::moduloActivo('existencias')) {
            return [];
        }
        $pdo = Database::conexion();
        $stmt = $pdo->query(
            "SELECT id_producto, nombre_producto, cantidad_existente, minimo_stock,
                    CASE WHEN cantidad_existente <= 0 THEN 'agotado' ELSE 'bajo_minimo' END AS estado_stock
             FROM productos
             WHERE estado = 1 AND controla_stock = 1
               AND (cantidad_existente <= 0 OR cantidad_existente <= minimo_stock)
             ORDER BY (cantidad_existente - minimo_stock) ASC"
        );

        return $stmt->fetchAll();
    }

    /** Productos elegidos para la cuadrícula rápida de Ventas, en el orden en que deben aparecer. */
    public function listarDestacados(): array
    {
        $pdo = Database::conexion();
        $stmt = $pdo->query(
            'SELECT ' . self::CAMPOS_BASE . ', c.descripcion AS categoria
             FROM productos p
             JOIN categorias c ON p.categoria = c.id_categoria
             WHERE p.destacado_orden IS NOT NULL AND p.estado = 1
             ORDER BY p.destacado_orden ASC'
        );

        return $stmt->fetchAll();
    }

    /** Reemplaza el conjunto completo de destacados por esta lista, en este orden — mismo patrón que combos::guardarComponentes(). */
    public function guardarDestacados(array $idsEnOrden): void
    {
        $pdo = Database::conexion();
        $pdo->beginTransaction();
        try {
            $pdo->exec('UPDATE productos SET destacado_orden = NULL WHERE destacado_orden IS NOT NULL');
            $stmt = $pdo->prepare('UPDATE productos SET destacado_orden = :orden WHERE id_producto = :id');
            foreach (array_values($idsEnOrden) as $orden => $idProducto) {
                $stmt->execute(['orden' => $orden, 'id' => (int) $idProducto]);
            }
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /** Categorías elegidas para la cuadrícula por categoría de Ventas, en el orden en que deben aparecer. */
    public function listarCategoriasCuadricula(): array
    {
        $pdo = Database::conexion();
        $stmt = $pdo->query(
            "SELECT c.id_categoria, c.descripcion,
                    (SELECT COUNT(*) FROM productos p WHERE p.categoria = c.id_categoria AND p.estado = 1) AS total_productos
             FROM categorias c
             WHERE c.orden_cuadricula IS NOT NULL AND c.estado = 1
             ORDER BY c.orden_cuadricula ASC"
        );

        return $stmt->fetchAll();
    }

    /** Reemplaza el conjunto completo de categorías de la cuadrícula por esta lista, en este orden — mismo patrón que guardarDestacados(). */
    public function guardarCategoriasCuadricula(array $idsEnOrden): void
    {
        $pdo = Database::conexion();
        $pdo->beginTransaction();
        try {
            $pdo->exec('UPDATE categorias SET orden_cuadricula = NULL WHERE orden_cuadricula IS NOT NULL');
            $stmt = $pdo->prepare('UPDATE categorias SET orden_cuadricula = :orden WHERE id_categoria = :id');
            foreach (array_values($idsEnOrden) as $orden => $idCategoria) {
                $stmt->execute(['orden' => $orden, 'id' => (int) $idCategoria]);
            }
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /** Productos activos de una categoría, en el orden manual de su cuadrícula (empate alfabético). */
    public function listarProductosCategoriaCuadricula(int $idCategoria): array
    {
        $pdo = Database::conexion();
        $stmt = $pdo->prepare(
            'SELECT ' . self::CAMPOS_BASE . '
             FROM productos p
             WHERE p.categoria = :categoria AND p.estado = 1
             ORDER BY p.orden_categoria ASC, p.nombre_producto ASC'
        );
        $stmt->execute(['categoria' => $idCategoria]);

        return $stmt->fetchAll();
    }

    /** Reemplaza el orden manual de los productos DE ESA categoría — solo toca filas que de verdad pertenecen a ella. */
    public function guardarOrdenProductosCategoria(int $idCategoria, array $idsEnOrden): void
    {
        $pdo = Database::conexion();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('UPDATE productos SET orden_categoria = :orden WHERE id_producto = :id AND categoria = :categoria');
            foreach (array_values($idsEnOrden) as $orden => $idProducto) {
                $stmt->execute(['orden' => $orden, 'id' => (int) $idProducto, 'categoria' => $idCategoria]);
            }
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public function listarCategorias(): array
    {
        $pdo = Database::conexion();
        $stmt = $pdo->query('SELECT id_categoria, descripcion FROM categorias WHERE estado = 1 ORDER BY descripcion');

        return $stmt->fetchAll();
    }

    public function crearCategoria(string $descripcion): int
    {
        $pdo = Database::conexion();
        // Si ya hay una transacción abierta (ej. la carga masiva de productos, que crea
        // categorías nuevas dentro de su propia transacción por fila), nos sumamos a esa en
        // vez de abrir otra — PDO/SQLite no soportan transacciones anidadas.
        $transaccionPropia = !$pdo->inTransaction();
        if ($transaccionPropia) {
            $pdo->beginTransaction();
        }
        try {
            $stmt = $pdo->prepare("INSERT INTO categorias (descripcion, estado, actualizado_en) VALUES (:descripcion, 1, datetime('now','localtime'))");
            $stmt->execute(['descripcion' => $descripcion]);
            $idCategoria = (int) $pdo->lastInsertId();
            Sincronizacion::marcarOrigenPropio($pdo, 'categorias', 'id_categoria', 'origen_id_categoria', $idCategoria);
            $this->encolarEventoCategoria($pdo, $idCategoria);
            if ($transaccionPropia) {
                $pdo->commit();
            }

            return $idCategoria;
        } catch (Throwable $e) {
            if ($transaccionPropia) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    public function editarCategoria(int $idCategoria, string $descripcion): bool
    {
        $pdo = Database::conexion();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("UPDATE categorias SET descripcion = :descripcion, actualizado_en = datetime('now','localtime') WHERE id_categoria = :id");
            $ok = $stmt->execute(['descripcion' => $descripcion, 'id' => $idCategoria]);
            Sincronizacion::marcarOrigenPropio($pdo, 'categorias', 'id_categoria', 'origen_id_categoria', $idCategoria);
            $this->encolarEventoCategoria($pdo, $idCategoria);
            $pdo->commit();

            return $ok;
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /** Arma y encola el evento "categoria" para las demás cajas — usado al crear y al editar. */
    private function encolarEventoCategoria(PDO $pdo, int $idCategoria): void
    {
        if (!Sincronizacion::sincronizacionActiva()) {
            return;
        }
        $stmt = $pdo->prepare('SELECT descripcion, estado, actualizado_en FROM categorias WHERE id_categoria = :id');
        $stmt->execute(['id' => $idCategoria]);
        $c = $stmt->fetch();
        if (!$c) {
            return;
        }
        $origen = Sincronizacion::origenDe($pdo, 'categorias', 'id_categoria', 'origen_id_categoria', $idCategoria);
        if ($origen === null) {
            return;
        }
        Sincronizacion::registrarEventoSaliente($pdo, 'categoria', $idCategoria, [
            'caja_origen' => $origen['caja'],
            'id_categoria_origen' => $origen['id'],
            'descripcion' => $c['descripcion'],
            'estado' => (int) $c['estado'],
            'actualizado_en' => $c['actualizado_en'],
        ]);
    }
}
