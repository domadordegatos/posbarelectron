<?php
/**
 * Crear al vuelo un motivo nuevo (ej. "Vencido para caridad") desde el formulario de
 * salida/entrada sin pérdida ni ganancia. Si ya existe uno con ese nombre (sin importar
 * mayúsculas) devuelve el existente.
 */
require_once "../modelo/bootstrap.php";
require_once "../modelo/motivos_ajuste.php";

Auth::requerirPermisoApi('ajustes_inventario.registrar');
Csrf::validarApi();

header('Content-Type: application/json');

try {
    $motivo = (new motivos_ajuste())->crear(trim($_POST['nombre'] ?? ''));
    echo json_encode(['ok' => true] + $motivo);
} catch (InvalidArgumentException $e) {
    echo json_encode(['error' => $e->getMessage()]);
} catch (Throwable $e) {
    error_log('Error al crear motivo de ajuste: ' . $e->getMessage());
    echo json_encode(['error' => 'No se pudo crear el motivo.']);
}
