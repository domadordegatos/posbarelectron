<?php
require_once __DIR__ . '/Database.php';

class combos
{
    /** Reemplaza por completo la receta de un combo — el editor siempre manda la lista completa. */
    public function guardarComponentes(int $idProducto, array $componentes): bool
    {
        $pdo = Database::conexion();

        $pdo->beginTransaction();
        try {
            $pdo->prepare('DELETE FROM producto_componentes WHERE producto_combo_id = :id')->execute(['id' => $idProducto]);

            $stmt = $pdo->prepare(
                'INSERT INTO producto_componentes (producto_combo_id, producto_base_id, cantidad)
                 VALUES (:combo, :base, :cantidad)'
            );
            foreach ($componentes as $componente) {
                $idBase = (int) ($componente['producto_base_id'] ?? 0);
                $cantidad = (int) ($componente['cantidad'] ?? 0);
                if ($idBase <= 0 || $cantidad <= 0 || $idBase === $idProducto) {
                    continue;
                }
                $stmt->execute(['combo' => $idProducto, 'base' => $idBase, 'cantidad' => $cantidad]);
            }

            $pdo->commit();

            return true;
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public function obtenerComponentes(int $idProducto): array
    {
        $pdo = Database::conexion();
        $stmt = $pdo->prepare(
            'SELECT pc.producto_base_id, pc.cantidad, p.nombre_producto
             FROM producto_componentes pc
             JOIN productos p ON p.id_producto = pc.producto_base_id
             WHERE pc.producto_combo_id = :id'
        );
        $stmt->execute(['id' => $idProducto]);

        return $stmt->fetchAll();
    }
}
