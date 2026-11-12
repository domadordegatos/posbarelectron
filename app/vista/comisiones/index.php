<?php
require_once "../../modelo/bootstrap.php";

Auth::requerirModulo('profesionales');
$usuarioActual = Auth::requerirPermiso('movimientos.ver');
$tituloPagina = 'Comisiones';
$paginaActiva = 'comisiones';

require_once __DIR__ . "/../parciales/head.php";
require_once __DIR__ . "/../parciales/sidebar_inicio.php";
?>

<div class="pb-card mb-3">
    <div class="pb-card-body d-flex flex-wrap align-items-end gap-2">
        <div>
            <label for="fecha_inicio" class="form-label mb-1">Desde</label>
            <input type="date" id="fecha_inicio" class="form-control">
        </div>
        <div>
            <label for="fecha_fin" class="form-label mb-1">Hasta</label>
            <input type="date" id="fecha_fin" class="form-control">
        </div>
        <div>
            <label for="filtro_profesional" class="form-label mb-1">Profesional</label>
            <select id="filtro_profesional" class="form-select">
                <option value="">Todos</option>
            </select>
        </div>
        <button class="btn btn-primary" onclick="cargarComisiones()"><i class="bi bi-search"></i> Buscar</button>
    </div>
</div>

<div class="pb-card mb-3">
    <div class="pb-card-body">
        <h6 class="mb-3">Resumen por profesional</h6>
        <div class="table-responsive">
            <table class="table table-sm mb-2" id="tabla_resumen_comisiones">
                <thead>
                    <tr><th>Profesional</th><th class="text-center">Servicios</th><th class="text-end">Vendido</th><th class="text-end">Comisión</th></tr>
                </thead>
                <tbody></tbody>
                <tfoot></tfoot>
            </table>
        </div>
        <div class="form-text">
            Cuenta solo ventas no anuladas y lo que no se devolvió (una devolución parcial baja la comisión en la
            misma proporción). La comisión se calcula sobre lo cobrado por cada servicio, con su descuento, y no incluye la propina.
        </div>
    </div>
</div>

<div class="pb-card">
    <div class="pb-card-body">
        <h6 class="mb-3">Detalle</h6>
        <div class="pb-table-wrap">
            <table class="table table-bordered table-sm pb-table-compact" id="tabla_detalle_comisiones">
                <thead>
                    <tr><th>Fecha</th><th>Factura</th><th>Profesional</th><th>Servicio / producto</th><th class="text-end">Vendido</th><th class="text-end">%</th><th class="text-end">Comisión</th></tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . "/../parciales/pie.php"; ?>
<script>
$(function () {
    const hoy = new Date().toLocaleDateString('sv-SE');
    $('#fecha_inicio, #fecha_fin').val(hoy);
    $.getJSON('/controlador/listar_profesionales.php', { todos: 1 }, function (lista) {
        lista.forEach(function (p) {
            $('<option></option>').val(p.id_profesional).text(p.nombre + (parseInt(p.estado, 10) === 1 ? '' : ' (inactivo)')).appendTo('#filtro_profesional');
        });
    });
    cargarComisiones();
});

function cargarComisiones() {
    const inicio = $('#fecha_inicio').val();
    const fin = $('#fecha_fin').val();
    if (!inicio || !fin) {
        pb.error('Selecciona ambas fechas.');
        return;
    }
    $.getJSON('/controlador/comisiones_por_fecha.php', { fecha_inicio: inicio, fecha_fin: fin, profesional: $('#filtro_profesional').val() }, function (r) {
        const $resumen = $('#tabla_resumen_comisiones tbody').empty();
        if (r.resumen.length === 0) {
            $resumen.append('<tr><td colspan="4" class="text-center text-muted">No hay servicios con profesional en este rango.</td></tr>');
        }
        r.resumen.forEach(function (f) {
            $resumen.append(`<tr><td>${pb.escaparHtml(f.nombre)}</td><td class="text-center">${f.servicios}</td><td class="text-end">${pb.moneda(f.vendido)}</td><td class="text-end fw-semibold">${pb.moneda(f.comision)}</td></tr>`);
        });
        $('#tabla_resumen_comisiones tfoot').html(r.resumen.length === 0 ? '' :
            `<tr class="fw-bold"><td>Total</td><td></td><td class="text-end">${pb.moneda(r.total_vendido)}</td><td class="text-end">${pb.moneda(r.total_comision)}</td></tr>`);

        const $detalle = $('#tabla_detalle_comisiones tbody').empty();
        if (r.detalle.length === 0) {
            $detalle.append('<tr><td colspan="7" class="text-center text-muted">Sin movimientos.</td></tr>');
        }
        r.detalle.forEach(function (d) {
            const devuelto = parseFloat(d.cantidad_devuelta) > 0 ? ` <small class="text-muted">(devueltas ${d.cantidad_devuelta} de ${d.cantidad})</small>` : '';
            $detalle.append(`<tr>
                <td>${d.fecha}</td><td class="text-center">${d.id_factura}</td><td>${pb.escaparHtml(d.profesional)}</td>
                <td>${pb.escaparHtml(d.nombre_producto)}${devuelto}</td>
                <td class="text-end">${pb.moneda(d.vendido)}</td><td class="text-end">${d.comision_porcentaje}%</td><td class="text-end">${pb.moneda(d.comision)}</td>
            </tr>`);
        });
    }).fail(() => pb.error('Error al cargar las comisiones.'));
}
</script>
