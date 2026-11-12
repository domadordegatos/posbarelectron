<?php
require_once __DIR__ . '/Database.php';

/** Registro de quién hizo qué cambio administrativo y cuándo — solo lectura para el rol admin. */
class Auditoria
{
    public static function registrar(?int $usuarioId, string $accion, string $entidadTipo, ?int $entidadId, array $detalle = []): void
    {
        $pdo = Database::conexion();
        $pdo->prepare(
            'INSERT INTO auditoria (usuario_id, accion, entidad_tipo, entidad_id, detalle)
             VALUES (:usuario_id, :accion, :entidad_tipo, :entidad_id, :detalle)'
        )->execute([
            'usuario_id' => $usuarioId,
            'accion' => $accion,
            'entidad_tipo' => $entidadTipo,
            'entidad_id' => $entidadId,
            'detalle' => json_encode($detalle, JSON_UNESCAPED_UNICODE),
        ]);
    }

    public static function listar(int $pagina, int $porPagina, string $fechaInicio = '', string $fechaFin = ''): array
    {
        $pdo = Database::conexion();
        $offset = max(0, ($pagina - 1) * $porPagina);
        $condiciones = [];
        $params = [];
        if ($fechaInicio !== '' && $fechaFin !== '') {
            $condiciones[] = 'date(a.creado_en) BETWEEN :inicio AND :fin';
            $params['inicio'] = $fechaInicio;
            $params['fin'] = $fechaFin;
        }
        $whereSql = !empty($condiciones) ? 'WHERE ' . implode(' AND ', $condiciones) : '';

        $stmt = $pdo->prepare(
            "SELECT a.id_evento, a.accion, a.entidad_tipo, a.entidad_id, a.detalle, a.creado_en, u.nombre AS usuario
             FROM auditoria a
             LEFT JOIN usuarios u ON u.id_usuario = a.usuario_id
             $whereSql
             ORDER BY a.id_evento DESC
             LIMIT :porPagina OFFSET :offset"
        );
        foreach ($params as $clave => $valor) {
            $stmt->bindValue(":$clave", $valor, PDO::PARAM_STR);
        }
        $stmt->bindValue(':porPagina', $porPagina, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $filas = $stmt->fetchAll();

        $stmtTotal = $pdo->prepare("SELECT COUNT(*) FROM auditoria a $whereSql");
        foreach ($params as $clave => $valor) {
            $stmtTotal->bindValue(":$clave", $valor, PDO::PARAM_STR);
        }
        $stmtTotal->execute();
        $total = (int) $stmtTotal->fetchColumn();

        return ['filas' => $filas, 'total' => $total];
    }
}
