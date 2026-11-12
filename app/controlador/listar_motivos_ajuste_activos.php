<?php
/** Para poblar el selector "Motivo" del formulario de salida/entrada sin pérdida ni ganancia. */
require_once "../modelo/bootstrap.php";
require_once "../modelo/motivos_ajuste.php";

Auth::requerirPermisoApi('ajustes_inventario.registrar');

header('Content-Type: application/json');

echo json_encode((new motivos_ajuste())->listarActivos());
