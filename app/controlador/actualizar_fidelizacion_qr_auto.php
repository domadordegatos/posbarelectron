<?php
require_once "../modelo/bootstrap.php";

Auth::requerirRolApi('admin');
Csrf::validarApi();

header('Content-Type: application/json');

$habilitado = !empty($_POST['habilitado']);
$ok = Configuracion::actualizarFidelizacionQrAuto($habilitado);

echo json_encode(['ok' => $ok]);
