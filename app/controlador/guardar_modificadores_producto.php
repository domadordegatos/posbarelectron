<?php
require_once "../modelo/bootstrap.php";
require_once "../modelo/Modificadores.php";

Auth::requerirPermisoApi('productos.editar');
Csrf::validarApi();

header('Content-Type: application/json');

$idProducto = filter_input(INPUT_POST, 'id_producto', FILTER_VALIDATE_INT);
$gruposRaw = $_POST['grupos_modificadores'] ?? '[]';
$grupos = json_decode($gruposRaw, true) ?: [];

if ($idProducto === false || $idProducto === null) {
    echo json_encode(['error' => 'Producto inválido.']);
    exit;
}

try {
    (new Modificadores())->guardarGrupos($idProducto, $grupos);
    echo json_encode(['ok' => true]);
} catch (Throwable $e) {
    error_log('Error al guardar modificadores: ' . $e->getMessage());
    echo json_encode(['error' => 'No se pudo guardar los modificadores.']);
}
