<?php
require_once "../modelo/bootstrap.php";
require_once "../modelo/productos.php";
require_once "../modelo/Promociones.php";

Auth::requerirLoginApi();

header('Content-Type: application/json');

$resultados = (new productos())->listarDestacados();

// Mismo criterio que buscar_producto.php con contexto=venta — la cuadrícula
// también es un punto de entrada al carrito, debe mostrar el precio real
// que el servidor va a cobrar al confirmar.
foreach ($resultados as &$producto) {
    $resultado = Promociones::precioConPromocion((int) $producto['id_producto'], (float) $producto['precio_unitario']);
    $producto['precio_unitario'] = $resultado['precio'];
    $producto['promocion_nombre'] = $resultado['promocion']['nombre'] ?? null;
}
unset($producto);

echo json_encode($resultados);
