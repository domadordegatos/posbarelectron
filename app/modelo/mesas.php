<?php
require_once __DIR__ . '/Database.php';

/**
 * Catálogo fijo de mesas (nombre + activa/inactiva) — lo configura el
 * admin una sola vez desde Configuración; distinto de `comandas`, que es
 * la cuenta/pedido activo en un momento dado sobre una de estas mesas.
 */
class mesas
{
    public function listar(): array
    {
        $pdo = Database::conexion();

        return $pdo->query('SELECT id_mesa, nombre, activa FROM mesas ORDER BY nombre')->fetchAll();
    }

    public function crear(string $nombre): int
    {
        $pdo = Database::conexion();
        $stmt = $pdo->prepare('INSERT INTO mesas (nombre, activa) VALUES (:nombre, 1)');
        $stmt->execute(['nombre' => $nombre]);

        return (int) $pdo->lastInsertId();
    }

    public function actualizar(int $id, string $nombre, int $activa): bool
    {
        $pdo = Database::conexion();
        $stmt = $pdo->prepare('UPDATE mesas SET nombre = :nombre, activa = :activa WHERE id_mesa = :id');

        return $stmt->execute(['nombre' => $nombre, 'activa' => $activa, 'id' => $id]);
    }
}
