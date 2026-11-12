<?php
require_once __DIR__ . '/Database.php';

class permisos
{
    public function listar(): array
    {
        $pdo = Database::conexion();
        $stmt = $pdo->query('SELECT id_permiso, clave, descripcion FROM permisos ORDER BY clave');

        return $stmt->fetchAll();
    }

    public function obtenerParaRol(string $rol): array
    {
        $pdo = Database::conexion();
        $stmt = $pdo->prepare(
            'SELECT p.clave FROM rol_permisos rp
             JOIN permisos p ON rp.id_permiso = p.id_permiso
             WHERE rp.rol = :rol'
        );
        $stmt->execute(['rol' => $rol]);

        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    /** Reemplaza por completo el conjunto de permisos concedidos a un rol. */
    public function guardarParaRol(string $rol, array $claves): bool
    {
        $pdo = Database::conexion();

        $pdo->beginTransaction();
        try {
            $pdo->prepare('DELETE FROM rol_permisos WHERE rol = :rol')->execute(['rol' => $rol]);

            $stmtId = $pdo->prepare('SELECT id_permiso FROM permisos WHERE clave = :clave');
            $stmtInsert = $pdo->prepare('INSERT INTO rol_permisos (rol, id_permiso) VALUES (:rol, :id_permiso)');

            foreach ($claves as $clave) {
                $stmtId->execute(['clave' => $clave]);
                $idPermiso = $stmtId->fetchColumn();
                if ($idPermiso !== false) {
                    $stmtInsert->execute(['rol' => $rol, 'id_permiso' => $idPermiso]);
                }
            }

            $pdo->commit();

            return true;
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }
}
