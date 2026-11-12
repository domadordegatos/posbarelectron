<?php
require_once "../modelo/bootstrap.php";
require_once "../modelo/comandas.php";

Auth::requerirModuloApi('mesas_comandas');
Csrf::validarApi();

header('Content-Type: application/json');

$comandaId = filter_input(INPUT_POST, 'comanda_id', FILTER_VALIDATE_INT);

if ($comandaId === false || $comandaId === null) {
    http_response_code(400);
    echo json_encode(['error' => 'Mesa inválida.']);
    exit;
}

$obj = new comandas();

try {
    $obj->cancelar($comandaId);
    echo json_encode(['ok' => true]);
} catch (ComandaException $e) {
    http_response_code(409);
    echo json_encode(['error' => $e->getMessage()]);
} catch (Throwable $e) {
    error_log('Error al cancelar mesa: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Error al cancelar la mesa.']);
}
