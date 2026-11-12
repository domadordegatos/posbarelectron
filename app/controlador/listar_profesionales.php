<?php
require_once "../modelo/bootstrap.php";
require_once "../modelo/profesionales.php";

// Lo usa Ventas para el selector "Profesional" (solo activos). Cualquier usuario
// que venda; la lista completa (con inactivos) es solo para el administrador.
$usuario = Auth::requerirLoginApi();

header('Content-Type: application/json');

if (!Configuracion::moduloActivo('profesionales')) {
    echo json_encode([]);
    exit;
}

$todos = !empty($_GET['todos']) && $usuario['rol'] === 'admin';

echo json_encode((new profesionales())->listar($todos));
