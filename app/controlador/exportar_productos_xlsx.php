<?php
require_once "../modelo/bootstrap.php";
require_once "../modelo/ExportacionProductos.php";

Auth::requerirPermiso('productos.ver');

$libro = (new ExportacionProductos())->generarXlsx();

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="productos_' . date('Y-m-d') . '.xlsx"');
header('Cache-Control: max-age=0');

$escritor = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($libro);
$escritor->save('php://output');
