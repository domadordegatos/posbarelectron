<?php
/**
 * Compra/gasto o pérdida de algo que NO es un producto del inventario (gas,
 * luz, bolsas plásticas...): se elige un concepto del catálogo
 * (conceptos_gasto) en vez de un producto. tipo = 'compra' | 'perdida'; cada
 * uno exige su permiso, igual que registrar_compra.php / registrar_perdida.php.
 */
require_once "../modelo/bootstrap.php";
require_once "../modelo/inventario.php";
require_once "../modelo/turnos.php";

$tipo = ($_POST['tipo'] ?? '') === 'perdida' ? 'perdida' : 'compra';
$usuario = Auth::requerirPermisoApi($tipo === 'perdida' ? 'perdidas.registrar' : 'compras.registrar');
Csrf::validarApi();

header('Content-Type: application/json');

$idConcepto = filter_input(INPUT_POST, 'concepto_id', FILTER_VALIDATE_INT);
$cantidad = filter_input(INPUT_POST, 'cantidad', FILTER_VALIDATE_INT);
$valor = filter_input(INPUT_POST, 'valor', FILTER_VALIDATE_FLOAT);
$proveedorId = filter_input(INPUT_POST, 'proveedor_id', FILTER_VALIDATE_INT);
$proveedorId = ($proveedorId === false || $proveedorId === null) ? null : $proveedorId;
$nota = trim($_POST['nota'] ?? '');
$fecha = trim($_POST['fecha'] ?? '');
$fecha = $fecha !== '' ? $fecha : null;

if ($idConcepto === false || $idConcepto === null || $cantidad === false || $cantidad === null || $cantidad <= 0
    || $valor === false || $valor === null
) {
    echo json_encode(['error' => 'Datos inválidos.']);
    exit;
}

$obj = new inventario();

try {
    if ($tipo === 'perdida') {
        $idFactura = $obj->registrarPerdidaConcepto((int) $usuario['id'], $idConcepto, $cantidad, $valor, $nota, $fecha);
    } else {
        // Igual que un Egreso de Ventas: si hay un turno abierto Y el cajero no lo marcó como
        // "gasto general", queda ligado a él (se resta del efectivo de la caja de hoy). Un gasto
        // de otro día, o uno que se quiera dejar fuera del cuadre de hoy, se registra sin turno_id.
        $afectaTurno = ($_POST['afecta_turno'] ?? '1') !== '0';
        $turnoId = null;
        if ($afectaTurno) {
            $turnoAbierto = (new turnos())->turnoAbierto();
            $turnoId = $turnoAbierto['id_turno'] ?? null;
        }
        $idFactura = $obj->registrarGastoConcepto(
            (int) $usuario['id'], $idConcepto, $cantidad, $valor,
            (string) ($_POST['metodo_pago'] ?? 'efectivo'), $proveedorId, $nota,
            $turnoId, $fecha
        );
    }
    echo json_encode(['ok' => true, 'id_factura' => $idFactura]);
} catch (InvalidArgumentException $e) {
    echo json_encode(['error' => $e->getMessage()]);
} catch (Throwable $e) {
    error_log('Error al registrar gasto/pérdida por concepto: ' . $e->getMessage());
    echo json_encode(['error' => 'No se pudo registrar.']);
}
