<?php
require_once "../../modelo/bootstrap.php";

$usuarioActual = Auth::requerirRol('admin');
$tituloPagina = 'Panel';
$paginaActiva = 'home';
$modExistencias = Configuracion::moduloActivo('existencias');
$modVencimientos = Configuracion::moduloActivo('vencimientos');

require_once __DIR__ . "/../parciales/head.php";
require_once __DIR__ . "/../parciales/sidebar_inicio.php";
?>

<div class="row g-3 mb-4">
    <div class="col-sm-6 col-lg-3">
        <div class="pb-stat">
            <div class="pb-stat-icon"><i class="bi bi-cash-coin fs-4"></i></div>
            <div>
                <div class="pb-stat-value" id="statTotalVentas">—</div>
                <div class="pb-stat-label">Ventas de hoy</div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-3">
        <div class="pb-stat">
            <div class="pb-stat-icon"><i class="bi bi-receipt fs-4"></i></div>
            <div>
                <div class="pb-stat-value" id="statNumVentas">—</div>
                <div class="pb-stat-label">Facturas emitidas hoy</div>
            </div>
        </div>
    </div>
    <?php if ($modExistencias): ?>
    <div class="col-sm-6 col-lg-3">
        <div class="pb-stat">
            <div class="pb-stat-icon"><i class="bi bi-exclamation-triangle fs-4"></i></div>
            <div>
                <div class="pb-stat-value" id="statStockBajo">—</div>
                <div class="pb-stat-label">Productos con stock bajo</div>
            </div>
        </div>
    </div>
    <?php endif; ?>
    <div class="col-sm-6 col-lg-3">
        <div class="pb-stat">
            <div class="pb-stat-icon"><i class="bi bi-graph-up-arrow fs-4"></i></div>
            <div>
                <div class="pb-stat-value" id="statUtilidad">—</div>
                <div class="pb-stat-label">Utilidad estimada hoy</div>
            </div>
        </div>
    </div>
    <?php if ($modVencimientos): ?>
    <div class="col-sm-6 col-lg-3">
        <div class="pb-stat">
            <div class="pb-stat-icon"><i class="bi bi-exclamation-diamond fs-4"></i></div>
            <div>
                <div class="pb-stat-value" id="statVencimientos">—</div>
                <div class="pb-stat-label">Próximos a vencer</div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<div class="row g-3 mb-3">
    <div class="col-12">
        <div class="pb-card">
            <div class="pb-card-body">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                    <h6 class="mb-0">Balance de este período <span class="text-muted small fw-normal" id="balancePeriodoFechas"></span></h6>
                    <a href="/vista/compras_gastos/index.php" class="small">Ver el detalle en Compras y gastos →</a>
                </div>
                <div class="row g-2" id="balancePeriodoTarjetas"><p class="text-muted small mb-0">Cargando…</p></div>
                <div class="form-text mt-2">
                    El balance es lo vendido menos lo pagado en gastos y compras (flujo simple, no es la utilidad contable).
                    El período de corte (semanal, quincenal o mensual) se ajusta en Configuración.
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-12">
        <div class="pb-card">
            <div class="pb-card-body">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                    <h6 class="mb-0">Ventas por período</h6>
                    <select class="form-select form-select-sm" style="width:auto;" id="selectDiasReporte" onchange="cargarReportesPeriodo()">
                        <option value="7">Últimos 7 días</option>
                        <option value="14" selected>Últimos 14 días</option>
                        <option value="30">Últimos 30 días</option>
                        <option value="90">Últimos 90 días</option>
                    </select>
                </div>
                <div class="row g-3">
                    <div class="col-lg-8">
                        <div id="graficoVentasPorDia"><p class="text-muted small mb-0">Cargando…</p></div>
                    </div>
                    <div class="col-lg-4">
                        <div class="small text-muted mb-1">Vs. período anterior</div>
                        <div id="comparativaPeriodo"><p class="text-muted small mb-0">Cargando…</p></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-12">
        <div class="pb-card">
            <div class="pb-card-body">
                <h6 class="mb-3">Productos más vendidos en el período (por valor)</h6>
                <div id="graficoTopProductos"><p class="text-muted small mb-0">Cargando…</p></div>
            </div>
        </div>
    </div>
    <?php if ($modExistencias): ?>
    <div class="col-12">
        <div class="pb-card">
            <div class="pb-card-body">
                <h6 class="mb-1"><i class="bi bi-hourglass-split text-muted"></i> Rotación lenta en el período</h6>
                <p class="text-muted small mb-3">Tienen existencia pero no se han vendido en ese rango — candidatos a dar de baja, donar o dar salida por canje.</p>
                <div class="pb-table-wrap" style="max-height:300px; overflow-y:auto;">
                    <table class="table table-sm align-middle mb-0">
                        <thead>
                            <tr><th>Producto</th><th>Categoría</th><th class="text-end">Existencia</th><th class="text-end">Sin venderse</th><th class="text-end">Valor inmovilizado</th></tr>
                        </thead>
                        <tbody id="tablaRotacionLenta"><tr><td colspan="5" class="text-center text-muted">Cargando…</td></tr></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<div class="row g-3">
    <div class="col-lg-6">
        <div class="pb-card">
            <div class="pb-card-body">
                <h6 class="mb-3">Productos más vendidos hoy</h6>
                <ul class="list-group list-group-flush" id="listaTopProductos">
                    <li class="list-group-item text-muted">Cargando…</li>
                </ul>
            </div>
        </div>
    </div>
    <?php if ($modExistencias): ?>
    <div class="col-lg-6">
        <div class="pb-card">
            <div class="pb-card-body">
                <h6 class="mb-3"><i class="bi bi-exclamation-triangle text-danger"></i> Stock bajo</h6>
                <ul class="list-group list-group-flush" id="listaStockBajo">
                    <li class="list-group-item text-muted">Cargando…</li>
                </ul>
            </div>
        </div>
    </div>
    <?php endif; ?>
    <?php if ($modVencimientos): ?>
    <div class="col-12">
        <div class="pb-card">
            <div class="pb-card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="mb-0"><i class="bi bi-exclamation-diamond text-warning"></i> Próximos a vencer</h6>
                    <a href="/vista/vencimientos/index.php" class="small">Ver todos →</a>
                </div>
                <ul class="list-group list-group-flush" id="listaVencimientos">
                    <li class="list-group-item text-muted">Cargando…</li>
                </ul>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . "/../parciales/pie.php"; ?>
<script>
const ETIQUETAS_PERIODO = { semanal: 'semana', quincenal: 'quincena', mensual: 'mes' };

function cargarBalancePeriodo() {
    $.getJSON('/controlador/balance_periodo.php', function (data) {
        const r = data.resumen;
        $('#balancePeriodoFechas').text(`(${ETIQUETAS_PERIODO[data.tipo] || data.tipo}: ${data.inicio} a ${data.fin})`);
        const tarjeta = (titulo, valor, clase) => `<div class="col-6 col-md-4"><div class="border rounded p-2"><div class="small text-muted">${titulo}</div><div class="fs-5 fw-semibold ${clase || ''}">${pb.moneda(valor)}</div></div></div>`;
        $('#balancePeriodoTarjetas').html(
            tarjeta('Ventas', r.ventas) +
            tarjeta('Gastos y compras', r.gastos_y_compras) +
            tarjeta('Balance', r.balance, r.balance < 0 ? 'text-danger' : 'text-success')
        );
    }).fail(() => $('#balancePeriodoTarjetas').html('<p class="text-danger small mb-0">No se pudo cargar el balance del período.</p>'));
}

$(function () {
    cargarReportesPeriodo();
    cargarBalancePeriodo();

    $.getJSON('/controlador/dashboard_resumen.php', function (data) {
        $('#statTotalVentas').text(pb.moneda(data.total_ventas));
        $('#statNumVentas').text(data.num_ventas);
        $('#statStockBajo').text(data.stock_bajo.length);
        $('#statUtilidad').text(pb.moneda(data.utilidad_hoy));

        const $top = $('#listaTopProductos').empty();
        if (data.top_productos.length === 0) {
            $top.append('<li class="list-group-item text-muted">Aún no hay ventas hoy.</li>');
        } else {
            data.top_productos.forEach(function (p) {
                $top.append(`<li class="list-group-item d-flex justify-content-between">
                    <span>${p.nombre_producto}</span>
                    <strong>${p.unidades} uds.</strong>
                </li>`);
            });
        }

        const $bajo = $('#listaStockBajo').empty();
        if (data.stock_bajo.length === 0) {
            $bajo.append('<li class="list-group-item text-muted">Todo el inventario está en niveles saludables.</li>');
        } else {
            data.stock_bajo.forEach(function (p) {
                const badge = p.estado_stock === 'agotado'
                    ? '<span class="badge bg-danger">Agotado</span>'
                    : '<span class="badge bg-warning text-dark">Bajo mínimo</span>';
                $bajo.append(`<li class="list-group-item d-flex justify-content-between align-items-center pb-stock-bajo">
                    <span>${p.nombre_producto} ${badge}</span>
                    <strong>${p.cantidad_existente} / mín. ${p.minimo_stock}</strong>
                </li>`);
            });
        }

        const vencimientos = data.vencimientos || [];
        $('#statVencimientos').text(vencimientos.length);
        const $venc = $('#listaVencimientos').empty();
        if (vencimientos.length === 0) {
            $venc.append('<li class="list-group-item text-muted">Nada próximo a vencer.</li>');
        } else {
            vencimientos.slice(0, 8).forEach(function (a) {
                const badge = a.estado === 'rojo'
                    ? '<span class="badge bg-danger">Vencido</span>'
                    : '<span class="badge bg-warning text-dark">Próximo</span>';
                const lote = a.descripcion_lote ? ' — ' + a.descripcion_lote : '';
                $venc.append(`<li class="list-group-item d-flex justify-content-between align-items-center">
                    <span>${a.nombre_producto}${lote} ${badge}</span>
                    <strong>${a.fecha_vencimiento}</strong>
                </li>`);
            });
        }
    }).fail(function () {
        pb.error('No se pudo cargar el resumen del panel.');
    });
});

function cargarReportesPeriodo() {
    const dias = $('#selectDiasReporte').val() || 14;
    $.getJSON('/controlador/reportes_dashboard.php', { dias: dias }, function (data) {
        pb.graficoBarras(
            'graficoVentasPorDia',
            data.ventas_por_dia.map(d => ({
                etiqueta: d.fecha,
                etiquetaCorta: d.fecha.slice(8, 10) + '/' + d.fecha.slice(5, 7),
                valor: d.total,
            })),
            { formatearValor: pb.moneda }
        );

        pb.graficoBarras(
            'graficoTopProductos',
            data.top_productos.map(p => ({
                etiqueta: p.nombre_producto,
                etiquetaCorta: p.nombre_producto.length > 10 ? p.nombre_producto.slice(0, 9) + '…' : p.nombre_producto,
                valor: p.total,
            })),
            { formatearValor: pb.moneda, anchoBarra: 36 }
        );

        const comp = data.comparativa;
        const variacion = comp.variacion_porcentaje;
        let variacionHtml;
        if (variacion === null) {
            variacionHtml = '<span class="text-muted">Sin ventas en el período anterior para comparar.</span>';
        } else {
            const claseColor = variacion >= 0 ? 'text-success' : 'text-danger';
            const icono = variacion >= 0 ? 'bi-arrow-up-right' : 'bi-arrow-down-right';
            variacionHtml = `<span class="${claseColor}"><i class="bi ${icono}"></i> ${variacion >= 0 ? '+' : ''}${variacion.toFixed(1)}%</span>`;
        }
        $('#comparativaPeriodo').html(`
            <div class="d-flex justify-content-between"><span>Este período</span><strong>${pb.moneda(comp.periodo_actual.total)}</strong></div>
            <div class="d-flex justify-content-between text-muted small"><span>Período anterior</span><span>${pb.moneda(comp.periodo_anterior.total)}</span></div>
            <div class="mt-2">${variacionHtml}</div>
        `);

        const $rotacion = $('#tablaRotacionLenta');
        if ($rotacion.length) {
            $rotacion.empty();
            if (!data.rotacion_lenta || data.rotacion_lenta.length === 0) {
                $rotacion.append('<tr><td colspan="5" class="text-center text-muted">Todo lo que tiene existencia se vendió en este período.</td></tr>');
            } else {
                data.rotacion_lenta.forEach(function (p) {
                    const sinVenderse = p.dias_sin_vender === null ? 'Nunca' : `${p.dias_sin_vender} día(s)`;
                    $rotacion.append(`<tr>
                        <td>${pb.escaparHtml(p.nombre_producto)}</td>
                        <td>${pb.escaparHtml(p.categoria)}</td>
                        <td class="text-end">${p.cantidad_existente}</td>
                        <td class="text-end ${p.dias_sin_vender === null ? 'text-danger' : ''}">${sinVenderse}</td>
                        <td class="text-end">${pb.moneda(p.valor_inmovilizado)}</td>
                    </tr>`);
                });
            }
        }
    }).fail(function () {
        pb.error('No se pudieron cargar los reportes del período.');
    });
}
</script>
</body>
</html>
