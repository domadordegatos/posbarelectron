<?php
require_once "../modelo/bootstrap.php";
require_once "../modelo/Fidelizacion.php";

Auth::requerirRolApi('admin');
Csrf::validarApi();

header('Content-Type: application/json');

$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
$activo = filter_input(INPUT_POST, 'activo', FILTER_VALIDATE_INT);

if ($id === false || $id === null || $activo === false || $activo === null) {
    echo json_encode(['error' => 'Datos inválidos.']);
    exit;
}

try {
    Fidelizacion::cambiarEstadoNivel($id, $activo === 1);
    echo json_encode(['ok' => true]);
} catch (LicenciaSinConexionException $e) {
    echo json_encode(['error' => 'Sin conexión a internet.']);
} catch (Throwable $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
