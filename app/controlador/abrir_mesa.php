<?php
/**
 * Abre-o-reanuda una mesa del catálogo fijo — si ya tiene una comanda
 * abierta, la carga tal cual (con sus items); si está libre, crea una
 * nueva vacía. Un solo endpoint para los dos casos, así el frontend no
 * necesita distinguir "mesa libre" de "mesa ocupada" al hacer clic.
 */
require_once "../modelo/bootstrap.php";
require_once "../modelo/comandas.php";

$usuario = Auth::requerirModuloApi('mesas_comandas');
Csrf::validarApi();

header('Content-Type: application/json');

$mesaId = filter_input(INPUT_POST, 'mesa_id', FILTER_VALIDATE_INT);

if ($mesaId === false || $mesaId === null) {
    http_response_code(400);
    echo json_encode(['error' => 'Mesa inválida.']);
    exit;
}

$obj = new comandas();

try {
    $comanda = $obj->abrirOReanudar((int) $usuario['id'], $mesaId);
    echo json_encode(['ok' => true] + $comanda);
} catch (InvalidArgumentException $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
} catch (Throwable $e) {
    error_log('Error al abrir mesa: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Error al abrir la mesa.']);
}
