<?php
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Configuracion.php';

class Promociones
{
    public static function listar(): array
    {
        $pdo = Database::conexion();
        $stmt = $pdo->query(
            'SELECT pr.*, p.nombre_producto
             FROM promociones pr
             JOIN productos p ON p.id_producto = pr.producto_id
             ORDER BY pr.id_promocion DESC'
        );

        return $stmt->fetchAll();
    }

    public static function obtener(int $id): ?array
    {
        $pdo = Database::conexion();
        $stmt = $pdo->prepare('SELECT * FROM promociones WHERE id_promocion = :id');
        $stmt->execute(['id' => $id]);

        return $stmt->fetch() ?: null;
    }

    public static function crear(
        string $nombre,
        int $productoId,
        string $tipoDescuento,
        float $valor,
        ?array $diasSemana,
        ?string $horaInicio,
        ?string $horaFin
    ): int {
        self::validar($nombre, $tipoDescuento, $valor, $horaInicio, $horaFin);

        $pdo = Database::conexion();
        $stmt = $pdo->prepare(
            'INSERT INTO promociones (nombre, producto_id, tipo_descuento, valor, dias_semana, hora_inicio, hora_fin)
             VALUES (:nombre, :producto_id, :tipo, :valor, :dias, :hora_inicio, :hora_fin)'
        );
        $stmt->execute([
            'nombre' => $nombre,
            'producto_id' => $productoId,
            'tipo' => $tipoDescuento,
            'valor' => $valor,
            'dias' => $diasSemana ? implode(',', $diasSemana) : null,
            'hora_inicio' => $horaInicio ?: null,
            'hora_fin' => $horaFin ?: null,
        ]);

        return (int) $pdo->lastInsertId();
    }

    public static function actualizar(
        int $id,
        string $nombre,
        string $tipoDescuento,
        float $valor,
        ?array $diasSemana,
        ?string $horaInicio,
        ?string $horaFin,
        bool $estado
    ): bool {
        self::validar($nombre, $tipoDescuento, $valor, $horaInicio, $horaFin);

        $pdo = Database::conexion();
        $stmt = $pdo->prepare(
            'UPDATE promociones
             SET nombre = :nombre, tipo_descuento = :tipo, valor = :valor, dias_semana = :dias,
                 hora_inicio = :hora_inicio, hora_fin = :hora_fin, estado = :estado
             WHERE id_promocion = :id'
        );

        return $stmt->execute([
            'nombre' => $nombre,
            'tipo' => $tipoDescuento,
            'valor' => $valor,
            'dias' => $diasSemana ? implode(',', $diasSemana) : null,
            'hora_inicio' => $horaInicio ?: null,
            'hora_fin' => $horaFin ?: null,
            'estado' => $estado ? 1 : 0,
            'id' => $id,
        ]);
    }

    public static function eliminar(int $id): bool
    {
        $pdo = Database::conexion();

        return $pdo->prepare('DELETE FROM promociones WHERE id_promocion = :id')->execute(['id' => $id]);
    }

    private static function validar(string $nombre, string $tipoDescuento, float $valor, ?string $horaInicio, ?string $horaFin): void
    {
        if (trim($nombre) === '') {
            throw new InvalidArgumentException('El nombre de la promoción es obligatorio.');
        }
        if (!in_array($tipoDescuento, ['porcentaje', 'fijo'], true)) {
            throw new InvalidArgumentException('Tipo de descuento inválido.');
        }
        if ($valor <= 0) {
            throw new InvalidArgumentException('El valor del descuento debe ser mayor a cero.');
        }
        if ($tipoDescuento === 'porcentaje' && $valor > 100) {
            throw new InvalidArgumentException('El porcentaje de descuento no puede superar 100.');
        }
        if (($horaInicio !== null) !== ($horaFin !== null)) {
            throw new InvalidArgumentException('Indica hora de inicio y de fin, o deja ambas vacías.');
        }
    }

    /**
     * Promoción vigente ahora mismo para ese producto, o null. Si hay más
     * de una vigente a la vez, se usa la más reciente (id más alto) — no
     * se acumulan descuentos.
     */
    public static function activaParaProducto(int $productoId, ?DateTimeImmutable $momento = null): ?array
    {
        if (!Configuracion::moduloActivo('promociones')) {
            return null;
        }

        $momento = $momento ?? new DateTimeImmutable('now');
        $diaSemana = (int) $momento->format('w');
        $horaActual = $momento->format('H:i');

        $pdo = Database::conexion();
        $stmt = $pdo->prepare('SELECT * FROM promociones WHERE producto_id = :id AND estado = 1 ORDER BY id_promocion DESC');
        $stmt->execute(['id' => $productoId]);

        foreach ($stmt->fetchAll() as $promo) {
            if ($promo['dias_semana']) {
                $dias = array_map('intval', explode(',', $promo['dias_semana']));
                if (!in_array($diaSemana, $dias, true)) {
                    continue;
                }
            }
            if ($promo['hora_inicio'] && $promo['hora_fin']) {
                if (!self::dentroDeHorario($horaActual, $promo['hora_inicio'], $promo['hora_fin'])) {
                    continue;
                }
            }

            return $promo;
        }

        return null;
    }

    private static function dentroDeHorario(string $actual, string $inicio, string $fin): bool
    {
        // Rango normal (ej. 15:00–18:00), o que cruza medianoche (ej. 22:00–02:00).
        if ($inicio <= $fin) {
            return $actual >= $inicio && $actual < $fin;
        }

        return $actual >= $inicio || $actual < $fin;
    }

    /** Aplica la promoción vigente (si hay) sobre un precio base. Nunca deja el precio en negativo. */
    public static function precioConPromocion(int $productoId, float $precioBase, ?DateTimeImmutable $momento = null): array
    {
        $promo = self::activaParaProducto($productoId, $momento);
        if (!$promo) {
            return ['precio' => $precioBase, 'promocion' => null];
        }

        $precio = $promo['tipo_descuento'] === 'porcentaje'
            ? $precioBase * (1 - ((float) $promo['valor'] / 100))
            : $precioBase - (float) $promo['valor'];

        return ['precio' => round(max(0, $precio), 2), 'promocion' => $promo];
    }
}
