<?php
require_once "../modelo/bootstrap.php";
require_once "../modelo/Promociones.php";

Auth::requerirModuloApi('promociones');
Auth::requerirRolApi('admin');
Csrf::validarApi();

header('Content-Type: application/json');

$nombre = trim($_POST['nombre'] ?? '');
$productoId = filter_input(INPUT_POST, 'producto_id', FILTER_VALIDATE_INT);
$tipoDescuento = trim($_POST['tipo_descuento'] ?? '');
$valor = filter_input(INPUT_POST, 'valor', FILTER_VALIDATE_FLOAT);
$diasSemana = array_values(array_filter(explode(',', $_POST['dias_semana'] ?? ''), fn ($d) => $d !== ''));
$diasSemana = !empty($diasSemana) ? array_map('intval', $diasSemana) : null;
$horaInicio = trim($_POST['hora_inicio'] ?? '') ?: null;
$horaFin = trim($_POST['hora_fin'] ?? '') ?: null;

if ($productoId === false || $productoId === null || $valor === false || $valor === null) {
    echo json_encode(['error' => 'Completa el producto y el valor del descuento.']);
    exit;
}

try {
    $id = Promociones::crear($nombre, $productoId, $tipoDescuento, $valor, $diasSemana, $horaInicio, $horaFin);
    echo json_encode(['ok' => true, 'id_promocion' => $id]);
} catch (InvalidArgumentException $e) {
    echo json_encode(['error' => $e->getMessage()]);
} catch (Throwable $e) {
    error_log('Error al crear promoción: ' . $e->getMessage());
    echo json_encode(['error' => 'Error al crear la promoción.']);
}
