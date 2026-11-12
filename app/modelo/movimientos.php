<?php
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Sincronizacion.php';

class movimientos
{
    public function ventasPorFecha(string $fechaInicio, string $fechaFin): array
    {
        $pdo = Database::conexion();
        $stmt = $pdo->prepare(
            'SELECT f.id_factura, f.fecha, f.hora, f.total AS total_venta, f.ajuste, f.propina, f.anulada,
                    f.anulada_motivo, f.metodo_pago, COALESCE(f.usuario_origen_nombre, u.nombre) AS vendedor,
                    f.origen_caja_codigo, f.origen_id_factura,
                    COALESCE(SUM(m.total - m.costo_unitario * m.unidades_totales), 0) AS utilidad,
                    COALESCE((
                        SELECT SUM(md.total) FROM movimiento md
                        WHERE md.movimiento_origen_id IN (SELECT id_movimiento FROM movimiento mo WHERE mo.id_factura = f.id_factura)
                    ), 0) AS total_devuelto
             FROM facturas f
             LEFT JOIN usuarios u ON f.usuario_id = u.id_usuario
             LEFT JOIN movimiento m ON m.id_factura = f.id_factura AND m.tipo_transaccion = 1
             WHERE f.tipo_transaccion = 1 AND f.fecha BETWEEN :inicio AND :fin
             GROUP BY f.id_factura, f.fecha, f.hora, f.total, f.ajuste, f.propina, f.anulada, f.anulada_motivo, f.metodo_pago, u.nombre
             ORDER BY f.fecha DESC, f.hora DESC'
        );
        $stmt->execute(['inicio' => $fechaInicio, 'fin' => $fechaFin]);
        $filas = $stmt->fetchAll();

        // "Factura #" tal cual se muestra — el id crudo en un solo equipo, "C2-000042" con
        // varias cajas (ver Sincronizacion::numeroFactura()). Se calcula en PHP, no en SQL,
        // para mantener la consulta simple.
        foreach ($filas as &$fila) {
            $fila['numero_factura'] = Sincronizacion::numeroFactura(
                $fila['origen_caja_codigo'],
                $fila['origen_id_factura'] !== null ? (int) $fila['origen_id_factura'] : null,
                (int) $fila['id_factura']
            );
        }
        unset($fila);

        return $filas;
    }

    public function insumosPorFecha(string $fechaInicio, string $fechaFin): array
    {
        $pdo = Database::conexion();
        $stmt = $pdo->prepare(
            "SELECT f.id_factura, f.fecha, m.descripcion, f.total, tt.descripcion AS tipo_transaccion, m.id_movimiento,
                    p.nombre_producto, m.presentacion, m.cantidad, m.unidades_totales,
                    cg.nombre AS concepto, cg.categoria AS concepto_categoria,
                    f.anulada, f.anulada_motivo
             FROM facturas f
             JOIN tipo_transaccion tt ON f.tipo_transaccion = tt.id_transaccion
             JOIN movimiento m ON m.id_factura = f.id_factura
             LEFT JOIN productos p ON m.producto_id = p.id_producto
             LEFT JOIN conceptos_gasto cg ON m.concepto_id = cg.id_concepto
             WHERE f.tipo_transaccion IN (2, 3, 4, 7, 8) AND f.fecha BETWEEN :inicio AND :fin
             ORDER BY f.fecha DESC"
        );
        $stmt->execute(['inicio' => $fechaInicio, 'fin' => $fechaFin]);

        return $stmt->fetchAll();
    }

    public function facturaDetalle(int $idFactura): ?array
    {
        $pdo = Database::conexion();
        $stmt = $pdo->prepare(
            'SELECT f.id_factura, f.fecha, f.hora, f.ajuste, f.propina, f.anulada, f.anulada_motivo, f.metodo_pago,
                    f.fidelizacion_qr_url, f.origen_caja_codigo, f.origen_id_factura,
                    COALESCE(f.usuario_origen_nombre, u.nombre) AS vendedor, c.nombre AS cliente, f.cliente_id,
                    m.id_movimiento, p.nombre_producto, m.presentacion, m.unidades_por_paquete, m.cantidad,
                    m.cantidad_devuelta, m.afecto_stock, m.anulacion_destino, m.precio_unitario, m.tasa_iva, m.valor_iva, m.total, m.descuento_linea, m.descripcion,
                    COALESCE(m.profesional_origen_nombre, pr.nombre) AS profesional
             FROM facturas f
             LEFT JOIN usuarios u ON f.usuario_id = u.id_usuario
             LEFT JOIN clientes c ON f.cliente_id = c.id_cliente
             JOIN movimiento m ON m.id_factura = f.id_factura
             JOIN productos p ON m.producto_id = p.id_producto
             LEFT JOIN profesionales pr ON pr.id_profesional = m.profesional_id
             WHERE f.id_factura = :id'
        );
        $stmt->execute(['id' => $idFactura]);
        $filas = $stmt->fetchAll();

        if (empty($filas)) {
            return null;
        }

        $idsMovimiento = array_column($filas, 'id_movimiento');
        $marcadores = implode(',', array_fill(0, count($idsMovimiento), '?'));
        $stmtModificadores = $pdo->prepare(
            "SELECT id_movimiento, nombre_snapshot, precio_cobrado, incluido
             FROM movimiento_modificadores WHERE id_movimiento IN ($marcadores)
             ORDER BY id_detalle ASC"
        );
        $stmtModificadores->execute($idsMovimiento);
        $modificadoresPorMovimiento = [];
        foreach ($stmtModificadores->fetchAll() as $mod) {
            $modificadoresPorMovimiento[(int) $mod['id_movimiento']][] = $mod;
        }
        foreach ($filas as &$fila) {
            $fila['modificadores'] = $modificadoresPorMovimiento[(int) $fila['id_movimiento']] ?? [];
        }
        unset($fila);

        $subtotal = array_sum(array_map(fn ($f) => (float) $f['total'], $filas));
        $ajuste = (float) $filas[0]['ajuste'];
        $propina = (float) $filas[0]['propina'];

        $stmtPagos = $pdo->prepare('SELECT metodo_pago, valor FROM factura_pagos WHERE id_factura = :id');
        $stmtPagos->execute(['id' => $idFactura]);

        $stmtEdiciones = $pdo->prepare(
            'SELECT fe.fecha_hora, fe.ajuste_anterior, fe.ajuste_nuevo, fe.total_anterior, fe.total_nuevo, fe.motivo,
                    u.nombre AS usuario
             FROM factura_ediciones fe
             LEFT JOIN usuarios u ON fe.usuario_id = u.id_usuario
             WHERE fe.id_factura = :id
             ORDER BY fe.id_edicion ASC'
        );
        $stmtEdiciones->execute(['id' => $idFactura]);

        return [
            'id_factura' => $filas[0]['id_factura'],
            'numero_factura' => Sincronizacion::numeroFactura(
                $filas[0]['origen_caja_codigo'],
                $filas[0]['origen_id_factura'] !== null ? (int) $filas[0]['origen_id_factura'] : null,
                (int) $filas[0]['id_factura']
            ),
            'fecha' => $filas[0]['fecha'],
            'hora' => $filas[0]['hora'],
            'vendedor' => $filas[0]['vendedor'],
            'cliente' => $filas[0]['cliente'],
            'cliente_id' => $filas[0]['cliente_id'] !== null ? (int) $filas[0]['cliente_id'] : null,
            'anulada' => (bool) $filas[0]['anulada'],
            'anulada_motivo' => $filas[0]['anulada_motivo'],
            'metodo_pago' => $filas[0]['metodo_pago'],
            'fidelizacion_qr_url' => $filas[0]['fidelizacion_qr_url'],
            'pagos' => $stmtPagos->fetchAll(),
            'subtotal' => $subtotal,
            'ajuste' => $ajuste,
            'propina' => $propina,
            'total_venta' => $subtotal + $ajuste + $propina,
            'productos' => $filas,
            'ediciones' => $stmtEdiciones->fetchAll(),
        ];
    }

    public function insumoDetalle(int $idMovimiento): ?array
    {
        $pdo = Database::conexion();
        $stmt = $pdo->prepare(
            'SELECT m.id_movimiento, m.fecha, m.descripcion, m.total, tt.descripcion AS tipo_transaccion, m.id_factura,
                    p.nombre_producto, m.presentacion, m.cantidad, m.unidades_totales,
                    cg.nombre AS concepto, cg.categoria AS concepto_categoria,
                    f.anulada, f.anulada_motivo, f.id_factura AS factura_id
             FROM movimiento m
             JOIN tipo_transaccion tt ON m.tipo_transaccion = tt.id_transaccion
             LEFT JOIN productos p ON m.producto_id = p.id_producto
             LEFT JOIN conceptos_gasto cg ON m.concepto_id = cg.id_concepto
             JOIN facturas f ON f.id_factura = m.id_factura
             WHERE m.id_movimiento = :id'
        );
        $stmt->execute(['id' => $idMovimiento]);
        $fila = $stmt->fetch();

        return $fila ?: null;
    }

    /** Total vendido por día en el rango, con los días sin ventas en $0 (para que el gráfico no tenga huecos). */
    public function ventasPorDia(string $fechaInicio, string $fechaFin): array
    {
        $pdo = Database::conexion();
        $stmt = $pdo->prepare(
            'SELECT fecha, COALESCE(SUM(total), 0) AS total
             FROM facturas
             WHERE tipo_transaccion = 1 AND anulada = 0 AND fecha BETWEEN :inicio AND :fin
             GROUP BY fecha'
        );
        $stmt->execute(['inicio' => $fechaInicio, 'fin' => $fechaFin]);
        $porFecha = [];
        foreach ($stmt->fetchAll() as $fila) {
            $porFecha[$fila['fecha']] = (float) $fila['total'];
        }

        $resultado = [];
        $actual = new DateTimeImmutable($fechaInicio);
        $fin = new DateTimeImmutable($fechaFin);
        while ($actual <= $fin) {
            $fecha = $actual->format('Y-m-d');
            $resultado[] = ['fecha' => $fecha, 'total' => $porFecha[$fecha] ?? 0.0];
            $actual = $actual->modify('+1 day');
        }

        return $resultado;
    }

    /** Productos más vendidos (por valor) en el rango — unidades_totales, no cantidad, para no mezclar paquetes con unidades sueltas. */
    public function topProductosPeriodo(string $fechaInicio, string $fechaFin, int $limite = 8): array
    {
        $pdo = Database::conexion();
        $stmt = $pdo->prepare(
            'SELECT p.nombre_producto, SUM(m.unidades_totales) AS unidades, SUM(m.total) AS total
             FROM movimiento m
             JOIN productos p ON m.producto_id = p.id_producto
             JOIN facturas f ON f.id_factura = m.id_factura
             WHERE m.tipo_transaccion = 1 AND f.anulada = 0 AND f.fecha BETWEEN :inicio AND :fin
             GROUP BY m.producto_id, p.nombre_producto
             ORDER BY total DESC
             LIMIT :limite'
        );
        $stmt->bindValue(':inicio', $fechaInicio);
        $stmt->bindValue(':fin', $fechaFin);
        $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /** Total vendido en el rango vs. el mismo número de días inmediatamente anterior, con el % de variación. */
    public function comparativaPeriodos(string $fechaInicio, string $fechaFin): array
    {
        $inicio = new DateTimeImmutable($fechaInicio);
        $fin = new DateTimeImmutable($fechaFin);
        $dias = (int) $fin->diff($inicio)->days + 1;

        $finAnterior = $inicio->modify('-1 day');
        $inicioAnterior = $finAnterior->modify('-' . ($dias - 1) . ' days');

        $totalActual = $this->totalVentasPeriodo($fechaInicio, $fechaFin);
        $totalAnterior = $this->totalVentasPeriodo($inicioAnterior->format('Y-m-d'), $finAnterior->format('Y-m-d'));
        $variacion = $totalAnterior > 0 ? (($totalActual - $totalAnterior) / $totalAnterior) * 100 : null;

        return [
            'periodo_actual' => ['inicio' => $fechaInicio, 'fin' => $fechaFin, 'total' => $totalActual],
            'periodo_anterior' => ['inicio' => $inicioAnterior->format('Y-m-d'), 'fin' => $finAnterior->format('Y-m-d'), 'total' => $totalAnterior],
            'variacion_porcentaje' => $variacion,
        ];
    }

    private function totalVentasPeriodo(string $fechaInicio, string $fechaFin): float
    {
        $pdo = Database::conexion();
        $stmt = $pdo->prepare(
            'SELECT COALESCE(SUM(total), 0) FROM facturas WHERE tipo_transaccion = 1 AND anulada = 0 AND fecha BETWEEN :inicio AND :fin'
        );
        $stmt->execute(['inicio' => $fechaInicio, 'fin' => $fechaFin]);

        return (float) $stmt->fetchColumn();
    }

    /**
     * Productos con existencia que no se han vendido en los últimos $dias (o nunca) — para decidir
     * qué dar de baja, donar o dar salida por canje (ver inventario::registrarAjusteInventario()).
     * "valor_inmovilizado" es cuánto capital (a costo) está parado en ese producto, para priorizar.
     */
    public function productosRotacionLenta(int $dias = 30, int $limite = 15): array
    {
        $filas = Database::conexion()->query(
            "SELECT p.id_producto, p.nombre_producto, p.cantidad_existente, p.costo_unitario, c.descripcion AS categoria,
                    (SELECT MAX(f.fecha) FROM movimiento m
                     JOIN facturas f ON f.id_factura = m.id_factura
                     WHERE m.producto_id = p.id_producto AND m.tipo_transaccion = 1 AND f.anulada = 0) AS ultima_venta
             FROM productos p
             JOIN categorias c ON c.id_categoria = p.categoria
             WHERE p.estado = 1 AND p.controla_stock = 1 AND p.cantidad_existente > 0"
        )->fetchAll();

        $fechaLimite = date('Y-m-d', strtotime("-{$dias} days"));
        $filtrados = array_values(array_filter(
            $filas,
            fn (array $f) => $f['ultima_venta'] === null || $f['ultima_venta'] < $fechaLimite
        ));

        usort($filtrados, function (array $a, array $b) {
            if ($a['ultima_venta'] === null && $b['ultima_venta'] === null) {
                return 0;
            }
            if ($a['ultima_venta'] === null) {
                return -1; // nunca vendido = lo peor, primero
            }
            if ($b['ultima_venta'] === null) {
                return 1;
            }

            return strcmp($a['ultima_venta'], $b['ultima_venta']);
        });

        $resultado = array_slice($filtrados, 0, $limite);
        $hoy = new DateTimeImmutable(date('Y-m-d'));
        foreach ($resultado as &$fila) {
            $fila['dias_sin_vender'] = $fila['ultima_venta'] === null
                ? null
                : (int) $hoy->diff(new DateTimeImmutable($fila['ultima_venta']))->days;
            $fila['valor_inmovilizado'] = (float) $fila['cantidad_existente'] * (float) $fila['costo_unitario'];
        }
        unset($fila);

        return $resultado;
    }

    public function tiposTransaccion(): array
    {
        $pdo = Database::conexion();
        $stmt = $pdo->query('SELECT id_transaccion, descripcion FROM tipo_transaccion ORDER BY id_transaccion DESC');

        return $stmt->fetchAll();
    }

    public function resumenHoy(): array
    {
        $pdo = Database::conexion();
        $hoy = date('Y-m-d');

        $stmt = $pdo->prepare(
            'SELECT COUNT(*) AS num_ventas, COALESCE(SUM(total), 0) AS total_ventas, COALESCE(SUM(propina), 0) AS total_propinas
             FROM facturas WHERE tipo_transaccion = 1 AND anulada = 0 AND fecha = :hoy'
        );
        $stmt->execute(['hoy' => $hoy]);
        $ventas = $stmt->fetch();

        $stmtUtilidad = $pdo->prepare(
            'SELECT COALESCE(SUM(m.total - m.costo_unitario * m.unidades_totales), 0) AS utilidad
             FROM movimiento m
             JOIN facturas f ON f.id_factura = m.id_factura
             WHERE m.tipo_transaccion = 1 AND f.anulada = 0 AND m.fecha = :hoy'
        );
        $stmtUtilidad->execute(['hoy' => $hoy]);
        $utilidad = $stmtUtilidad->fetch();

        $stmtDevuelto = $pdo->prepare(
            'SELECT COALESCE(SUM(total), 0) AS total_devuelto FROM facturas WHERE tipo_transaccion = 6 AND fecha = :hoy'
        );
        $stmtDevuelto->execute(['hoy' => $hoy]);
        $devuelto = $stmtDevuelto->fetch();

        // unidades_totales (no cantidad) para no mezclar "2 paquetes" con
        // "2 unidades" en el ranking de más vendidos.
        $stmtTop = $pdo->prepare(
            'SELECT p.nombre_producto, SUM(m.unidades_totales) AS unidades
             FROM movimiento m
             JOIN productos p ON m.producto_id = p.id_producto
             JOIN facturas f ON f.id_factura = m.id_factura
             WHERE m.tipo_transaccion = 1 AND f.anulada = 0 AND m.fecha = :hoy
             GROUP BY m.producto_id, p.nombre_producto
             ORDER BY unidades DESC
             LIMIT 5'
        );
        $stmtTop->execute(['hoy' => $hoy]);

        return [
            'num_ventas' => (int) $ventas['num_ventas'],
            'total_ventas' => (float) $ventas['total_ventas'],
            'total_propinas' => (float) $ventas['total_propinas'],
            'utilidad_hoy' => (float) $utilidad['utilidad'],
            'total_devuelto' => (float) $devuelto['total_devuelto'],
            'top_productos' => $stmtTop->fetchAll(),
        ];
    }

    /** Totales de un rango de fechas — usado para comparar un período contra otro. */
    public function resumenPeriodo(string $fechaInicio, string $fechaFin): array
    {
        $pdo = Database::conexion();

        $stmtVentas = $pdo->prepare(
            'SELECT COUNT(*) AS num_ventas, COALESCE(SUM(total), 0) AS total_ventas, COALESCE(SUM(propina), 0) AS total_propinas
             FROM facturas WHERE tipo_transaccion = 1 AND anulada = 0 AND fecha BETWEEN :inicio AND :fin'
        );
        $stmtVentas->execute(['inicio' => $fechaInicio, 'fin' => $fechaFin]);
        $ventas = $stmtVentas->fetch();

        $stmtUtilidad = $pdo->prepare(
            'SELECT COALESCE(SUM(m.total - m.costo_unitario * m.unidades_totales), 0) AS utilidad,
                    COALESCE(SUM(m.descuento_linea), 0) AS total_descuentos
             FROM movimiento m
             JOIN facturas f ON f.id_factura = m.id_factura
             WHERE m.tipo_transaccion = 1 AND f.anulada = 0 AND m.fecha BETWEEN :inicio AND :fin'
        );
        $stmtUtilidad->execute(['inicio' => $fechaInicio, 'fin' => $fechaFin]);
        $utilidad = $stmtUtilidad->fetch();

        $stmtDevuelto = $pdo->prepare(
            'SELECT COALESCE(SUM(total), 0) AS total_devuelto
             FROM facturas WHERE tipo_transaccion = 6 AND fecha BETWEEN :inicio AND :fin'
        );
        $stmtDevuelto->execute(['inicio' => $fechaInicio, 'fin' => $fechaFin]);
        $devuelto = $stmtDevuelto->fetch();

        $numVentas = (int) $ventas['num_ventas'];
        $totalVentas = (float) $ventas['total_ventas'];

        return [
            'num_ventas' => $numVentas,
            'total_ventas' => $totalVentas,
            'ticket_promedio' => $numVentas > 0 ? $totalVentas / $numVentas : 0.0,
            'utilidad' => (float) $utilidad['utilidad'],
            'total_descuentos' => (float) $utilidad['total_descuentos'],
            'total_devuelto' => (float) $devuelto['total_devuelto'],
            'total_propinas' => (float) $ventas['total_propinas'],
        ];
    }

    /**
     * Resumen de gastos del período, sin contar lo anulado. "grupos" separa:
     * servicio / insumo / otro (gastos con concepto), egreso_suelto (el
     * "Egreso" de Ventas sin concepto), compra_producto (compras de
     * mercancía, type 3) y perdida (pérdidas, no mueven caja).
     * "balance" es plata que entró por ventas menos plata que salió por gastos
     * y compras (flujo simple; NO es la utilidad contable) — las pérdidas no
     * restan porque no fueron un pago.
     */
    public function resumenGastos(string $fechaInicio, string $fechaFin): array
    {
        $pdo = Database::conexion();

        $stmt = $pdo->prepare(
            "SELECT CASE WHEN f.tipo_transaccion = 3 THEN 'compra_producto'
                         WHEN f.tipo_transaccion = 4 THEN 'perdida'
                         WHEN cg.categoria IS NOT NULL THEN cg.categoria
                         ELSE 'egreso_suelto' END AS grupo,
                    COUNT(*) AS cantidad,
                    COALESCE(SUM(f.total), 0) AS total
             FROM facturas f
             LEFT JOIN conceptos_gasto cg
                    ON cg.id_concepto = (SELECT m.concepto_id FROM movimiento m WHERE m.id_factura = f.id_factura LIMIT 1)
             WHERE f.tipo_transaccion IN (2, 3, 4) AND f.anulada = 0 AND f.fecha BETWEEN :inicio AND :fin
             GROUP BY grupo"
        );
        $stmt->execute(['inicio' => $fechaInicio, 'fin' => $fechaFin]);
        $grupos = ['servicio' => ['cantidad' => 0, 'total' => 0.0], 'insumo' => ['cantidad' => 0, 'total' => 0.0],
                   'otro' => ['cantidad' => 0, 'total' => 0.0], 'egreso_suelto' => ['cantidad' => 0, 'total' => 0.0],
                   'compra_producto' => ['cantidad' => 0, 'total' => 0.0], 'perdida' => ['cantidad' => 0, 'total' => 0.0]];
        foreach ($stmt->fetchAll() as $fila) {
            $grupos[$fila['grupo']] = ['cantidad' => (int) $fila['cantidad'], 'total' => (float) $fila['total']];
        }

        // Por concepto (lo que pagaste en "Luz", en "Gas"...) — solo gastos, no pérdidas.
        $stmtConcepto = $pdo->prepare(
            "SELECT cg.nombre, cg.categoria, COUNT(*) AS cantidad, COALESCE(SUM(f.total), 0) AS total
             FROM facturas f
             JOIN movimiento m ON m.id_factura = f.id_factura
             JOIN conceptos_gasto cg ON cg.id_concepto = m.concepto_id
             WHERE f.tipo_transaccion = 2 AND f.anulada = 0 AND f.fecha BETWEEN :inicio AND :fin
             GROUP BY cg.id_concepto
             ORDER BY total DESC"
        );
        $stmtConcepto->execute(['inicio' => $fechaInicio, 'fin' => $fechaFin]);

        $stmtVentas = $pdo->prepare(
            'SELECT COALESCE(SUM(total), 0) FROM facturas WHERE tipo_transaccion = 1 AND anulada = 0 AND fecha BETWEEN :inicio AND :fin'
        );
        $stmtVentas->execute(['inicio' => $fechaInicio, 'fin' => $fechaFin]);
        $ventas = (float) $stmtVentas->fetchColumn();

        $salidas = $grupos['servicio']['total'] + $grupos['insumo']['total'] + $grupos['otro']['total']
            + $grupos['egreso_suelto']['total'] + $grupos['compra_producto']['total'];

        return [
            'grupos' => $grupos,
            'por_concepto' => $stmtConcepto->fetchAll(),
            'ventas' => $ventas,
            'gastos_y_compras' => $salidas,
            'balance' => $ventas - $salidas,
        ];
    }
}
