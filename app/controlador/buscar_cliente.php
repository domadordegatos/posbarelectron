<?php
require_once "../modelo/bootstrap.php";
require_once "../modelo/clientes.php";

Auth::requerirModuloApi('clientes_credito');

header('Content-Type: application/json');

$termino = trim($_POST['termino'] ?? '');

if ($termino === '') {
    echo json_encode([]);
    exit;
}

$obj = new clientes();
$resultados = $obj->buscar($termino, 10);

foreach ($resultados as &$c) {
    $c['saldo'] = $obj->saldo((int) $c['id_cliente']);
}

echo json_encode($resultados);
