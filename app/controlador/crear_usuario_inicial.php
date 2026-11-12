<?php
/**
 * Crea el primer usuario administrador — solo funciona si la instalación
 * todavía no tiene ningún usuario (arranque en frío de una app de
 * escritorio local, sin panel de superadministrador todavía). En cuanto
 * exista un usuario, este endpoint queda inutilizado.
 */
require_once "../modelo/bootstrap.php";
require_once "../modelo/usuarios.php";

header('Content-Type: application/json');

Csrf::validarApi();

$obj = new usuarios();

if (count($obj->listar()) > 0) {
    http_response_code(403);
    echo json_encode(['error' => 'Esta instalación ya tiene usuarios — usa el login normal.']);
    exit;
}

$usuario = trim($_POST['usuario'] ?? '');
$password = (string) ($_POST['password'] ?? '');
// El nombre para mostrar es opcional aquí (arranque rápido) — si no lo
// mandan, se usa el propio usuario; se puede poner un nombre real después
// desde Usuarios sin perder nada.
$nombre = trim($_POST['nombre'] ?? '');
if ($nombre === '') {
    $nombre = $usuario;
}

if ($usuario === '' || strlen($password) < 6) {
    echo json_encode(['error' => 'Completa usuario y una contraseña de al menos 6 caracteres.']);
    exit;
}

$obj->crear($nombre, $usuario, $password, 'admin', 1);

$sesion = Auth::intentarLogin($usuario, $password);
echo json_encode(['ok' => true, 'redirect' => '/vista/home/index.php']);
