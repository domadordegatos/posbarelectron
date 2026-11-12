<?php
require_once "../modelo/bootstrap.php";
require_once "../modelo/usuarios.php";

Auth::requerirSuperAdminApi();
Csrf::validarApi();

header('Content-Type: application/json');

$idUsuario = filter_input(INPUT_POST, 'id_usuario', FILTER_VALIDATE_INT);
$password = (string) ($_POST['password'] ?? '');

if ($idUsuario === false || $idUsuario === null || strlen($password) < 6) {
    echo json_encode(['error' => 'Selecciona un usuario e indica una contraseña de al menos 6 caracteres.']);
    exit;
}

$obj = new usuarios();
$ok = $obj->restablecerPassword($idUsuario, $password);
echo json_encode($ok ? ['ok' => true] : ['error' => 'Error al restablecer la contraseña.']);
