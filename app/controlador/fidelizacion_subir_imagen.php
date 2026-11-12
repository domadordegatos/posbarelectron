<?php
require_once "../modelo/bootstrap.php";
require_once "../modelo/Fidelizacion.php";

Auth::requerirRolApi('admin');
Csrf::validarApi();

header('Content-Type: application/json');

$tipo = trim($_POST['tipo'] ?? '');

try {
    $url = Fidelizacion::subirImagen($tipo, $_FILES['imagen'] ?? []);
    echo json_encode(['ok' => true, 'url' => $url]);
} catch (InvalidArgumentException $e) {
    echo json_encode(['error' => $e->getMessage()]);
} catch (LicenciaSinConexionException $e) {
    echo json_encode(['error' => 'Sin conexión a internet.']);
} catch (Throwable $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
