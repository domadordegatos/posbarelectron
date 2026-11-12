<?php
require_once "../modelo/bootstrap.php";
require_once "../modelo/conceptos_gasto.php";

// Administrar el catálogo (renombrar, categoría, activar/desactivar) es trabajo
// de catálogo, como editar productos.
Auth::requerirPermisoApi('productos.editar');

header('Content-Type: application/json');

echo json_encode((new conceptos_gasto())->listarTodos());
