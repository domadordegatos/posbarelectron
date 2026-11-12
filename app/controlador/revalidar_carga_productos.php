<?php
/**
 * Igual que previsualizar_carga_productos.php pero sobre filas que el usuario ya editó a mano
 * en la tabla de vista previa (en vez de un archivo nuevo) — mismo validar(), misma respuesta,
 * para que corregir un error no obligue a re-subir el CSV completo.
 */
require_once "../modelo/bootstrap.php";
require_once "../modelo/ImportacionProductos.php";

Auth::requerirPermisoApi('productos.editar');
Csrf::validarApi();

header('Content-Type: application/json');

$filas = json_decode($_POST['filas'] ?? '', true);
if (!is_array($filas)) {
    echo json_encode(['error' => 'Datos inválidos.']);
    exit;
}

try {
    $importador = new ImportacionProductos();
    echo json_encode($importador->validar($filas));
} catch (Throwable $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
