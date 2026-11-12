<?php
require_once "../modelo/bootstrap.php";
require_once "../modelo/productos.php";

Auth::requerirRolApi('admin');
Csrf::validarApi();

header('Content-Type: application/json');

$idCategoria = filter_input(INPUT_POST, 'categoria', FILTER_VALIDATE_INT);
$idsJson = $_POST['ids'] ?? '[]';
$ids = json_decode($idsJson, true);
if (!$idCategoria || !is_array($ids)) {
    echo json_encode(['error' => 'Datos inválidos.']);
    exit;
}
$ids = array_map('intval', $ids);

try {
    (new productos())->guardarOrdenProductosCategoria($idCategoria, $ids);
    echo json_encode(['ok' => true]);
} catch (Throwable $e) {
    error_log('Error al guardar orden de productos de categoría: ' . $e->getMessage());
    echo json_encode(['error' => 'No se pudo guardar el orden.']);
}
