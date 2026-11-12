<?php
require_once "../modelo/bootstrap.php";
require_once "../modelo/facturas.php";
require_once "../modelo/turnos.php";

$usuario = Auth::requerirLoginApi();
Csrf::validarApi();

header('Content-Type: application/json');

$fecha = trim($_POST['fecha'] ?? '');
$hora = trim($_POST['hora'] ?? '');
$tipoTransaccion = filter_input(INPUT_POST, 'tipo_transaccion', FILTER_VALIDATE_INT);
$descripcion = trim($_POST['descripcion'] ?? '');
$valor = filter_input(INPUT_POST, 'valor', FILTER_VALIDATE_FLOAT);
$metodoPago = $_POST['metodo_pago'] ?? 'efectivo';

if ($fecha === '' || $hora === '' || $tipoTransaccion === false || $tipoTransaccion === null
    || $descripcion === '' || $valor === false || $valor === null || $valor <= 0
) {
    echo json_encode(['error' => 'Todos los campos son obligatorios.']);
    exit;
}

// Reporte real: un egreso que no corresponde al turno de hoy (otro día, o uno que el cajero marca
// como "gasto general" aunque sea de hoy) no debe restarse del efectivo de la caja abierta — el
// checkbox por defecto mantiene el comportamiento de siempre (sí lo descuenta).
$afectaTurno = ($_POST['afecta_turno'] ?? '1') !== '0';
$turnoId = null;
if ($afectaTurno) {
    $turnoAbierto = (new turnos())->turnoAbierto();
    $turnoId = $turnoAbierto['id_turno'] ?? null;
}

$obj = new facturas();

try {
    $obj->registrarInsumo((int) $usuario['id'], $tipoTransaccion, $descripcion, $valor, $fecha, $hora, $metodoPago, $turnoId);
    echo json_encode(1);
} catch (Throwable $e) {
    error_log('Error al registrar egreso: ' . $e->getMessage());
    echo json_encode(0);
}
