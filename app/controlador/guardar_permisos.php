<?php
require_once "../modelo/bootstrap.php";
require_once "../modelo/permisos.php";
require_once "../modelo/Auditoria.php";

$usuarioActual = Auth::requerirRolApi('admin');
Csrf::validarApi();

header('Content-Type: application/json');

$clavesRaw = $_POST['claves'] ?? '[]';
$claves = json_decode($clavesRaw, true);

if (!is_array($claves)) {
    echo json_encode(['error' => 'Datos inválidos.']);
    exit;
}

try {
    (new permisos())->guardarParaRol('cajero', $claves);
    Auditoria::registrar((int) $usuarioActual['id'], 'permisos.guardar', 'rol_cajero', null, ['claves' => $claves]);
    echo json_encode(['ok' => true]);
} catch (Throwable $e) {
    error_log('Error al guardar permisos: ' . $e->getMessage());
    echo json_encode(['error' => 'Error al guardar los permisos.']);
}
