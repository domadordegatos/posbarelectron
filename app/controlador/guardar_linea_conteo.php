<?php
require_once "../modelo/bootstrap.php";
require_once "../modelo/ConteoInventario.php";

Auth::requerirPermisoApi('conteo_inventario.registrar');
Csrf::validarApi();

header('Content-Type: application/json');

$idConteo = filter_input(INPUT_POST, 'conteo_id', FILTER_VALIDATE_INT);
$idProducto = filter_input(INPUT_POST, 'producto_id', FILTER_VALIDATE_INT);
// Campo vacío = "todavía no se ha contado esta línea" (vuelve a NULL), distinto de "se contaron 0".
$cantidadTexto = trim($_POST['cantidad'] ?? '');
$cantidad = $cantidadTexto === '' ? null : filter_var($cantidadTexto, FILTER_VALIDATE_INT);

if (!$idConteo || !$idProducto || ($cantidadTexto !== '' && $cantidad === false)) {
    echo json_encode(['error' => 'Datos inválidos.']);
    exit;
}

try {
    $resultado = (new ConteoInventario())->guardarCantidadContada($idConteo, $idProducto, $cantidad);
    echo json_encode($resultado);
} catch (InvalidArgumentException $e) {
    echo json_encode(['error' => $e->getMessage()]);
} catch (Throwable $e) {
    error_log('Error al guardar línea de conteo: ' . $e->getMessage());
    echo json_encode(['error' => 'No se pudo guardar.']);
}
