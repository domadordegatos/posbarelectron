<?php
require_once "../modelo/bootstrap.php";
require_once "../modelo/Fidelizacion.php";

Auth::requerirLoginApi();
Csrf::validarApi();

header('Content-Type: application/json');

$numeroDocumento = trim($_POST['numero_documento'] ?? '');
if ($numeroDocumento === '') {
    echo json_encode(['error' => 'Indica el número de documento.']);
    exit;
}

try {
    echo json_encode(Fidelizacion::registrarVisita($numeroDocumento));
} catch (LicenciaSinConexionException $e) {
    echo json_encode(['error' => 'Sin conexión a internet.']);
} catch (Throwable $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
