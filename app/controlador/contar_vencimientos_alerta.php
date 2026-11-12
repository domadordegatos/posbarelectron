<?php
/** Para el aviso (número rojo) junto a "Vencimientos" en el menú — visible desde cualquier pantalla, no solo el Panel. */
require_once "../modelo/bootstrap.php";
require_once "../modelo/vencimientos.php";

Auth::requerirModuloApi('vencimientos');
Auth::requerirPermisoApi('productos.ver');

header('Content-Type: application/json');

echo json_encode(['cantidad' => count((new vencimientos())->proximosAVencer())]);
