<?php
require_once __DIR__ . '/Database.php';

/**
 * Catálogo de conceptos de compras/gastos y pérdidas que no son productos del
 * inventario (gas, luz, bolsas plásticas...). Ver migración 035.
 */
class conceptos_gasto
{
    public const CATEGORIAS = ['servicio', 'insumo', 'otro'];

    /**
     * Conceptos que se siembran solos: los universales sirven a cualquier
     * negocio; el resto depende del tipo (Configuracion::TIPOS_NEGOCIO). Un
     * tipo sin entrada (ej. 'personalizado') solo recibe los universales.
     * La migración 036 aplica este mismo mapa a las instalaciones existentes.
     */
    public const SEMILLAS_UNIVERSALES = [
        ['Luz', 'servicio'], ['Agua', 'servicio'], ['Internet', 'servicio'], ['Arriendo', 'servicio'], ['Nómina', 'otro'],
    ];
    public const SEMILLAS_POR_TIPO = [
        'tienda' => [['Bolsas plásticas', 'insumo'], ['Aseo', 'insumo'], ['Transporte y fletes', 'servicio']],
        'supermercado' => [['Bolsas plásticas', 'insumo'], ['Aseo', 'insumo'], ['Transporte y fletes', 'servicio']],
        'bar_restaurante' => [
            ['Gas', 'servicio'], ['Bolsas plásticas', 'insumo'], ['Vasos y platos desechables', 'insumo'],
            ['Servilletas', 'insumo'], ['Aseo', 'insumo'],
        ],
        'servicios' => [['Productos de trabajo', 'insumo'], ['Toallas y lavandería', 'servicio'], ['Aseo', 'insumo']],
        'taller' => [['Herramientas', 'insumo'], ['Lubricantes y aseo', 'insumo'], ['Transporte y fletes', 'servicio']],
    ];

    /**
     * Agrega (sin duplicar ni tocar lo que ya existe o se renombró) los
     * conceptos universales y los del tipo de negocio. Lo llama Superadmin al
     * cambiar el tipo de negocio de una instalación.
     */
    public function sembrarParaTipo(string $tipoNegocio): int
    {
        $pdo = Database::conexion();
        $stmt = $pdo->prepare('INSERT OR IGNORE INTO conceptos_gasto (nombre, categoria) VALUES (:nombre, :categoria)');
        $agregados = 0;
        foreach (array_merge(self::SEMILLAS_UNIVERSALES, self::SEMILLAS_POR_TIPO[$tipoNegocio] ?? []) as [$nombre, $categoria]) {
            $stmt->execute(['nombre' => $nombre, 'categoria' => $categoria]);
            $agregados += $stmt->rowCount();
        }

        return $agregados;
    }

    /** Para la pantalla de administración: todos (también los desactivados) con cuántos movimientos los usan. */
    public function listarTodos(): array
    {
        return Database::conexion()->query(
            'SELECT cg.id_concepto, cg.nombre, cg.categoria, cg.estado,
                    (SELECT COUNT(*) FROM movimiento m WHERE m.concepto_id = cg.id_concepto) AS usos
             FROM conceptos_gasto cg
             ORDER BY cg.estado DESC, cg.nombre COLLATE NOCASE ASC'
        )->fetchAll();
    }

    /** Renombra, cambia la categoría o activa/desactiva. No permite dejar dos conceptos con el mismo nombre. */
    public function editar(int $idConcepto, string $nombre, string $categoria, int $estado): array
    {
        $nombre = trim(preg_replace('/\s+/u', ' ', $nombre));
        if ($nombre === '') {
            throw new InvalidArgumentException('El nombre es obligatorio.');
        }
        if (mb_strlen($nombre) > 80) {
            throw new InvalidArgumentException('El nombre es demasiado largo (máximo 80 caracteres).');
        }
        if (!in_array($categoria, self::CATEGORIAS, true)) {
            throw new InvalidArgumentException('Categoría inválida.');
        }
        if ($this->obtener($idConcepto) === null) {
            throw new InvalidArgumentException('Ese concepto no existe.');
        }

        $pdo = Database::conexion();
        $otro = $this->buscarPorNombre($nombre);
        if ($otro !== null && (int) $otro['id_concepto'] !== $idConcepto) {
            throw new InvalidArgumentException('Ya existe otro concepto con ese nombre.');
        }

        $pdo->prepare('UPDATE conceptos_gasto SET nombre = :nombre, categoria = :categoria, estado = :estado WHERE id_concepto = :id')
            ->execute(['nombre' => $nombre, 'categoria' => $categoria, 'estado' => $estado === 1 ? 1 : 0, 'id' => $idConcepto]);

        return $this->obtener($idConcepto);
    }

    /** Sin término devuelve los primeros del catálogo (para mostrar las opciones al enfocar el campo). */
    public function buscar(string $termino, int $limite = 10): array
    {
        $pdo = Database::conexion();
        $stmt = $pdo->prepare(
            "SELECT id_concepto, nombre, categoria
             FROM conceptos_gasto
             WHERE estado = 1 AND nombre LIKE :like
             ORDER BY nombre COLLATE NOCASE ASC
             LIMIT :limite"
        );
        $stmt->bindValue(':like', '%' . trim($termino) . '%', PDO::PARAM_STR);
        $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * Busca por nombre sin importar mayúsculas NI tildes de mayúscula ("NÓMINA" = "Nómina"):
     * el índice único de SQLite (NOCASE) solo ignora mayúsculas ASCII, así que la comparación
     * real se hace con mb_strtolower; el índice queda como red de seguridad.
     */
    private function buscarPorNombre(string $nombre): ?array
    {
        $buscado = mb_strtolower($nombre);
        foreach (Database::conexion()->query('SELECT id_concepto, nombre, estado FROM conceptos_gasto')->fetchAll() as $fila) {
            if (mb_strtolower($fila['nombre']) === $buscado) {
                return $fila;
            }
        }

        return null;
    }

    public function obtener(int $idConcepto): ?array
    {
        $stmt = Database::conexion()->prepare(
            'SELECT id_concepto, nombre, categoria, estado FROM conceptos_gasto WHERE id_concepto = :id'
        );
        $stmt->execute(['id' => $idConcepto]);

        return $stmt->fetch() ?: null;
    }

    /**
     * Crea el concepto; si ya existe uno con ese nombre (sin importar
     * mayúsculas) devuelve el existente en vez de fallar — y si estaba
     * desactivado lo reactiva.
     */
    public function crear(string $nombre, string $categoria): array
    {
        $nombre = trim(preg_replace('/\s+/u', ' ', $nombre));
        if ($nombre === '') {
            throw new InvalidArgumentException('El nombre es obligatorio.');
        }
        if (mb_strlen($nombre) > 80) {
            throw new InvalidArgumentException('El nombre es demasiado largo (máximo 80 caracteres).');
        }
        $categoria = in_array($categoria, self::CATEGORIAS, true) ? $categoria : 'otro';

        $pdo = Database::conexion();
        $existente = $this->buscarPorNombre($nombre);

        if ($existente) {
            if ((int) $existente['estado'] !== 1) {
                $pdo->prepare('UPDATE conceptos_gasto SET estado = 1 WHERE id_concepto = :id')
                    ->execute(['id' => $existente['id_concepto']]);
            }

            return $this->obtener((int) $existente['id_concepto']);
        }

        $pdo->prepare('INSERT INTO conceptos_gasto (nombre, categoria) VALUES (:nombre, :categoria)')
            ->execute(['nombre' => $nombre, 'categoria' => $categoria]);

        return $this->obtener((int) $pdo->lastInsertId());
    }
}
