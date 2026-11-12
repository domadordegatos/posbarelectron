<?php
require_once "../../modelo/bootstrap.php";

$usuarioActual = Auth::requerirPermiso('movimientos.ver');
$tituloPagina = 'Reportes';
$paginaActiva = 'reportes';

require_once __DIR__ . "/../parciales/head.php";
require_once __DIR__ . "/../parciales/sidebar_inicio.php";
?>

<div class="row g-3">
    <div class="col-lg-6">
        <div class="pb-card mb-3">
            <div class="pb-card-body">
                <h6 class="mb-3"><i class="bi bi-file-earmark-spreadsheet"></i> Exportar ventas a Excel/CSV</h6>
                <div class="row g-2 align-items-end">
                    <div class="col-6">
                        <label for="csv_fecha_inicio" class="form-label mb-1">Desde</label>
                        <input type="date" id="csv_fecha_inicio" class="form-control">
                    </div>
                    <div class="col-6">
                        <label for="csv_fecha_fin" class="form-label mb-1">Hasta</label>
                        <input type="date" id="csv_fecha_fin" class="form-control">
                    </div>
                </div>
                <button type="button" class="btn btn-primary mt-2" onclick="exportarVentasCsv()">
                    <i class="bi bi-download"></i> Descargar CSV
                </button>
            </div>
        </div>

        <div class="pb-card">
            <div class="pb-card-body">
                <h6 class="mb-3"><i class="bi bi-stars"></i> Ranking de modificadores/toppings</h6>
                <div class="row g-2 align-items-end mb-2">
                    <div class="col-6">
                        <label for="ranking_fecha_inicio" class="form-label mb-1">Desde</label>
                        <input type="date" id="ranking_fecha_inicio" class="form-control">
                    </div>
                    <div class="col-6">
                        <label for="ranking_fecha_fin" class="form-label mb-1">Hasta</label>
                        <input type="date" id="ranking_fecha_fin" class="form-control">
                    </div>
                </div>
                <button type="button" class="btn btn-primary mb-3" onclick="cargarRankingModificadores()">
                    <i class="bi bi-search"></i> Consultar
                </button>
                <div class="pb-table-wrap">
                    <table class="table table-sm table-bordered">
                        <thead>
                            <tr>
                                <th>Opción</th>
                                <th class="text-center">Veces elegida</th>
                                <th class="text-center">Veces cobrada</th>
                                <th class="text-end">Ingreso generado</th>
                            </tr>
                        </thead>
                        <tbody id="tablaRankingModificadores">
                            <tr><td colspan="4" class="text-center text-muted">Elige un rango y consulta.</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="pb-card">
            <div class="pb-card-body">
                <h6 class="mb-3"><i class="bi bi-bar-chart-line"></i> Comparar dos períodos</h6>
                <div class="row g-2 mb-2">
                    <div class="col-6">
                        <label class="form-label mb-1 small text-muted">Período A — desde</label>
                        <input type="date" id="comp_inicio_a" class="form-control form-control-sm">
                    </div>
                    <div class="col-6">
                        <label class="form-label mb-1 small text-muted">Período A — hasta</label>
                        <input type="date" id="comp_fin_a" class="form-control form-control-sm">
                    </div>
                </div>
                <div class="row g-2 mb-2">
                    <div class="col-6">
                        <label class="form-label mb-1 small text-muted">Período B — desde</label>
                        <input type="date" id="comp_inicio_b" class="form-control form-control-sm">
                    </div>
                    <div class="col-6">
                        <label class="form-label mb-1 small text-muted">Período B — hasta</label>
                        <input type="date" id="comp_fin_b" class="form-control form-control-sm">
                    </div>
                </div>
                <button type="button" class="btn btn-primary mb-3" onclick="cargarComparativo()">
                    <i class="bi bi-search"></i> Comparar
                </button>
                <div class="pb-table-wrap">
                    <table class="table table-sm table-bordered">
                        <thead>
                            <tr>
                                <th></th>
                                <th class="text-end">Período A</th>
                                <th class="text-end">Período B</th>
                                <th class="text-end">Diferencia</th>
                            </tr>
                        </thead>
                        <tbody id="tablaComparativo">
                            <tr><td colspan="4" class="text-center text-muted">Elige los dos rangos y compara.</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mt-1">
    <div class="col-lg-6">
        <div class="pb-card">
            <div class="pb-card-body">
                <h6 class="mb-2"><i class="bi bi-download"></i> Exportar resumen de esta caja</h6>
                <p class="text-muted small mb-3">
                    Si tienes más de una caja (cada una es su propia licencia, sin conectarse entre sí en
                    vivo), exporta el resumen del día en cada una y únelos en "Consolidar cajas" al lado.
                </p>
                <div class="mb-2">
                    <label for="export_caja_nombre" class="form-label mb-1">Nombre de esta caja (opcional)</label>
                    <input type="text" class="form-control" id="export_caja_nombre" placeholder="Ej. Caja 1 — Sede Norte">
                </div>
                <div class="row g-2 align-items-end">
                    <div class="col-6">
                        <label for="export_fecha_inicio" class="form-label mb-1">Desde</label>
                        <input type="date" id="export_fecha_inicio" class="form-control">
                    </div>
                    <div class="col-6">
                        <label for="export_fecha_fin" class="form-label mb-1">Hasta</label>
                        <input type="date" id="export_fecha_fin" class="form-control">
                    </div>
                </div>
                <button type="button" class="btn btn-primary mt-2" onclick="exportarResumenDia()">
                    <i class="bi bi-download"></i> Descargar resumen de esta caja
                </button>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="pb-card">
            <div class="pb-card-body">
                <h6 class="mb-2"><i class="bi bi-collection"></i> Consolidar cajas</h6>
                <p class="text-muted small mb-3">
                    Elige 2 o más archivos de resumen ya exportados (de cada caja, del mismo día) para ver
                    el total combinado. Solo se leen en esta pantalla, nunca se suben a ningún servidor.
                </p>
                <input type="file" class="form-control mb-3" id="archivos_consolidar" accept=".json" multiple>
                <div class="pb-table-wrap">
                    <table class="table table-sm table-bordered">
                        <thead>
                            <tr>
                                <th>Caja</th>
                                <th class="text-center">Ventas</th>
                                <th class="text-end">Total</th>
                                <th class="text-end">Utilidad</th>
                            </tr>
                        </thead>
                        <tbody id="tablaConsolidado">
                            <tr><td colspan="4" class="text-center text-muted">Elige 2 o más archivos exportados.</td></tr>
                        </tbody>
                    </table>
                </div>
                <div id="consolidadoTotal"></div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . "/../parciales/pie.php"; ?>
<script>
$(function () {
    const hoy = new Date().toISOString().split('T')[0];
    $('#csv_fecha_inicio, #ranking_fecha_inicio, #comp_inicio_a, #comp_inicio_b, #export_fecha_inicio').val(hoy);
    $('#csv_fecha_fin, #ranking_fecha_fin, #comp_fin_a, #comp_fin_b, #export_fecha_fin').val(hoy);
});

function exportarResumenDia() {
    const inicio = $('#export_fecha_inicio').val();
    const fin = $('#export_fecha_fin').val();
    if (!inicio || !fin) {
        pb.error('Selecciona ambas fechas.');
        return;
    }
    const cajaNombre = encodeURIComponent($('#export_caja_nombre').val().trim());
    window.location.href = `/controlador/exportar_resumen_dia.php?fecha_inicio=${inicio}&fecha_fin=${fin}&caja_nombre=${cajaNombre}`;
}

document.getElementById('archivos_consolidar').addEventListener('change', async function (e) {
    const archivos = Array.from(e.target.files);
    const resultados = [];
    for (const archivo of archivos) {
        try {
            const texto = await archivo.text();
            const datos = JSON.parse(texto);
            if (!datos.resumen) throw new Error('formato inválido');
            resultados.push(datos);
        } catch (err) {
            pb.error(`No se pudo leer "${archivo.name}" — ¿es un archivo de resumen exportado desde aquí?`);
        }
    }
    renderConsolidado(resultados);
});

function renderConsolidado(resultados) {
    const $body = $('#tablaConsolidado').empty();
    if (resultados.length === 0) {
        $body.append('<tr><td colspan="4" class="text-center text-muted">Elige 2 o más archivos exportados.</td></tr>');
        $('#consolidadoTotal').empty();
        return;
    }
    let totalVentas = 0, totalNum = 0, totalUtilidad = 0;
    resultados.forEach(function (r) {
        const resumen = r.resumen || {};
        totalVentas += resumen.total_ventas || 0;
        totalNum += resumen.num_ventas || 0;
        totalUtilidad += resumen.utilidad || 0;
        $body.append(`
            <tr>
                <td>${r.caja_nombre || '—'}</td>
                <td class="text-center">${resumen.num_ventas || 0}</td>
                <td class="text-end">${pb.moneda(resumen.total_ventas || 0)}</td>
                <td class="text-end">${pb.moneda(resumen.utilidad || 0)}</td>
            </tr>
        `);
    });
    $('#consolidadoTotal').html(`
        <div class="d-flex justify-content-between fw-bold border-top pt-2 mt-2">
            <span>Total combinado (${resultados.length} caja${resultados.length === 1 ? '' : 's'}, ${totalNum} ventas)</span>
            <span>${pb.moneda(totalVentas)}</span>
        </div>
    `);
}

function exportarVentasCsv() {
    const inicio = $('#csv_fecha_inicio').val();
    const fin = $('#csv_fecha_fin').val();
    if (!inicio || !fin) {
        pb.error('Selecciona ambas fechas.');
        return;
    }
    window.location.href = `/controlador/exportar_ventas_csv.php?fecha_inicio=${inicio}&fecha_fin=${fin}`;
}

function cargarRankingModificadores() {
    const fechaInicio = $('#ranking_fecha_inicio').val();
    const fechaFin = $('#ranking_fecha_fin').val();
    if (!fechaInicio || !fechaFin) {
        pb.error('Selecciona ambas fechas.');
        return;
    }
    $.ajax({
        type: 'POST',
        url: '/controlador/reportes_ranking_modificadores.php',
        data: { fecha_inicio: fechaInicio, fecha_fin: fechaFin },
        dataType: 'json',
        success: function (filas) {
            const $body = $('#tablaRankingModificadores').empty();
            if (!Array.isArray(filas) || filas.length === 0) {
                $body.append('<tr><td colspan="4" class="text-center text-muted">Sin datos en este rango.</td></tr>');
                return;
            }
            filas.forEach(function (f) {
                $body.append(`
                    <tr>
                        <td>${f.nombre}</td>
                        <td class="text-center">${f.veces_elegido}</td>
                        <td class="text-center">${f.veces_cobrado}</td>
                        <td class="text-end">${pb.moneda(f.ingreso_generado)}</td>
                    </tr>
                `);
            });
        },
        error: function () { pb.error('Error al consultar el ranking.'); }
    });
}

function filaComparativo(etiqueta, valorA, valorB, esMoneda) {
    const fmt = (v) => esMoneda ? pb.moneda(v) : v;
    const diferencia = valorB - valorA;
    const claseDif = diferencia > 0 ? 'text-success' : (diferencia < 0 ? 'text-danger' : 'text-muted');
    const signo = diferencia > 0 ? '+' : '';
    return `
        <tr>
            <td>${etiqueta}</td>
            <td class="text-end">${fmt(valorA)}</td>
            <td class="text-end">${fmt(valorB)}</td>
            <td class="text-end ${claseDif}">${signo}${fmt(diferencia)}</td>
        </tr>
    `;
}

function cargarComparativo() {
    const inicioA = $('#comp_inicio_a').val();
    const finA = $('#comp_fin_a').val();
    const inicioB = $('#comp_inicio_b').val();
    const finB = $('#comp_fin_b').val();
    if (!inicioA || !finA || !inicioB || !finB) {
        pb.error('Selecciona los dos rangos completos.');
        return;
    }
    $.ajax({
        type: 'POST',
        url: '/controlador/reportes_comparativo.php',
        data: { inicio_a: inicioA, fin_a: finA, inicio_b: inicioB, fin_b: finB },
        dataType: 'json',
        success: function (r) {
            if (r.error) {
                pb.error(r.error);
                return;
            }
            const a = r.periodo_a;
            const b = r.periodo_b;
            const $body = $('#tablaComparativo').empty();
            $body.append(filaComparativo('Ventas', a.num_ventas, b.num_ventas, false));
            $body.append(filaComparativo('Total vendido', a.total_ventas, b.total_ventas, true));
            $body.append(filaComparativo('Ticket promedio', a.ticket_promedio, b.ticket_promedio, true));
            $body.append(filaComparativo('Utilidad', a.utilidad, b.utilidad, true));
            $body.append(filaComparativo('Descuentos', a.total_descuentos, b.total_descuentos, true));
            $body.append(filaComparativo('Devuelto', a.total_devuelto, b.total_devuelto, true));
            $body.append(filaComparativo('Propinas', a.total_propinas, b.total_propinas, true));
        },
        error: function () { pb.error('Error al comparar los períodos.'); }
    });
}
</script>
