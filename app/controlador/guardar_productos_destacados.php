<?php
require_once "../modelo/bootstrap.php";
require_once "../modelo/productos.php";

Auth::requerirRolApi('admin');
Csrf::validarApi();

header('Content-Type: application/json');

$idsJson = $_POST['ids'] ?? '[]';
$ids = json_decode($idsJson, true);
if (!is_array($ids)) {
    echo json_encode(['error' => 'Lista inválida.']);
    exit;
}
$ids = array_map('intval', $ids);

try {
    (new productos())->guardarDestacados($ids);
    echo json_encode(['ok' => true]);
} catch (Throwable $e) {
    error_log('Error al guardar productos destacados: ' . $e->getMessage());
    echo json_encode(['error' => 'No se pudo guardar el orden.']);
}
