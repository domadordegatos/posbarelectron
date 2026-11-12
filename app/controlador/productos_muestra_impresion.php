<?php
require_once "../modelo/bootstrap.php";
require_once "../modelo/Database.php";

// Solo para la hoja de prueba de impresión (Configuración): unos cuantos
// productos reales y al azar del catálogo, para que la factura de prueba se
// vea como se verá la de verdad. Solo lectura.
Auth::requerirRolApi('admin');

header('Content-Type: application/json');

$cantidad = max(1, min(8, (int) ($_GET['cantidad'] ?? 4)));

$stmt = Database::conexion()->prepare(
    'SELECT nombre_producto, precio_unitario
     FROM productos
     WHERE estado = 1 AND precio_unitario > 0 AND se_vende_por_peso = 0
     ORDER BY RANDOM()
     LIMIT :limite'
);
$stmt->bindValue(':limite', $cantidad, PDO::PARAM_INT);
$stmt->execute();

echo json_encode(['ok' => true, 'productos' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
