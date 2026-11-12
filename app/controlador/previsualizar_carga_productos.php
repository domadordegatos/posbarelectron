<?php
require_once "../modelo/bootstrap.php";
require_once "../modelo/ImportacionProductos.php";

Auth::requerirPermisoApi('productos.editar');
Csrf::validarApi();

header('Content-Type: application/json');

if (!isset($_FILES['archivo']) || $_FILES['archivo']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['error' => 'No se recibió ningún archivo válido.']);
    exit;
}

$extension = strtolower((string) pathinfo($_FILES['archivo']['name'], PATHINFO_EXTENSION));
if ($extension !== 'xlsx') {
    echo json_encode(['error' => 'Solo se aceptan archivos .xlsx — descarga la plantilla y súbela sin cambiarle el formato.']);
    exit;
}

try {
    $importador = new ImportacionProductos();
    $filasCrudas = $importador->leer($_FILES['archivo']['tmp_name']);
    $resultado = $importador->validar($filasCrudas);
    echo json_encode($resultado);
} catch (Throwable $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
