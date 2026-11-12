<?php
/** Edita el lote/fecha de un vencimiento existente (los avisos no cambian). */
require_once "../modelo/bootstrap.php";
require_once "../modelo/vencimientos.php";

Auth::requerirModuloApi('vencimientos');
Auth::requerirPermisoApi('productos.editar');
Csrf::validarApi();

header('Content-Type: application/json');

$idVencimiento = filter_input(INPUT_POST, 'vencimiento_id', FILTER_VALIDATE_INT);
$descripcionLote = $_POST['descripcion_lote'] ?? null;
$fechaVencimiento = trim($_POST['fecha_vencimiento'] ?? '');

if (!$idVencimiento || $fechaVencimiento === '') {
    echo json_encode(['error' => 'Datos inválidos.']);
    exit;
}

try {
    $ok = (new vencimientos())->editarVencimiento($idVencimiento, $descripcionLote, $fechaVencimiento);
    echo json_encode(['ok' => $ok]);
} catch (VencimientoException $e) {
    echo json_encode(['error' => $e->getMessage()]);
} catch (Throwable $e) {
    error_log('Error al editar vencimiento: ' . $e->getMessage());
    echo json_encode(['error' => 'No se pudo editar el vencimiento.']);
}
