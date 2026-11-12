<?php
require_once "../modelo/bootstrap.php";
require_once "../modelo/productos.php";
require_once "../modelo/Promociones.php";

Auth::requerirLoginApi();

header('Content-Type: application/json');

$productoBuscar = trim($_POST['producto_buscar'] ?? '');
$contexto = $_POST['contexto'] ?? '';

if ($productoBuscar === '') {
    echo json_encode([]);
    exit;
}

$obj = new productos();
$resultados = $obj->buscar($productoBuscar, 10);

// Solo el buscador de Ventas necesita el precio con promoción ya aplicado
// (para que el carrito muestre exactamente lo que va a cobrar el servidor
// al confirmar) — el buscador de componentes de combo en Productos usa el
// precio real de catálogo, sin tocar.
if ($contexto === 'venta') {
    foreach ($resultados as &$producto) {
        $resultado = Promociones::precioConPromocion((int) $producto['id_producto'], (float) $producto['precio_unitario']);
        $producto['precio_unitario'] = $resultado['precio'];
        $producto['promocion_nombre'] = $resultado['promocion']['nombre'] ?? null;
    }
    unset($producto);
}

echo json_encode($resultados);
