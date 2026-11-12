<?php
require_once "../modelo/bootstrap.php";
require_once "../modelo/comandas.php";

Auth::requerirModuloApi('mesas_comandas');
Csrf::validarApi();

header('Content-Type: application/json');

$comandaId = filter_input(INPUT_POST, 'comanda_id', FILTER_VALIDATE_INT);
$itemsRaw = $_POST['items'] ?? '[]';
$items = is_string($itemsRaw) ? json_decode($itemsRaw, true) : $itemsRaw;
$clienteId = filter_input(INPUT_POST, 'cliente_id', FILTER_VALIDATE_INT);
$clienteId = ($clienteId === false || $clienteId === null) ? null : $clienteId;
$imprimir = !empty($_POST['imprimir']);

if ($comandaId === false || $comandaId === null || !is_array($items)) {
    http_response_code(400);
    echo json_encode(['error' => 'Datos inválidos.']);
    exit;
}

$obj = new comandas();

try {
    $obj->guardarItems($comandaId, $items, $clienteId);
    // Solo si el cajero pidió imprimir ("Guardar e imprimir comanda"): se
    // calcula ANTES de marcar como impreso — compara contra el snapshot de
    // la última impresión para saber qué es de verdad nuevo. Con "Guardar
    // sin imprimir" NO se marca nada, para que esos productos sigan
    // pendientes de comanda (antes se marcaban como impresos igual y luego
    // "Guardar e imprimir" no encontraba nada nuevo).
    $nuevosParaImprimir = [];
    if ($imprimir) {
        $nuevosParaImprimir = $obj->calcularNuevosParaImprimir($comandaId);
        $obj->marcarComoImpreso($comandaId);
    }
    echo json_encode(['ok' => true, 'nuevos_para_imprimir' => $nuevosParaImprimir]);
} catch (ComandaException $e) {
    http_response_code(409);
    echo json_encode(['error' => $e->getMessage()]);
} catch (Throwable $e) {
    error_log('Error al guardar ítems de mesa: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Error al guardar la mesa.']);
}
