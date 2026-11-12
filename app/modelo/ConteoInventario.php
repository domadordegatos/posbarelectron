<?php
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Configuracion.php';
require_once __DIR__ . '/inventario.php';

/**
 * Conteo físico de inventario ("stocktake"): el día que el negocio cuenta todo (o una categoría)
 * y compara contra lo que dice el sistema, para registrar las diferencias. Ver migración 053.
 *
 * No inventa un mecanismo financiero nuevo — al cerrar, cada diferencia se registra reusando
 * inventario::registrarPerdida() (faltante como pérdida real) o
 * inventario::registrarAjusteInventario() (faltante/sobrante sin pérdida ni ganancia, con motivo):
 * el negocio elige cómo quiere contabilizarlas, el conteo solo las detecta y las aplica.
 */
class ConteoInventario
{
    /** Solo un conteo abierto a la vez — evita que dos personas cuenten lo mismo por separado y se pisen. */
    private static function exigirExistencias(): void
    {
        if (!Configuracion::moduloActivo('existencias')) {
            throw new InvalidArgumentException('El control de existencias no está activado para este negocio.');
        }
    }

    public function conteoAbierto(): ?array
    {
        $stmt = Database::conexion()->query(
            "SELECT c.*, cat.descripcion AS categoria_nombre, u.nombre AS usuario_nombre
             FROM conteos_inventario c
             LEFT JOIN categorias cat ON cat.id_categoria = c.categoria_id
             LEFT JOIN usuarios u ON u.id_usuario = c.usuario_id
             WHERE c.estado = 'abierto'
             ORDER BY c.id_conteo DESC LIMIT 1"
        );

        return $stmt->fetch() ?: null;
    }

    /**
     * Abre un conteo nuevo y toma la "foto" (cantidad_sistema) de cada producto en el alcance
     * elegido — solo productos activos que controlan stock (los demás no tienen cantidad que contar).
     */
    public function iniciar(int $usuarioId, ?int $categoriaId, string $nota = ''): int
    {
        self::exigirExistencias();
        if ($this->conteoAbierto() !== null) {
            throw new InvalidArgumentException('Ya hay un conteo abierto — ciérralo antes de iniciar uno nuevo.');
        }

        $pdo = Database::conexion();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare(
                'INSERT INTO conteos_inventario (usuario_id, categoria_id, nota) VALUES (:usuario_id, :categoria_id, :nota)'
            );
            $stmt->execute([
                'usuario_id' => $usuarioId,
                'categoria_id' => $categoriaId,
                'nota' => trim($nota) !== '' ? trim($nota) : null,
            ]);
            $idConteo = (int) $pdo->lastInsertId();

            $filtroCategoria = $categoriaId !== null ? 'AND categoria = :categoria_id' : '';
            $stmtProductos = $pdo->prepare(
                "SELECT id_producto, cantidad_existente FROM productos
                 WHERE estado = 1 AND controla_stock = 1 $filtroCategoria"
            );
            $stmtProductos->execute($categoriaId !== null ? ['categoria_id' => $categoriaId] : []);
            $productos = $stmtProductos->fetchAll();

            if (empty($productos)) {
                throw new InvalidArgumentException('No hay productos con control de inventario en ese alcance.');
            }

            $stmtDetalle = $pdo->prepare(
                'INSERT INTO conteo_detalle (conteo_id, producto_id, cantidad_sistema) VALUES (:conteo_id, :producto_id, :cantidad_sistema)'
            );
            foreach ($productos as $p) {
                $stmtDetalle->execute([
                    'conteo_id' => $idConteo,
                    'producto_id' => $p['id_producto'],
                    'cantidad_sistema' => (int) $p['cantidad_existente'],
                ]);
            }

            $pdo->commit();

            return $idConteo;
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /** Líneas del conteo con nombre/categoría del producto y la diferencia ya calculada. */
    public function detalle(int $idConteo): array
    {
        $stmt = Database::conexion()->prepare(
            "SELECT d.id_detalle, d.producto_id, d.cantidad_sistema, d.cantidad_contada, d.contado_en,
                    p.nombre_producto, p.codigo, cat.descripcion AS categoria,
                    (d.cantidad_contada - d.cantidad_sistema) AS diferencia
             FROM conteo_detalle d
             JOIN productos p ON p.id_producto = d.producto_id
             JOIN categorias cat ON cat.id_categoria = p.categoria
             WHERE d.conteo_id = :id
             ORDER BY p.nombre_producto COLLATE NOCASE ASC"
        );
        $stmt->execute(['id' => $idConteo]);

        return $stmt->fetchAll();
    }

    /** Guarda (o borra, si $cantidad es null) lo contado en una sola línea — autoguardado por fila. */
    public function guardarCantidadContada(int $idConteo, int $idProducto, ?int $cantidad): array
    {
        if ($this->obtenerCabecera($idConteo)['estado'] !== 'abierto') {
            throw new InvalidArgumentException('Este conteo ya está cerrado.');
        }
        if ($cantidad !== null && $cantidad < 0) {
            throw new InvalidArgumentException('La cantidad contada no puede ser negativa.');
        }

        $pdo = Database::conexion();
        $stmt = $pdo->prepare(
            "UPDATE conteo_detalle
             SET cantidad_contada = :cantidad, contado_en = :contado_en
             WHERE conteo_id = :conteo_id AND producto_id = :producto_id"
        );
        $actualizado = $stmt->execute([
            'cantidad' => $cantidad,
            'contado_en' => $cantidad !== null ? date('Y-m-d H:i:s') : null,
            'conteo_id' => $idConteo,
            'producto_id' => $idProducto,
        ]);
        if ($stmt->rowCount() === 0) {
            throw new InvalidArgumentException('Ese producto no está en este conteo.');
        }

        return ['ok' => (bool) $actualizado];
    }

    /** Para la barra de progreso: cuántas líneas hay, cuántas ya se contaron y cuántas tienen diferencia. */
    public function resumen(int $idConteo): array
    {
        $stmt = Database::conexion()->prepare(
            'SELECT
                COUNT(*) AS total,
                SUM(CASE WHEN cantidad_contada IS NOT NULL THEN 1 ELSE 0 END) AS contadas,
                SUM(CASE WHEN cantidad_contada IS NOT NULL AND cantidad_contada <> cantidad_sistema THEN 1 ELSE 0 END) AS con_diferencia,
                SUM(CASE WHEN cantidad_contada IS NOT NULL AND cantidad_contada < cantidad_sistema THEN 1 ELSE 0 END) AS faltantes,
                SUM(CASE WHEN cantidad_contada IS NOT NULL AND cantidad_contada > cantidad_sistema THEN 1 ELSE 0 END) AS sobrantes
             FROM conteo_detalle WHERE conteo_id = :id'
        );
        $stmt->execute(['id' => $idConteo]);
        $fila = $stmt->fetch();

        return [
            'total' => (int) $fila['total'],
            'contadas' => (int) $fila['contadas'],
            'con_diferencia' => (int) $fila['con_diferencia'],
            'faltantes' => (int) $fila['faltantes'],
            'sobrantes' => (int) $fila['sobrantes'],
        ];
    }

    private function obtenerCabecera(int $idConteo): array
    {
        $stmt = Database::conexion()->prepare('SELECT * FROM conteos_inventario WHERE id_conteo = :id');
        $stmt->execute(['id' => $idConteo]);
        $fila = $stmt->fetch();
        if (!$fila) {
            throw new InvalidArgumentException('Ese conteo no existe.');
        }

        return $fila;
    }

    /**
     * Cierra el conteo y aplica cada diferencia encontrada como un movimiento real, reusando
     * inventario::registrarPerdida()/registrarAjusteInventario() (ambos ya a prueba de
     * transacción anidada, así que esto queda todo o nada). $tratamientoFaltante: 'perdida'
     * (afecta el balance de pérdidas/ganancias) o 'ajuste' (no, con $idMotivoFaltante). Los
     * sobrantes siempre se registran como ajuste de entrada sin ganancia, con $idMotivoSobrante.
     * Las líneas sin contar o sin diferencia simplemente no generan ningún movimiento.
     */
    public function cerrar(
        int $idConteo,
        int $usuarioId,
        string $tratamientoFaltante,
        ?int $idMotivoFaltante,
        ?int $idMotivoSobrante
    ): array {
        $cabecera = $this->obtenerCabecera($idConteo);
        if ($cabecera['estado'] !== 'abierto') {
            throw new InvalidArgumentException('Este conteo ya está cerrado.');
        }
        $tratamientoFaltante = $tratamientoFaltante === 'ajuste' ? 'ajuste' : 'perdida';

        $lineas = Database::conexion()->prepare(
            "SELECT d.id_detalle, d.producto_id, d.cantidad_sistema, d.cantidad_contada, p.costo_unitario, p.nombre_producto
             FROM conteo_detalle d
             JOIN productos p ON p.id_producto = d.producto_id
             WHERE d.conteo_id = :id AND d.cantidad_contada IS NOT NULL AND d.cantidad_contada <> d.cantidad_sistema"
        );
        $lineas->execute(['id' => $idConteo]);
        $lineas = $lineas->fetchAll();

        if (!empty($lineas)) {
            $hayFaltantes = false;
            $haySobrantes = false;
            foreach ($lineas as $l) {
                if ((int) $l['cantidad_contada'] < (int) $l['cantidad_sistema']) {
                    $hayFaltantes = true;
                } else {
                    $haySobrantes = true;
                }
            }
            if ($hayFaltantes && $tratamientoFaltante === 'ajuste' && $idMotivoFaltante === null) {
                throw new InvalidArgumentException('Elige el motivo para los faltantes.');
            }
            if ($haySobrantes && $idMotivoSobrante === null) {
                throw new InvalidArgumentException('Elige el motivo para los sobrantes.');
            }
        }

        $inv = new inventario();
        $pdo = Database::conexion();
        $pdo->beginTransaction();
        try {
            $aplicadas = 0;
            foreach ($lineas as $l) {
                $diferencia = (int) $l['cantidad_contada'] - (int) $l['cantidad_sistema'];
                $cantidad = abs($diferencia);
                $descripcion = "Conteo físico #{$idConteo}: {$l['nombre_producto']} (sistema {$l['cantidad_sistema']}, contado {$l['cantidad_contada']})";

                if ($diferencia < 0) {
                    if ($tratamientoFaltante === 'perdida') {
                        $idFactura = $inv->registrarPerdida(
                            $usuarioId, (int) $l['producto_id'], 'unidad', $cantidad,
                            $cantidad * (float) $l['costo_unitario'], $descripcion
                        );
                    } else {
                        $idFactura = $inv->registrarAjusteInventario(
                            $usuarioId, (int) $l['producto_id'], 'salida', 'unidad', $cantidad,
                            $idMotivoFaltante, $descripcion
                        );
                    }
                } else {
                    $idFactura = $inv->registrarAjusteInventario(
                        $usuarioId, (int) $l['producto_id'], 'entrada', 'unidad', $cantidad,
                        $idMotivoSobrante, $descripcion
                    );
                }

                $pdo->prepare('UPDATE conteo_detalle SET registro_id_factura = :id_factura WHERE id_detalle = :id')
                    ->execute(['id_factura' => $idFactura, 'id' => $l['id_detalle']]);
                $aplicadas++;
            }

            $pdo->prepare(
                "UPDATE conteos_inventario
                 SET estado = 'cerrado', fecha_cierre = datetime('now', 'localtime'), usuario_cierre_id = :usuario_id
                 WHERE id_conteo = :id"
            )->execute(['usuario_id' => $usuarioId, 'id' => $idConteo]);

            $pdo->commit();

            return ['diferencias_aplicadas' => $aplicadas];
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /** Historial de conteos (abiertos y cerrados), más recientes primero, con su resumen. */
    public function historial(int $limite = 30): array
    {
        $stmt = Database::conexion()->prepare(
            "SELECT c.*, cat.descripcion AS categoria_nombre, u.nombre AS usuario_nombre, uc.nombre AS usuario_cierre_nombre,
                    (SELECT COUNT(*) FROM conteo_detalle d WHERE d.conteo_id = c.id_conteo) AS total_lineas,
                    (SELECT COUNT(*) FROM conteo_detalle d WHERE d.conteo_id = c.id_conteo AND d.cantidad_contada IS NOT NULL AND d.cantidad_contada <> d.cantidad_sistema) AS con_diferencia
             FROM conteos_inventario c
             LEFT JOIN categorias cat ON cat.id_categoria = c.categoria_id
             LEFT JOIN usuarios u ON u.id_usuario = c.usuario_id
             LEFT JOIN usuarios uc ON uc.id_usuario = c.usuario_cierre_id
             ORDER BY c.id_conteo DESC
             LIMIT :limite"
        );
        $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }
}
