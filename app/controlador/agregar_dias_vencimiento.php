<?php
/** Agrega OTRO aviso a un vencimiento que ya existe (mismo lote/fecha) — no vuelve a pedirlos. */
require_once "../modelo/bootstrap.php";
require_once "../modelo/vencimientos.php";

Auth::requerirModuloApi('vencimientos');
Auth::requerirPermisoApi('productos.editar');
Csrf::validarApi();

header('Content-Type: application/json');

$idVencimiento = filter_input(INPUT_POST, 'vencimiento_id', FILTER_VALIDATE_INT);
$diasAntes = filter_input(INPUT_POST, 'dias_antes', FILTER_VALIDATE_INT);

if (!$idVencimiento || $diasAntes === false || $diasAntes === null) {
    echo json_encode(['error' => 'Datos inválidos.']);
    exit;
}

try {
    $idAlerta = (new vencimientos())->agregarAlertaAVencimiento($idVencimiento, $diasAntes);
    echo json_encode(['ok' => true, 'id_alerta' => $idAlerta]);
} catch (VencimientoException $e) {
    echo json_encode(['error' => $e->getMessage()]);
} catch (Throwable $e) {
    error_log('Error al agregar aviso de vencimiento: ' . $e->getMessage());
    echo json_encode(['error' => 'No se pudo agregar el aviso.']);
}
