<?php
require_once "../modelo/bootstrap.php";
require_once "../modelo/facturas.php";
require_once "../modelo/turnos.php";
require_once "../modelo/comandas.php";
require_once "../modelo/Sincronizacion.php";

$usuario = Auth::requerirLoginApi();
Csrf::validarApi();

header('Content-Type: application/json');

$productosRaw = $_POST['productos'] ?? null;
$descripcion = trim($_POST['descripcion'] ?? '');
$ajuste = filter_input(INPUT_POST, 'ajuste', FILTER_VALIDATE_FLOAT);
$ajuste = ($ajuste === false || $ajuste === null) ? 0.0 : $ajuste;
$propina = filter_input(INPUT_POST, 'propina', FILTER_VALIDATE_FLOAT);
$propina = ($propina === false || $propina === null || $propina < 0) ? 0.0 : $propina;
// Sin el módulo "propina" no se acepta ninguna, aunque alguien la mande a mano.
if (!Configuracion::moduloActivo('propina')) {
    $propina = 0.0;
}
$pagosRaw = $_POST['pagos'] ?? '[]';
$clienteId = filter_input(INPUT_POST, 'cliente_id', FILTER_VALIDATE_INT);
$clienteId = ($clienteId === false || $clienteId === null) ? null : $clienteId;
$comandaId = filter_input(INPUT_POST, 'comanda_id', FILTER_VALIDATE_INT);
$comandaId = ($comandaId === false || $comandaId === null) ? null : $comandaId;

$turnoAbierto = (new turnos())->turnoAbierto();
$turnoId = $turnoAbierto['id_turno'] ?? null;

if ($productosRaw === null) {
    http_response_code(400);
    echo json_encode(['error' => 'Faltan datos necesarios para procesar la venta.']);
    exit;
}

$productos = is_string($productosRaw) ? json_decode($productosRaw, true) : $productosRaw;
$pagos = is_string($pagosRaw) ? json_decode($pagosRaw, true) : $pagosRaw;
$pagos = is_array($pagos) ? $pagos : [];

if (!is_array($productos) || empty($productos)) {
    http_response_code(400);
    echo json_encode(['error' => 'No se recibieron productos válidos.']);
    exit;
}

foreach ($productos as $p) {
    if (!isset($p['id_producto'], $p['cantidad'])
        || !is_numeric($p['id_producto']) || !is_numeric($p['cantidad'])
        || (int) $p['cantidad'] <= 0
        || !in_array($p['presentacion'] ?? 'unidad', ['unidad', 'paquete'], true)
    ) {
        http_response_code(400);
        echo json_encode(['error' => 'Uno de los productos del carrito tiene datos inválidos.']);
        exit;
    }
}

$obj = new facturas();

try {
    $idFactura = $obj->registrarVenta((int) $usuario['id'], $productos, $descripcion, $ajuste, $pagos, $turnoId, $clienteId, $propina);

    // La venta ya quedó registrada y confirmada (factura + inventario, todo
    // ya comprometido en la base de datos) — cerrar la mesa es un paso de
    // limpieza APARTE. Si esto falla (ej. la mesa ya se había cerrado por un
    // doble clic en "Cobrar mesa"), NO se le puede reportar al cajero como
    // si la venta hubiera fallado: ya se cobró y ya se descontó inventario,
    // decirle "error al procesar la venta" lo confundiría a pensar que no
    // pasó nada y podría intentarlo de nuevo, duplicando el cobro.
    if ($comandaId !== null) {
        try {
            (new comandas())->cerrar($comandaId, $idFactura);
        } catch (Throwable $e) {
            error_log("No se pudo cerrar la comanda $comandaId tras la factura $idFactura: " . $e->getMessage());
        }
    }

    // "Factura #" que se muestra: el id crudo en una instalación de un solo equipo, o
    // "C2-000042" en cuanto hay más de una caja (ver Sincronizacion::numeroFactura()).
    $origen = Database::conexion()->prepare('SELECT origen_caja_codigo, origen_id_factura FROM facturas WHERE id_factura = :id');
    $origen->execute(['id' => $idFactura]);
    $filaOrigen = $origen->fetch();
    $numeroFactura = Sincronizacion::numeroFactura(
        $filaOrigen['origen_caja_codigo'] ?? null,
        $filaOrigen['origen_id_factura'] !== null ? (int) $filaOrigen['origen_id_factura'] : null,
        $idFactura
    );

    echo json_encode(['ok' => true, 'id_factura' => $idFactura, 'numero_factura' => $numeroFactura]);
} catch (SinExistenciaException $e) {
    http_response_code(409);
    echo json_encode(['error' => $e->getMessage()]);
} catch (CupoCreditoException $e) {
    http_response_code(409);
    echo json_encode(['error' => $e->getMessage()]);
} catch (InvalidArgumentException $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
} catch (Throwable $e) {
    error_log('Error al registrar venta: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Error al procesar la venta.']);
}
