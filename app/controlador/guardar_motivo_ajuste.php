<?php
require_once "../modelo/bootstrap.php";
require_once "../modelo/motivos_ajuste.php";

Auth::requerirPermisoApi('productos.editar');
Csrf::validarApi();

header('Content-Type: application/json');

$idMotivo = filter_input(INPUT_POST, 'id_motivo', FILTER_VALIDATE_INT);
if ($idMotivo === false || $idMotivo === null) {
    echo json_encode(['error' => 'Motivo inválido.']);
    exit;
}

try {
    $motivo = (new motivos_ajuste())->editar($idMotivo, trim($_POST['nombre'] ?? ''), !empty($_POST['estado']) ? 1 : 0);
    echo json_encode(['ok' => true] + $motivo);
} catch (InvalidArgumentException $e) {
    echo json_encode(['error' => $e->getMessage()]);
} catch (Throwable $e) {
    error_log('Error al guardar motivo de ajuste: ' . $e->getMessage());
    echo json_encode(['error' => 'No se pudo guardar el motivo.']);
}
