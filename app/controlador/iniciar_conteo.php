<?php
require_once "../modelo/bootstrap.php";
require_once "../modelo/ConteoInventario.php";

$usuario = Auth::requerirPermisoApi('conteo_inventario.registrar');
Csrf::validarApi();

header('Content-Type: application/json');

$categoriaId = filter_input(INPUT_POST, 'categoria_id', FILTER_VALIDATE_INT);
$nota = trim($_POST['nota'] ?? '');

try {
    $idConteo = (new ConteoInventario())->iniciar((int) $usuario['id'], $categoriaId ?: null, $nota);
    echo json_encode(['ok' => true, 'id_conteo' => $idConteo]);
} catch (InvalidArgumentException $e) {
    echo json_encode(['error' => $e->getMessage()]);
} catch (Throwable $e) {
    error_log('Error al iniciar conteo: ' . $e->getMessage());
    echo json_encode(['error' => 'No se pudo iniciar el conteo.']);
}
