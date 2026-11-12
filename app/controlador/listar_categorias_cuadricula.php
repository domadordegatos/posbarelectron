<?php
require_once "../modelo/bootstrap.php";
require_once "../modelo/productos.php";

Auth::requerirLoginApi();

header('Content-Type: application/json');

echo json_encode((new productos())->listarCategoriasCuadricula());
