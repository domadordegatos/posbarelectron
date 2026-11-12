<?php
require_once "../modelo/bootstrap.php";
require_once "../modelo/SuperAdmin.php";

header('Content-Type: application/json');

Csrf::validarApi();

$ip = $_SERVER['REMOTE_ADDR'] ?? 'desconocida';
$usuario = trim($_POST['usuario'] ?? '');
$codigo = trim($_POST['codigo'] ?? '');

if (SuperAdmin::estaBloqueado($ip)) {
    http_response_code(429);
    echo json_encode(['error' => 'Demasiados intentos fallidos. Espera unos minutos antes de volver a intentar.']);
    exit;
}

if ($usuario === '' || $codigo === '') {
    echo json_encode(['error' => 'Usuario y código son obligatorios.']);
    exit;
}

$valido = SuperAdmin::estaConfigurado()
    && hash_equals(SuperAdmin::usuarioRoot(), $usuario)
    && SuperAdmin::validarCodigo($codigo);

SuperAdmin::registrarIntento($ip, $usuario, $valido);

if (!$valido) {
    http_response_code(401);
    echo json_encode(['error' => 'Usuario o código incorrecto.']);
    exit;
}

Auth::iniciarSesion();
session_regenerate_id(true);
$_SESSION['superadmin'] = true;
$_SESSION['superadmin_desde'] = time();

echo json_encode(['ok' => true]);
