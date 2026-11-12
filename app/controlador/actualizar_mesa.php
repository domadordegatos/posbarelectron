<?php
require_once "../modelo/bootstrap.php";
require_once "../modelo/mesas.php";

Auth::requerirModuloApi('mesas_comandas');
Auth::requerirRolApi('admin');
Csrf::validarApi();

header('Content-Type: application/json');

$id = filter_input(INPUT_POST, 'id_mesa', FILTER_VALIDATE_INT);
$nombre = trim($_POST['nombre'] ?? '');
$activa = filter_input(INPUT_POST, 'activa', FILTER_VALIDATE_INT);

if ($id === false || $id === null || $nombre === '' || $activa === false || $activa === null) {
    echo json_encode(['error' => 'Datos inválidos.']);
    exit;
}

$ok = (new mesas())->actualizar($id, $nombre, $activa);

echo json_encode(['ok' => (bool) $ok]);
