<?php
require_once "../modelo/bootstrap.php";
require_once "../modelo/proveedores.php";

Auth::requerirModuloApi('proveedores');

header('Content-Type: application/json');

$termino = trim($_POST['termino'] ?? '');

if ($termino === '') {
    echo json_encode([]);
    exit;
}

echo json_encode((new proveedores())->buscar($termino, 10));
