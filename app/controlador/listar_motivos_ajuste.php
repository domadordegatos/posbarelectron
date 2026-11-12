<?php
require_once "../modelo/bootstrap.php";
require_once "../modelo/motivos_ajuste.php";

// Administrar el catálogo (renombrar, activar/desactivar) es trabajo de catálogo, como editar productos.
Auth::requerirPermisoApi('productos.editar');

header('Content-Type: application/json');

echo json_encode((new motivos_ajuste())->listarTodos());
