<?php
require_once "../modelo/bootstrap.php";
require_once "../modelo/Modificadores.php";

Auth::requerirPermisoApi('productos.editar');
Csrf::validarApi();

header('Content-Type: application/json');

$idOrigen = filter_input(INPUT_POST, 'producto_origen', FILTER_VALIDATE_INT);
$idDestino = filter_input(INPUT_POST, 'producto_destino', FILTER_VALIDATE_INT);

if (!$idOrigen || !$idDestino) {
    echo json_encode(['error' => 'Datos inválidos.']);
    exit;
}

try {
    $resumen = (new Modificadores())->copiarDesdeProducto($idOrigen, $idDestino);
    echo json_encode(['ok' => true] + $resumen);
} catch (InvalidArgumentException $e) {
    echo json_encode(['error' => $e->getMessage()]);
} catch (Throwable $e) {
    error_log('Error al copiar modificadores: ' . $e->getMessage());
    echo json_encode(['error' => 'No se pudieron copiar los modificadores.']);
}
