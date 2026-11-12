<?php
require_once "../modelo/bootstrap.php";
require_once "../modelo/Fidelizacion.php";

// Cualquier usuario logueado (admin o cajero) puede generarlo en el momento
// del cobro — no es una acción de administración, es parte de vender.
Auth::requerirLoginApi();
Csrf::validarApi();

header('Content-Type: application/json');

try {
    $r = Fidelizacion::generarQr();
    echo json_encode(['ok' => true, 'url' => $r['url'] ?? '', 'expira_en' => $r['expira_en'] ?? null]);
} catch (LicenciaSinConexionException $e) {
    echo json_encode(['error' => 'Sin conexión a internet — este código no se puede generar sin conexión.']);
} catch (Throwable $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
