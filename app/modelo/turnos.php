<?php
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Auditoria.php';

class TurnoException extends RuntimeException
{
}

class turnos
{
    public function turnoAbierto(): ?array
    {
        $pdo = Database::conexion();
        $stmt = $pdo->prepare(
            "SELECT t.id_turno, t.usuario_id, t.fecha_apertura, t.hora_apertura, t.monto_apertura, u.nombre AS abierto_por
             FROM turnos t
             JOIN usuarios u ON t.usuario_id = u.id_usuario
             WHERE t.estado = 'abierto'
             ORDER BY t.id_turno DESC LIMIT 1"
        );
        $stmt->execute();
        $fila = $stmt->fetch();

        return $fila ?: null;
    }

    public function abrirTurno(int $usuarioId, float $montoApertura): int
    {
        if (!is_finite($montoApertura)) {
            throw new TurnoException('El monto de apertura no es válido.');
        }

        // La caja es una sola para todo el negocio: hay un único turno abierto a la vez, lo abra quien lo abra.
        // El "solo si no hay otro abierto" va DENTRO del mismo INSERT (una sola operación atómica), para que dos
        // aperturas casi simultáneas (dos equipos, doble clic) no dejen dos turnos abiertos.
        $pdo = Database::conexion();
        $stmt = $pdo->prepare(
            "INSERT INTO turnos (usuario_id, fecha_apertura, hora_apertura, monto_apertura, estado)
             SELECT :usuario_id, :fecha, :hora, :monto, 'abierto'
             WHERE NOT EXISTS (SELECT 1 FROM turnos WHERE estado = 'abierto')"
        );
        $stmt->execute([
            'usuario_id' => $usuarioId,
            'fecha' => date('Y-m-d'),
            'hora' => date('H:i:s'),
            'monto' => max(0, $montoApertura),
        ]);
        if ($stmt->rowCount() === 0) {
            throw new TurnoException('Ya hay un turno abierto. Ciérralo antes de abrir uno nuevo.');
        }

        return (int) $pdo->lastInsertId();
    }

    /**
     * Corrige el monto de apertura de un turno — el abierto o uno YA CERRADO (días anteriores) — por
     * ejemplo cuando se digitó 330 en vez de 330.000. Cambia la base del cuadre de caja, así que es
     * una acción delicada: $usuarioId es quien la hizo, $autorizadoPor el administrador que la
     * autorizó (el mismo, si la hizo un administrador), y queda todo en turno_ajustes y en la
     * auditoría. En un turno cerrado el efectivo esperado se corrige en la misma cantidad
     * (esperado = apertura + ventas en efectivo − egresos, así que sube o baja igual que la
     * apertura) y la diferencia se recalcula contra lo que se contó; el ajuste guarda cómo estaban
     * el esperado y la diferencia antes y después.
     *
     * @return array{anterior: float, nuevo: float, diferencia: float, turno_estado: string, esperado_anterior: ?float, esperado_nuevo: ?float, diferencia_anterior: ?float, diferencia_nueva: ?float}
     */
    public function ajustarApertura(int $idTurno, float $montoNuevo, int $usuarioId, int $autorizadoPor, string $motivo): array
    {
        $motivo = trim($motivo);
        if ($motivo === '') {
            throw new TurnoException('Indica el motivo del ajuste.');
        }
        if (!is_finite($montoNuevo) || $montoNuevo < 0) {
            throw new TurnoException('El monto debe ser un número mayor o igual a cero.');
        }
        if ($montoNuevo > 1000000000) {
            throw new TurnoException('Ese monto es demasiado grande: revisa los ceros.');
        }
        $montoNuevo = round($montoNuevo, 2);

        $pdo = Database::conexion();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('SELECT id_turno, estado, monto_apertura, monto_esperado, monto_cierre_real, diferencia FROM turnos WHERE id_turno = :id');
            $stmt->execute(['id' => $idTurno]);
            $turno = $stmt->fetch();
            if (!$turno) {
                throw new TurnoException('Ese turno no existe.');
            }

            $anterior = (float) $turno['monto_apertura'];
            if (abs($anterior - $montoNuevo) < 0.005) {
                throw new TurnoException('Ese ya es el monto de apertura del turno.');
            }
            $delta = $montoNuevo - $anterior;

            $cerrado = $turno['estado'] === 'cerrado';
            $esperadoAnterior = $esperadoNuevo = $diferenciaAnterior = $diferenciaNueva = null;
            if ($cerrado) {
                $esperadoAnterior = (float) $turno['monto_esperado'];
                $esperadoNuevo = round($esperadoAnterior + $delta, 2);
                $diferenciaAnterior = (float) $turno['diferencia'];
                $diferenciaNueva = round((float) $turno['monto_cierre_real'] - $esperadoNuevo, 2);
            }

            $pdo->prepare(
                "INSERT INTO turno_ajustes
                    (turno_id, tipo, monto_anterior, monto_nuevo, motivo, usuario_id, autorizado_por,
                     turno_estado, esperado_anterior, esperado_nuevo, diferencia_anterior, diferencia_nueva)
                 VALUES (:turno, 'apertura', :anterior, :nuevo, :motivo, :usuario, :autoriza,
                         :estado, :ea, :en, :da, :dn)"
            )->execute([
                'turno' => $idTurno, 'anterior' => $anterior, 'nuevo' => $montoNuevo, 'motivo' => $motivo,
                'usuario' => $usuarioId, 'autoriza' => $autorizadoPor, 'estado' => $cerrado ? 'cerrado' : 'abierto',
                'ea' => $esperadoAnterior, 'en' => $esperadoNuevo, 'da' => $diferenciaAnterior, 'dn' => $diferenciaNueva,
            ]);

            if ($cerrado) {
                $pdo->prepare('UPDATE turnos SET monto_apertura = :monto, monto_esperado = :esperado, diferencia = :diferencia WHERE id_turno = :id')
                    ->execute(['monto' => $montoNuevo, 'esperado' => $esperadoNuevo, 'diferencia' => $diferenciaNueva, 'id' => $idTurno]);
            } else {
                $pdo->prepare('UPDATE turnos SET monto_apertura = :monto WHERE id_turno = :id')
                    ->execute(['monto' => $montoNuevo, 'id' => $idTurno]);
            }

            Auditoria::registrar($usuarioId, 'turno.ajustar_apertura', 'turnos', $idTurno, [
                'turno_estado' => $cerrado ? 'cerrado' : 'abierto',
                'monto_anterior' => $anterior,
                'monto_nuevo' => $montoNuevo,
                'diferencia' => round($delta, 2),
                'esperado_anterior' => $esperadoAnterior,
                'esperado_nuevo' => $esperadoNuevo,
                'diferencia_cuadre_anterior' => $diferenciaAnterior,
                'diferencia_cuadre_nueva' => $diferenciaNueva,
                'motivo' => $motivo,
                'autorizado_por' => $autorizadoPor,
            ]);

            $pdo->commit();

            return [
                'anterior' => $anterior, 'nuevo' => $montoNuevo, 'diferencia' => round($delta, 2),
                'turno_estado' => $cerrado ? 'cerrado' : 'abierto',
                'esperado_anterior' => $esperadoAnterior, 'esperado_nuevo' => $esperadoNuevo,
                'diferencia_anterior' => $diferenciaAnterior, 'diferencia_nueva' => $diferenciaNueva,
            ];
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Corrige el monto CONTADO al cerrar un turno YA CERRADO (cualquiera, de cualquier fecha) — por
     * ejemplo cuando se digitó 450 en vez de 450.000. A diferencia de la apertura, esto NO afecta el
     * efectivo esperado (no depende de lo que se contó, solo de la apertura y los movimientos de ese
     * turno), así que únicamente se recalcula la diferencia del cuadre. $usuarioId es quien la hizo,
     * $autorizadoPor el administrador que la autorizó (el mismo, si la hizo un administrador), y
     * queda todo en turno_ajustes (tipo 'cierre') y en la auditoría.
     *
     * @return array{anterior: float, nuevo: float, esperado: float, diferencia_anterior: float, diferencia_nueva: float}
     */
    public function ajustarCierre(int $idTurno, float $montoNuevo, int $usuarioId, int $autorizadoPor, string $motivo): array
    {
        $motivo = trim($motivo);
        if ($motivo === '') {
            throw new TurnoException('Indica el motivo del ajuste.');
        }
        if (!is_finite($montoNuevo) || $montoNuevo < 0) {
            throw new TurnoException('El monto debe ser un número mayor o igual a cero.');
        }
        if ($montoNuevo > 1000000000) {
            throw new TurnoException('Ese monto es demasiado grande: revisa los ceros.');
        }
        $montoNuevo = round($montoNuevo, 2);

        $pdo = Database::conexion();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('SELECT id_turno, estado, monto_esperado, monto_cierre_real, diferencia FROM turnos WHERE id_turno = :id');
            $stmt->execute(['id' => $idTurno]);
            $turno = $stmt->fetch();
            if (!$turno) {
                throw new TurnoException('Ese turno no existe.');
            }
            if ($turno['estado'] !== 'cerrado') {
                throw new TurnoException('Solo se puede corregir el monto contado de un turno ya cerrado.');
            }

            $anterior = (float) $turno['monto_cierre_real'];
            if (abs($anterior - $montoNuevo) < 0.005) {
                throw new TurnoException('Ese ya es el monto contado del turno.');
            }

            $esperado = (float) $turno['monto_esperado'];
            $diferenciaAnterior = (float) $turno['diferencia'];
            $diferenciaNueva = round($montoNuevo - $esperado, 2);

            $pdo->prepare(
                "INSERT INTO turno_ajustes
                    (turno_id, tipo, monto_anterior, monto_nuevo, motivo, usuario_id, autorizado_por,
                     turno_estado, esperado_anterior, esperado_nuevo, diferencia_anterior, diferencia_nueva)
                 VALUES (:turno, 'cierre', :anterior, :nuevo, :motivo, :usuario, :autoriza,
                         'cerrado', :esp, :esp, :da, :dn)"
            )->execute([
                'turno' => $idTurno, 'anterior' => $anterior, 'nuevo' => $montoNuevo, 'motivo' => $motivo,
                'usuario' => $usuarioId, 'autoriza' => $autorizadoPor,
                'esp' => $esperado, 'da' => $diferenciaAnterior, 'dn' => $diferenciaNueva,
            ]);

            $pdo->prepare('UPDATE turnos SET monto_cierre_real = :monto, diferencia = :diferencia WHERE id_turno = :id')
                ->execute(['monto' => $montoNuevo, 'diferencia' => $diferenciaNueva, 'id' => $idTurno]);

            Auditoria::registrar($usuarioId, 'turno.ajustar_cierre', 'turnos', $idTurno, [
                'monto_anterior' => $anterior,
                'monto_nuevo' => $montoNuevo,
                'esperado' => $esperado,
                'diferencia_anterior' => $diferenciaAnterior,
                'diferencia_nueva' => $diferenciaNueva,
                'motivo' => $motivo,
                'autorizado_por' => $autorizadoPor,
            ]);

            $pdo->commit();

            return [
                'anterior' => $anterior, 'nuevo' => $montoNuevo, 'esperado' => $esperado,
                'diferencia_anterior' => $diferenciaAnterior, 'diferencia_nueva' => $diferenciaNueva,
            ];
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /** Historial de ajustes de UN turno (del más antiguo al más nuevo), con nombres de quien lo hizo y de quien lo autorizó. */
    public function ajustesDeTurno(int $idTurno): array
    {
        $stmt = Database::conexion()->prepare(
            "SELECT a.id_ajuste, a.tipo, a.monto_anterior, a.monto_nuevo, a.motivo, a.creado_en, a.turno_estado,
                    a.esperado_anterior, a.esperado_nuevo, a.diferencia_anterior, a.diferencia_nueva,
                    u.nombre AS hecho_por, au.nombre AS autorizado_por_nombre
             FROM turno_ajustes a
             LEFT JOIN usuarios u ON u.id_usuario = a.usuario_id
             LEFT JOIN usuarios au ON au.id_usuario = a.autorizado_por
             WHERE a.turno_id = :id
             ORDER BY a.id_ajuste ASC"
        );
        $stmt->execute(['id' => $idTurno]);

        return $stmt->fetchAll();
    }

    /**
     * Historial de TODOS los ajustes de apertura y de cierre (el más reciente primero), de cualquier
     * turno: qué día era el turno, de cuánto a cuánto, cómo cambió el cuadre, el motivo, quién lo
     * hizo y quién lo autorizó. El rango filtra por la fecha en que se HIZO el ajuste.
     */
    public function historialAjustes(?string $fechaInicio = null, ?string $fechaFin = null, int $limite = 300): array
    {
        $condiciones = [];
        $params = [];
        if ($fechaInicio !== null && $fechaInicio !== '') {
            $condiciones[] = 'date(a.creado_en) >= :inicio';
            $params['inicio'] = $fechaInicio;
        }
        if ($fechaFin !== null && $fechaFin !== '') {
            $condiciones[] = 'date(a.creado_en) <= :fin';
            $params['fin'] = $fechaFin;
        }
        $donde = $condiciones ? 'WHERE ' . implode(' AND ', $condiciones) : '';

        $stmt = Database::conexion()->prepare(
            "SELECT a.id_ajuste, a.tipo, a.creado_en, a.turno_id, a.turno_estado, a.monto_anterior, a.monto_nuevo, a.motivo,
                    a.esperado_anterior, a.esperado_nuevo, a.diferencia_anterior, a.diferencia_nueva,
                    t.fecha_apertura, t.hora_apertura,
                    u.nombre AS hecho_por, au.nombre AS autorizado_por_nombre
             FROM turno_ajustes a
             JOIN turnos t ON t.id_turno = a.turno_id
             LEFT JOIN usuarios u ON u.id_usuario = a.usuario_id
             LEFT JOIN usuarios au ON au.id_usuario = a.autorizado_por
             $donde
             ORDER BY a.id_ajuste DESC
             LIMIT :limite"
        );
        foreach ($params as $clave => $valor) {
            $stmt->bindValue(":$clave", $valor, PDO::PARAM_STR);
        }
        $stmt->bindValue(':limite', max(1, min(1000, $limite)), PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /** Totales en vivo del turno abierto: ventas por método de pago, egresos, efectivo esperado. */
    public function resumenTurno(int $idTurno): array
    {
        $pdo = Database::conexion();

        $stmt = $pdo->prepare(
            "SELECT
                COALESCE((SELECT SUM(fp.valor) FROM factura_pagos fp
                          JOIN facturas f ON f.id_factura = fp.id_factura
                          WHERE f.turno_id = :id1 AND f.tipo_transaccion = 1 AND f.anulada = 0
                            AND fp.metodo_pago = 'efectivo'), 0) AS ventas_efectivo,
                COALESCE((SELECT SUM(fp.valor) FROM factura_pagos fp
                          JOIN facturas f ON f.id_factura = fp.id_factura
                          WHERE f.turno_id = :id2 AND f.tipo_transaccion = 1 AND f.anulada = 0
                            AND fp.metodo_pago != 'efectivo'), 0) AS ventas_otros,
                COALESCE((SELECT SUM(total) FROM facturas
                          WHERE turno_id = :id3 AND tipo_transaccion = 2 AND anulada = 0 AND metodo_pago = 'efectivo'), 0) AS egresos_efectivo"
        );
        $stmt->execute(['id1' => $idTurno, 'id2' => $idTurno, 'id3' => $idTurno]);
        $totales = $stmt->fetch();

        $turno = $this->obtener($idTurno);
        $montoApertura = $turno ? (float) $turno['monto_apertura'] : 0.0;
        $esperado = $montoApertura + (float) $totales['ventas_efectivo'] - (float) $totales['egresos_efectivo'];

        return [
            'monto_apertura' => $montoApertura,
            'ventas_efectivo' => (float) $totales['ventas_efectivo'],
            'ventas_otros' => (float) $totales['ventas_otros'],
            'egresos_efectivo' => (float) $totales['egresos_efectivo'],
            'esperado' => $esperado,
        ];
    }

    public function obtener(int $idTurno): ?array
    {
        $pdo = Database::conexion();
        $stmt = $pdo->prepare('SELECT * FROM turnos WHERE id_turno = :id');
        $stmt->execute(['id' => $idTurno]);
        $fila = $stmt->fetch();

        return $fila ?: null;
    }

    public function cerrarTurno(int $idTurno, int $usuarioId, float $montoContado, string $observaciones = ''): array
    {
        $pdo = Database::conexion();

        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("SELECT * FROM turnos WHERE id_turno = :id AND estado = 'abierto'");
            $stmt->execute(['id' => $idTurno]);
            $turno = $stmt->fetch();
            if (!$turno) {
                throw new TurnoException('Ese turno no existe o ya está cerrado.');
            }

            $resumen = $this->resumenTurno($idTurno);
            $esperado = $resumen['esperado'];
            $diferencia = $montoContado - $esperado;

            $stmtCerrar = $pdo->prepare(
                "UPDATE turnos
                 SET estado = 'cerrado', fecha_cierre = :fecha, hora_cierre = :hora,
                     monto_cierre_real = :monto_real, monto_esperado = :esperado, diferencia = :diferencia,
                     observaciones = :observaciones, cerrado_por = :cerrado_por
                 WHERE id_turno = :id"
            );
            $stmtCerrar->execute([
                'fecha' => date('Y-m-d'),
                'hora' => date('H:i:s'),
                'monto_real' => $montoContado,
                'esperado' => $esperado,
                'diferencia' => $diferencia,
                'observaciones' => $observaciones !== '' ? $observaciones : null,
                'cerrado_por' => $usuarioId,
                'id' => $idTurno,
            ]);

            $pdo->commit();

            return [
                'esperado' => $esperado,
                'monto_cierre_real' => $montoContado,
                'diferencia' => $diferencia,
            ];
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Desglose completo del turno para el reporte Z impreso al cerrar:
     * ventas por método de pago, cantidad de ventas/anulaciones, egresos.
     * Separado de resumenTurno() (que solo trae lo mínimo para el cuadre de
     * efectivo en pantalla) para no arriesgar esa pantalla ya probada.
     */
    public function reporteCierre(int $idTurno): array
    {
        $pdo = Database::conexion();

        $turno = $this->obtener($idTurno);
        if (!$turno) {
            throw new TurnoException('Ese turno no existe.');
        }

        $stmtPagos = $pdo->prepare(
            "SELECT fp.metodo_pago, SUM(fp.valor) AS total
             FROM factura_pagos fp
             JOIN facturas f ON f.id_factura = fp.id_factura
             WHERE f.turno_id = :id AND f.tipo_transaccion = 1 AND f.anulada = 0
             GROUP BY fp.metodo_pago"
        );
        $stmtPagos->execute(['id' => $idTurno]);
        $pagosPorMetodo = $stmtPagos->fetchAll();

        $stmtVentas = $pdo->prepare(
            "SELECT COUNT(*) AS cantidad, COALESCE(SUM(total), 0) AS total
             FROM facturas WHERE turno_id = :id AND tipo_transaccion = 1 AND anulada = 0"
        );
        $stmtVentas->execute(['id' => $idTurno]);
        $ventas = $stmtVentas->fetch();

        $stmtAnuladas = $pdo->prepare(
            "SELECT COUNT(*) FROM facturas WHERE turno_id = :id AND tipo_transaccion = 1 AND anulada = 1"
        );
        $stmtAnuladas->execute(['id' => $idTurno]);

        $stmtEgresos = $pdo->prepare(
            "SELECT COUNT(*) AS cantidad, COALESCE(SUM(total), 0) AS total
             FROM facturas WHERE turno_id = :id AND tipo_transaccion = 2 AND anulada = 0"
        );
        $stmtEgresos->execute(['id' => $idTurno]);
        $egresos = $stmtEgresos->fetch();

        // Quién abrió y quién cerró la caja pueden ser personas distintas (la caja es una sola para todos).
        $stmtNombres = $pdo->prepare(
            "SELECT (SELECT nombre FROM usuarios WHERE id_usuario = :abrio) AS abierto_por,
                    (SELECT nombre FROM usuarios WHERE id_usuario = :cerro) AS cerrado_por_nombre"
        );
        $stmtNombres->execute(['abrio' => $turno['usuario_id'], 'cerro' => $turno['cerrado_por']]);
        $nombres = $stmtNombres->fetch();
        $turno['abierto_por'] = $nombres['abierto_por'] ?? null;
        $turno['cerrado_por_nombre'] = $nombres['cerrado_por_nombre'] ?? null;

        return [
            'turno' => $turno,
            'ventas_cantidad' => (int) $ventas['cantidad'],
            'ventas_total' => (float) $ventas['total'],
            'ventas_anuladas' => (int) $stmtAnuladas->fetchColumn(),
            'pagos_por_metodo' => $pagosPorMetodo,
            'egresos_cantidad' => (int) $egresos['cantidad'],
            'egresos_total' => (float) $egresos['total'],
            'ajustes_apertura' => $this->ajustesDeTurno($idTurno),
        ];
    }

    public function historial(int $limite = 30): array
    {
        $pdo = Database::conexion();
        $stmt = $pdo->prepare(
            "SELECT t.id_turno, t.fecha_apertura, t.hora_apertura, t.monto_apertura,
                    t.fecha_cierre, t.hora_cierre, t.monto_cierre_real, t.monto_esperado, t.diferencia,
                    u.nombre AS abierto_por, c.nombre AS cerrado_por_nombre,
                    (SELECT COUNT(*) FROM turno_ajustes a WHERE a.turno_id = t.id_turno) AS n_ajustes,
                    (SELECT a.monto_anterior FROM turno_ajustes a WHERE a.turno_id = t.id_turno ORDER BY a.id_ajuste ASC LIMIT 1) AS apertura_original
             FROM turnos t
             JOIN usuarios u ON t.usuario_id = u.id_usuario
             LEFT JOIN usuarios c ON t.cerrado_por = c.id_usuario
             WHERE t.estado = 'cerrado'
             ORDER BY t.id_turno DESC
             LIMIT :limite"
        );
        $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }
}
