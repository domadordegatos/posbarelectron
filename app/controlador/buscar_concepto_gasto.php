<?php
require_once "../modelo/bootstrap.php";
require_once "../modelo/conceptos_gasto.php";

// Buscar entre los conceptos de compras/gastos y pérdidas (gas, luz, bolsas...)
// no exige un permiso propio: basta poder registrar compras o pérdidas.
Auth::requerirLoginApi();
if (!Auth::tienePermiso('compras.registrar') && !Auth::tienePermiso('perdidas.registrar')) {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'No tienes permisos para esta acción']);
    exit;
}

header('Content-Type: application/json');

// Sin término también responde: muestra las primeras opciones del catálogo.
$termino = trim($_POST['termino'] ?? '');

echo json_encode((new conceptos_gasto())->buscar($termino, 12));
