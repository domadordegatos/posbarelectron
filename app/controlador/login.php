<?php
require_once "../modelo/bootstrap.php";

header('Content-Type: application/json');

Csrf::validarApi();

$usuario = trim($_POST['usuario'] ?? '');
$password = (string) ($_POST['password'] ?? '');

if ($usuario === '' || $password === '') {
    echo json_encode(['error' => 'Usuario y contraseña son obligatorios.']);
    exit;
}

$sesion = Auth::intentarLogin($usuario, $password);

if (!$sesion) {
    echo json_encode(['error' => 'Usuario o contraseña incorrectos.']);
    exit;
}

$destino = $sesion['rol'] === 'admin' ? '/vista/home/index.php' : '/vista/ventas/index.php';
echo json_encode(['ok' => true, 'redirect' => $destino]);
