<?php
require_once "../modelo/bootstrap.php";
require_once "../modelo/Fidelizacion.php";

Auth::requerirRolApi('admin');
Csrf::validarApi();

header('Content-Type: application/json');

$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT) ?: null;
$visitasRequeridas = filter_input(INPUT_POST, 'visitas_requeridas', FILTER_VALIDATE_INT);
$tipoRecompensa = trim($_POST['tipo_recompensa'] ?? '');
$valor = filter_input(INPUT_POST, 'valor', FILTER_VALIDATE_FLOAT) ?: 0.0;
$descripcion = trim($_POST['descripcion'] ?? '');

if ($visitasRequeridas === false || $visitasRequeridas === null || $tipoRecompensa === '') {
    echo json_encode(['error' => 'Completa las visitas requeridas y el tipo de recompensa.']);
    exit;
}

try {
    Fidelizacion::guardarNivel($id, $visitasRequeridas, $tipoRecompensa, $valor, $descripcion);
    echo json_encode(['ok' => true]);
} catch (InvalidArgumentException $e) {
    echo json_encode(['error' => $e->getMessage()]);
} catch (LicenciaSinConexionException $e) {
    echo json_encode(['error' => 'Sin conexión a internet.']);
} catch (Throwable $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
