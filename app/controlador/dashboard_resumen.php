<?php
require_once "../modelo/bootstrap.php";
require_once "../modelo/movimientos.php";
require_once "../modelo/productos.php";
require_once "../modelo/vencimientos.php";

Auth::requerirLoginApi();

header('Content-Type: application/json');

$movimientosObj = new movimientos();
$productosObj = new productos();

$resumen = $movimientosObj->resumenHoy();
$resumen['stock_bajo'] = $productosObj->productosStockBajo();
$resumen['vencimientos'] = Configuracion::moduloActivo('vencimientos') ? (new vencimientos())->proximosAVencer() : [];

echo json_encode($resumen);
