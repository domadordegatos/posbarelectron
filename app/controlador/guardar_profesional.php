<?php
require_once "../modelo/bootstrap.php";
require_once "../modelo/profesionales.php";

// Crear o editar un profesional (con id = editar). Solo el administrador.
Auth::requerirRolApi('admin');
Auth::requerirModuloApi('profesionales');
Csrf::validarApi();

header('Content-Type: application/json');

$id = filter_input(INPUT_POST, 'id_profesional', FILTER_VALIDATE_INT);
$nombre = trim($_POST['nombre'] ?? '');
$comision = filter_input(INPUT_POST, 'comision_porcentaje', FILTER_VALIDATE_FLOAT);

if ($comision === false || $comision === null) {
    echo json_encode(['error' => 'La comisión debe ser un número entre 0 y 100.']);
    exit;
}

$obj = new profesionales();

try {
    if ($id !== false && $id !== null && $id > 0) {
        $profesional = $obj->editar($id, $nombre, $comision, !empty($_POST['estado']) ? 1 : 0);
    } else {
        $profesional = $obj->crear($nombre, $comision);
    }
    echo json_encode(['ok' => true] + $profesional);
} catch (InvalidArgumentException $e) {
    echo json_encode(['error' => $e->getMessage()]);
} catch (Throwable $e) {
    error_log('Error al guardar profesional: ' . $e->getMessage());
    echo json_encode(['error' => 'No se pudo guardar el profesional.']);
}
