<?php
require_once "../modelo/bootstrap.php";
require_once "../modelo/ImportacionProductos.php";

$usuarioActual = Auth::requerirPermisoApi('productos.editar');
Csrf::validarApi();

header('Content-Type: application/json');

$filasRaw = $_POST['filas'] ?? '';
$filas = json_decode($filasRaw, true);
if (!is_array($filas) || empty($filas)) {
    echo json_encode(['error' => 'No hay filas para procesar. Vuelve a subir el archivo.']);
    exit;
}

try {
    $importador = new ImportacionProductos();
    $resultado = $importador->confirmar($filas, (int) $usuarioActual['id']);
    echo json_encode($resultado);
} catch (Throwable $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
