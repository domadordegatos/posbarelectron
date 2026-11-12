<?php
require_once __DIR__ . '/Database.php';

class VencimientoException extends RuntimeException
{
}

/**
 * "Semaforización" de vencimientos, en dos niveles: un VENCIMIENTO es un lote (descripción
 * opcional) con UNA fecha límite; cada vencimiento puede tener cualquier cantidad de AVISOS
 * (con cuántos días de anticipación avisar) — así agregar un segundo aviso a un lote que ya
 * existe no pide la fecha otra vez, solo el número de días. Puramente informativo — no separa
 * existencias por lote, solo avisa que algo de ese producto está por vencer; el negocio revisa
 * físicamente cuál es.
 */
class vencimientos
{
    /**
     * Agrega un aviso. Si ya existe un vencimiento igual (mismo producto + lote + fecha) lo reusa
     * en vez de crear uno duplicado.
     */
    public function agregarAlerta(int $productoId, ?string $descripcionLote, string $fechaVencimiento, int $diasAntes): int
    {
        $descripcionLote = self::normalizarLote($descripcionLote);
        if (!self::fechaValida($fechaVencimiento)) {
            throw new VencimientoException('La fecha de vencimiento no es válida.');
        }
        self::validarDiasAntes($diasAntes);

        $pdo = Database::conexion();
        $pdo->beginTransaction();
        try {
            $idVencimiento = self::obtenerOCrearVencimiento($pdo, $productoId, $descripcionLote, $fechaVencimiento);
            $stmt = $pdo->prepare('INSERT INTO producto_vencimiento_alertas (vencimiento_id, dias_antes) VALUES (:v, :d)');
            $stmt->execute(['v' => $idVencimiento, 'd' => $diasAntes]);
            $idAlerta = (int) $pdo->lastInsertId();
            $pdo->commit();

            return $idAlerta;
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /** Agrega OTRO aviso a un vencimiento que ya existe — no vuelve a pedir lote ni fecha. */
    public function agregarAlertaAVencimiento(int $idVencimiento, int $diasAntes): int
    {
        self::validarDiasAntes($diasAntes);

        $pdo = Database::conexion();
        $existe = $pdo->prepare('SELECT 1 FROM producto_vencimientos WHERE id_vencimiento = :id');
        $existe->execute(['id' => $idVencimiento]);
        if (!$existe->fetch()) {
            throw new VencimientoException('Ese vencimiento no existe.');
        }

        $stmt = $pdo->prepare('INSERT INTO producto_vencimiento_alertas (vencimiento_id, dias_antes) VALUES (:v, :d)');
        $stmt->execute(['v' => $idVencimiento, 'd' => $diasAntes]);

        return (int) $pdo->lastInsertId();
    }

    /** Elimina UN aviso. Si era el último de su vencimiento, el vencimiento (lote/fecha) se borra también. */
    public function eliminarAlerta(int $idAlerta): bool
    {
        $pdo = Database::conexion();
        $stmt = $pdo->prepare('SELECT vencimiento_id FROM producto_vencimiento_alertas WHERE id_alerta = :id');
        $stmt->execute(['id' => $idAlerta]);
        $idVencimiento = $stmt->fetchColumn();

        $ok = $pdo->prepare('DELETE FROM producto_vencimiento_alertas WHERE id_alerta = :id')->execute(['id' => $idAlerta]);

        if ($ok && $idVencimiento) {
            $quedan = $pdo->prepare('SELECT COUNT(*) FROM producto_vencimiento_alertas WHERE vencimiento_id = :v');
            $quedan->execute(['v' => $idVencimiento]);
            if ((int) $quedan->fetchColumn() === 0) {
                $pdo->prepare('DELETE FROM producto_vencimientos WHERE id_vencimiento = :id')->execute(['id' => $idVencimiento]);
            }
        }

        return (bool) $ok;
    }

    /** Edita el lote/fecha de un vencimiento existente (los avisos no cambian). */
    public function editarVencimiento(int $idVencimiento, ?string $descripcionLote, string $fechaVencimiento): bool
    {
        $descripcionLote = self::normalizarLote($descripcionLote);
        if (!self::fechaValida($fechaVencimiento)) {
            throw new VencimientoException('La fecha de vencimiento no es válida.');
        }

        $pdo = Database::conexion();
        $stmt = $pdo->prepare('SELECT producto_id FROM producto_vencimientos WHERE id_vencimiento = :id');
        $stmt->execute(['id' => $idVencimiento]);
        $productoId = $stmt->fetchColumn();
        if ($productoId === false) {
            throw new VencimientoException('Ese vencimiento no existe.');
        }

        $stmt = $pdo->prepare(
            "SELECT id_vencimiento FROM producto_vencimientos
             WHERE producto_id = :p AND COALESCE(descripcion_lote, '') = :lote AND fecha_vencimiento = :f AND id_vencimiento != :id"
        );
        $stmt->execute(['p' => $productoId, 'lote' => $descripcionLote ?? '', 'f' => $fechaVencimiento, 'id' => $idVencimiento]);
        if ($stmt->fetchColumn()) {
            throw new VencimientoException('Ya existe otro vencimiento de este producto con ese mismo lote y fecha.');
        }

        $stmt = $pdo->prepare('UPDATE producto_vencimientos SET descripcion_lote = :lote, fecha_vencimiento = :f WHERE id_vencimiento = :id');

        return $stmt->execute(['lote' => $descripcionLote, 'f' => $fechaVencimiento, 'id' => $idVencimiento]);
    }

    /** Elimina el vencimiento completo (lote/fecha) con todos sus avisos. */
    public function eliminarVencimiento(int $idVencimiento): bool
    {
        $pdo = Database::conexion();
        $pdo->prepare('DELETE FROM producto_vencimiento_alertas WHERE vencimiento_id = :id')->execute(['id' => $idVencimiento]);

        return $pdo->prepare('DELETE FROM producto_vencimientos WHERE id_vencimiento = :id')->execute(['id' => $idVencimiento]);
    }

    /** Vencimientos de UN producto (para su ficha), cada uno con sus avisos — el más próximo primero. */
    public function porProducto(int $productoId): array
    {
        $stmt = Database::conexion()->prepare(
            'SELECT id_vencimiento, producto_id, descripcion_lote, fecha_vencimiento, creado_en
             FROM producto_vencimientos WHERE producto_id = :id ORDER BY fecha_vencimiento ASC'
        );
        $stmt->execute(['id' => $productoId]);

        return array_map([self::class, 'conAlertasYEstado'], $stmt->fetchAll());
    }

    /** Todos los vencimientos de todos los productos — para el Panel y la pantalla de Vencimientos. Rojo y amarillo primero. */
    public function todos(): array
    {
        $stmt = Database::conexion()->query(
            'SELECT v.id_vencimiento, v.producto_id, v.descripcion_lote, v.fecha_vencimiento, v.creado_en, p.nombre_producto
             FROM producto_vencimientos v
             JOIN productos p ON p.id_producto = v.producto_id
             ORDER BY v.fecha_vencimiento ASC'
        );
        $vencimientos = array_map([self::class, 'conAlertasYEstado'], $stmt->fetchAll());

        usort($vencimientos, fn (array $a, array $b) => self::pesoEstado($a['estado']) <=> self::pesoEstado($b['estado']));

        return $vencimientos;
    }

    /** Solo lo que ya necesita atención (amarillo o rojo) — para el indicador del Panel. */
    public function proximosAVencer(): array
    {
        return array_values(array_filter($this->todos(), fn (array $v) => $v['estado'] !== 'verde'));
    }

    /** Peor estado de cada producto (o nada si no tiene vencimientos) — para pintar el catálogo. */
    public function estadosPorProductos(array $productoIds): array
    {
        $productoIds = array_values(array_unique(array_map('intval', $productoIds)));
        if (!$productoIds) {
            return [];
        }

        $marcadores = implode(',', array_fill(0, count($productoIds), '?'));
        $stmt = Database::conexion()->prepare(
            "SELECT v.producto_id, v.fecha_vencimiento, a.dias_antes
             FROM producto_vencimientos v
             JOIN producto_vencimiento_alertas a ON a.vencimiento_id = v.id_vencimiento
             WHERE v.producto_id IN ($marcadores)"
        );
        $stmt->execute($productoIds);

        $hoy = new DateTimeImmutable(date('Y-m-d'));
        $estados = [];
        foreach ($stmt->fetchAll() as $fila) {
            $fecha = new DateTimeImmutable($fila['fecha_vencimiento']);
            $diasRestantes = (int) $hoy->diff($fecha)->format('%r%a');
            $estado = self::calcularEstado($diasRestantes, (int) $fila['dias_antes']);
            $pid = (int) $fila['producto_id'];
            if (!isset($estados[$pid]) || self::pesoEstado($estado) < self::pesoEstado($estados[$pid])) {
                $estados[$pid] = $estado;
            }
        }

        return $estados;
    }

    private static function pesoEstado(string $estado): int
    {
        return ['rojo' => 0, 'amarillo' => 1, 'verde' => 2][$estado] ?? 3;
    }

    private static function calcularEstado(int $diasRestantes, int $diasAntes): string
    {
        if ($diasRestantes < 0) {
            return 'rojo';
        }

        return $diasRestantes <= $diasAntes ? 'amarillo' : 'verde';
    }

    /** Agrega 'dias_restantes', la lista de 'alertas' (con su propio estado) y el 'estado' general (el más urgente de sus avisos). */
    private static function conAlertasYEstado(array $vencimiento): array
    {
        $stmt = Database::conexion()->prepare(
            'SELECT id_alerta, dias_antes, creado_en FROM producto_vencimiento_alertas WHERE vencimiento_id = :id ORDER BY dias_antes ASC'
        );
        $stmt->execute(['id' => $vencimiento['id_vencimiento']]);

        $hoy = new DateTimeImmutable(date('Y-m-d'));
        $fecha = new DateTimeImmutable($vencimiento['fecha_vencimiento']);
        $diasRestantes = (int) $hoy->diff($fecha)->format('%r%a');

        $alertas = [];
        $peorEstado = 'verde';
        foreach ($stmt->fetchAll() as $a) {
            $estado = self::calcularEstado($diasRestantes, (int) $a['dias_antes']);
            $alertas[] = ['id_alerta' => (int) $a['id_alerta'], 'dias_antes' => (int) $a['dias_antes'], 'estado' => $estado];
            if (self::pesoEstado($estado) < self::pesoEstado($peorEstado)) {
                $peorEstado = $estado;
            }
        }

        $vencimiento['dias_restantes'] = $diasRestantes;
        $vencimiento['alertas'] = $alertas;
        $vencimiento['estado'] = $peorEstado;

        return $vencimiento;
    }

    private static function obtenerOCrearVencimiento(PDO $pdo, int $productoId, ?string $lote, string $fecha): int
    {
        $stmt = $pdo->prepare(
            "SELECT id_vencimiento FROM producto_vencimientos
             WHERE producto_id = :p AND COALESCE(descripcion_lote, '') = :lote AND fecha_vencimiento = :f"
        );
        $stmt->execute(['p' => $productoId, 'lote' => $lote ?? '', 'f' => $fecha]);
        $existente = $stmt->fetchColumn();
        if ($existente) {
            return (int) $existente;
        }

        $stmt = $pdo->prepare('INSERT INTO producto_vencimientos (producto_id, descripcion_lote, fecha_vencimiento) VALUES (:p, :lote, :f)');
        $stmt->execute(['p' => $productoId, 'lote' => $lote, 'f' => $fecha]);

        return (int) $pdo->lastInsertId();
    }

    private static function validarDiasAntes(int $diasAntes): void
    {
        if ($diasAntes < 0 || $diasAntes > 3650) {
            throw new VencimientoException('Los días de anticipación deben ser un número entre 0 y 3650.');
        }
    }

    private static function normalizarLote(?string $lote): ?string
    {
        $lote = $lote !== null ? trim($lote) : null;

        return $lote === '' ? null : $lote;
    }

    private static function fechaValida(string $fecha): bool
    {
        $d = DateTime::createFromFormat('Y-m-d', $fecha);

        return $d !== false && $d->format('Y-m-d') === $fecha;
    }
}
