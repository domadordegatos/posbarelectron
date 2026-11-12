<?php
require_once __DIR__ . '/Database.php';

class proveedores
{
    public function buscar(string $termino, int $limite = 10): array
    {
        $pdo = Database::conexion();
        $like = '%' . trim($termino) . '%';
        $stmt = $pdo->prepare(
            'SELECT id_proveedor, nombre, nit, telefono
             FROM proveedores
             WHERE estado = 1 AND (nombre LIKE :like1 OR nit LIKE :like2)
             ORDER BY nombre ASC
             LIMIT :limite'
        );
        $stmt->bindValue(':like1', $like, PDO::PARAM_STR);
        $stmt->bindValue(':like2', $like, PDO::PARAM_STR);
        $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function listar(): array
    {
        $pdo = Database::conexion();
        $stmt = $pdo->query('SELECT id_proveedor, nombre, nit, telefono, direccion, estado FROM proveedores ORDER BY nombre ASC');

        return $stmt->fetchAll();
    }

    public function crear(string $nombre, ?string $nit, ?string $telefono, ?string $direccion): int
    {
        $pdo = Database::conexion();
        $stmt = $pdo->prepare(
            'INSERT INTO proveedores (nombre, nit, telefono, direccion) VALUES (:nombre, :nit, :telefono, :direccion)'
        );

        return $stmt->execute([
            'nombre' => $nombre,
            'nit' => $nit ?: null,
            'telefono' => $telefono ?: null,
            'direccion' => $direccion ?: null,
        ]) ? 1 : 0;
    }

    public function editar(int $id, string $nombre, ?string $nit, ?string $telefono, ?string $direccion, int $estado): int
    {
        $pdo = Database::conexion();
        $stmt = $pdo->prepare(
            'UPDATE proveedores SET nombre = :nombre, nit = :nit, telefono = :telefono, direccion = :direccion, estado = :estado
             WHERE id_proveedor = :id'
        );

        return $stmt->execute([
            'nombre' => $nombre,
            'nit' => $nit ?: null,
            'telefono' => $telefono ?: null,
            'direccion' => $direccion ?: null,
            'estado' => $estado,
            'id' => $id,
        ]) ? 1 : 0;
    }
}
