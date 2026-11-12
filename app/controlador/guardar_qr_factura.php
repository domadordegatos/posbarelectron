<?php
require_once "../modelo/bootstrap.php";

// "Best effort": se llama en paralelo justo después de imprimir el ticket
// de una venta (ver mostrarTicket() en vista/ventas/index.php) — si esto
// falla o nunca llega a completarse, la venta ya quedó registrada igual;
// solo significa que ese QR no se podrá reimprimir después desde Facturas.
Auth::requerirLoginApi();
Csrf::validarApi();

header('Content-Type: application/json');

$idFactura = filter_input(INPUT_POST, 'id_factura', FILTER_VALIDATE_INT);
$qrUrl = trim($_POST['qr_url'] ?? '');

if ($idFactura === false || $idFactura === null || $qrUrl === '' || strlen($qrUrl) > 500
    || !preg_match('#^https?://#', $qrUrl)
) {
    echo json_encode(['error' => 'Datos inválidos.']);
    exit;
}

$pdo = Database::conexion();
$stmt = $pdo->prepare('SELECT id_factura FROM facturas WHERE id_factura = :id AND tipo_transaccion = 1');
$stmt->execute(['id' => $idFactura]);
if (!$stmt->fetch()) {
    echo json_encode(['error' => 'Esa factura no existe.']);
    exit;
}

$pdo->prepare('UPDATE facturas SET fidelizacion_qr_url = :url WHERE id_factura = :id')
    ->execute(['url' => $qrUrl, 'id' => $idFactura]);

echo json_encode(['ok' => true]);
