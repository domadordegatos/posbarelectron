<?php
require_once "../modelo/bootstrap.php";
require_once "../modelo/Auditoria.php";

Auth::requerirRolApi('admin');

header('Content-Type: application/json');

$pagina = filter_input(INPUT_POST, 'pagina', FILTER_VALIDATE_INT) ?: 1;
$fechaInicio = trim($_POST['fecha_inicio'] ?? '');
$fechaFin = trim($_POST['fecha_fin'] ?? '');

echo json_encode(Auditoria::listar($pagina, 30, $fechaInicio, $fechaFin));
