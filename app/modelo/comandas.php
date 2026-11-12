<?php
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Modificadores.php';
require_once __DIR__ . '/Configuracion.php';

class ComandaException extends RuntimeException
{
}

class comandas
{
    /**
     * Si la mesa (del catálogo fijo, ver modelo/mesas.php) ya tiene una
     * comanda abierta, la reutiliza tal cual (con sus items) — si no,
     * crea una nueva vacía. Así el mismo botón de la cuadrícula sirve
     * tanto para "empezar" una mesa libre como para "seguir" una ocupada.
     */
    public function abrirOReanudar(int $usuarioId, int $mesaId): array
    {
        $pdo = Database::conexion();

        $stmt = $pdo->prepare("SELECT id_comanda FROM comandas WHERE mesa_id = :mesa_id AND estado = 'abierta'");
        $stmt->execute(['mesa_id' => $mesaId]);
        $existente = $stmt->fetch();

        if ($existente) {
            return $this->obtenerConItems((int) $existente['id_comanda']);
        }

        $stmtMesa = $pdo->prepare('SELECT nombre FROM mesas WHERE id_mesa = :id AND activa = 1');
        $stmtMesa->execute(['id' => $mesaId]);
        $mesa = $stmtMesa->fetch();
        if (!$mesa) {
            throw new InvalidArgumentException('Esa mesa no existe o está deshabilitada.');
        }

        $stmtInsert = $pdo->prepare(
            "INSERT INTO comandas (mesa_id, mesa_nombre, usuario_id, fecha_apertura, hora_apertura, estado)
             VALUES (:mesa_id, :mesa_nombre, :usuario_id, :fecha, :hora, 'abierta')"
        );
        $stmtInsert->execute([
            'mesa_id' => $mesaId,
            'mesa_nombre' => $mesa['nombre'],
            'usuario_id' => $usuarioId,
            'fecha' => date('Y-m-d'),
            'hora' => date('H:i:s'),
        ]);

        return $this->obtenerConItems((int) $pdo->lastInsertId());
    }

    private function obtenerConItems(int $comandaId): array
    {
        $comanda = $this->obtener($comandaId);
        $comanda['items'] = $this->obtenerItems($comandaId);

        return $comanda;
    }

    public function guardarItems(int $comandaId, array $items, ?int $clienteId = null): bool
    {
        $pdo = Database::conexion();

        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("SELECT id_comanda FROM comandas WHERE id_comanda = :id AND estado = 'abierta'");
            $stmt->execute(['id' => $comandaId]);
            if (!$stmt->fetch()) {
                throw new ComandaException('Esa mesa no existe o ya no está abierta.');
            }

            $pdo->prepare('UPDATE comandas SET cliente_id = :cliente_id WHERE id_comanda = :id')
                ->execute(['cliente_id' => $clienteId, 'id' => $comandaId]);

            // ON DELETE CASCADE en comanda_item_modificadores limpia solos
            // los modificadores de los ítems que se borran aquí.
            $pdo->prepare('DELETE FROM comanda_items WHERE comanda_id = :id')->execute(['id' => $comandaId]);

            $stmtInsert = $pdo->prepare(
                'INSERT INTO comanda_items (comanda_id, producto_id, presentacion, cantidad, descuento_tipo, descuento_valor, profesional_id)
                 VALUES (:comanda_id, :producto_id, :presentacion, :cantidad, :descuento_tipo, :descuento_valor, :profesional_id)'
            );
            // Solo se guarda un profesional que exista (un id inventado no debe romper la mesa).
            $stmtExisteProfesional = $pdo->prepare('SELECT 1 FROM profesionales WHERE id_profesional = :id');
            $stmtInsertMod = $pdo->prepare(
                'INSERT INTO comanda_item_modificadores (comanda_item_id, modificador_id, orden)
                 VALUES (:comanda_item_id, :modificador_id, :orden)'
            );
            foreach ($items as $item) {
                $presentacion = in_array($item['presentacion'] ?? 'unidad', ['unidad', 'paquete'], true)
                    ? $item['presentacion'] : 'unidad';
                $cantidad = (int) ($item['cantidad'] ?? 0);
                if ($cantidad <= 0) {
                    continue;
                }
                $descuentoTipo = in_array($item['descuento_tipo'] ?? null, ['porcentaje', 'fijo'], true)
                    ? $item['descuento_tipo'] : null;
                $profesionalId = (int) ($item['profesional_id'] ?? 0);
                if ($profesionalId > 0) {
                    $stmtExisteProfesional->execute(['id' => $profesionalId]);
                    if (!$stmtExisteProfesional->fetchColumn()) {
                        $profesionalId = 0;
                    }
                }
                $stmtInsert->execute([
                    'profesional_id' => $profesionalId > 0 ? $profesionalId : null,
                    'comanda_id' => $comandaId,
                    'producto_id' => (int) $item['id_producto'],
                    'presentacion' => $presentacion,
                    'cantidad' => $cantidad,
                    'descuento_tipo' => $descuentoTipo,
                    'descuento_valor' => max(0.0, (float) ($item['descuento_valor'] ?? 0)),
                ]);
                $idItem = (int) $pdo->lastInsertId();

                foreach (array_values((array) ($item['modificadores'] ?? [])) as $orden => $idModificador) {
                    $stmtInsertMod->execute([
                        'comanda_item_id' => $idItem,
                        'modificador_id' => (int) $idModificador,
                        'orden' => $orden,
                    ]);
                }
            }

            $pdo->commit();

            return true;
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Usado por main.js antes de aplicar una actualización forzada: mientras
     * haya una mesa abierta, no es seguro instalar (se perdería el pedido en
     * curso de esa mesa si no está guardado en una factura todavía).
     */
    public function hayAbiertas(): bool
    {
        $pdo = Database::conexion();
        $stmt = $pdo->query("SELECT EXISTS(SELECT 1 FROM comandas WHERE estado = 'abierta')");
        return (bool) $stmt->fetchColumn();
    }

    /**
     * La cuadrícula completa que se ve en Ventas: todas las mesas activas
     * del catálogo, cada una con su estado (libre/ocupada) y el total
     * acumulado si tiene una comanda abierta en este momento.
     */
    public function listarConEstado(): array
    {
        $pdo = Database::conexion();
        $stmt = $pdo->query(
            "SELECT m.id_mesa, m.nombre,
                    c.id_comanda, cl.nombre AS cliente_nombre,
                    COALESCE(SUM(
                        CASE WHEN ci.presentacion = 'paquete' AND p.precio_paquete IS NOT NULL
                             THEN ci.cantidad * p.precio_paquete
                             ELSE ci.cantidad * p.precio_unitario
                        END
                    ), 0) AS total_estimado,
                    COALESCE(SUM(ci.cantidad), 0) AS total_items
             FROM mesas m
             LEFT JOIN comandas c ON c.mesa_id = m.id_mesa AND c.estado = 'abierta'
             LEFT JOIN clientes cl ON cl.id_cliente = c.cliente_id
             LEFT JOIN comanda_items ci ON ci.comanda_id = c.id_comanda
             LEFT JOIN productos p ON p.id_producto = ci.producto_id
             WHERE m.activa = 1
             GROUP BY m.id_mesa, m.nombre, c.id_comanda, cl.nombre
             ORDER BY m.nombre"
        );

        return $stmt->fetchAll();
    }

    public function obtener(int $comandaId): array|false
    {
        $pdo = Database::conexion();
        $stmt = $pdo->prepare(
            'SELECT co.*, cl.nombre AS cliente_nombre
             FROM comandas co
             LEFT JOIN clientes cl ON cl.id_cliente = co.cliente_id
             WHERE co.id_comanda = :id'
        );
        $stmt->execute(['id' => $comandaId]);
        $fila = $stmt->fetch();

        return $fila ?: false;
    }

    /** Firma estable de una línea para comparar "es lo mismo que ya se imprimió" — el descuento no importa para cocina, solo qué preparar. */
    private function firmaItemImpresion(array $item): string
    {
        $idsModificadores = array_map(
            static fn($m) => (int) ($m['id_modificador'] ?? $m),
            $item['modificadores'] ?? []
        );
        sort($idsModificadores);

        return $item['producto_id'] . '|' . $item['presentacion'] . '|' . implode(',', $idsModificadores);
    }

    /**
     * Compara el estado actual de la comanda (ya guardado) contra lo último
     * que se mandó a imprimir (comandas.impreso_json) y devuelve solo lo que
     * aumentó desde entonces — así "Guardar mesa" nunca reimprime algo que
     * cocina ya despachó, solo lo que se agregó de nuevo.
     */
    public function calcularNuevosParaImprimir(int $comandaId): array
    {
        $comanda = $this->obtener($comandaId);
        if (!$comanda) {
            return [];
        }
        $previos = json_decode($comanda['impreso_json'] ?? '', true) ?: [];
        $itemsActuales = $this->obtenerItems($comandaId);

        $nuevos = [];
        foreach ($itemsActuales as $item) {
            $firma = $this->firmaItemImpresion($item);
            $cantidadPrevia = (float) ($previos[$firma] ?? 0);
            $delta = (float) $item['cantidad'] - $cantidadPrevia;
            if ($delta > 0) {
                $item['cantidad'] = $delta;
                $nuevos[] = $item;
            }
        }

        return $nuevos;
    }

    /** Guarda el estado actual completo como "ya impreso" — se llama justo después de calcularNuevosParaImprimir(), una vez el ticket ya se mandó a la impresora. */
    public function marcarComoImpreso(int $comandaId): void
    {
        $itemsActuales = $this->obtenerItems($comandaId);
        $snapshot = [];
        foreach ($itemsActuales as $item) {
            $snapshot[$this->firmaItemImpresion($item)] = (float) $item['cantidad'];
        }

        $pdo = Database::conexion();
        $pdo->prepare('UPDATE comandas SET impreso_json = :json WHERE id_comanda = :id')
            ->execute(['json' => json_encode($snapshot), 'id' => $comandaId]);
    }

    public function obtenerItems(int $comandaId): array
    {
        $pdo = Database::conexion();
        $stmt = $pdo->prepare(
            'SELECT ci.id_item, ci.producto_id, ci.presentacion, ci.cantidad,
                    ci.descuento_tipo, ci.descuento_valor,
                    p.nombre_producto, p.precio_unitario, p.precio_paquete, p.unidades_por_paquete,
                    p.cantidad_existente, p.controla_stock, p.tasa_iva, ci.profesional_id
             FROM comanda_items ci
             JOIN productos p ON p.id_producto = ci.producto_id
             WHERE ci.comanda_id = :id
             ORDER BY ci.id_item ASC'
        );
        $stmt->execute(['id' => $comandaId]);
        $items = $stmt->fetchAll();
        if (!Configuracion::moduloActivo('existencias')) {
            foreach ($items as &$item) {
                $item['controla_stock'] = 0;
            }
            unset($item);
        }

        if (empty($items)) {
            return [];
        }

        $stmtMods = $pdo->prepare(
            'SELECT modificador_id FROM comanda_item_modificadores WHERE comanda_item_id = :id ORDER BY orden ASC'
        );
        // Cuáles de esos ids siguen siendo válidos HOY para este producto
        // (opción activa, grupo activo y del mismo producto).
        $stmtVigentes = $pdo->prepare(
            'SELECT m.id_modificador
             FROM modificadores m
             JOIN grupos_modificadores g ON g.id_grupo = m.grupo_id
             WHERE g.producto_id = :producto AND g.activo = 1 AND m.activo = 1'
        );
        // Si el producto tiene grupos configurados el cajero debe poder
        // reabrir el selector aunque esta línea no tenga nada elegido.
        $stmtTieneGrupos = $pdo->prepare(
            'SELECT EXISTS(SELECT 1 FROM grupos_modificadores WHERE producto_id = :producto AND activo = 1)'
        );
        $modelo = new Modificadores();
        foreach ($items as &$item) {
            $stmtMods->execute(['id' => $item['id_item']]);
            $idsModificadores = array_map('intval', array_column($stmtMods->fetchAll(), 'modificador_id'));
            $item['modificadores'] = [];
            if (!empty($idsModificadores)) {
                // Se descarta SOLO lo que ya no existe (el admin lo quitó
                // mientras la mesa estaba abierta) — antes, con que una sola
                // opción fallara, se perdían todas las de esa línea.
                $stmtVigentes->execute(['producto' => $item['producto_id']]);
                $vigentes = array_flip(array_map('intval', array_column($stmtVigentes->fetchAll(), 'id_modificador')));
                $idsValidos = array_values(array_filter($idsModificadores, fn($id) => isset($vigentes[$id])));
                if (!empty($idsValidos)) {
                    try {
                        $item['modificadores'] = $modelo->resolverSeleccion((int) $item['producto_id'], $idsValidos)['modificadores'];
                    } catch (InvalidArgumentException $e) {
                        $item['modificadores'] = [];
                    }
                }
            }
            $stmtTieneGrupos->execute(['producto' => $item['producto_id']]);
            $item['tiene_grupos'] = (int) $stmtTieneGrupos->fetchColumn();
        }
        unset($item);

        return $items;
    }

    public function cerrar(int $comandaId, int $idFactura): bool
    {
        $pdo = Database::conexion();
        $stmt = $pdo->prepare(
            "UPDATE comandas SET estado = 'cerrada', id_factura = :id_factura
             WHERE id_comanda = :id AND estado = 'abierta'"
        );
        $stmt->execute(['id_factura' => $idFactura, 'id' => $comandaId]);

        if ($stmt->rowCount() === 0) {
            throw new ComandaException('Esa mesa no existe o ya no está abierta.');
        }

        return true;
    }

    public function cancelar(int $comandaId): bool
    {
        $pdo = Database::conexion();
        $stmt = $pdo->prepare("UPDATE comandas SET estado = 'cancelada' WHERE id_comanda = :id AND estado = 'abierta'");
        $stmt->execute(['id' => $comandaId]);

        if ($stmt->rowCount() === 0) {
            throw new ComandaException('Esa mesa no existe o ya no está abierta.');
        }

        return true;
    }
}
