<?php
require_once "../modelo/bootstrap.php";
require_once "../modelo/motivos_ajuste.php";
require_once "../modelo/ImportacionAjustesInventario.php";

Auth::requerirPermiso('ajustes_inventario.registrar');

$motivos = array_column((new motivos_ajuste())->listarActivos(), 'nombre');
$libro = (new ImportacionAjustesInventario())->generarPlantillaXlsx($motivos);

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="plantilla_ajustes_inventario.xlsx"');
header('Cache-Control: max-age=0');

$escritor = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($libro);
$escritor->save('php://output');
