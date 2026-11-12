<?php
require_once "../modelo/bootstrap.php";

Auth::requerirRolApi('admin');
Csrf::validarApi();

header('Content-Type: application/json');

$modo = trim($_POST['modo'] ?? '');
$ok = Configuracion::actualizarCuadriculaModo($modo);

echo json_encode($ok ? ['ok' => true] : ['error' => 'Modo inválido.']);
