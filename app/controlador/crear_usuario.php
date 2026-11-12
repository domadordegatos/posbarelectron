<?php
require_once "../modelo/bootstrap.php";
require_once "../modelo/usuarios.php";
require_once "../modelo/Auditoria.php";

$usuarioActual = Auth::requerirRolApi('admin');
Csrf::validarApi();

header('Content-Type: application/json');

$nombre = trim($_POST['nombre'] ?? '');
$usuario = trim($_POST['usuario'] ?? '');
$password = (string) ($_POST['password'] ?? '');
$rol = $_POST['rol'] ?? '';
$estado = filter_input(INPUT_POST, 'estado', FILTER_VALIDATE_INT);

if ($nombre === '' || $usuario === '' || strlen($password) < 6
    || !in_array($rol, ['admin', 'cajero'], true) || $estado === false || $estado === null
) {
    echo json_encode(['error' => 'Datos inválidos. La contraseña debe tener al menos 6 caracteres.']);
    exit;
}

$obj = new usuarios();

if ($obj->existeUsuario($usuario)) {
    echo json_encode(['error' => 'Ya existe un usuario con ese nombre de acceso.']);
    exit;
}

$resultado = $obj->crear($nombre, $usuario, $password, $rol, $estado);
if ($resultado === 1) {
    Auditoria::registrar((int) $usuarioActual['id'], 'usuario.crear', 'usuario', (int) Database::conexion()->lastInsertId(), [
        'usuario' => $usuario,
        'rol' => $rol,
        'estado' => $estado,
    ]);
}

echo json_encode($resultado);
