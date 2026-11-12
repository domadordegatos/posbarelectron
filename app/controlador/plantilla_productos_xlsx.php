<?php
require_once "../modelo/bootstrap.php";
require_once "../modelo/productos.php";
require_once "../modelo/ImportacionProductos.php";

Auth::requerirPermiso('productos.editar');

$categoriasExistentes = array_column((new productos())->listarCategorias(), 'descripcion');
$libro = (new ImportacionProductos())->generarPlantillaXlsx($categoriasExistentes);

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="plantilla_productos.xlsx"');
header('Cache-Control: max-age=0');

$escritor = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($libro);
$escritor->save('php://output');
