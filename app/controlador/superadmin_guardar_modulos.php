<?php
require_once "../modelo/bootstrap.php";

Auth::requerirSuperAdminApi();
Csrf::validarApi();

header('Content-Type: application/json');

$tipoNegocio = $_POST['tipo_negocio'] ?? 'personalizado';
$modulosRaw = $_POST['modulos'] ?? '[]';
$modulosMarcados = is_string($modulosRaw) ? json_decode($modulosRaw, true) : $modulosRaw;
$modulosMarcados = is_array($modulosMarcados) ? $modulosMarcados : [];

$modulos = [];
foreach (Configuracion::MODULOS as $modulo) {
    $modulos[$modulo] = in_array($modulo, $modulosMarcados, true);
}

Configuracion::guardarModulos((string) $tipoNegocio, $modulos);

echo json_encode(['ok' => true]);
