<?php
/**
 * Crear al vuelo un concepto de compra/gasto o pérdida (ej. "Gas") desde la
 * pantalla de Productos → Compras / Pérdidas. Igual que crear un cliente en
 * Ventas: lo puede hacer quien pueda registrar compras o pérdidas. Si ya
 * existe uno con ese nombre (sin importar mayúsculas) devuelve el existente.
 */
require_once "../modelo/bootstrap.php";
require_once "../modelo/conceptos_gasto.php";

Auth::requerirLoginApi();
Csrf::validarApi();
if (!Auth::tienePermiso('compras.registrar') && !Auth::tienePermiso('perdidas.registrar')) {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'No tienes permisos para esta acción']);
    exit;
}

header('Content-Type: application/json');

try {
    $concepto = (new conceptos_gasto())->crear(trim($_POST['nombre'] ?? ''), (string) ($_POST['categoria'] ?? 'otro'));
    echo json_encode(['ok' => true] + $concepto);
} catch (InvalidArgumentException $e) {
    echo json_encode(['error' => $e->getMessage()]);
} catch (Throwable $e) {
    error_log('Error al crear concepto de gasto: ' . $e->getMessage());
    echo json_encode(['error' => 'No se pudo crear el concepto.']);
}
