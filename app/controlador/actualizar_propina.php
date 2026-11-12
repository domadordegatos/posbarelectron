<?php
require_once "../modelo/bootstrap.php";

// El porcentaje sugerido y si se aplica solo al cobrar son decisión del negocio
// (admin). El módulo "propina" en sí lo enciende el proveedor: sin él no hay nada que configurar.
Auth::requerirRolApi('admin');
Csrf::validarApi();

header('Content-Type: application/json');

if (!Configuracion::moduloActivo('propina')) {
    echo json_encode(['error' => 'La propina no está activada para este negocio.']);
    exit;
}

$porcentaje = filter_input(INPUT_POST, 'porcentaje', FILTER_VALIDATE_FLOAT);
if ($porcentaje === false || $porcentaje === null || $porcentaje < 0 || $porcentaje > 30) {
    echo json_encode(['error' => 'El porcentaje debe estar entre 0 y 30.']);
    exit;
}

$ok = Configuracion::guardarParametrosPropina($porcentaje, !empty($_POST['por_defecto']));

echo json_encode(['ok' => $ok]);
