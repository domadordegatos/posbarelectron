<?php
require_once "../modelo/bootstrap.php";
require_once "../modelo/productos.php";
require_once "../modelo/Promociones.php";

Auth::requerirLoginApi();

header('Content-Type: application/json');

$idCategoria = filter_input(INPUT_GET, 'categoria', FILTER_VALIDATE_INT);
if (!$idCategoria) {
    echo json_encode(['error' => 'Categoría inválida.']);
    exit;
}

$resultados = (new productos())->listarProductosCategoriaCuadricula($idCategoria);

// Mismo criterio que listar_productos_destacados.php — la cuadrícula es un
// punto de entrada al carrito, debe mostrar el precio real que el servidor
// va a cobrar al confirmar (con promoción aplicada si hay una vigente).
foreach ($resultados as &$producto) {
    $resultado = Promociones::precioConPromocion((int) $producto['id_producto'], (float) $producto['precio_unitario']);
    $producto['precio_unitario'] = $resultado['precio'];
    $producto['promocion_nombre'] = $resultado['promocion']['nombre'] ?? null;
}
unset($producto);

echo json_encode($resultados);
