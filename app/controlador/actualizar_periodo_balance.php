<?php
require_once "../modelo/bootstrap.php";
require_once "../modelo/Auditoria.php";

$usuarioActual = Auth::requerirRolApi('admin');
Csrf::validarApi();

header('Content-Type: application/json');

$tipo = trim($_POST['tipo'] ?? '');
$dia = filter_input(INPUT_POST, 'dia', FILTER_VALIDATE_INT);

if ($dia === false || $dia === null) {
    echo json_encode(['error' => 'Indica el día.']);
    exit;
}

try {
    Configuracion::actualizarPeriodoBalance($tipo, $dia);
    Auditoria::registrar((int) $usuarioActual['id'], 'configuracion.periodo_balance', 'configuracion_negocio', 1, [
        'tipo' => $tipo,
        'dia' => $dia,
    ]);
    echo json_encode(['ok' => true]);
} catch (InvalidArgumentException $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
