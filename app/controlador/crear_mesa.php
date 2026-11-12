<?php
require_once "../modelo/bootstrap.php";
require_once "../modelo/mesas.php";

Auth::requerirModuloApi('mesas_comandas');
Auth::requerirRolApi('admin');
Csrf::validarApi();

header('Content-Type: application/json');

$nombre = trim($_POST['nombre'] ?? '');

if ($nombre === '') {
    echo json_encode(['error' => 'El nombre de la mesa es obligatorio.']);
    exit;
}

$id = (new mesas())->crear($nombre);

echo json_encode(['ok' => true, 'id_mesa' => $id, 'nombre' => $nombre]);
