<?php
require_once "../modelo/bootstrap.php";
require_once "../modelo/ConteoInventario.php";

$usuario = Auth::requerirPermisoApi('conteo_inventario.registrar');
Csrf::validarApi();

header('Content-Type: application/json');

$idConteo = filter_input(INPUT_POST, 'conteo_id', FILTER_VALIDATE_INT);
$tratamientoFaltante = ($_POST['tratamiento_faltante'] ?? 'perdida') === 'ajuste' ? 'ajuste' : 'perdida';
$idMotivoFaltante = filter_input(INPUT_POST, 'id_motivo_faltante', FILTER_VALIDATE_INT) ?: null;
$idMotivoSobrante = filter_input(INPUT_POST, 'id_motivo_sobrante', FILTER_VALIDATE_INT) ?: null;

if (!$idConteo) {
    echo json_encode(['error' => 'Conteo inválido.']);
    exit;
}

try {
    $resultado = (new ConteoInventario())->cerrar(
        $idConteo, (int) $usuario['id'], $tratamientoFaltante, $idMotivoFaltante, $idMotivoSobrante
    );
    echo json_encode(['ok' => true] + $resultado);
} catch (InvalidArgumentException $e) {
    echo json_encode(['error' => $e->getMessage()]);
} catch (Throwable $e) {
    error_log('Error al cerrar conteo: ' . $e->getMessage());
    echo json_encode(['error' => 'No se pudo cerrar el conteo.']);
}
