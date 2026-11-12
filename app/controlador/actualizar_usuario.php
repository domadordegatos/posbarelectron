<?php
require_once "../modelo/bootstrap.php";
require_once "../modelo/usuarios.php";
require_once "../modelo/Auditoria.php";

$usuarioActual = Auth::requerirRolApi('admin');
Csrf::validarApi();

header('Content-Type: application/json');

$id = filter_input(INPUT_POST, 'id_usuario', FILTER_VALIDATE_INT);
$nombre = trim($_POST['nombre'] ?? '');
$usuario = trim($_POST['usuario'] ?? '');
$rol = $_POST['rol'] ?? '';
$estado = filter_input(INPUT_POST, 'estado', FILTER_VALIDATE_INT);
$nuevaPassword = trim($_POST['password'] ?? '');

if ($id === false || $id === null || $nombre === '' || $usuario === ''
    || !in_array($rol, ['admin', 'cajero'], true) || $estado === false || $estado === null
    || ($nuevaPassword !== '' && strlen($nuevaPassword) < 6)
) {
    echo json_encode(['error' => 'Datos inválidos.']);
    exit;
}

$obj = new usuarios();

if ($obj->existeUsuario($usuario, $id)) {
    echo json_encode(['error' => 'Ya existe otro usuario con ese nombre de acceso.']);
    exit;
}

// No permitir dejar la instalación sin ningún admin activo.
if (($rol !== 'admin' || $estado !== 1) && $obj->contarAdminsActivosExcluyendo($id) === 0) {
    echo json_encode(['error' => 'Debe quedar al menos un administrador activo.']);
    exit;
}

$resultado = $obj->actualizar($id, $nombre, $usuario, $rol, $estado, $nuevaPassword !== '' ? $nuevaPassword : null);
if ($resultado === 1) {
    Auditoria::registrar((int) $usuarioActual['id'], 'usuario.editar', 'usuario', $id, [
        'usuario' => $usuario,
        'rol' => $rol,
        'estado' => $estado,
        'password_cambiada' => $nuevaPassword !== '',
    ]);
}

echo json_encode($resultado);
