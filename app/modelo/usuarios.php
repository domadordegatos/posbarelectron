<?php
require_once __DIR__ . '/Database.php';

class usuarios
{
    public function listar(): array
    {
        $pdo = Database::conexion();
        $stmt = $pdo->query(
            'SELECT id_usuario, nombre, usuario, rol, estado, creado_en
             FROM usuarios ORDER BY nombre ASC'
        );

        return $stmt->fetchAll();
    }

    public function contarAdminsActivosExcluyendo(int $excluirId): int
    {
        $pdo = Database::conexion();
        $stmt = $pdo->prepare(
            "SELECT COUNT(*) AS total FROM usuarios
             WHERE rol = 'admin' AND estado = 1 AND id_usuario != :id"
        );
        $stmt->execute(['id' => $excluirId]);

        return (int) $stmt->fetch()['total'];
    }

    public function existeUsuario(string $usuario, ?int $excluirId = null): bool
    {
        $pdo = Database::conexion();
        $sql = 'SELECT id_usuario FROM usuarios WHERE usuario = :usuario';
        $params = ['usuario' => $usuario];
        if ($excluirId !== null) {
            $sql .= ' AND id_usuario != :id';
            $params['id'] = $excluirId;
        }
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        return (bool) $stmt->fetch();
    }

    public function crear(string $nombre, string $usuario, string $password, string $rol, int $estado): int
    {
        $pdo = Database::conexion();
        $stmt = $pdo->prepare(
            'INSERT INTO usuarios (nombre, usuario, password_hash, rol, estado)
             VALUES (:nombre, :usuario, :hash, :rol, :estado)'
        );

        return $stmt->execute([
            'nombre' => $nombre,
            'usuario' => $usuario,
            'hash' => password_hash($password, PASSWORD_BCRYPT),
            'rol' => $rol,
            'estado' => $estado,
        ]) ? 1 : 0;
    }

    /**
     * Restablece solo la contraseña, sin tocar nombre/usuario/rol/estado.
     * Usado por herramientas/restablecer_password.php (CLI) y por el
     * panel de superadministrador (soporte remoto sin necesitar SSH).
     */
    public function restablecerPassword(int $id, string $password): int
    {
        $pdo = Database::conexion();
        $stmt = $pdo->prepare('UPDATE usuarios SET password_hash = :hash WHERE id_usuario = :id');

        return $stmt->execute([
            'hash' => password_hash($password, PASSWORD_BCRYPT),
            'id' => $id,
        ]) ? 1 : 0;
    }

    public function actualizar(int $id, string $nombre, string $usuario, string $rol, int $estado, ?string $nuevaPassword): int
    {
        $pdo = Database::conexion();

        if ($nuevaPassword !== null && $nuevaPassword !== '') {
            $stmt = $pdo->prepare(
                'UPDATE usuarios SET nombre = :nombre, usuario = :usuario, rol = :rol, estado = :estado, password_hash = :hash
                 WHERE id_usuario = :id'
            );
            $params = [
                'nombre' => $nombre,
                'usuario' => $usuario,
                'rol' => $rol,
                'estado' => $estado,
                'hash' => password_hash($nuevaPassword, PASSWORD_BCRYPT),
                'id' => $id,
            ];
        } else {
            $stmt = $pdo->prepare(
                'UPDATE usuarios SET nombre = :nombre, usuario = :usuario, rol = :rol, estado = :estado
                 WHERE id_usuario = :id'
            );
            $params = [
                'nombre' => $nombre,
                'usuario' => $usuario,
                'rol' => $rol,
                'estado' => $estado,
                'id' => $id,
            ];
        }

        return $stmt->execute($params) ? 1 : 0;
    }
}
