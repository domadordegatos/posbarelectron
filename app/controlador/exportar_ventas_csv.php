<?php
require_once "../modelo/bootstrap.php";
require_once "../modelo/movimientos.php";

Auth::requerirPermiso('movimientos.ver');

$fechaInicio = trim($_GET['fecha_inicio'] ?? '');
$fechaFin = trim($_GET['fecha_fin'] ?? '');

if ($fechaInicio === '' || $fechaFin === '') {
    http_response_code(400);
    exit('Indica el rango de fechas.');
}

$filas = (new movimientos())->ventasPorFecha($fechaInicio, $fechaFin);

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="ventas_' . $fechaInicio . '_a_' . $fechaFin . '.csv"');

$salida = fopen('php://output', 'w');
// BOM al inicio para que Excel en Windows detecte UTF-8 solo (si no, daña tildes/ñ).
fwrite($salida, "\xEF\xBB\xBF");
fputcsv($salida, ['Factura', 'Fecha', 'Hora', 'Vendedor', 'Total', 'Ajuste', 'Propina', 'Metodo de pago', 'Anulada', 'Utilidad', 'Devuelto']);
foreach ($filas as $f) {
    fputcsv($salida, [
        $f['id_factura'],
        $f['fecha'],
        $f['hora'],
        $f['vendedor'] ?? '',
        $f['total_venta'],
        $f['ajuste'],
        $f['propina'],
        $f['metodo_pago'],
        $f['anulada'] ? 'Si' : 'No',
        $f['utilidad'],
        $f['total_devuelto'],
    ]);
}
fclose($salida);
