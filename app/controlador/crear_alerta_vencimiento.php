<?php
require_once "../modelo/bootstrap.php";
require_once "../modelo/vencimientos.php";

Auth::requerirModuloApi('vencimientos');
Auth::requerirPermisoApi('productos.editar');
Csrf::validarApi();

header('Content-Type: application/json');

$productoId = filter_input(INPUT_POST, 'producto_id', FILTER_VALIDATE_INT);
$descripcionLote = $_POST['descripcion_lote'] ?? null;
$fechaVencimiento = trim($_POST['fecha_vencimiento'] ?? '');
$diasAntes = filter_input(INPUT_POST, 'dias_antes', FILTER_VALIDATE_INT);

if (!$productoId || $fechaVencimiento === '' || $diasAntes === false || $diasAntes === null) {
    echo json_encode(['error' => 'Datos inválidos.']);
    exit;
}

try {
    $idAlerta = (new vencimientos())->agregarAlerta($productoId, $descripcionLote, $fechaVencimiento, $diasAntes);
    echo json_encode(['ok' => true, 'id_alerta' => $idAlerta]);
} catch (VencimientoException $e) {
    echo json_encode(['error' => $e->getMessage()]);
} catch (Throwable $e) {
    error_log('Error al crear alerta de vencimiento: ' . $e->getMessage());
    echo json_encode(['error' => 'No se pudo crear la alerta.']);
}
