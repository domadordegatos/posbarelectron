<?php
require_once "../modelo/bootstrap.php";
require_once "../modelo/productos.php";
require_once "../modelo/vencimientos.php";

Auth::requerirPermisoApi('productos.ver');

header('Content-Type: application/json');

$pagina = filter_input(INPUT_POST, 'pagina', FILTER_VALIDATE_INT) ?: 1;
$termino = trim($_POST['termino'] ?? '');
$porPagina = filter_input(INPUT_POST, 'por_pagina', FILTER_VALIDATE_INT) ?: 10;
$porPagina = max(1, min(500, $porPagina));
$orden = ($_POST['orden'] ?? 'asc') === 'desc' ? 'desc' : 'asc';

$obj = new productos();
$resultado = $obj->listarPaginado($pagina, $porPagina, $termino, $orden);

$filas = $resultado['filas'];
if (Configuracion::moduloActivo('vencimientos') && $filas) {
    $estados = (new vencimientos())->estadosPorProductos(array_column($filas, 'id_producto'));
    foreach ($filas as &$fila) {
        $fila['estado_vencimiento'] = $estados[(int) $fila['id_producto']] ?? null;
    }
    unset($fila);
}

echo json_encode([
    'filas' => $filas,
    'total_paginas' => (int) ceil($resultado['total'] / $porPagina),
]);
