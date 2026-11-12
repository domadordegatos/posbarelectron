<?php
require_once __DIR__ . '/Database.php';

/**
 * Catálogo editable de motivos para "salida/entrada de inventario sin pérdida ni ganancia"
 * (canje, donación, vencimiento...) — mismo patrón que conceptos_gasto.php (ver migración 035),
 * adaptado (sin "categoría", acá no aplica). Ver migración 051.
 */
class motivos_ajuste
{
    /** Para la pantalla de administración: todos (también los desactivados) con cuántos movimientos los usan. */
    public function listarTodos(): array
    {
        return Database::conexion()->query(
            'SELECT m.id_motivo, m.nombre, m.estado,
                    (SELECT COUNT(*) FROM movimiento mv WHERE mv.motivo_ajuste_id = m.id_motivo) AS usos
             FROM motivos_ajuste_inventario m
             ORDER BY m.estado DESC, m.nombre COLLATE NOCASE ASC'
        )->fetchAll();
    }

    /** Solo los activos — para poblar el selector del formulario. */
    public function listarActivos(): array
    {
        return Database::conexion()->query(
            'SELECT id_motivo, nombre FROM motivos_ajuste_inventario WHERE estado = 1 ORDER BY nombre COLLATE NOCASE ASC'
        )->fetchAll();
    }

    public function obtener(int $idMotivo): ?array
    {
        $stmt = Database::conexion()->prepare('SELECT id_motivo, nombre, estado FROM motivos_ajuste_inventario WHERE id_motivo = :id');
        $stmt->execute(['id' => $idMotivo]);

        return $stmt->fetch() ?: null;
    }

    /**
     * Crea el motivo; si ya existe uno con ese nombre (sin importar mayúsculas) devuelve el
     * existente en vez de fallar — y si estaba desactivado lo reactiva.
     */
    public function crear(string $nombre): array
    {
        $nombre = trim(preg_replace('/\s+/u', ' ', $nombre));
        if ($nombre === '') {
            throw new InvalidArgumentException('El nombre es obligatorio.');
        }
        if (mb_strlen($nombre) > 80) {
            throw new InvalidArgumentException('El nombre es demasiado largo (máximo 80 caracteres).');
        }

        $pdo = Database::conexion();
        $existente = $this->buscarPorNombre($nombre);

        if ($existente) {
            if ((int) $existente['estado'] !== 1) {
                $pdo->prepare('UPDATE motivos_ajuste_inventario SET estado = 1 WHERE id_motivo = :id')
                    ->execute(['id' => $existente['id_motivo']]);
            }

            return $this->obtener((int) $existente['id_motivo']);
        }

        $pdo->prepare('INSERT INTO motivos_ajuste_inventario (nombre) VALUES (:nombre)')->execute(['nombre' => $nombre]);

        return $this->obtener((int) $pdo->lastInsertId());
    }

    /** Renombra o activa/desactiva. No permite dejar dos motivos con el mismo nombre. */
    public function editar(int $idMotivo, string $nombre, int $estado): array
    {
        $nombre = trim(preg_replace('/\s+/u', ' ', $nombre));
        if ($nombre === '') {
            throw new InvalidArgumentException('El nombre es obligatorio.');
        }
        if (mb_strlen($nombre) > 80) {
            throw new InvalidArgumentException('El nombre es demasiado largo (máximo 80 caracteres).');
        }
        if ($this->obtener($idMotivo) === null) {
            throw new InvalidArgumentException('Ese motivo no existe.');
        }

        $otro = $this->buscarPorNombre($nombre);
        if ($otro !== null && (int) $otro['id_motivo'] !== $idMotivo) {
            throw new InvalidArgumentException('Ya existe otro motivo con ese nombre.');
        }

        Database::conexion()
            ->prepare('UPDATE motivos_ajuste_inventario SET nombre = :nombre, estado = :estado WHERE id_motivo = :id')
            ->execute(['nombre' => $nombre, 'estado' => $estado === 1 ? 1 : 0, 'id' => $idMotivo]);

        return $this->obtener($idMotivo);
    }

    /**
     * Busca por nombre sin importar mayúsculas NI tildes de mayúscula: el índice único de SQLite
     * (NOCASE) solo ignora mayúsculas ASCII, así que la comparación real se hace con
     * mb_strtolower; el índice queda como red de seguridad.
     */
    private function buscarPorNombre(string $nombre): ?array
    {
        $buscado = mb_strtolower($nombre);
        foreach (Database::conexion()->query('SELECT id_motivo, nombre, estado FROM motivos_ajuste_inventario')->fetchAll() as $fila) {
            if (mb_strtolower($fila['nombre']) === $buscado) {
                return $fila;
            }
        }

        return null;
    }
}
