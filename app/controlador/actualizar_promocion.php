<?php
require_once "../modelo/bootstrap.php";
require_once "../modelo/Promociones.php";

Auth::requerirModuloApi('promociones');
Auth::requerirRolApi('admin');
Csrf::validarApi();

header('Content-Type: application/json');

$idPromocion = filter_input(INPUT_POST, 'id_promocion', FILTER_VALIDATE_INT);
$nombre = trim($_POST['nombre'] ?? '');
$tipoDescuento = trim($_POST['tipo_descuento'] ?? '');
$valor = filter_input(INPUT_POST, 'valor', FILTER_VALIDATE_FLOAT);
$diasSemana = array_values(array_filter(explode(',', $_POST['dias_semana'] ?? ''), fn ($d) => $d !== ''));
$diasSemana = !empty($diasSemana) ? array_map('intval', $diasSemana) : null;
$horaInicio = trim($_POST['hora_inicio'] ?? '') ?: null;
$horaFin = trim($_POST['hora_fin'] ?? '') ?: null;
$estado = !empty($_POST['estado']);

if ($idPromocion === false || $idPromocion === null || $valor === false || $valor === null) {
    echo json_encode(['error' => 'Completa el valor del descuento.']);
    exit;
}

try {
    Promociones::actualizar($idPromocion, $nombre, $tipoDescuento, $valor, $diasSemana, $horaInicio, $horaFin, $estado);
    echo json_encode(['ok' => true]);
} catch (InvalidArgumentException $e) {
    echo json_encode(['error' => $e->getMessage()]);
} catch (Throwable $e) {
    error_log('Error al actualizar promoción: ' . $e->getMessage());
    echo json_encode(['error' => 'Error al actualizar la promoción.']);
}
