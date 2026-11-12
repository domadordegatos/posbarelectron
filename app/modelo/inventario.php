<?php
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Configuracion.php';
require_once __DIR__ . '/Sincronizacion.php';
require_once __DIR__ . '/Auditoria.php';
require_once __DIR__ . '/motivos_ajuste.php';

class inventario
{

    /** Compras y pérdidas de PRODUCTOS mueven existencias: sin el módulo "existencias" no aplican (los gastos por concepto sí). */
    private static function exigirExistencias(): void
    {
        if (!Configuracion::moduloActivo('existencias')) {
            throw new InvalidArgumentException('El control de existencias no está activado para este negocio. Usa "Otro (gastos)" para registrar gastos.');
        }
    }

    /**
     * Fecha opcional al registrar una compra/gasto/pérdida — para corregir algo que se pagó un día
     * pero en realidad corresponde a otro (p. ej. un insumo que se pagó hoy pero fue de ayer).
     * Vacía o ausente = hoy. Nunca se acepta una fecha futura (evita un error de tecleo, ej. el año).
     */
    private static function fechaRegistro(?string $fecha): string
    {
        if ($fecha === null || trim($fecha) === '') {
            return date('Y-m-d');
        }
        $fecha = trim($fecha);
        $d = DateTime::createFromFormat('Y-m-d', $fecha);
        if (!$d || $d->format('Y-m-d') !== $fecha) {
            throw new InvalidArgumentException('La fecha no es válida.');
        }
        if ($fecha > date('Y-m-d')) {
            throw new InvalidArgumentException('La fecha no puede ser futura.');
        }

        return $fecha;
    }

    /**
     * Compra a proveedor: suma stock y recalcula el costo unitario como
     * promedio ponderado (una compra a $0 — obsequio — diluye el costo
     * hacia abajo, que es lo correcto).
     */
    public function registrarCompra(int $usuarioId, int $idProducto, string $presentacion, int $cantidad, float $costoTotal, ?int $proveedorId = null, ?string $fecha = null): int
    {
        self::exigirExistencias();
        if ($cantidad <= 0) {
            throw new InvalidArgumentException('La cantidad debe ser mayor a cero.');
        }
        $presentacion = $presentacion === 'paquete' ? 'paquete' : 'unidad';
        $costoTotal = max(0, $costoTotal);

        $pdo = Database::conexion();
        $fecha = self::fechaRegistro($fecha);
        $hora = date('H:i:s');

        $pdo->beginTransaction();
        try {
            $stmtProducto = $pdo->prepare(
                'SELECT nombre_producto, unidades_por_paquete, cantidad_existente, costo_unitario FROM productos WHERE id_producto = :id'
            );
            $stmtProducto->execute(['id' => $idProducto]);
            $producto = $stmtProducto->fetch();
            if (!$producto) {
                throw new InvalidArgumentException('Ese producto no existe.');
            }

            $unidadesPorPaquete = max(1, (int) $producto['unidades_por_paquete']);
            $unidadesTotales = $presentacion === 'paquete' ? $cantidad * $unidadesPorPaquete : $cantidad;
            $costoUnitarioCompra = $unidadesTotales > 0 ? $costoTotal / $unidadesTotales : 0;

            $stockActual = (int) $producto['cantidad_existente'];
            $costoActual = (float) $producto['costo_unitario'];
            $nuevoStock = $stockActual + $unidadesTotales;
            $nuevoCosto = $nuevoStock > 0
                ? (($stockActual * $costoActual) + ($unidadesTotales * $costoUnitarioCompra)) / $nuevoStock
                : 0;

            $stmtFactura = $pdo->prepare(
                'INSERT INTO facturas (fecha, hora, usuario_id, tipo_transaccion, total, proveedor_id)
                 VALUES (:fecha, :hora, :usuario_id, 3, :total, :proveedor_id)'
            );
            $stmtFactura->execute([
                'fecha' => $fecha,
                'hora' => $hora,
                'usuario_id' => $usuarioId,
                'total' => $costoTotal,
                'proveedor_id' => $proveedorId,
            ]);
            $idFactura = (int) $pdo->lastInsertId();

            $descripcion = "Compra: {$producto['nombre_producto']}" . ($costoTotal == 0 ? ' (obsequio)' : '');
            $stmtMovimiento = $pdo->prepare(
                'INSERT INTO movimiento
                    (id_factura, usuario_id, tipo_transaccion, fecha, hora, producto_id, presentacion,
                     unidades_por_paquete, cantidad, unidades_totales, precio_unitario, costo_unitario,
                     total, descripcion, afecto_stock)
                 VALUES (:id_factura, :usuario_id, 3, :fecha, :hora, :producto_id, :presentacion,
                         :unidades_por_paquete, :cantidad, :unidades_totales, 0, :costo_unitario,
                         :total, :descripcion, 1)'
            );
            $stmtMovimiento->execute([
                'id_factura' => $idFactura,
                'usuario_id' => $usuarioId,
                'fecha' => $fecha,
                'hora' => $hora,
                'producto_id' => $idProducto,
                'presentacion' => $presentacion,
                'unidades_por_paquete' => $unidadesPorPaquete,
                'cantidad' => $cantidad,
                'unidades_totales' => $unidadesTotales,
                'costo_unitario' => $costoUnitarioCompra,
                'total' => $costoTotal,
                'descripcion' => $descripcion,
            ]);

            $pdo->prepare('UPDATE productos SET cantidad_existente = :stock, costo_unitario = :costo WHERE id_producto = :id')
                ->execute(['stock' => $nuevoStock, 'costo' => $nuevoCosto, 'id' => $idProducto]);

            Sincronizacion::marcarOrigenPropio($pdo, 'facturas', 'id_factura', 'origen_id_factura', $idFactura);
            if (Sincronizacion::sincronizacionActiva()) {
                $origenFactura = Sincronizacion::origenDe($pdo, 'facturas', 'id_factura', 'origen_id_factura', $idFactura);
                $origenProducto = Sincronizacion::origenDe($pdo, 'productos', 'id_producto', 'origen_id_producto', $idProducto);
                if ($origenFactura !== null && $origenProducto !== null) {
                    Sincronizacion::registrarEventoSaliente($pdo, 'compra', $idFactura, [
                        'caja_origen' => $origenFactura['caja'],
                        'id_factura_origen' => $origenFactura['id'],
                        'producto' => $origenProducto,
                        'presentacion' => $presentacion,
                        'cantidad' => $cantidad,
                        'unidades_totales' => $unidadesTotales,
                        'costo_total' => $costoTotal,
                        'fecha' => $fecha,
                        'hora' => $hora,
                        'descripcion' => $descripcion,
                    ]);
                }
            }

            $pdo->commit();

            return $idFactura;
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /** Pérdida/daño: descuenta stock y queda registrada en facturas/movimiento (tipo 4). */
    public function registrarPerdida(int $usuarioId, int $idProducto, string $presentacion, int $cantidad, float $valorEstimado, string $descripcion = '', ?string $fecha = null): int
    {
        self::exigirExistencias();
        if ($cantidad <= 0) {
            throw new InvalidArgumentException('La cantidad debe ser mayor a cero.');
        }
        $presentacion = $presentacion === 'paquete' ? 'paquete' : 'unidad';

        $pdo = Database::conexion();
        $fecha = self::fechaRegistro($fecha);
        $hora = date('H:i:s');

        // Si ya hay una transacción abierta (ej. inventario::cerrarConteo(), que registra varias
        // pérdidas/ajustes de una sola vez), nos sumamos a esa en vez de abrir otra anidada — PDO/SQLite
        // no soportan transacciones anidadas (mismo arreglo que crearProducto()/crearCategoria()).
        $transaccionPropia = !$pdo->inTransaction();
        if ($transaccionPropia) {
            $pdo->beginTransaction();
        }
        try {
            $stmtProducto = $pdo->prepare(
                'SELECT nombre_producto, unidades_por_paquete, cantidad_existente, costo_unitario FROM productos WHERE id_producto = :id'
            );
            $stmtProducto->execute(['id' => $idProducto]);
            $producto = $stmtProducto->fetch();
            if (!$producto) {
                throw new InvalidArgumentException('Ese producto no existe.');
            }

            $unidadesPorPaquete = max(1, (int) $producto['unidades_por_paquete']);
            $unidadesTotales = $presentacion === 'paquete' ? $cantidad * $unidadesPorPaquete : $cantidad;

            if ((int) $producto['cantidad_existente'] < $unidadesTotales) {
                throw new InvalidArgumentException(
                    "No puedes reportar más pérdida de la que hay en existencia (disponible: {$producto['cantidad_existente']})."
                );
            }

            $stmtFactura = $pdo->prepare(
                'INSERT INTO facturas (fecha, hora, usuario_id, tipo_transaccion, total) VALUES (:fecha, :hora, :usuario_id, 4, :total)'
            );
            $stmtFactura->execute(['fecha' => $fecha, 'hora' => $hora, 'usuario_id' => $usuarioId, 'total' => max(0, $valorEstimado)]);
            $idFactura = (int) $pdo->lastInsertId();

            $descripcionFinal = $descripcion !== '' ? $descripcion : "Pérdida: {$producto['nombre_producto']}";
            $stmtMovimiento = $pdo->prepare(
                'INSERT INTO movimiento
                    (id_factura, usuario_id, tipo_transaccion, fecha, hora, producto_id, presentacion,
                     unidades_por_paquete, cantidad, unidades_totales, precio_unitario, costo_unitario,
                     total, descripcion, afecto_stock)
                 VALUES (:id_factura, :usuario_id, 4, :fecha, :hora, :producto_id, :presentacion,
                         :unidades_por_paquete, :cantidad, :unidades_totales, 0, :costo_unitario,
                         :total, :descripcion, 1)'
            );
            $stmtMovimiento->execute([
                'id_factura' => $idFactura,
                'usuario_id' => $usuarioId,
                'fecha' => $fecha,
                'hora' => $hora,
                'producto_id' => $idProducto,
                'presentacion' => $presentacion,
                'unidades_por_paquete' => $unidadesPorPaquete,
                'cantidad' => $cantidad,
                'unidades_totales' => $unidadesTotales,
                'costo_unitario' => (float) $producto['costo_unitario'],
                'total' => max(0, $valorEstimado),
                'descripcion' => $descripcionFinal,
            ]);

            $pdo->prepare('UPDATE productos SET cantidad_existente = cantidad_existente - :cantidad WHERE id_producto = :id')
                ->execute(['cantidad' => $unidadesTotales, 'id' => $idProducto]);

            Sincronizacion::marcarOrigenPropio($pdo, 'facturas', 'id_factura', 'origen_id_factura', $idFactura);
            if (Sincronizacion::sincronizacionActiva()) {
                $origenFactura = Sincronizacion::origenDe($pdo, 'facturas', 'id_factura', 'origen_id_factura', $idFactura);
                $origenProducto = Sincronizacion::origenDe($pdo, 'productos', 'id_producto', 'origen_id_producto', $idProducto);
                if ($origenFactura !== null && $origenProducto !== null) {
                    Sincronizacion::registrarEventoSaliente($pdo, 'perdida', $idFactura, [
                        'caja_origen' => $origenFactura['caja'],
                        'id_factura_origen' => $origenFactura['id'],
                        'producto' => $origenProducto,
                        'presentacion' => $presentacion,
                        'cantidad' => $cantidad,
                        'unidades_totales' => $unidadesTotales,
                        'valor_estimado' => max(0, $valorEstimado),
                        'fecha' => $fecha,
                        'hora' => $hora,
                        'descripcion' => $descripcionFinal,
                    ]);
                }
            }

            if ($transaccionPropia) {
                $pdo->commit();
            }

            return $idFactura;
        } catch (Throwable $e) {
            if ($transaccionPropia) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Salida o entrada de inventario que NO es compra, venta ni pérdida real — un canje/trueque
     * con otra empresa, una donación, una muestra, o un vencimiento que el negocio no quiere
     * contar como pérdida. Mueve existencia real (igual que una compra/pérdida) pero:
     *   - el total de la factura queda siempre en $0 — no representa plata que entró ni salió.
     *   - NO recalcula costo_unitario del producto (ni al salir ni al entrar), porque no hubo
     *     ninguna transacción de verdad detrás con la que promediar un costo.
     *   - tipo 7 (salida) / tipo 8 (entrada), ambos fuera de movimientos::resumenGastos(), así
     *     que nunca suman ni restan del balance de pérdidas/ganancias — solo quedan en el
     *     historial como trazabilidad.
     */
    public function registrarAjusteInventario(
        int $usuarioId,
        int $idProducto,
        string $direccion,
        string $presentacion,
        int $cantidad,
        int $idMotivo,
        string $descripcion = '',
        ?string $fecha = null
    ): int {
        self::exigirExistencias();
        if ($cantidad <= 0) {
            throw new InvalidArgumentException('La cantidad debe ser mayor a cero.');
        }
        $motivo = (new motivos_ajuste())->obtener($idMotivo);
        if ($motivo === null || (int) $motivo['estado'] !== 1) {
            throw new InvalidArgumentException('Elige un motivo válido.');
        }
        $direccion = $direccion === 'entrada' ? 'entrada' : 'salida';
        $tipo = $direccion === 'entrada' ? 8 : 7;
        $presentacion = $presentacion === 'paquete' ? 'paquete' : 'unidad';

        $pdo = Database::conexion();
        $fecha = self::fechaRegistro($fecha);
        $hora = date('H:i:s');

        // Ver el mismo comentario en registrarPerdida(): si ya hay una transacción abierta (ej.
        // cerrarConteo()), nos sumamos a esa en vez de abrir otra anidada.
        $transaccionPropia = !$pdo->inTransaction();
        if ($transaccionPropia) {
            $pdo->beginTransaction();
        }
        try {
            $stmtProducto = $pdo->prepare(
                'SELECT nombre_producto, unidades_por_paquete, cantidad_existente, costo_unitario FROM productos WHERE id_producto = :id'
            );
            $stmtProducto->execute(['id' => $idProducto]);
            $producto = $stmtProducto->fetch();
            if (!$producto) {
                throw new InvalidArgumentException('Ese producto no existe.');
            }

            $unidadesPorPaquete = max(1, (int) $producto['unidades_por_paquete']);
            $unidadesTotales = $presentacion === 'paquete' ? $cantidad * $unidadesPorPaquete : $cantidad;

            if ($direccion === 'salida' && (int) $producto['cantidad_existente'] < $unidadesTotales) {
                throw new InvalidArgumentException(
                    "No puedes dar salida a más de lo que hay en existencia (disponible: {$producto['cantidad_existente']})."
                );
            }

            $stmtFactura = $pdo->prepare(
                'INSERT INTO facturas (fecha, hora, usuario_id, tipo_transaccion, total) VALUES (:fecha, :hora, :usuario_id, :tipo, 0)'
            );
            $stmtFactura->execute(['fecha' => $fecha, 'hora' => $hora, 'usuario_id' => $usuarioId, 'tipo' => $tipo]);
            $idFactura = (int) $pdo->lastInsertId();

            $prefijo = $direccion === 'entrada' ? 'Entrada sin ganancia' : 'Salida sin pérdida';
            $descripcion = trim($descripcion);
            $descripcionFinal = "$prefijo ({$motivo['nombre']}): {$producto['nombre_producto']}" . ($descripcion !== '' ? " — $descripcion" : '');

            $stmtMovimiento = $pdo->prepare(
                'INSERT INTO movimiento
                    (id_factura, usuario_id, tipo_transaccion, fecha, hora, producto_id, presentacion,
                     unidades_por_paquete, cantidad, unidades_totales, precio_unitario, costo_unitario,
                     total, descripcion, motivo_ajuste_id, afecto_stock)
                 VALUES (:id_factura, :usuario_id, :tipo, :fecha, :hora, :producto_id, :presentacion,
                         :unidades_por_paquete, :cantidad, :unidades_totales, 0, :costo_unitario,
                         0, :descripcion, :motivo_id, 1)'
            );
            $stmtMovimiento->execute([
                'id_factura' => $idFactura,
                'usuario_id' => $usuarioId,
                'tipo' => $tipo,
                'fecha' => $fecha,
                'hora' => $hora,
                'producto_id' => $idProducto,
                'presentacion' => $presentacion,
                'unidades_por_paquete' => $unidadesPorPaquete,
                'cantidad' => $cantidad,
                'unidades_totales' => $unidadesTotales,
                'costo_unitario' => (float) $producto['costo_unitario'],
                'descripcion' => $descripcionFinal,
                'motivo_id' => $idMotivo,
            ]);

            $operador = $direccion === 'entrada' ? '+' : '-';
            $pdo->prepare("UPDATE productos SET cantidad_existente = cantidad_existente $operador :cantidad WHERE id_producto = :id")
                ->execute(['cantidad' => $unidadesTotales, 'id' => $idProducto]);

            Sincronizacion::marcarOrigenPropio($pdo, 'facturas', 'id_factura', 'origen_id_factura', $idFactura);
            if (Sincronizacion::sincronizacionActiva()) {
                $origenFactura = Sincronizacion::origenDe($pdo, 'facturas', 'id_factura', 'origen_id_factura', $idFactura);
                $origenProducto = Sincronizacion::origenDe($pdo, 'productos', 'id_producto', 'origen_id_producto', $idProducto);
                if ($origenFactura !== null && $origenProducto !== null) {
                    Sincronizacion::registrarEventoSaliente($pdo, 'ajuste_inventario', $idFactura, [
                        'caja_origen' => $origenFactura['caja'],
                        'id_factura_origen' => $origenFactura['id'],
                        'producto' => $origenProducto,
                        'direccion' => $direccion,
                        'presentacion' => $presentacion,
                        'cantidad' => $cantidad,
                        'unidades_totales' => $unidadesTotales,
                        'motivo' => $motivo['nombre'],
                        'fecha' => $fecha,
                        'hora' => $hora,
                        'descripcion' => $descripcionFinal,
                    ]);
                }
            }

            if ($transaccionPropia) {
                $pdo->commit();
            }

            return $idFactura;
        } catch (Throwable $e) {
            if ($transaccionPropia) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Compra o gasto de algo que NO es un producto del inventario (gas, luz,
     * bolsas plásticas...). No toca el stock de nadie. Queda como egreso
     * (tipo 2), igual que el "Egreso" suelto de Ventas: si se pagó en efectivo
     * y hay un turno abierto, entra al cuadre de caja (ver turnos.php).
     */
    public function registrarGastoConcepto(
        int $usuarioId,
        int $idConcepto,
        int $cantidad,
        float $valor,
        string $metodoPago = 'efectivo',
        ?int $proveedorId = null,
        string $nota = '',
        ?int $turnoId = null,
        ?string $fecha = null
    ): int {
        if ($valor <= 0) {
            throw new InvalidArgumentException('El valor pagado debe ser mayor a cero.');
        }
        $metodoPago = in_array($metodoPago, ['efectivo', 'tarjeta', 'transferencia'], true) ? $metodoPago : 'efectivo';

        return $this->registrarMovimientoConcepto(2, $usuarioId, $idConcepto, $cantidad, $valor, $nota, [
            'metodo_pago' => $metodoPago,
            'proveedor_id' => $proveedorId,
            'turno_id' => $turnoId,
            'fecha' => $fecha,
        ], 'Gasto');
    }

    /**
     * Pérdida o daño de algo que NO es un producto del inventario (se cayeron
     * los vasos, se dañó una caja de bolsas...). Queda registrada con su valor
     * (tipo 4) pero no descuenta stock ni mueve la caja: nada salió de ella.
     */
    public function registrarPerdidaConcepto(int $usuarioId, int $idConcepto, int $cantidad, float $valorEstimado, string $motivo = '', ?string $fecha = null): int
    {
        if ($valorEstimado <= 0) {
            throw new InvalidArgumentException('Indica el valor estimado de la pérdida.');
        }

        return $this->registrarMovimientoConcepto(4, $usuarioId, $idConcepto, $cantidad, $valorEstimado, $motivo, ['fecha' => $fecha], 'Pérdida');
    }

    private function registrarMovimientoConcepto(int $tipo, int $usuarioId, int $idConcepto, int $cantidad, float $valor, string $nota, array $extra, string $prefijo): int
    {
        if ($cantidad <= 0) {
            throw new InvalidArgumentException('La cantidad debe ser mayor a cero.');
        }

        $pdo = Database::conexion();
        $stmtConcepto = $pdo->prepare('SELECT nombre FROM conceptos_gasto WHERE id_concepto = :id AND estado = 1');
        $stmtConcepto->execute(['id' => $idConcepto]);
        $concepto = $stmtConcepto->fetch();
        if (!$concepto) {
            throw new InvalidArgumentException('Ese concepto no existe.');
        }

        $fecha = self::fechaRegistro($extra['fecha'] ?? null);
        $hora = date('H:i:s');
        $nota = trim($nota);
        $descripcion = "$prefijo: {$concepto['nombre']}" . ($nota !== '' ? " — $nota" : '');

        $pdo->beginTransaction();
        try {
            $pdo->prepare(
                'INSERT INTO facturas (fecha, hora, usuario_id, tipo_transaccion, total, metodo_pago, turno_id, proveedor_id)
                 VALUES (:fecha, :hora, :usuario_id, :tipo, :total, :metodo_pago, :turno_id, :proveedor_id)'
            )->execute([
                'fecha' => $fecha,
                'hora' => $hora,
                'usuario_id' => $usuarioId,
                'tipo' => $tipo,
                'total' => $valor,
                'metodo_pago' => $extra['metodo_pago'] ?? 'efectivo',
                'turno_id' => $extra['turno_id'] ?? null,
                'proveedor_id' => $extra['proveedor_id'] ?? null,
            ]);
            $idFactura = (int) $pdo->lastInsertId();

            // afecto_stock = 0: no hay producto ni existencias que tocar (ni que
            // reponer si algún día se anula).
            $pdo->prepare(
                'INSERT INTO movimiento
                    (id_factura, usuario_id, tipo_transaccion, fecha, hora, producto_id, concepto_id,
                     cantidad, unidades_totales, precio_unitario, total, descripcion, afecto_stock)
                 VALUES (:id_factura, :usuario_id, :tipo, :fecha, :hora, NULL, :concepto_id,
                         :cantidad, 0, 0, :total, :descripcion, 0)'
            )->execute([
                'id_factura' => $idFactura,
                'usuario_id' => $usuarioId,
                'tipo' => $tipo,
                'fecha' => $fecha,
                'hora' => $hora,
                'concepto_id' => $idConcepto,
                'cantidad' => $cantidad,
                'total' => $valor,
                'descripcion' => $descripcion,
            ]);

            $pdo->commit();

            return $idFactura;
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Anula un gasto (egreso, con o sin concepto) o una pérdida de un concepto
     * — los movimientos que NO tocan inventario, así que anular no tiene
     * nada que reponer. Queda marcada (motivo, quién, cuándo) y deja de contar
     * en el cuadre de caja y en los reportes; sigue visible en el listado.
     *
     * No se anulan aquí las compras ni las pérdidas de productos (esas mueven
     * el stock y el costo promedio), ni un gasto de un turno de caja ya cerrado
     * (cambiaría un cuadre que ya se entregó).
     */
    public function anularGasto(int $idFactura, int $usuarioId, string $motivo): void
    {
        $motivo = trim($motivo);
        if ($motivo === '') {
            throw new InvalidArgumentException('Indica el motivo de la anulación.');
        }

        $pdo = Database::conexion();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('SELECT * FROM facturas WHERE id_factura = :id');
            $stmt->execute(['id' => $idFactura]);
            $factura = $stmt->fetch();

            if (!$factura) {
                throw new InvalidArgumentException('Ese movimiento no existe.');
            }
            if ((int) $factura['anulada'] === 1) {
                throw new InvalidArgumentException('Este movimiento ya estaba anulado.');
            }

            $tipo = (int) $factura['tipo_transaccion'];
            $stmtProductos = $pdo->prepare('SELECT COUNT(*) FROM movimiento WHERE id_factura = :id AND producto_id IS NOT NULL');
            $stmtProductos->execute(['id' => $idFactura]);
            $tocaProductos = (int) $stmtProductos->fetchColumn() > 0;

            $esGasto = $tipo === 2;
            $esPerdidaDeConcepto = $tipo === 4 && !$tocaProductos;
            if (!$esGasto && !$esPerdidaDeConcepto) {
                throw new InvalidArgumentException(
                    'Solo se pueden anular gastos y pérdidas de conceptos. Las compras y pérdidas de productos ya movieron el inventario.'
                );
            }
            if ($esGasto && $tocaProductos) {
                throw new InvalidArgumentException('Este egreso está ligado a un producto y no se puede anular desde aquí.');
            }

            if ($factura['turno_id'] !== null) {
                $stmtTurno = $pdo->prepare('SELECT estado FROM turnos WHERE id_turno = :id');
                $stmtTurno->execute(['id' => $factura['turno_id']]);
                if ($stmtTurno->fetchColumn() === 'cerrado') {
                    throw new InvalidArgumentException('Este gasto pertenece a un turno de caja ya cerrado; no se puede anular.');
                }
            }

            $pdo->prepare(
                "UPDATE facturas
                 SET anulada = 1, anulada_motivo = :motivo, anulada_por = :usuario_id, anulada_en = datetime('now', 'localtime')
                 WHERE id_factura = :id"
            )->execute(['motivo' => $motivo, 'usuario_id' => $usuarioId, 'id' => $idFactura]);

            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Corrige el valor, la cantidad (solo si tiene concepto — un egreso suelto no la usa) y la nota de un
     * gasto o una pérdida de concepto ya registrados — para cuando se anotó mal, sin tener que anular y
     * volver a crearlo. Mismas restricciones que anularGasto(): nada que haya movido inventario, y nada
     * de un turno de caja ya cerrado (cambiaría un cuadre que ya se entregó). Queda auditado.
     */
    public function editarGasto(int $idFactura, int $usuarioId, float $valor, string $descripcion, string $motivo, ?int $cantidad = null): void
    {
        $motivo = trim($motivo);
        $descripcion = trim($descripcion);
        if ($motivo === '') {
            throw new InvalidArgumentException('Indica el motivo de la edición.');
        }
        if ($valor <= 0) {
            throw new InvalidArgumentException('El valor debe ser mayor a cero.');
        }
        if ($descripcion === '') {
            throw new InvalidArgumentException('La descripción no puede quedar vacía.');
        }

        $pdo = Database::conexion();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('SELECT * FROM facturas WHERE id_factura = :id');
            $stmt->execute(['id' => $idFactura]);
            $factura = $stmt->fetch();
            if (!$factura) {
                throw new InvalidArgumentException('Ese movimiento no existe.');
            }
            if ((int) $factura['anulada'] === 1) {
                throw new InvalidArgumentException('Este movimiento está anulado; no se puede editar.');
            }

            $stmtMov = $pdo->prepare('SELECT * FROM movimiento WHERE id_factura = :id LIMIT 1');
            $stmtMov->execute(['id' => $idFactura]);
            $movimiento = $stmtMov->fetch();
            if (!$movimiento) {
                throw new InvalidArgumentException('Ese movimiento no existe.');
            }

            $tipo = (int) $factura['tipo_transaccion'];
            $tieneConcepto = $movimiento['concepto_id'] !== null;
            $esGasto = $tipo === 2;
            $esPerdidaDeConcepto = $tipo === 4 && $movimiento['producto_id'] === null;
            if (!$esGasto && !$esPerdidaDeConcepto) {
                throw new InvalidArgumentException(
                    'Solo se pueden editar gastos y pérdidas de conceptos. Las compras y pérdidas de productos no se editan desde aquí.'
                );
            }

            if ($factura['turno_id'] !== null) {
                $stmtTurno = $pdo->prepare('SELECT estado FROM turnos WHERE id_turno = :id');
                $stmtTurno->execute(['id' => $factura['turno_id']]);
                if ($stmtTurno->fetchColumn() === 'cerrado') {
                    throw new InvalidArgumentException('Este gasto pertenece a un turno de caja ya cerrado; no se puede editar.');
                }
            }

            // La cantidad solo existe (y se puede editar) cuando el gasto tiene un concepto con cantidad —
            // un egreso suelto o una pérdida de concepto libre la ignoran, se quedan como estaban.
            $cantidadNueva = (int) $movimiento['cantidad'];
            if ($tieneConcepto) {
                if ($cantidad === null || $cantidad <= 0) {
                    throw new InvalidArgumentException('La cantidad debe ser mayor a cero.');
                }
                $cantidadNueva = $cantidad;
            }

            $valorAnterior = (float) $factura['total'];
            $cantidadAnterior = (int) $movimiento['cantidad'];
            $descripcionAnterior = (string) $movimiento['descripcion'];

            $pdo->prepare('UPDATE facturas SET total = :total WHERE id_factura = :id')
                ->execute(['total' => $valor, 'id' => $idFactura]);
            $pdo->prepare('UPDATE movimiento SET total = :total, cantidad = :cantidad, descripcion = :descripcion WHERE id_movimiento = :id')
                ->execute(['total' => $valor, 'cantidad' => $cantidadNueva, 'descripcion' => $descripcion, 'id' => $movimiento['id_movimiento']]);

            Auditoria::registrar($usuarioId, 'gasto.editar', 'factura', $idFactura, [
                'motivo' => $motivo,
                'valor_anterior' => $valorAnterior, 'valor_nuevo' => $valor,
                'cantidad_anterior' => $cantidadAnterior, 'cantidad_nueva' => $cantidadNueva,
                'descripcion_anterior' => $descripcionAnterior, 'descripcion_nueva' => $descripcion,
            ]);

            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }
}
