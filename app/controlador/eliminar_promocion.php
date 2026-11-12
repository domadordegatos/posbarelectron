<?php
require_once "../modelo/bootstrap.php";
require_once "../modelo/Promociones.php";

Auth::requerirModuloApi('promociones');
Auth::requerirRolApi('admin');
Csrf::validarApi();

header('Content-Type: application/json');

$idPromocion = filter_input(INPUT_POST, 'id_promocion', FILTER_VALIDATE_INT);

if ($idPromocion === false || $idPromocion === null) {
    echo json_encode(['error' => 'Promoción inválida.']);
    exit;
}

echo json_encode(['ok' => Promociones::eliminar($idPromocion)]);
