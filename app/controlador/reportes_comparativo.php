<?php
require_once "../modelo/bootstrap.php";
require_once "../modelo/movimientos.php";

Auth::requerirPermisoApi('movimientos.ver');

header('Content-Type: application/json');

$inicioA = trim($_POST['inicio_a'] ?? '');
$finA = trim($_POST['fin_a'] ?? '');
$inicioB = trim($_POST['inicio_b'] ?? '');
$finB = trim($_POST['fin_b'] ?? '');

if ($inicioA === '' || $finA === '' || $inicioB === '' || $finB === '') {
    echo json_encode(['error' => 'Indica los dos rangos de fechas.']);
    exit;
}

$obj = new movimientos();
echo json_encode([
    'periodo_a' => $obj->resumenPeriodo($inicioA, $finA),
    'periodo_b' => $obj->resumenPeriodo($inicioB, $finB),
]);
