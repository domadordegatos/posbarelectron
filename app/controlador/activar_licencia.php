<?php
require_once "../modelo/bootstrap.php";
require_once "../modelo/Licencia.php";

header('Content-Type: application/json; charset=utf-8');

Csrf::validarApi();

if (Licencia::estaActivada()) {
    echo json_encode(['ok' => true]);
    exit;
}

$claveLicencia = trim($_POST['clave_licencia'] ?? '');
$nombreDispositivo = trim($_POST['nombre_dispositivo'] ?? '');

if ($claveLicencia === '') {
    echo json_encode(['ok' => false, 'error' => 'Ingresa la clave de licencia.']);
    exit;
}

try {
    Licencia::activar($claveLicencia, $nombreDispositivo);
    echo json_encode(['ok' => true]);
} catch (Throwable $e) {
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
