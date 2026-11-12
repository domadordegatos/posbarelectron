<?php
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Configuracion.php';
require_once __DIR__ . '/Promociones.php';
require_once __DIR__ . '/Modificadores.php';
require_once __DIR__ . '/Auditoria.php';
require_once __DIR__ . '/Sincronizacion.php';

class SinExistenciaException extends RuntimeException
{
}

class CupoCreditoException extends RuntimeException
{
}

class facturas
{
    /**
     * Registra una venta completa (cabecera + líneas de movimiento +
     * descuento de inventario) en una única transacción. El precio de
     * cada línea se resuelve siempre contra el catálogo — el cliente
     * nunca dicta el precio, solo qué, cuánto y en qué presentación.
     *
     * @param array $productos [{id_producto, cantidad, presentacion: 'unidad'|'paquete'}, ...]
     * @param array $pagos [{metodo_pago, valor}, ...] — si viene vacío se asume todo en efectivo.
     */
    public function registrarVenta(
        int $usuarioId,
        array $productos,
        string $descripcion,
        float $ajuste = 0,
        array $pagos = [],
        ?int $turnoId = null,
        ?int $clienteId = null,
        float $propina = 0
    ): int {
        $propina = max(0.0, $propina);
        if (empty($productos)) {
            throw new InvalidArgumentException('No hay productos para vender.');
        }

        $metodosValidos = ['efectivo', 'tarjeta', 'transferencia', 'credito'];
        $pagos = array_values(array_filter(array_map(function ($p) use ($metodosValidos) {
            $metodo = in_array($p['metodo_pago'] ?? '', $metodosValidos, true) ? $p['metodo_pago'] : null;
            $valor = (float) ($p['valor'] ?? 0);
            if ($metodo === null || $valor <= 0) {
                return null;
            }
            if ($metodo === 'credito' && !Configuracion::moduloActivo('clientes_credito')) {
                return null;
            }
            return ['metodo_pago' => $metodo, 'valor' => $valor];
        }, $pagos)));

        $tieneCredito = false;
        foreach ($pagos as $p) {
            if ($p['metodo_pago'] === 'credito') {
                $tieneCredito = true;
                break;
            }
        }
        if ($tieneCredito && $clienteId === null) {
            throw new InvalidArgumentException('Selecciona un cliente para la porción a crédito.');
        }

        $pdo = Database::conexion();
        $fecha = date('Y-m-d');
        $hora = date('H:i:s');

        $pdo->beginTransaction();
        try {
            $cliente = null;
            if ($tieneCredito) {
                $stmtCliente = $pdo->prepare('SELECT nombre, limite_credito FROM clientes WHERE id_cliente = :id AND estado = 1');
                $stmtCliente->execute(['id' => $clienteId]);
                $cliente = $stmtCliente->fetch();
                if (!$cliente) {
                    throw new InvalidArgumentException('El cliente seleccionado no existe o está inactivo.');
                }
            }

            $stmtFactura = $pdo->prepare(
                'INSERT INTO facturas (fecha, hora, usuario_id, tipo_transaccion, total, ajuste, metodo_pago, turno_id, cliente_id)
                 VALUES (:fecha, :hora, :usuario_id, 1, 0, 0, :metodo_pago, :turno_id, :cliente_id)'
            );
            $stmtFactura->execute([
                'fecha' => $fecha,
                'hora' => $hora,
                'usuario_id' => $usuarioId,
                'metodo_pago' => 'efectivo', // provisional, se corrige al final cuando se conocen los pagos reales
                'turno_id' => $turnoId,
                'cliente_id' => $clienteId,
            ]);
            $idFactura = (int) $pdo->lastInsertId();

            // Sin el módulo "existencias" (negocios de servicios) ningún producto
            // controla inventario: no se valida ni se descuenta nada, sin importar
            // cómo haya quedado guardado cada producto.
            $existenciasActivas = Configuracion::moduloActivo('existencias') ? 1 : 0;
            $stmtProducto = $pdo->prepare(
                'SELECT nombre_producto, precio_unitario, precio_paquete, unidades_por_paquete,
                        cantidad_existente, CASE WHEN ' . $existenciasActivas . ' = 1 THEN controla_stock ELSE 0 END AS controla_stock,
                        costo_unitario, tasa_iva, es_combo, se_vende_por_peso
                 FROM productos WHERE id_producto = :id'
            );
            $stmtDescontar = $pdo->prepare(
                'UPDATE productos SET cantidad_existente = cantidad_existente - :cantidad WHERE id_producto = :id'
            );
            // Módulo "profesionales": cada línea puede llevar quién la realizó; el porcentaje de
            // comisión vigente se GUARDA en la línea (si luego cambia, lo vendido no cambia).
            $profesionalesActivos = Configuracion::moduloActivo('profesionales');
            $stmtProfesional = $pdo->prepare('SELECT id_profesional, comision_porcentaje FROM profesionales WHERE id_profesional = :id');
            $stmtMovimiento = $pdo->prepare(
                'INSERT INTO movimiento
                    (id_factura, usuario_id, tipo_transaccion, fecha, hora, producto_id, presentacion,
                     unidades_por_paquete, cantidad, unidades_totales, afecto_stock, precio_unitario,
                     costo_unitario, tasa_iva, valor_iva, total, descuento_linea, peso_kg, descripcion,
                     profesional_id, comision_porcentaje, comision_valor)
                 VALUES (:id_factura, :usuario_id, 1, :fecha, :hora, :producto_id, :presentacion,
                         :unidades_por_paquete, :cantidad, :unidades_totales, :afecto_stock, :precio_unitario,
                         :costo_unitario, :tasa_iva, :valor_iva, :total, :descuento_linea, :peso_kg, :descripcion,
                         :profesional_id, :comision_porcentaje, :comision_valor)'
            );
            $stmtComponentes = $pdo->prepare(
                'SELECT producto_base_id, cantidad FROM producto_componentes WHERE producto_combo_id = :id'
            );
            $stmtDetalleCombo = $pdo->prepare(
                'INSERT INTO movimiento_combo_detalle (id_movimiento, producto_id, unidades_descontadas)
                 VALUES (:id_movimiento, :producto_id, :unidades)'
            );
            $stmtDetalleModificador = $pdo->prepare(
                'INSERT INTO movimiento_modificadores
                    (id_movimiento, modificador_id, producto_id, nombre_snapshot, precio_cobrado, incluido, afecto_stock, unidades_descontadas)
                 VALUES (:id_movimiento, :modificador_id, :producto_id, :nombre_snapshot, :precio_cobrado, :incluido, :afecto_stock, :unidades_descontadas)'
            );
            $modificadoresModelo = new Modificadores();

            $subtotal = 0.0;

            foreach ($productos as $producto) {
                $idProducto = (int) $producto['id_producto'];
                $presentacionSolicitada = $producto['presentacion'] ?? 'unidad';

                $stmtProducto->execute(['id' => $idProducto]);
                $fila = $stmtProducto->fetch();
                if (!$fila) {
                    throw new SinExistenciaException("El producto #$idProducto no existe.");
                }

                $esPorPeso = (bool) $fila['se_vende_por_peso'];
                $presentacion = $esPorPeso ? 'peso' : ($presentacionSolicitada === 'paquete' ? 'paquete' : 'unidad');
                // El peso lo manda el cajero (viene de la báscula) — nunca es
                // un número entero de "cantidad", así que se acepta con
                // decimales; todo lo demás sigue siendo unidades enteras.
                $cantidad = $esPorPeso ? round((float) ($producto['cantidad'] ?? 0), 3) : (int) $producto['cantidad'];

                if ($cantidad <= 0) {
                    throw new InvalidArgumentException('La cantidad de cada línea debe ser mayor a cero.');
                }

                $unidadesPorPaquete = max(1, (int) $fila['unidades_por_paquete']);
                $esCombo = (bool) $fila['es_combo'] && Configuracion::moduloActivo('combos');

                if ($esPorPeso) {
                    // Si el catálogo tiene un precio por kilo fijo (>0), ese
                    // manda siempre. Si lo dejó en $0 (precio variable, ej.
                    // carnes/frutas que cambian a diario), se exige el precio
                    // que escribió el cajero en esta venta puntual — es una
                    // confianza que el propio admin habilitó al dejarlo en 0,
                    // no una que el sistema le dé por su cuenta.
                    if ((float) $fila['precio_unitario'] > 0) {
                        $precioLinea = (float) $fila['precio_unitario'];
                    } else {
                        $precioLinea = max(0.0, (float) ($producto['precio_kg_manual'] ?? 0));
                        if ($precioLinea <= 0) {
                            throw new InvalidArgumentException(
                                "Indica el precio por kilo de \"{$fila['nombre_producto']}\"."
                            );
                        }
                    }
                    $unidadesTotales = 0;
                } elseif ($presentacion === 'paquete') {
                    if ($fila['precio_paquete'] === null) {
                        throw new InvalidArgumentException(
                            "\"{$fila['nombre_producto']}\" no tiene configurado un precio por paquete."
                        );
                    }
                    $precioLinea = (float) $fila['precio_paquete'];
                    $unidadesTotales = $cantidad * $unidadesPorPaquete;
                } else {
                    // Las promociones (happy hour, etc.) solo aplican a la venta
                    // por unidad — el precio de paquete ya es un precio especial
                    // deliberado, no se combina con otro descuento encima.
                    $precioLinea = Promociones::precioConPromocion($idProducto, (float) $fila['precio_unitario'])['precio'];
                    $unidadesTotales = $cantidad;
                }

                // La venta por peso no controla inventario todavía (se pesa a
                // ojo, igual que un plato preparado al momento).
                $controlaStock = !$esPorPeso && (bool) $fila['controla_stock'] && !$esCombo;

                if ($controlaStock && (int) $fila['cantidad_existente'] < $unidadesTotales) {
                    throw new SinExistenciaException(
                        "Existencia insuficiente de \"{$fila['nombre_producto']}\" (disponible: {$fila['cantidad_existente']})."
                    );
                }

                $modificadoresLinea = [];
                $extraModificadores = 0.0;
                if (Configuracion::moduloActivo('modificadores') && !empty($producto['modificadores'])) {
                    $seleccion = $modificadoresModelo->resolverSeleccion($idProducto, (array) $producto['modificadores']);
                    $modificadoresLinea = $seleccion['modificadores'];
                    $extraModificadores = $seleccion['extra'];

                    // Un modificador es un producto real — si controla stock
                    // (ej. un empaque cerrado, no un helado servido a ojo),
                    // se valida y descuenta exactamente igual que un
                    // componente de combo, escalado por las unidades de esta línea.
                    foreach ($modificadoresLinea as $modLinea) {
                        if ($modLinea['controla_stock'] && $modLinea['cantidad_existente'] < $unidadesTotales) {
                            throw new SinExistenciaException(
                                "Existencia insuficiente de \"{$modLinea['nombre']}\" (disponible: {$modLinea['cantidad_existente']})."
                            );
                        }
                    }
                }

                $totalLinea = ($precioLinea + $extraModificadores) * $cantidad;

                // Descuento manual puntual a esta línea (ej. "esta empanada
                // de ayer al 50%") — el tipo/valor lo manda el cliente, pero
                // el monto real siempre se calcula aquí sobre el total ya
                // resuelto en el servidor, nunca sobre un total que mande él.
                $descuentoTipo = in_array($producto['descuento_tipo'] ?? null, ['porcentaje', 'fijo'], true)
                    ? $producto['descuento_tipo'] : null;
                $descuentoValor = max(0.0, (float) ($producto['descuento_valor'] ?? 0));
                $descuentoLinea = 0.0;
                if ($descuentoTipo === 'porcentaje' && $descuentoValor > 0) {
                    $descuentoLinea = $totalLinea * (min(100, $descuentoValor) / 100);
                } elseif ($descuentoTipo === 'fijo' && $descuentoValor > 0) {
                    $descuentoLinea = min($descuentoValor, $totalLinea);
                }
                $totalLinea -= $descuentoLinea;

                $subtotal += $totalLinea;

                $tasaIva = Configuracion::moduloActivo('iva') ? (float) $fila['tasa_iva'] : 0.0;
                $valorIva = $tasaIva > 0 ? $totalLinea - ($totalLinea / (1 + $tasaIva / 100)) : 0.0;

                $costoUnitarioLinea = (float) $fila['costo_unitario'];
                if ($esCombo) {
                    $stmtComponentes->execute(['id' => $idProducto]);
                    $componentes = $stmtComponentes->fetchAll();
                    $costoUnitarioLinea = 0.0;
                    foreach ($componentes as $comp) {
                        $stmtProducto->execute(['id' => (int) $comp['producto_base_id']]);
                        $base = $stmtProducto->fetch();
                        if ($base) {
                            $costoUnitarioLinea += (float) $base['costo_unitario'] * (int) $comp['cantidad'];
                        }
                    }
                }
                // El costo de cada modificador elegido (gratis o cobrado) se
                // suma igual — el costo se incurre sin importar si se le
                // cobró o no al cliente, así el reporte de utilidad no
                // queda inflado por los "incluidos gratis".
                foreach ($modificadoresLinea as $modLinea) {
                    $costoUnitarioLinea += $modLinea['costo_unitario'];
                }

                // Comisión de esta línea (sobre lo que realmente se cobra por ella, ya con su descuento).
                $profesionalId = null;
                $comisionPorcentaje = 0.0;
                $comisionValor = 0.0;
                $idProfesionalPedido = (int) ($producto['profesional_id'] ?? 0);
                if ($profesionalesActivos && $idProfesionalPedido > 0) {
                    $stmtProfesional->execute(['id' => $idProfesionalPedido]);
                    $filaProfesional = $stmtProfesional->fetch();
                    if (!$filaProfesional) {
                        throw new InvalidArgumentException('El profesional elegido ya no existe.');
                    }
                    $profesionalId = (int) $filaProfesional['id_profesional'];
                    $comisionPorcentaje = (float) $filaProfesional['comision_porcentaje'];
                    $comisionValor = round($totalLinea * $comisionPorcentaje / 100, 2);
                }

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
                    'afecto_stock' => $controlaStock ? 1 : 0,
                    'precio_unitario' => $precioLinea,
                    'costo_unitario' => $costoUnitarioLinea,
                    'tasa_iva' => $tasaIva,
                    'valor_iva' => $valorIva,
                    'total' => $totalLinea,
                    'descuento_linea' => $descuentoLinea,
                    'peso_kg' => $esPorPeso ? $cantidad : null,
                    'descripcion' => $descripcion,
                    'profesional_id' => $profesionalId,
                    'comision_porcentaje' => $comisionPorcentaje,
                    'comision_valor' => $comisionValor,
                ]);
                $idMovimiento = (int) $pdo->lastInsertId();
                Sincronizacion::marcarOrigenPropio($pdo, 'movimiento', 'id_movimiento', 'origen_id_movimiento', $idMovimiento);

                foreach ($modificadoresLinea as $modLinea) {
                    $stmtDetalleModificador->execute([
                        'id_movimiento' => $idMovimiento,
                        'modificador_id' => $modLinea['id_modificador'],
                        'producto_id' => $modLinea['producto_id'],
                        'nombre_snapshot' => $modLinea['nombre'],
                        'precio_cobrado' => $modLinea['precio_cobrado'],
                        'incluido' => $modLinea['incluido'] ? 1 : 0,
                        'afecto_stock' => $modLinea['controla_stock'] ? 1 : 0,
                        'unidades_descontadas' => $modLinea['controla_stock'] ? $unidadesTotales : 0,
                    ]);
                    if ($modLinea['controla_stock']) {
                        $stmtDescontar->execute(['cantidad' => $unidadesTotales, 'id' => $modLinea['producto_id']]);
                    }
                }

                if ($controlaStock) {
                    $stmtDescontar->execute(['cantidad' => $unidadesTotales, 'id' => $idProducto]);
                }

                if ($esCombo) {
                    $stmtComponentes->execute(['id' => $idProducto]);
                    $componentes = $stmtComponentes->fetchAll();
                    foreach ($componentes as $comp) {
                        $idBase = (int) $comp['producto_base_id'];
                        $unidadesComponente = (int) $comp['cantidad'] * $unidadesTotales;

                        $stmtProducto->execute(['id' => $idBase]);
                        $base = $stmtProducto->fetch();
                        if (!$base) {
                            throw new SinExistenciaException("Un componente del combo \"{$fila['nombre_producto']}\" ya no existe.");
                        }
                        $baseControlaStock = (bool) $base['controla_stock'];

                        if ($baseControlaStock && (int) $base['cantidad_existente'] < $unidadesComponente) {
                            throw new SinExistenciaException(
                                "Existencia insuficiente de \"{$base['nombre_producto']}\" para armar \"{$fila['nombre_producto']}\" (disponible: {$base['cantidad_existente']})."
                            );
                        }

                        if ($baseControlaStock) {
                            $stmtDescontar->execute(['cantidad' => $unidadesComponente, 'id' => $idBase]);
                            $stmtDetalleCombo->execute([
                                'id_movimiento' => $idMovimiento,
                                'producto_id' => $idBase,
                                'unidades' => $unidadesComponente,
                            ]);
                        }
                    }
                }
            }

            // La propina se suma DESPUÉS del ajuste — es un valor aparte que
            // el cliente decide dar, no un descuento/recargo sobre el precio
            // de los productos; se cobra igual (por cualquier método) y
            // queda registrada por separado para poder reportarla sola.
            $totalFinal = $subtotal + $ajuste + $propina;
            if ($totalFinal < 0) {
                throw new InvalidArgumentException('El ajuste no puede dejar el total de la venta en negativo.');
            }

            if (empty($pagos)) {
                $pagos = [['metodo_pago' => 'efectivo', 'valor' => $totalFinal]];
            }

            $sumaPagos = array_sum(array_column($pagos, 'valor'));
            if (abs($sumaPagos - $totalFinal) > 1) {
                throw new InvalidArgumentException(
                    'La suma de los pagos (' . number_format($sumaPagos, 0, ',', '.') .
                    ') no coincide con el total a cobrar (' . number_format($totalFinal, 0, ',', '.') . ').'
                );
            }

            $valorCredito = 0.0;
            foreach ($pagos as $p) {
                if ($p['metodo_pago'] === 'credito') {
                    $valorCredito += $p['valor'];
                }
            }

            if ($valorCredito > 0 && (float) $cliente['limite_credito'] > 0) {
                $stmtSaldo = $pdo->prepare(
                    "SELECT
                        COALESCE((SELECT SUM(fp.valor) FROM factura_pagos fp
                                  JOIN facturas f2 ON f2.id_factura = fp.id_factura
                                  WHERE f2.cliente_id = :cliente_id1 AND fp.metodo_pago = 'credito'
                                    AND f2.anulada = 0 AND f2.id_factura != :id_factura), 0)
                        - COALESCE((SELECT SUM(total) FROM facturas
                                    WHERE cliente_id = :cliente_id2 AND tipo_transaccion = 5), 0) AS saldo"
                );
                $stmtSaldo->execute([
                    'cliente_id1' => $clienteId,
                    'id_factura' => $idFactura,
                    'cliente_id2' => $clienteId,
                ]);
                $saldoActual = (float) $stmtSaldo->fetch()['saldo'];

                if ($saldoActual + $valorCredito > (float) $cliente['limite_credito']) {
                    throw new CupoCreditoException(
                        "\"{$cliente['nombre']}\" no tiene cupo suficiente (debe " .
                        number_format($saldoActual, 0, ',', '.') . ', límite ' .
                        number_format((float) $cliente['limite_credito'], 0, ',', '.') . ').'
                    );
                }
            }

            $metodoResumen = count($pagos) === 1 ? $pagos[0]['metodo_pago'] : 'mixto';

            $stmtPago = $pdo->prepare(
                'INSERT INTO factura_pagos (id_factura, metodo_pago, valor) VALUES (:id_factura, :metodo, :valor)'
            );
            foreach ($pagos as $p) {
                $stmtPago->execute(['id_factura' => $idFactura, 'metodo' => $p['metodo_pago'], 'valor' => $p['valor']]);
            }

            $stmtActualizarFactura = $pdo->prepare(
                'UPDATE facturas SET total = :total, ajuste = :ajuste, propina = :propina, metodo_pago = :metodo WHERE id_factura = :id'
            );
            $stmtActualizarFactura->execute([
                'total' => $totalFinal,
                'ajuste' => $ajuste,
                'propina' => $propina,
                'metodo' => $metodoResumen,
                'id' => $idFactura,
            ]);

            Sincronizacion::marcarOrigenPropio($pdo, 'facturas', 'id_factura', 'origen_id_factura', $idFactura);
            $this->encolarEventoVenta($pdo, $idFactura, $usuarioId);

            $pdo->commit();

            return $idFactura;
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Arma y encola el evento "venta" para las demás cajas — se llama justo antes del commit
     * de registrarVenta(), cuando la factura y sus líneas ya están completas y con su origen
     * marcado. No-op si esta caja no está sincronizando (ver Sincronizacion::registrarEventoSaliente()).
     */
    private function encolarEventoVenta(PDO $pdo, int $idFactura, int $usuarioId): void
    {
        if (!Sincronizacion::sincronizacionActiva()) {
            return;
        }

        $stmtFactura = $pdo->prepare('SELECT * FROM facturas WHERE id_factura = :id');
        $stmtFactura->execute(['id' => $idFactura]);
        $f = $stmtFactura->fetch();

        $stmtUsuario = $pdo->prepare('SELECT nombre FROM usuarios WHERE id_usuario = :id');
        $stmtUsuario->execute(['id' => $usuarioId]);
        $nombreUsuario = $stmtUsuario->fetchColumn() ?: null;

        $clienteOrigen = $f['cliente_id'] !== null
            ? Sincronizacion::origenDe($pdo, 'clientes', 'id_cliente', 'origen_id_cliente', (int) $f['cliente_id'])
            : null;

        $stmtPagos = $pdo->prepare('SELECT metodo_pago, valor FROM factura_pagos WHERE id_factura = :id');
        $stmtPagos->execute(['id' => $idFactura]);

        $stmtLineas = $pdo->prepare('SELECT * FROM movimiento WHERE id_factura = :id ORDER BY id_movimiento');
        $stmtLineas->execute(['id' => $idFactura]);
        $lineas = [];
        foreach ($stmtLineas->fetchAll() as $linea) {
            $productoOrigen = $linea['producto_id'] !== null
                ? Sincronizacion::origenDe($pdo, 'productos', 'id_producto', 'origen_id_producto', (int) $linea['producto_id'])
                : null;

            $profesionalNombre = null;
            if ($linea['profesional_id'] !== null) {
                $stmtProf = $pdo->prepare('SELECT nombre FROM profesionales WHERE id_profesional = :id');
                $stmtProf->execute(['id' => $linea['profesional_id']]);
                $profesionalNombre = $stmtProf->fetchColumn() ?: null;
            }

            $stmtCombo = $pdo->prepare('SELECT producto_id, unidades_descontadas FROM movimiento_combo_detalle WHERE id_movimiento = :id');
            $stmtCombo->execute(['id' => $linea['id_movimiento']]);
            $combo = [];
            foreach ($stmtCombo->fetchAll() as $c) {
                $combo[] = [
                    'producto' => Sincronizacion::origenDe($pdo, 'productos', 'id_producto', 'origen_id_producto', (int) $c['producto_id']),
                    'unidades_descontadas' => (int) $c['unidades_descontadas'],
                ];
            }

            $stmtMod = $pdo->prepare('SELECT * FROM movimiento_modificadores WHERE id_movimiento = :id');
            $stmtMod->execute(['id' => $linea['id_movimiento']]);
            $modificadores = [];
            foreach ($stmtMod->fetchAll() as $m) {
                $modificadores[] = [
                    'producto' => $m['producto_id'] !== null ? Sincronizacion::origenDe($pdo, 'productos', 'id_producto', 'origen_id_producto', (int) $m['producto_id']) : null,
                    'nombre_snapshot' => $m['nombre_snapshot'],
                    'precio_cobrado' => (float) $m['precio_cobrado'],
                    'incluido' => (bool) $m['incluido'],
                    'afecto_stock' => (bool) $m['afecto_stock'],
                    'unidades_descontadas' => (int) $m['unidades_descontadas'],
                ];
            }

            $lineas[] = [
                'id_movimiento_origen' => (int) $linea['origen_id_movimiento'],
                'producto' => $productoOrigen,
                'presentacion' => $linea['presentacion'],
                'unidades_por_paquete' => (int) $linea['unidades_por_paquete'],
                'cantidad' => (float) $linea['cantidad'],
                'unidades_totales' => (float) $linea['unidades_totales'],
                'afecto_stock' => (bool) $linea['afecto_stock'],
                'precio_unitario' => (float) $linea['precio_unitario'],
                'costo_unitario' => (float) $linea['costo_unitario'],
                'tasa_iva' => (float) $linea['tasa_iva'],
                'valor_iva' => (float) $linea['valor_iva'],
                'total' => (float) $linea['total'],
                'descuento_linea' => (float) $linea['descuento_linea'],
                'peso_kg' => $linea['peso_kg'],
                'descripcion' => $linea['descripcion'],
                'profesional_origen_nombre' => $profesionalNombre,
                'comision_porcentaje' => (float) $linea['comision_porcentaje'],
                'comision_valor' => (float) $linea['comision_valor'],
                'combo_detalle' => $combo,
                'modificadores' => $modificadores,
            ];
        }

        Sincronizacion::registrarEventoSaliente($pdo, 'venta', $idFactura, [
            'caja_origen' => Sincronizacion::obtenerCajaCodigo(),
            'id_factura_origen' => (int) $f['origen_id_factura'],
            'fecha' => $f['fecha'],
            'hora' => $f['hora'],
            'total' => (float) $f['total'],
            'ajuste' => (float) $f['ajuste'],
            'propina' => (float) $f['propina'],
            'metodo_pago' => $f['metodo_pago'],
            'usuario_origen_nombre' => $nombreUsuario,
            'cliente' => $clienteOrigen,
            'pagos' => array_map(fn($p) => ['metodo_pago' => $p['metodo_pago'], 'valor' => (float) $p['valor']], $stmtPagos->fetchAll()),
            'lineas' => $lineas,
        ]);
    }

    /**
     * Anula una venta ya registrada: repone el inventario que sí se había
     * descontado (incluidos los componentes de combos) y marca la factura
     * como anulada.
     */
    public function anularVenta(int $idFactura, int $usuarioId, string $motivo, array $descartar = []): void
    {
        $pdo = Database::conexion();

        $pdo->beginTransaction();
        try {
            $stmtFactura = $pdo->prepare('SELECT * FROM facturas WHERE id_factura = :id');
            $stmtFactura->execute(['id' => $idFactura]);
            $factura = $stmtFactura->fetch();

            if (!$factura) {
                throw new InvalidArgumentException('Esa factura no existe.');
            }
            if ((int) $factura['tipo_transaccion'] !== 1) {
                throw new InvalidArgumentException('Solo se pueden anular ventas.');
            }
            if ((int) $factura['anulada'] === 1) {
                throw new InvalidArgumentException('Esta venta ya estaba anulada.');
            }

            $stmtLineas = $pdo->prepare(
                'SELECT id_movimiento, producto_id, cantidad, cantidad_devuelta, unidades_totales, afecto_stock, presentacion,
                        unidades_por_paquete, costo_unitario FROM movimiento WHERE id_factura = :id'
            );
            $stmtLineas->execute(['id' => $idFactura]);
            $lineas = $stmtLineas->fetchAll();
            $descartar = array_map('intval', $descartar);

            $stmtReponer = $pdo->prepare(
                'UPDATE productos SET cantidad_existente = cantidad_existente + :cantidad WHERE id_producto = :id'
            );
            $stmtDetalleCombo = $pdo->prepare(
                'SELECT producto_id, unidades_descontadas FROM movimiento_combo_detalle WHERE id_movimiento = :id'
            );
            $stmtDetalleModificador = $pdo->prepare(
                'SELECT producto_id, unidades_descontadas FROM movimiento_modificadores WHERE id_movimiento = :id AND afecto_stock = 1'
            );
            $stmtDestino = $pdo->prepare('UPDATE movimiento SET anulacion_destino = :destino WHERE id_movimiento = :id');
            $lineasDescartadas = [];
            foreach ($lineas as $linea) {
                $idLinea = (int) $linea['id_movimiento'];
                // Lo ya devuelto antes de anular no se repone otra vez (la devolución ya decidió su destino).
                $cantidadLinea = (float) $linea['cantidad'];
                $factor = $cantidadLinea > 0 ? max(0.0, ($cantidadLinea - (float) $linea['cantidad_devuelta']) / $cantidadLinea) : 1.0;
                $descarta = in_array($idLinea, $descartar, true);
                $unidadesRestantes = round((float) $linea['unidades_totales'] * $factor, 3);

                if ((int) $linea['afecto_stock'] === 1 && $linea['producto_id'] !== null && $factor > 0) {
                    if ($descarta) {
                        $stmtDestino->execute(['destino' => 'descartado', 'id' => $idLinea]);
                        $lineasDescartadas[] = $idLinea;
                        $this->registrarDescarteDeAnulacion($pdo, $usuarioId, $idFactura, $linea, $unidadesRestantes);
                        continue;
                    }
                    $stmtDestino->execute(['destino' => 'inventario', 'id' => $idLinea]);
                    $stmtReponer->execute(['cantidad' => $unidadesRestantes, 'id' => (int) $linea['producto_id']]);
                }

                $stmtDetalleCombo->execute(['id' => $idLinea]);
                foreach ($stmtDetalleCombo->fetchAll() as $detalle) {
                    $stmtReponer->execute(['cantidad' => round((float) $detalle['unidades_descontadas'] * $factor, 3), 'id' => (int) $detalle['producto_id']]);
                }

                $stmtDetalleModificador->execute(['id' => $idLinea]);
                foreach ($stmtDetalleModificador->fetchAll() as $detalle) {
                    $stmtReponer->execute(['cantidad' => round((float) $detalle['unidades_descontadas'] * $factor, 3), 'id' => (int) $detalle['producto_id']]);
                }
            }

            $stmtAnular = $pdo->prepare(
                "UPDATE facturas
                 SET anulada = 1, anulada_motivo = :motivo, anulada_por = :usuario_id, anulada_en = datetime('now', 'localtime')
                 WHERE id_factura = :id"
            );
            $stmtAnular->execute(['motivo' => $motivo, 'usuario_id' => $usuarioId, 'id' => $idFactura]);

            // Una venta anulada puede ser una que llegó sincronizada de otra caja (ya tiene
            // origen) o una hecha aquí mismo (marcarOrigenPropio no pisa el origen si ya lo
            // tenía — es idempotente). "caja_origen" del evento es el origen de LA FACTURA
            // anulada, no necesariamente esta caja: así el destino sabe cuál factura anular
            // sin importar dónde se hizo la venta originalmente.
            Sincronizacion::marcarOrigenPropio($pdo, 'facturas', 'id_factura', 'origen_id_factura', $idFactura);
            if (Sincronizacion::sincronizacionActiva()) {
                $origenFactura = Sincronizacion::origenDe($pdo, 'facturas', 'id_factura', 'origen_id_factura', $idFactura);
                if ($origenFactura !== null) {
                    $stmtUsuario = $pdo->prepare('SELECT nombre FROM usuarios WHERE id_usuario = :id');
                    $stmtUsuario->execute(['id' => $usuarioId]);
                    Sincronizacion::registrarEventoSaliente($pdo, 'venta_anulada', $idFactura, [
                        'caja_origen' => $origenFactura['caja'],
                        'id_factura_origen' => $origenFactura['id'],
                        'motivo' => $motivo,
                        'anulada_por_nombre' => $stmtUsuario->fetchColumn() ?: null,
                        'lineas_descartadas' => array_values(array_filter(array_map(
                            fn($idLinea) => Sincronizacion::origenDe($pdo, 'movimiento', 'id_movimiento', 'origen_id_movimiento', $idLinea),
                            $lineasDescartadas
                        ))),
                    ]);
                }
            }

            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Una línea descartada al anular NO vuelve a existencias: queda como pérdida (sin mover stock,
     * porque el producto nunca se repuso) para que el costo perdido aparezca en los reportes.
     */
    private function registrarDescarteDeAnulacion(PDO $pdo, int $usuarioId, int $idFacturaAnulada, array $linea, float $unidades): void
    {
        $costo = (float) $linea['costo_unitario'];
        $valor = round($costo * $unidades, 2);
        $fecha = date('Y-m-d');
        $hora = date('H:i:s');
        $nombre = $pdo->prepare('SELECT nombre_producto FROM productos WHERE id_producto = :id');
        $nombre->execute(['id' => (int) $linea['producto_id']]);
        $descripcion = 'Descartado al anular la venta #' . $idFacturaAnulada . ': ' . ($nombre->fetchColumn() ?: 'producto');

        $pdo->prepare('INSERT INTO facturas (fecha, hora, usuario_id, tipo_transaccion, total) VALUES (:f, :h, :u, 4, :t)')
            ->execute(['f' => $fecha, 'h' => $hora, 'u' => $usuarioId, 't' => $valor]);
        $idFactura = (int) $pdo->lastInsertId();
        $pdo->prepare(
            'INSERT INTO movimiento
                (id_factura, usuario_id, tipo_transaccion, fecha, hora, producto_id, presentacion, unidades_por_paquete,
                 cantidad, unidades_totales, precio_unitario, costo_unitario, total, descripcion, afecto_stock)
             VALUES (:idf, :u, 4, :f, :h, :p, :pres, :upp, :cant, :ut, 0, :costo, :t, :d, 0)'
        )->execute([
            'idf' => $idFactura, 'u' => $usuarioId, 'f' => $fecha, 'h' => $hora, 'p' => (int) $linea['producto_id'],
            'pres' => $linea['presentacion'], 'upp' => max(1, (int) $linea['unidades_por_paquete']),
            'cant' => $unidades, 'ut' => $unidades, 'costo' => $costo, 't' => $valor, 'd' => $descripcion,
        ]);

        Sincronizacion::marcarOrigenPropio($pdo, 'facturas', 'id_factura', 'origen_id_factura', $idFactura);
        if (Sincronizacion::sincronizacionActiva()) {
            $origenFactura = Sincronizacion::origenDe($pdo, 'facturas', 'id_factura', 'origen_id_factura', $idFactura);
            $origenProducto = Sincronizacion::origenDe($pdo, 'productos', 'id_producto', 'origen_id_producto', (int) $linea['producto_id']);
            if ($origenFactura !== null && $origenProducto !== null) {
                Sincronizacion::registrarEventoSaliente($pdo, 'perdida', $idFactura, [
                    'caja_origen' => $origenFactura['caja'],
                    'id_factura_origen' => $origenFactura['id'],
                    'producto' => $origenProducto,
                    'presentacion' => $linea['presentacion'],
                    'cantidad' => $unidades,
                    'unidades_totales' => $unidades,
                    'valor_estimado' => $valor,
                    'afecto_stock' => false,
                    'fecha' => $fecha,
                    'hora' => $hora,
                    'descripcion' => $descripcion,
                ]);
            }
        }
    }

    /**
     * Corrige a qué cliente pertenece una venta ya registrada (o la deja sin cliente). Sirve para
     * reparar ventas que quedaron a nombre de otra persona — por ejemplo las hechas con un cliente
     * creado al vuelo en Ventas, que por un error quedaban en el cliente número 1. Como el saldo de
     * cada cliente se calcula a partir de sus ventas a crédito, la deuda se mueve sola con la venta.
     * No cambia dinero, inventario ni totales; deja constancia en la auditoría (quién, cuándo,
     * cliente anterior, cliente nuevo y motivo).
     */
    public function cambiarClienteDeVenta(int $idFactura, ?int $idClienteNuevo, int $usuarioId, string $motivo): void
    {
        $motivo = trim($motivo);
        if ($motivo === '') {
            throw new InvalidArgumentException('Indica el motivo del cambio.');
        }

        $pdo = Database::conexion();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('SELECT id_factura, tipo_transaccion, anulada, cliente_id FROM facturas WHERE id_factura = :id');
            $stmt->execute(['id' => $idFactura]);
            $factura = $stmt->fetch();
            if (!$factura) {
                throw new InvalidArgumentException('Esa factura no existe.');
            }
            if ((int) $factura['tipo_transaccion'] !== 1) {
                throw new InvalidArgumentException('Solo se puede cambiar el cliente de una venta.');
            }
            if ((int) $factura['anulada'] === 1) {
                throw new InvalidArgumentException('Esa venta está anulada.');
            }

            $nombres = ['anterior' => null, 'nuevo' => null];
            $stmtCliente = $pdo->prepare('SELECT nombre FROM clientes WHERE id_cliente = :id');
            if ($factura['cliente_id'] !== null) {
                $stmtCliente->execute(['id' => $factura['cliente_id']]);
                $nombres['anterior'] = $stmtCliente->fetchColumn() ?: null;
            }
            if ($idClienteNuevo !== null) {
                $stmtCliente->execute(['id' => $idClienteNuevo]);
                $nombres['nuevo'] = $stmtCliente->fetchColumn();
                if ($nombres['nuevo'] === false) {
                    throw new InvalidArgumentException('Ese cliente no existe.');
                }
            }
            if (($factura['cliente_id'] === null ? null : (int) $factura['cliente_id']) === $idClienteNuevo) {
                throw new InvalidArgumentException('Esa venta ya está a nombre de ese cliente.');
            }

            // Una venta con parte a crédito necesita un cliente: sin él, esa deuda quedaría sin dueño.
            if ($idClienteNuevo === null) {
                $stmtCredito = $pdo->prepare("SELECT COUNT(*) FROM factura_pagos WHERE id_factura = :id AND metodo_pago = 'credito'");
                $stmtCredito->execute(['id' => $idFactura]);
                if ((int) $stmtCredito->fetchColumn() > 0) {
                    throw new InvalidArgumentException('Esta venta tiene una parte a crédito: debe quedar a nombre de un cliente.');
                }
            }

            $pdo->prepare('UPDATE facturas SET cliente_id = :cliente WHERE id_factura = :id')
                ->execute(['cliente' => $idClienteNuevo, 'id' => $idFactura]);

            Auditoria::registrar($usuarioId, 'factura.cambiar_cliente', 'facturas', $idFactura, [
                'cliente_anterior' => ['id' => $factura['cliente_id'] !== null ? (int) $factura['cliente_id'] : null, 'nombre' => $nombres['anterior']],
                'cliente_nuevo' => ['id' => $idClienteNuevo, 'nombre' => $nombres['nuevo'] ?: null],
                'motivo' => $motivo,
            ]);

            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Cambia el ajuste (y por lo tanto el total) de una venta ya generada,
     * dejando constancia en factura_ediciones de quién, cuándo, los valores
     * anteriores y el motivo. El subtotal se recalcula siempre a partir de
     * las líneas reales de movimiento — nunca se confía en un total enviado
     * por el cliente.
     */
    public function editarFactura(int $idFactura, int $usuarioId, float $nuevoAjuste, string $motivo): array
    {
        if (trim($motivo) === '') {
            throw new InvalidArgumentException('Indica el motivo de la edición.');
        }

        $pdo = Database::conexion();

        $pdo->beginTransaction();
        try {
            $stmtFactura = $pdo->prepare('SELECT * FROM facturas WHERE id_factura = :id');
            $stmtFactura->execute(['id' => $idFactura]);
            $factura = $stmtFactura->fetch();

            if (!$factura) {
                throw new InvalidArgumentException('Esa factura no existe.');
            }
            if ((int) $factura['tipo_transaccion'] !== 1) {
                throw new InvalidArgumentException('Solo se pueden editar facturas de venta.');
            }
            if ((int) $factura['anulada'] === 1) {
                throw new InvalidArgumentException('Esta venta está anulada.');
            }

            $stmtSubtotal = $pdo->prepare(
                "SELECT COALESCE(SUM(total), 0) FROM movimiento WHERE id_factura = :id AND tipo_transaccion = 1"
            );
            $stmtSubtotal->execute(['id' => $idFactura]);
            $subtotal = (float) $stmtSubtotal->fetchColumn();

            $totalAnterior = (float) $factura['total'];
            $ajusteAnterior = (float) $factura['ajuste'];
            // La propina no se toca aquí (solo se edita el ajuste) pero debe
            // seguir contando en el total — si no, editar el ajuste borraría
            // la propina del total sin que nadie lo pidiera.
            $propina = (float) $factura['propina'];
            $totalNuevo = $subtotal + $nuevoAjuste + $propina;

            if ($totalNuevo < 0) {
                throw new InvalidArgumentException('El ajuste no puede dejar el total de la venta en negativo.');
            }

            $pdo->prepare('UPDATE facturas SET total = :total, ajuste = :ajuste WHERE id_factura = :id')
                ->execute(['total' => $totalNuevo, 'ajuste' => $nuevoAjuste, 'id' => $idFactura]);

            $pdo->prepare(
                'INSERT INTO factura_ediciones
                    (id_factura, usuario_id, ajuste_anterior, ajuste_nuevo, total_anterior, total_nuevo, motivo)
                 VALUES (:id_factura, :usuario_id, :ajuste_anterior, :ajuste_nuevo, :total_anterior, :total_nuevo, :motivo)'
            )->execute([
                'id_factura' => $idFactura,
                'usuario_id' => $usuarioId,
                'ajuste_anterior' => $ajusteAnterior,
                'ajuste_nuevo' => $nuevoAjuste,
                'total_anterior' => $totalAnterior,
                'total_nuevo' => $totalNuevo,
                'motivo' => trim($motivo),
            ]);

            $pdo->commit();

            return ['total' => $totalNuevo, 'ajuste' => $nuevoAjuste];
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Devuelve parte (o el resto) de una línea de venta ya facturada.
     * Repone inventario (incluidos componentes de combo si aplica) y deja
     * constancia como una factura tipo "devolución" enlazada a la línea
     * original. Puede llamarse varias veces sobre la misma línea mientras
     * quede cantidad pendiente por devolver.
     */
    public function devolverLinea(int $idMovimiento, int $usuarioId, float $cantidad, string $motivo): int
    {
        if ($cantidad <= 0) {
            throw new InvalidArgumentException('La cantidad a devolver debe ser mayor a cero.');
        }

        $pdo = Database::conexion();
        $fecha = date('Y-m-d');
        $hora = date('H:i:s');

        $pdo->beginTransaction();
        try {
            $stmtLinea = $pdo->prepare(
                'SELECT m.*, f.tipo_transaccion AS factura_tipo, f.anulada AS factura_anulada
                 FROM movimiento m
                 JOIN facturas f ON f.id_factura = m.id_factura
                 WHERE m.id_movimiento = :id'
            );
            $stmtLinea->execute(['id' => $idMovimiento]);
            $linea = $stmtLinea->fetch();

            if (!$linea) {
                throw new InvalidArgumentException('Esa línea de venta no existe.');
            }
            if ((int) $linea['factura_tipo'] !== 1) {
                throw new InvalidArgumentException('Solo se pueden devolver líneas de una venta.');
            }
            if ((int) $linea['factura_anulada'] === 1) {
                throw new InvalidArgumentException('Esa venta está anulada.');
            }
            if ($linea['producto_id'] === null) {
                throw new InvalidArgumentException('Esa línea no corresponde a un producto.');
            }

            $restante = (float) $linea['cantidad'] - (float) $linea['cantidad_devuelta'];
            if ($cantidad > $restante + 0.001) {
                $restanteTexto = $linea['presentacion'] === 'peso' ? "$restante kg" : "$restante unidad(es)";
                throw new InvalidArgumentException("Solo queda(n) $restanteTexto por devolver de esta línea.");
            }

            $unidadesPorPaquete = max(1, (int) $linea['unidades_por_paquete']);
            $unidadesTotales = $linea['presentacion'] === 'paquete' ? $cantidad * $unidadesPorPaquete : $cantidad;

            $precioUnitario = (float) $linea['precio_unitario'];
            // El precio real cobrado por unidad puede ser mayor al
            // precio_unitario base si la línea tenía modificadores pagados
            // (ej. un extra) — se prorratea sobre el total realmente
            // facturado en esa línea, no sobre el precio de lista, para no
            // devolver de menos.
            $cantidadOriginalLinea = (float) $linea['cantidad'];
            $valorPorUnidad = $cantidadOriginalLinea > 0 ? (float) $linea['total'] / $cantidadOriginalLinea : $precioUnitario;
            $valorDevuelto = round($valorPorUnidad * $cantidad, 2);
            $tasaIva = (float) $linea['tasa_iva'];
            $valorIva = $tasaIva > 0 ? $valorDevuelto - ($valorDevuelto / (1 + $tasaIva / 100)) : 0.0;
            $afectoStock = (int) $linea['afecto_stock'];

            $stmtFactura = $pdo->prepare(
                'INSERT INTO facturas (fecha, hora, usuario_id, tipo_transaccion, total) VALUES (:fecha, :hora, :usuario_id, 6, :total)'
            );
            $stmtFactura->execute([
                'fecha' => $fecha,
                'hora' => $hora,
                'usuario_id' => $usuarioId,
                'total' => $valorDevuelto,
            ]);
            $idFacturaDevolucion = (int) $pdo->lastInsertId();

            $stmtMovimiento = $pdo->prepare(
                'INSERT INTO movimiento
                    (id_factura, usuario_id, tipo_transaccion, fecha, hora, producto_id, presentacion,
                     unidades_por_paquete, cantidad, unidades_totales, afecto_stock, precio_unitario,
                     costo_unitario, tasa_iva, valor_iva, total, peso_kg, descripcion, movimiento_origen_id)
                 VALUES (:id_factura, :usuario_id, 6, :fecha, :hora, :producto_id, :presentacion,
                         :unidades_por_paquete, :cantidad, :unidades_totales, :afecto_stock, :precio_unitario,
                         :costo_unitario, :tasa_iva, :valor_iva, :total, :peso_kg, :descripcion, :origen)'
            );
            $stmtMovimiento->execute([
                'id_factura' => $idFacturaDevolucion,
                'usuario_id' => $usuarioId,
                'fecha' => $fecha,
                'hora' => $hora,
                'producto_id' => $linea['producto_id'],
                'presentacion' => $linea['presentacion'],
                'unidades_por_paquete' => $unidadesPorPaquete,
                'cantidad' => $cantidad,
                'unidades_totales' => $unidadesTotales,
                'afecto_stock' => $afectoStock,
                'precio_unitario' => $precioUnitario,
                'costo_unitario' => $linea['costo_unitario'],
                'tasa_iva' => $tasaIva,
                'valor_iva' => $valorIva,
                'total' => $valorDevuelto,
                'peso_kg' => $linea['presentacion'] === 'peso' ? $cantidad : null,
                'descripcion' => $motivo,
                'origen' => $idMovimiento,
            ]);

            if ($afectoStock) {
                $pdo->prepare('UPDATE productos SET cantidad_existente = cantidad_existente + :cantidad WHERE id_producto = :id')
                    ->execute(['cantidad' => $unidadesTotales, 'id' => $linea['producto_id']]);
            }

            $cantidadOriginal = (float) $linea['cantidad'];
            $stmtReponer = $pdo->prepare(
                'UPDATE productos SET cantidad_existente = cantidad_existente + :cantidad WHERE id_producto = :id'
            );

            $stmtDetalleCombo = $pdo->prepare(
                'SELECT producto_id, unidades_descontadas FROM movimiento_combo_detalle WHERE id_movimiento = :id'
            );
            $stmtDetalleCombo->execute(['id' => $idMovimiento]);
            foreach ($stmtDetalleCombo->fetchAll() as $comp) {
                $unidadesComponente = (int) round(((int) $comp['unidades_descontadas'] * $cantidad) / $cantidadOriginal);
                if ($unidadesComponente <= 0) {
                    continue;
                }
                $stmtReponer->execute(['cantidad' => $unidadesComponente, 'id' => (int) $comp['producto_id']]);
            }

            $stmtDetalleModificador = $pdo->prepare(
                'SELECT producto_id, unidades_descontadas FROM movimiento_modificadores WHERE id_movimiento = :id AND afecto_stock = 1'
            );
            $stmtDetalleModificador->execute(['id' => $idMovimiento]);
            foreach ($stmtDetalleModificador->fetchAll() as $mod) {
                $unidadesModificador = (int) round(((int) $mod['unidades_descontadas'] * $cantidad) / $cantidadOriginal);
                if ($unidadesModificador <= 0) {
                    continue;
                }
                $stmtReponer->execute(['cantidad' => $unidadesModificador, 'id' => (int) $mod['producto_id']]);
            }

            $pdo->prepare('UPDATE movimiento SET cantidad_devuelta = cantidad_devuelta + :cantidad WHERE id_movimiento = :id')
                ->execute(['cantidad' => $cantidad, 'id' => $idMovimiento]);

            Sincronizacion::marcarOrigenPropio($pdo, 'facturas', 'id_factura', 'origen_id_factura', $idFacturaDevolucion);
            if (Sincronizacion::sincronizacionActiva()) {
                // La devolución nace en ESTA caja (evento tipo "creación": caja_origen es la
                // propia). La línea original devuelta puede ser de otra caja distinta — viaja
                // como su propia referencia {caja,id} en "movimiento_devuelto".
                $origenFacturaDevolucion = Sincronizacion::origenDe($pdo, 'facturas', 'id_factura', 'origen_id_factura', $idFacturaDevolucion);
                $origenMovimientoOriginal = Sincronizacion::origenDe($pdo, 'movimiento', 'id_movimiento', 'origen_id_movimiento', $idMovimiento);
                $origenProducto = $linea['producto_id'] !== null
                    ? Sincronizacion::origenDe($pdo, 'productos', 'id_producto', 'origen_id_producto', (int) $linea['producto_id'])
                    : null;
                if ($origenFacturaDevolucion !== null && $origenMovimientoOriginal !== null) {
                    Sincronizacion::registrarEventoSaliente($pdo, 'devolucion_linea', $idFacturaDevolucion, [
                        'caja_origen' => $origenFacturaDevolucion['caja'],
                        'id_factura_devolucion_origen' => $origenFacturaDevolucion['id'],
                        'movimiento_devuelto' => $origenMovimientoOriginal,
                        'fecha' => $fecha,
                        'hora' => $hora,
                        'cantidad' => $cantidad,
                        'motivo' => $motivo,
                        'producto' => $origenProducto,
                        'presentacion' => $linea['presentacion'],
                        'unidades_por_paquete' => $unidadesPorPaquete,
                        'unidades_totales' => $unidadesTotales,
                        'afecto_stock' => (bool) $afectoStock,
                        'precio_unitario' => $precioUnitario,
                        'costo_unitario' => (float) $linea['costo_unitario'],
                        'tasa_iva' => $tasaIva,
                        'valor_iva' => $valorIva,
                        'total' => $valorDevuelto,
                        'peso_kg' => $linea['presentacion'] === 'peso' ? $cantidad : null,
                    ]);
                }
            }

            $pdo->commit();

            return $idFacturaDevolucion;
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /** Registra un abono (pago) de un cliente a su cuenta, reduciendo lo que debe. */
    public function registrarAbono(int $usuarioId, int $clienteId, float $valor, string $descripcion = ''): int
    {
        if ($valor <= 0) {
            throw new InvalidArgumentException('El valor del abono debe ser mayor a cero.');
        }

        $pdo = Database::conexion();
        $fecha = date('Y-m-d');
        $hora = date('H:i:s');

        $pdo->beginTransaction();
        try {
            $stmtCliente = $pdo->prepare('SELECT nombre FROM clientes WHERE id_cliente = :id');
            $stmtCliente->execute(['id' => $clienteId]);
            $cliente = $stmtCliente->fetch();
            if (!$cliente) {
                throw new InvalidArgumentException('El cliente no existe.');
            }

            $stmtFactura = $pdo->prepare(
                'INSERT INTO facturas (fecha, hora, usuario_id, tipo_transaccion, total, cliente_id)
                 VALUES (:fecha, :hora, :usuario_id, 5, :total, :cliente_id)'
            );
            $stmtFactura->execute([
                'fecha' => $fecha,
                'hora' => $hora,
                'usuario_id' => $usuarioId,
                'total' => $valor,
                'cliente_id' => $clienteId,
            ]);
            $idFactura = (int) $pdo->lastInsertId();

            $descripcionFinal = $descripcion !== '' ? $descripcion : "Abono de {$cliente['nombre']}";

            $stmtMovimiento = $pdo->prepare(
                'INSERT INTO movimiento
                    (id_factura, usuario_id, tipo_transaccion, fecha, hora, producto_id, cantidad, precio_unitario, total, descripcion)
                 VALUES (:id_factura, :usuario_id, 5, :fecha, :hora, NULL, 0, 0, :total, :descripcion)'
            );
            $stmtMovimiento->execute([
                'id_factura' => $idFactura,
                'usuario_id' => $usuarioId,
                'fecha' => $fecha,
                'hora' => $hora,
                'total' => $valor,
                'descripcion' => $descripcionFinal,
            ]);

            Sincronizacion::marcarOrigenPropio($pdo, 'facturas', 'id_factura', 'origen_id_factura', $idFactura);
            if (Sincronizacion::sincronizacionActiva()) {
                $origenFactura = Sincronizacion::origenDe($pdo, 'facturas', 'id_factura', 'origen_id_factura', $idFactura);
                $origenCliente = Sincronizacion::origenDe($pdo, 'clientes', 'id_cliente', 'origen_id_cliente', $clienteId);
                if ($origenFactura !== null) {
                    Sincronizacion::registrarEventoSaliente($pdo, 'abono', $idFactura, [
                        'caja_origen' => $origenFactura['caja'],
                        'id_factura_origen' => $origenFactura['id'],
                        'cliente' => $origenCliente,
                        'valor' => $valor,
                        'descripcion' => $descripcionFinal,
                        'fecha' => $fecha,
                        'hora' => $hora,
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

    /** Registra un egreso general (no ligado a un producto puntual, ej. pagar un domicilio). */
    public function registrarInsumo(
        int $usuarioId,
        int $tipoTransaccion,
        string $descripcion,
        float $valor,
        string $fecha,
        string $hora,
        string $metodoPago = 'efectivo',
        ?int $turnoId = null
    ): int {
        $pdo = Database::conexion();
        $metodoPago = in_array($metodoPago, ['efectivo', 'tarjeta', 'transferencia'], true) ? $metodoPago : 'efectivo';

        $pdo->beginTransaction();
        try {
            $stmtFactura = $pdo->prepare(
                'INSERT INTO facturas (fecha, hora, usuario_id, tipo_transaccion, total, metodo_pago, turno_id)
                 VALUES (:fecha, :hora, :usuario_id, :tipo, :total, :metodo_pago, :turno_id)'
            );
            $stmtFactura->execute([
                'fecha' => $fecha,
                'hora' => $hora,
                'usuario_id' => $usuarioId,
                'tipo' => $tipoTransaccion,
                'total' => $valor,
                'metodo_pago' => $metodoPago,
                'turno_id' => $turnoId,
            ]);
            $idFactura = (int) $pdo->lastInsertId();

            $stmtMovimiento = $pdo->prepare(
                'INSERT INTO movimiento
                    (id_factura, usuario_id, tipo_transaccion, fecha, hora, producto_id, cantidad, precio_unitario, total, descripcion)
                 VALUES (:id_factura, :usuario_id, :tipo, :fecha, :hora, NULL, 0, 0, :total, :descripcion)'
            );
            $stmtMovimiento->execute([
                'id_factura' => $idFactura,
                'usuario_id' => $usuarioId,
                'tipo' => $tipoTransaccion,
                'fecha' => $fecha,
                'hora' => $hora,
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
}
