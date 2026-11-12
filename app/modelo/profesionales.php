<?php
require_once __DIR__ . '/Database.php';

/**
 * Profesionales (estilistas, mecánicos, terapeutas…) y sus comisiones. Ver
 * migración 038. La comisión se calcula en cada línea de venta
 * (facturas::registrarVenta) y aquí solo se administran las personas y se
 * consulta el reporte.
 */
class profesionales
{
    /** Para el selector de Ventas: solo los activos. Con $todos = true también los inactivos (pantalla de administración). */
    public function listar(bool $todos = false): array
    {
        $donde = $todos ? '' : 'WHERE estado = 1';

        return Database::conexion()->query(
            "SELECT id_profesional, nombre, comision_porcentaje, estado
             FROM profesionales $donde
             ORDER BY estado DESC, nombre COLLATE NOCASE ASC"
        )->fetchAll();
    }

    public function obtener(int $idProfesional): ?array
    {
        $stmt = Database::conexion()->prepare(
            'SELECT id_profesional, nombre, comision_porcentaje, estado FROM profesionales WHERE id_profesional = :id'
        );
        $stmt->execute(['id' => $idProfesional]);

        return $stmt->fetch() ?: null;
    }

    public function crear(string $nombre, float $comision): array
    {
        [$nombre, $comision] = $this->validar($nombre, $comision);
        $pdo = Database::conexion();
        if ($this->existeNombre($nombre, null)) {
            throw new InvalidArgumentException('Ya existe un profesional con ese nombre.');
        }
        $pdo->prepare('INSERT INTO profesionales (nombre, comision_porcentaje) VALUES (:nombre, :comision)')
            ->execute(['nombre' => $nombre, 'comision' => $comision]);

        return $this->obtener((int) $pdo->lastInsertId());
    }

    /** Cambiar el porcentaje NO altera lo ya vendido (cada línea guardó el que tenía). */
    public function editar(int $idProfesional, string $nombre, float $comision, int $estado): array
    {
        [$nombre, $comision] = $this->validar($nombre, $comision);
        if ($this->obtener($idProfesional) === null) {
            throw new InvalidArgumentException('Ese profesional no existe.');
        }
        if ($this->existeNombre($nombre, $idProfesional)) {
            throw new InvalidArgumentException('Ya existe otro profesional con ese nombre.');
        }
        Database::conexion()->prepare(
            'UPDATE profesionales SET nombre = :nombre, comision_porcentaje = :comision, estado = :estado WHERE id_profesional = :id'
        )->execute(['nombre' => $nombre, 'comision' => $comision, 'estado' => $estado === 1 ? 1 : 0, 'id' => $idProfesional]);

        return $this->obtener($idProfesional);
    }

    /**
     * Reporte de comisiones del período. Solo cuenta ventas no anuladas y, de
     * cada línea, lo que NO se devolvió (una devolución parcial reduce la
     * comisión en la misma proporción). Devuelve un resumen por profesional y el
     * detalle línea por línea.
     */
    public function comisionesPorFecha(string $fechaInicio, string $fechaFin, ?int $idProfesional = null): array
    {
        $pdo = Database::conexion();
        $filtro = $idProfesional !== null ? 'AND m.profesional_id = :prof' : '';
        $params = ['inicio' => $fechaInicio, 'fin' => $fechaFin];
        if ($idProfesional !== null) {
            $params['prof'] = $idProfesional;
        }

        // "neto" = la fracción de la línea que no se devolvió.
        $stmt = $pdo->prepare(
            "SELECT pr.id_profesional, pr.nombre,
                    SUM(CASE WHEN m.cantidad > m.cantidad_devuelta THEN 1 ELSE 0 END) AS servicios,
                    COALESCE(SUM(m.total * (m.cantidad - m.cantidad_devuelta) / m.cantidad), 0) AS vendido,
                    COALESCE(SUM(m.comision_valor * (m.cantidad - m.cantidad_devuelta) / m.cantidad), 0) AS comision
             FROM movimiento m
             JOIN facturas f ON f.id_factura = m.id_factura
             JOIN profesionales pr ON pr.id_profesional = m.profesional_id
             WHERE m.tipo_transaccion = 1 AND f.anulada = 0 AND m.cantidad > 0
               AND f.fecha BETWEEN :inicio AND :fin $filtro
             GROUP BY pr.id_profesional
             HAVING SUM(CASE WHEN m.cantidad > m.cantidad_devuelta THEN 1 ELSE 0 END) > 0
             ORDER BY comision DESC, pr.nombre COLLATE NOCASE ASC"
        );
        $stmt->execute($params);
        $resumen = $stmt->fetchAll();

        $stmtDetalle = $pdo->prepare(
            "SELECT f.id_factura, f.fecha, f.hora, pr.id_profesional, pr.nombre AS profesional,
                    p.nombre_producto, m.cantidad, m.cantidad_devuelta, m.comision_porcentaje,
                    m.total * (m.cantidad - m.cantidad_devuelta) / m.cantidad AS vendido,
                    m.comision_valor * (m.cantidad - m.cantidad_devuelta) / m.cantidad AS comision
             FROM movimiento m
             JOIN facturas f ON f.id_factura = m.id_factura
             JOIN profesionales pr ON pr.id_profesional = m.profesional_id
             JOIN productos p ON p.id_producto = m.producto_id
             WHERE m.tipo_transaccion = 1 AND f.anulada = 0 AND m.cantidad > m.cantidad_devuelta
               AND f.fecha BETWEEN :inicio AND :fin $filtro
             ORDER BY f.fecha DESC, f.id_factura DESC, m.id_movimiento ASC"
        );
        $stmtDetalle->execute($params);

        $totalVendido = 0.0;
        $totalComision = 0.0;
        foreach ($resumen as &$fila) {
            $fila['servicios'] = (int) $fila['servicios'];
            $fila['vendido'] = round((float) $fila['vendido'], 2);
            $fila['comision'] = round((float) $fila['comision'], 2);
            $totalVendido += $fila['vendido'];
            $totalComision += $fila['comision'];
        }
        unset($fila);

        return [
            'resumen' => $resumen,
            'detalle' => $stmtDetalle->fetchAll(),
            'total_vendido' => round($totalVendido, 2),
            'total_comision' => round($totalComision, 2),
        ];
    }

    private function validar(string $nombre, float $comision): array
    {
        $nombre = trim(preg_replace('/\s+/u', ' ', $nombre));
        if ($nombre === '') {
            throw new InvalidArgumentException('El nombre es obligatorio.');
        }
        if (mb_strlen($nombre) > 80) {
            throw new InvalidArgumentException('El nombre es demasiado largo (máximo 80 caracteres).');
        }
        if ($comision < 0 || $comision > 100) {
            throw new InvalidArgumentException('La comisión debe estar entre 0 y 100.');
        }

        return [$nombre, round($comision, 2)];
    }

    /**
     * "Ana Gómez" y "ANA GÓMEZ" son la misma persona. El índice único de la base solo
     * ignora mayúsculas ASCII (una "Ó" mayúscula no se reconoce), así que la comparación
     * real se hace aquí con mb_strtolower; el índice queda como red de seguridad.
     */
    private function existeNombre(string $nombre, ?int $exceptoId): bool
    {
        $buscado = mb_strtolower($nombre);
        foreach (Database::conexion()->query('SELECT id_profesional, nombre FROM profesionales')->fetchAll() as $fila) {
            if ((int) $fila['id_profesional'] !== ($exceptoId ?? 0) && mb_strtolower($fila['nombre']) === $buscado) {
                return true;
            }
        }

        return false;
    }
}
