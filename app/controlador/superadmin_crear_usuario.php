<?php
require_once "../modelo/bootstrap.php";
require_once "../modelo/usuarios.php";

Auth::requerirSuperAdminApi();
Csrf::validarApi();

header('Content-Type: application/json');

$nombre = trim($_POST['nombre'] ?? '');
$usuario = trim($_POST['usuario'] ?? '');
$password = (string) ($_POST['password'] ?? '');
$rol = $_POST['rol'] ?? 'admin';

if ($nombre === '' || $usuario === '' || strlen($password) < 6 || !in_array($rol, ['admin', 'cajero'], true)) {
    echo json_encode(['error' => 'Datos inválidos. La contraseña debe tener al menos 6 caracteres.']);
    exit;
}

$obj = new usuarios();

if ($obj->existeUsuario($usuario)) {
    echo json_encode(['error' => 'Ya existe un usuario con ese nombre de acceso.']);
    exit;
}

$ok = $obj->crear($nombre, $usuario, $password, $rol, 1);
echo json_encode($ok ? ['ok' => true] : ['error' => 'Error al crear el usuario.']);
