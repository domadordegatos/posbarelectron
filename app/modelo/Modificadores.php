<?php
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Configuracion.php';

class Modificadores
{
    /**
     * Sincroniza los grupos de un producto con la lista que manda el editor
     * (siempre la lista completa) SIN recrearlos: un grupo o una opción que
     * ya existía conserva su id, así lo que ya se eligió en una mesa abierta
     * sigue apuntando a algo válido aunque el admin edite el producto
     * mientras tanto. Antes se borraba todo y se volvía a crear con ids
     * nuevos en cada guardado, y las mesas abiertas perdían sus opciones.
     *
     * Un grupo existente se reconoce por su id (si el editor lo manda) o, si
     * no, por su nombre. Lo que ya no está en la lista se borra; si la base
     * de datos lo impide porque ya se usó en una venta o una mesa (llave
     * foránea), se desactiva en vez de borrarse — mismo patrón que ya se
     * usa con los niveles de fidelización.
     */
    public function guardarGrupos(int $idProducto, array $grupos): bool
    {
        $pdo = Database::conexion();

        $pdo->beginTransaction();
        try {
            $stmtExistentes = $pdo->prepare('SELECT id_grupo, nombre FROM grupos_modificadores WHERE producto_id = :id AND activo = 1');
            $stmtExistentes->execute(['id' => $idProducto]);
            $existentes = [];
            foreach ($stmtExistentes->fetchAll() as $fila) {
                $existentes[(int) $fila['id_grupo']] = mb_strtolower(trim($fila['nombre']));
            }
            $reutilizados = [];

            $stmtActualizarGrupo = $pdo->prepare(
                'UPDATE grupos_modificadores
                 SET nombre = :nombre, incluidos_gratis = :incluidos_gratis, minimo = :minimo, maximo = :maximo, orden = :orden
                 WHERE id_grupo = :id'
            );
            $stmtInsertarGrupo = $pdo->prepare(
                'INSERT INTO grupos_modificadores (producto_id, nombre, incluidos_gratis, minimo, maximo, orden)
                 VALUES (:producto_id, :nombre, :incluidos_gratis, :minimo, :maximo, :orden)'
            );
            $stmtOpcionesActuales = $pdo->prepare(
                'SELECT id_modificador, producto_id FROM modificadores
                 WHERE grupo_id = :grupo_id AND activo = 1 AND producto_id IS NOT NULL
                 ORDER BY id_modificador ASC'
            );
            $stmtOrdenOpcion = $pdo->prepare('UPDATE modificadores SET orden = :orden WHERE id_modificador = :id');
            // Cada opción es un producto real del catálogo (nombre/precio se
            // leen siempre en vivo de "productos" — ver obtenerGruposPorProducto()
            // y resolverSeleccion()) — nunca un texto/precio escrito a mano.
            // "nombre"/"precio_adicional" quedan sin usar (NOT NULL heredado
            // de la migración 022, se satisface con un valor vacío/0).
            $stmtInsertarOpcion = $pdo->prepare(
                "INSERT INTO modificadores (grupo_id, producto_id, nombre, precio_adicional, orden)
                 VALUES (:grupo_id, :producto_id, '', 0, :orden)"
            );

            foreach (array_values($grupos) as $ordenGrupo => $grupo) {
                $nombre = trim($grupo['nombre'] ?? '');
                if ($nombre === '') {
                    continue;
                }
                $maximo = isset($grupo['maximo']) && $grupo['maximo'] !== '' && $grupo['maximo'] !== null
                    ? max(0, (int) $grupo['maximo'])
                    : null;
                $datosGrupo = [
                    'nombre' => $nombre,
                    'incluidos_gratis' => max(0, (int) ($grupo['incluidos_gratis'] ?? 0)),
                    'minimo' => max(0, (int) ($grupo['minimo'] ?? 0)),
                    'maximo' => $maximo,
                    'orden' => $ordenGrupo,
                ];

                $idGrupo = null;
                $idEnviado = (int) ($grupo['id_grupo'] ?? 0);
                if ($idEnviado > 0 && isset($existentes[$idEnviado]) && !isset($reutilizados[$idEnviado])) {
                    $idGrupo = $idEnviado;
                } else {
                    $clave = mb_strtolower($nombre);
                    foreach ($existentes as $idExistente => $nombreExistente) {
                        if (!isset($reutilizados[$idExistente]) && $nombreExistente === $clave) {
                            $idGrupo = $idExistente;
                            break;
                        }
                    }
                }

                if ($idGrupo !== null) {
                    $stmtActualizarGrupo->execute($datosGrupo + ['id' => $idGrupo]);
                    $reutilizados[$idGrupo] = true;
                } else {
                    $stmtInsertarGrupo->execute($datosGrupo + ['producto_id' => $idProducto]);
                    $idGrupo = (int) $pdo->lastInsertId();
                }

                // Opciones deseadas, en orden: ni vacías, ni el mismo producto
                // que se está configurando (no puede incluirse a sí mismo), ni
                // repetidas dentro del grupo.
                $deseadas = [];
                foreach (array_values($grupo['modificadores'] ?? []) as $ordenMod => $mod) {
                    $idProductoBase = (int) ($mod['producto_base_id'] ?? 0);
                    if ($idProductoBase <= 0 || $idProductoBase === $idProducto || isset($deseadas[$idProductoBase])) {
                        continue;
                    }
                    $deseadas[$idProductoBase] = $ordenMod;
                }

                $stmtOpcionesActuales->execute(['grupo_id' => $idGrupo]);
                $actuales = [];
                foreach ($stmtOpcionesActuales->fetchAll() as $opcion) {
                    $idProductoOpcion = (int) $opcion['producto_id'];
                    if (isset($actuales[$idProductoOpcion])) {
                        $this->quitarOpcion($pdo, (int) $opcion['id_modificador']); // duplicada, sobra
                        continue;
                    }
                    $actuales[$idProductoOpcion] = (int) $opcion['id_modificador'];
                }

                foreach ($actuales as $idProductoOpcion => $idOpcion) {
                    if (isset($deseadas[$idProductoOpcion])) {
                        $stmtOrdenOpcion->execute(['orden' => $deseadas[$idProductoOpcion], 'id' => $idOpcion]);
                    } else {
                        $this->quitarOpcion($pdo, $idOpcion);
                    }
                }
                foreach ($deseadas as $idProductoBase => $ordenMod) {
                    if (!isset($actuales[$idProductoBase])) {
                        $stmtInsertarOpcion->execute(['grupo_id' => $idGrupo, 'producto_id' => $idProductoBase, 'orden' => $ordenMod]);
                    }
                }
            }

            // Grupos que ya no vienen en la lista: borrar, o desactivar si ya se usaron.
            foreach (array_keys($existentes) as $idExistente) {
                if (isset($reutilizados[$idExistente])) {
                    continue;
                }
                try {
                    $pdo->prepare('DELETE FROM modificadores WHERE grupo_id = :id')->execute(['id' => $idExistente]);
                    $pdo->prepare('DELETE FROM grupos_modificadores WHERE id_grupo = :id')->execute(['id' => $idExistente]);
                } catch (PDOException $e) {
                    $pdo->prepare('UPDATE grupos_modificadores SET activo = 0 WHERE id_grupo = :id')->execute(['id' => $idExistente]);
                }
            }

            $pdo->commit();

            return true;
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /** Borra una opción; si ya se usó en una venta o mesa (llave foránea) la desactiva en vez de borrarla. */
    private function quitarOpcion(PDO $pdo, int $idModificador): void
    {
        try {
            $pdo->prepare('DELETE FROM modificadores WHERE id_modificador = :id')->execute(['id' => $idModificador]);
        } catch (PDOException $e) {
            $pdo->prepare('UPDATE modificadores SET activo = 0 WHERE id_modificador = :id')->execute(['id' => $idModificador]);
        }
    }

    /**
     * Copia los grupos de modificadores de un producto a otro SIN borrar lo
     * que el destino ya tenga — nunca reemplaza, solo agrega. Si el destino
     * ya tiene un grupo con el mismo nombre (comparación sin mayúsculas ni
     * espacios), no crea uno segundo: fusiona ahí mismo, agregando solo las
     * opciones que todavía no estuvieran (por el producto real que
     * representan, no por texto) y dejando incluidos_gratis/mínimo/máximo
     * del grupo existente tal como estaban — se asume que si ya estaba
     * configurado, fue a propósito.
     */
    public function copiarDesdeProducto(int $idProductoOrigen, int $idProductoDestino): array
    {
        if ($idProductoOrigen === $idProductoDestino) {
            throw new InvalidArgumentException('Elige un producto distinto al actual para copiar.');
        }

        $gruposOrigen = $this->obtenerGruposPorProducto($idProductoOrigen);
        if (empty($gruposOrigen)) {
            return ['grupos_creados' => 0, 'grupos_fusionados' => 0, 'opciones_agregadas' => 0];
        }

        $pdo = Database::conexion();
        $pdo->beginTransaction();
        try {
            $indiceDestino = [];
            foreach ($this->obtenerGruposPorProducto($idProductoDestino) as $g) {
                $clave = mb_strtolower(trim($g['nombre']));
                $existentes = [];
                foreach ($g['modificadores'] as $m) {
                    $existentes[(int) $m['producto_id']] = true;
                }
                $indiceDestino[$clave] = ['id_grupo' => (int) $g['id_grupo'], 'productos_existentes' => $existentes];
            }

            $stmtOrdenGrupo = $pdo->prepare('SELECT COALESCE(MAX(orden), -1) + 1 FROM grupos_modificadores WHERE producto_id = :id AND activo = 1');
            $stmtInsertGrupo = $pdo->prepare(
                'INSERT INTO grupos_modificadores (producto_id, nombre, incluidos_gratis, minimo, maximo, orden)
                 VALUES (:producto_id, :nombre, :incluidos_gratis, :minimo, :maximo, :orden)'
            );
            $stmtOrdenMod = $pdo->prepare('SELECT COALESCE(MAX(orden), -1) + 1 FROM modificadores WHERE grupo_id = :grupo_id');
            $stmtInsertMod = $pdo->prepare(
                "INSERT INTO modificadores (grupo_id, producto_id, nombre, precio_adicional, orden) VALUES (:grupo_id, :producto_id, '', 0, :orden)"
            );

            $gruposCreados = 0;
            $gruposFusionados = 0;
            $opcionesAgregadas = 0;

            foreach ($gruposOrigen as $grupo) {
                $clave = mb_strtolower(trim($grupo['nombre']));
                // Nunca copiar una opción que termine apuntando al propio
                // producto destino (no puede incluirse a sí mismo).
                $opcionesValidas = array_values(array_filter(
                    $grupo['modificadores'],
                    fn($m) => (int) $m['producto_id'] !== $idProductoDestino
                ));

                if (isset($indiceDestino[$clave])) {
                    $idGrupoDestino = $indiceDestino[$clave]['id_grupo'];
                    $yaExisten = $indiceDestino[$clave]['productos_existentes'];
                    $stmtOrdenMod->execute(['grupo_id' => $idGrupoDestino]);
                    $ordenSiguiente = (int) $stmtOrdenMod->fetchColumn();
                    $agregadosAqui = 0;
                    foreach ($opcionesValidas as $mod) {
                        $idProductoBase = (int) $mod['producto_id'];
                        if (isset($yaExisten[$idProductoBase])) {
                            continue;
                        }
                        $stmtInsertMod->execute(['grupo_id' => $idGrupoDestino, 'producto_id' => $idProductoBase, 'orden' => $ordenSiguiente]);
                        $ordenSiguiente++;
                        $yaExisten[$idProductoBase] = true;
                        $agregadosAqui++;
                    }
                    if ($agregadosAqui > 0) {
                        $gruposFusionados++;
                        $opcionesAgregadas += $agregadosAqui;
                    }
                } else {
                    $stmtOrdenGrupo->execute(['id' => $idProductoDestino]);
                    $ordenGrupo = (int) $stmtOrdenGrupo->fetchColumn();
                    $stmtInsertGrupo->execute([
                        'producto_id' => $idProductoDestino,
                        'nombre' => $grupo['nombre'],
                        'incluidos_gratis' => (int) $grupo['incluidos_gratis'],
                        'minimo' => (int) $grupo['minimo'],
                        'maximo' => $grupo['maximo'] !== null ? (int) $grupo['maximo'] : null,
                        'orden' => $ordenGrupo,
                    ]);
                    $idGrupoNuevo = (int) $pdo->lastInsertId();
                    $productosExistentesNuevo = [];
                    foreach (array_values($opcionesValidas) as $orden => $mod) {
                        $idProductoBase = (int) $mod['producto_id'];
                        $stmtInsertMod->execute(['grupo_id' => $idGrupoNuevo, 'producto_id' => $idProductoBase, 'orden' => $orden]);
                        $productosExistentesNuevo[$idProductoBase] = true;
                    }
                    $gruposCreados++;
                    // Por si el origen tuviera dos grupos con el mismo
                    // nombre (caso raro) — evita duplicar el segundo también.
                    $indiceDestino[$clave] = ['id_grupo' => $idGrupoNuevo, 'productos_existentes' => $productosExistentesNuevo];
                }
            }

            $pdo->commit();

            return ['grupos_creados' => $gruposCreados, 'grupos_fusionados' => $gruposFusionados, 'opciones_agregadas' => $opcionesAgregadas];
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /** Grupos + sus opciones de un producto, en el orden en que se configuraron. Nombre/precio siempre en vivo. */
    public function obtenerGruposPorProducto(int $idProducto): array
    {
        $pdo = Database::conexion();
        $stmtGrupos = $pdo->prepare(
            'SELECT id_grupo, nombre, incluidos_gratis, minimo, maximo
             FROM grupos_modificadores WHERE producto_id = :id AND activo = 1 ORDER BY orden ASC, id_grupo ASC'
        );
        $stmtGrupos->execute(['id' => $idProducto]);
        $grupos = $stmtGrupos->fetchAll();

        // Un producto desactivado (p.estado = 1) desaparece solo de la lista
        // de opciones, sin romper nada — el admin lo nota porque el grupo
        // se queda con menos opciones de las que configuró.
        $stmtModificadores = $pdo->prepare(
            'SELECT m.id_modificador, m.producto_id, p.nombre_producto AS nombre, p.precio_unitario AS precio_adicional
             FROM modificadores m
             JOIN productos p ON p.id_producto = m.producto_id
             WHERE m.grupo_id = :grupo_id AND m.activo = 1 AND p.estado = 1
             ORDER BY m.orden ASC, m.id_modificador ASC'
        );
        foreach ($grupos as &$grupo) {
            $stmtModificadores->execute(['grupo_id' => $grupo['id_grupo']]);
            $grupo['modificadores'] = $stmtModificadores->fetchAll();
        }
        unset($grupo);

        return $grupos;
    }

    /**
     * Resuelve una selección de modificadores para una línea de venta: los
     * ids y el orden los manda el cliente (así sabe cuáles se marcaron
     * primero), pero el nombre, el precio, el stock y a qué grupo/producto
     * pertenecen SIEMPRE se leen de la base de datos — nunca del cliente.
     * Los primeros "incluidos_gratis" elegidos de cada grupo quedan en $0,
     * el resto se cobra al precio real del producto en ese momento.
     *
     * @param int[] $idsSeleccionados en el orden en que se fueron marcando
     * @return array{modificadores: array, extra: float}
     */
    public function resolverSeleccion(int $idProducto, array $idsSeleccionados): array
    {
        $ids = array_values(array_unique(array_map('intval', $idsSeleccionados)));
        if (empty($ids)) {
            return ['modificadores' => [], 'extra' => 0.0];
        }

        $pdo = Database::conexion();
        $marcadores = implode(',', array_fill(0, count($ids), '?'));
        // Sin el módulo "existencias" ningún modificador descuenta ni valida inventario.
        $existenciasActivas = Configuracion::moduloActivo('existencias') ? 1 : 0;
        $stmt = $pdo->prepare(
            "SELECT m.id_modificador, m.grupo_id, g.producto_id AS grupo_producto_id, g.incluidos_gratis,
                    p.id_producto AS topping_producto_id, p.nombre_producto AS nombre, p.precio_unitario AS precio_adicional,
                    CASE WHEN $existenciasActivas = 1 THEN p.controla_stock ELSE 0 END AS controla_stock, p.cantidad_existente, p.costo_unitario
             FROM modificadores m
             JOIN grupos_modificadores g ON g.id_grupo = m.grupo_id AND g.activo = 1
             JOIN productos p ON p.id_producto = m.producto_id
             WHERE m.id_modificador IN ($marcadores) AND m.activo = 1"
        );
        $stmt->execute($ids);
        $porId = [];
        foreach ($stmt->fetchAll() as $fila) {
            $porId[(int) $fila['id_modificador']] = $fila;
        }

        $conteoPorGrupo = [];
        $resultado = [];
        $extra = 0.0;

        foreach ($ids as $id) {
            if (!isset($porId[$id])) {
                throw new InvalidArgumentException('Uno de los modificadores seleccionados ya no existe.');
            }
            $mod = $porId[$id];
            if ((int) $mod['grupo_producto_id'] !== $idProducto) {
                throw new InvalidArgumentException('Uno de los modificadores seleccionados no pertenece a este producto.');
            }

            $grupoId = (int) $mod['grupo_id'];
            $conteoPorGrupo[$grupoId] = ($conteoPorGrupo[$grupoId] ?? 0) + 1;
            $incluido = $conteoPorGrupo[$grupoId] <= (int) $mod['incluidos_gratis'];
            $precioCobrado = $incluido ? 0.0 : (float) $mod['precio_adicional'];
            $extra += $precioCobrado;

            $resultado[] = [
                'id_modificador' => $id,
                'producto_id' => (int) $mod['topping_producto_id'],
                'nombre' => $mod['nombre'],
                'precio_cobrado' => $precioCobrado,
                'incluido' => $incluido,
                'controla_stock' => (bool) $mod['controla_stock'],
                'cantidad_existente' => (int) $mod['cantidad_existente'],
                'costo_unitario' => (float) $mod['costo_unitario'],
            ];
        }

        return ['modificadores' => $resultado, 'extra' => $extra];
    }

    /** Qué modificadores/toppings se eligieron más en un rango de fechas — para saber cuáles conviene stockear. */
    public function rankingVentas(string $fechaInicio, string $fechaFin, int $limite = 20): array
    {
        $pdo = Database::conexion();
        // Una fila de movimiento_modificadores es "por línea", no "por
        // unidad" — si la línea vendió 3 helados con Oreo, solo hay 1 fila,
        // así que hay que multiplicar por la cantidad de esa línea para no
        // subestimar cuántas veces se eligió de verdad.
        $stmt = $pdo->prepare(
            'SELECT mm.nombre_snapshot AS nombre,
                    SUM(m.cantidad) AS veces_elegido,
                    SUM(CASE WHEN mm.incluido = 0 THEN m.cantidad ELSE 0 END) AS veces_cobrado,
                    COALESCE(SUM(mm.precio_cobrado * m.cantidad), 0) AS ingreso_generado
             FROM movimiento_modificadores mm
             JOIN movimiento m ON m.id_movimiento = mm.id_movimiento
             JOIN facturas f ON f.id_factura = m.id_factura
             WHERE f.tipo_transaccion = 1 AND f.anulada = 0 AND f.fecha BETWEEN :inicio AND :fin
             GROUP BY mm.nombre_snapshot
             ORDER BY veces_elegido DESC
             LIMIT :limite'
        );
        $stmt->bindValue(':inicio', $fechaInicio, PDO::PARAM_STR);
        $stmt->bindValue(':fin', $fechaFin, PDO::PARAM_STR);
        $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }
}
