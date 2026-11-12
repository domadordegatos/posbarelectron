<?php
require_once "../modelo/bootstrap.php";
require_once "../modelo/conceptos_gasto.php";

Auth::requerirPermisoApi('productos.editar');
Csrf::validarApi();

header('Content-Type: application/json');

$idConcepto = filter_input(INPUT_POST, 'id_concepto', FILTER_VALIDATE_INT);
if ($idConcepto === false || $idConcepto === null) {
    echo json_encode(['error' => 'Concepto inválido.']);
    exit;
}

try {
    $concepto = (new conceptos_gasto())->editar(
        $idConcepto,
        trim($_POST['nombre'] ?? ''),
        (string) ($_POST['categoria'] ?? ''),
        !empty($_POST['estado']) ? 1 : 0
    );
    echo json_encode(['ok' => true] + $concepto);
} catch (InvalidArgumentException $e) {
    echo json_encode(['error' => $e->getMessage()]);
} catch (Throwable $e) {
    error_log('Error al guardar concepto de gasto: ' . $e->getMessage());
    echo json_encode(['error' => 'No se pudo guardar el concepto.']);
}
