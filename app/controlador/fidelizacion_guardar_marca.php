<?php
require_once "../modelo/bootstrap.php";
require_once "../modelo/Fidelizacion.php";

Auth::requerirRolApi('admin');
Csrf::validarApi();

header('Content-Type: application/json');

$nombrePrograma = trim($_POST['nombre_programa'] ?? '');
$colorPrincipal = trim($_POST['color_principal'] ?? '');
$eslogan = trim($_POST['eslogan'] ?? '');
$fuente = trim($_POST['fuente'] ?? '');

if ($nombrePrograma === '') {
    echo json_encode(['error' => 'El nombre del programa es obligatorio.']);
    exit;
}

try {
    Fidelizacion::guardarMarca($nombrePrograma, $colorPrincipal, $eslogan, $fuente);
    echo json_encode(['ok' => true]);
} catch (InvalidArgumentException $e) {
    echo json_encode(['error' => $e->getMessage()]);
} catch (LicenciaSinConexionException $e) {
    echo json_encode(['error' => 'Sin conexión a internet.']);
} catch (Throwable $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
