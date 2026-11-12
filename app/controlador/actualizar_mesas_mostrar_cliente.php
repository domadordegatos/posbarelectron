<?php
require_once "../modelo/bootstrap.php";

Auth::requerirRolApi('admin');
Csrf::validarApi();

header('Content-Type: application/json');

$mostrar = !empty($_POST['mostrar']);
$ok = Configuracion::actualizarMesasMostrarCliente($mostrar);

echo json_encode(['ok' => $ok]);
