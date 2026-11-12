<?php
require_once "../../modelo/bootstrap.php";

$usuarioActual = Auth::requerirPermiso('movimientos.ver');
$tituloPagina = 'Compras y gastos';
$paginaActiva = 'compras_gastos';
$puedeAnular = Auth::tienePermiso('ventas.anular');
$puedeConceptos = Auth::tienePermiso('productos.editar');

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
        <button class="btn btn-primary" onclick="filtrarPorFecha()"><i class="bi bi-search"></i> Buscar</button>
        <button class="btn btn-outline-secondary" onclick="irAlPeriodoActual()" title="Vuelve al período de balance configurado en Configuración"><i class="bi bi-calendar-range"></i> Período actual</button>
        <?php if ($puedeConceptos): ?>
        <button class="btn btn-outline-secondary ms-auto" onclick="abrirConceptos()"><i class="bi bi-tags"></i> Administrar conceptos</button>
        <?php endif; ?>
    </div>
</div>

<div class="pb-card mb-3">
    <div class="pb-card-body">
        <h6 class="mb-3">Resumen del período</h6>
        <div class="row g-2 mb-3" id="resumen_tarjetas"></div>
        <div class="row g-3">
            <div class="col-md-6">
                <div class="small fw-semibold mb-1">Por tipo de gasto</div>
                <table class="table table-sm mb-0" id="tabla_resumen_grupos"><tbody></tbody></table>
            </div>
            <div class="col-md-6">
                <div class="small fw-semibold mb-1">Por concepto</div>
                <table class="table table-sm mb-0" id="tabla_resumen_conceptos"><tbody></tbody></table>
            </div>
        </div>
        <div class="form-text mt-2">
            El balance es lo vendido menos lo pagado en gastos y compras (flujo simple, no es la utilidad contable).
            Las pérdidas se muestran aparte y no restan porque no salió dinero. Lo anulado no cuenta.
        </div>
    </div>
</div>

<div class="pb-card">
    <div class="pb-card-body">
        <div class="pb-table-wrap">
            <table class="table table-bordered table-sm pb-table-compact" id="tabla_insumos">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Fecha</th>
                        <th>Producto / concepto</th>
                        <th>Descripción</th>
                        <th>Total</th>
                        <th>Tipo</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="modalDetalle" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title" id="modalDetalleTitulo">Detalle</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="modalDetalleContenido"></div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<?php if ($puedeAnular): ?>
<div class="modal fade" id="modalEditarGasto" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title">Corregir movimiento</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted small mb-2" id="eg_contexto"></p>
                <div class="mb-2">
                    <label for="eg_descripcion" class="form-label mb-1">Descripción</label>
                    <input type="text" class="form-control" id="eg_descripcion" maxlength="200">
                </div>
                <div class="row g-2 mb-2">
                    <div class="col-6" id="eg_bloque_cantidad">
                        <label for="eg_cantidad" class="form-label mb-1">Cantidad</label>
                        <input type="number" class="form-control" id="eg_cantidad" min="1" step="1">
                    </div>
                    <div class="col-6">
                        <label for="eg_valor" class="form-label mb-1">Valor</label>
                        <input type="number" class="form-control" id="eg_valor" min="1">
                    </div>
                </div>
                <div class="mb-1">
                    <label for="eg_motivo" class="form-label mb-1">Motivo de la corrección</label>
                    <input type="text" class="form-control" id="eg_motivo" maxlength="150" placeholder="Ej. me equivoqué en el valor">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" onclick="guardarEdicionGasto()"><i class="bi bi-check2-circle"></i> Guardar</button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if ($puedeConceptos): ?>
<div class="modal fade" id="modalConceptos" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title">Conceptos de compras, gastos y pérdidas</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted small">
                    Son las cosas que se compran o se pagan y no son productos del inventario (gas, luz, bolsas…).
                    Renombrar un concepto también cambia cómo se ve en los movimientos anteriores.
                    Desactivarlo lo quita de las listas sin borrar su historial.
                </p>
                <div class="table-responsive">
                    <table class="table table-sm align-middle" id="tabla_conceptos">
                        <thead><tr><th>Nombre</th><th style="width:150px;">Tipo</th><th class="text-center" style="width:80px;">Activo</th><th class="text-center" style="width:70px;">Usos</th><th style="width:60px;"></th></tr></thead>
                        <tbody></tbody>
                    </table>
                </div>
                <div class="border-top pt-3 mt-2">
                    <div class="small fw-semibold mb-1">Agregar concepto</div>
                    <div class="d-flex gap-2 flex-wrap">
                        <input type="text" class="form-control" id="concepto_nuevo_nombre" placeholder="Ej. Publicidad" maxlength="80" style="max-width:280px;">
                        <select class="form-select" id="concepto_nuevo_categoria" style="max-width:160px;">
                            <option value="servicio">Servicio</option>
                            <option value="insumo">Insumo</option>
                            <option value="otro">Otro</option>
                        </select>
                        <button type="button" class="btn btn-primary" onclick="agregarConcepto()"><i class="bi bi-plus-lg"></i> Agregar</button>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . "/../parciales/pie.php"; ?>
<script>
const PUEDE_ANULAR = <?php echo $puedeAnular ? 'true' : 'false'; ?>;
const ETIQUETAS_GRUPO = {
    servicio: 'Servicios (luz, agua, gas…)',
    insumo: 'Insumos (bolsas, desechables…)',
    otro: 'Otros gastos con concepto',
    egreso_suelto: 'Egresos sueltos (sin concepto)',
    compra_producto: 'Compras de mercancía',
    perdida: 'Pérdidas (no restan)',
};

const ETIQUETAS_TIPO_TRANSACCION = { salida: 'Salida (sin pérdida/ganancia)', entrada: 'Entrada (sin pérdida/ganancia)' };
function etiquetaTipoTransaccion(tipo) {
    return ETIQUETAS_TIPO_TRANSACCION[tipo] || tipo;
}

$(function () {
    irAlPeriodoActual();
});

/** Pone en los filtros el período de corte configurado (Configuración > Período de balance) y busca. */
function irAlPeriodoActual() {
    $.getJSON('/controlador/balance_periodo.php', function (data) {
        $('#fecha_inicio').val(data.inicio);
        $('#fecha_fin').val(data.fin);
        filtrarPorFecha();
    }).fail(function () {
        // Si por lo que sea falla, al menos deja algo buscable en vez de quedar vacío.
        const hoy = new Date().toLocaleDateString('sv-SE');
        $('#fecha_inicio, #fecha_fin').val(hoy);
        filtrarPorFecha();
    });
}

function filtrarPorFecha() {
    const fechaInicio = $('#fecha_inicio').val();
    const fechaFin = $('#fecha_fin').val();
    if (!fechaInicio || !fechaFin) {
        pb.error('Selecciona ambas fechas.');
        return;
    }
    cargarInsumos(fechaInicio, fechaFin);
    cargarResumen(fechaInicio, fechaFin);
}

function cargarResumen(fechaInicio, fechaFin) {
    $.getJSON('/controlador/resumen_gastos.php', { fecha_inicio: fechaInicio, fecha_fin: fechaFin }, function (r) {
        const tarjeta = (titulo, valor, clase) => `<div class="col-6 col-md-4"><div class="border rounded p-2"><div class="small text-muted">${titulo}</div><div class="fs-5 fw-semibold ${clase || ''}">${pb.moneda(valor)}</div></div></div>`;
        $('#resumen_tarjetas').html(
            tarjeta('Ventas', r.ventas) +
            tarjeta('Gastos y compras', r.gastos_y_compras) +
            tarjeta('Balance', r.balance, r.balance < 0 ? 'text-danger' : 'text-success')
        );
        const $grupos = $('#tabla_resumen_grupos tbody').empty();
        Object.keys(ETIQUETAS_GRUPO).forEach(function (clave) {
            const g = r.grupos[clave];
            if (!g || g.cantidad === 0) return;
            $grupos.append(`<tr><td>${ETIQUETAS_GRUPO[clave]} <small class="text-muted">(${g.cantidad})</small></td><td class="text-end">${pb.moneda(g.total)}</td></tr>`);
        });
        if ($grupos.children().length === 0) $grupos.append('<tr><td class="text-muted">Sin gastos en este rango.</td></tr>');

        const $conceptos = $('#tabla_resumen_conceptos tbody').empty();
        r.por_concepto.forEach(function (c) {
            $conceptos.append(`<tr><td>${pb.escaparHtml(c.nombre)} <small class="text-muted">(${c.cantidad})</small></td><td class="text-end">${pb.moneda(c.total)}</td></tr>`);
        });
        if ($conceptos.children().length === 0) $conceptos.append('<tr><td class="text-muted">Ningún gasto con concepto.</td></tr>');
    }).fail(() => pb.error('Error al cargar el resumen.'));
}

// Guarda cada fila ya cargada por id_movimiento — abrirEditarGasto() la usa sin pedirla otra vez.
let insumosCache = {};

function cargarInsumos(fechaInicio, fechaFin) {
    $.getJSON('/controlador/insumos_por_fecha.php', { fecha_inicio: fechaInicio, fecha_fin: fechaFin }, function (insumos) {
        const $tbody = $('#tabla_insumos tbody').empty();
        insumosCache = {};
        if (insumos.length === 0) {
            $tbody.append('<tr><td colspan="7" class="text-center text-muted">No hay movimientos en este rango.</td></tr>');
            return;
        }
        insumos.forEach(function (i) {
            insumosCache[i.id_movimiento] = i;
            const anulado = parseInt(i.anulada, 10) === 1;
            // "salida"/"entrada": ajustes de inventario sin pérdida ni ganancia (canje, donación,
            // muestra...) — color neutro aparte, para no confundirlos con una compra o pérdida real.
            const esAjuste = i.tipo_transaccion === 'salida' || i.tipo_transaccion === 'entrada';
            const clase = anulado ? 'table-secondary' : esAjuste ? 'table-light' : (i.tipo_transaccion === 'compra' ? 'table-info' : (i.tipo_transaccion === 'perdida' ? 'table-danger' : 'table-warning'));
            const descripcionCorta = i.descripcion.length > 30 ? i.descripcion.substring(0, 30) + '…' : i.descripcion;
            // Un producto del inventario, o un concepto (gas, luz, bolsas...), o un egreso suelto sin ninguno de los dos.
            let producto = '—';
            if (i.nombre_producto) {
                producto = `${pb.escaparHtml(i.nombre_producto)} <small class="text-muted">(${i.cantidad} ${i.presentacion === 'paquete' ? 'paq.' : 'u.'})</small>`;
            } else if (i.concepto) {
                producto = `${pb.escaparHtml(i.concepto)} <small class="text-muted">(${pb.escaparHtml(i.concepto_categoria)}${i.cantidad > 1 ? ' · x' + i.cantidad : ''})</small>`;
            }
            // Solo lo que NO mueve inventario se puede anular o editar (gastos y pérdidas de conceptos).
            const corregible = !anulado && PUEDE_ANULAR && (i.tipo_transaccion === 'egreso' || (i.tipo_transaccion === 'perdida' && !i.nombre_producto));
            const botonEditar = corregible
                ? `<button class="btn btn-sm btn-outline-secondary ms-1" title="Corregir" onclick="abrirEditarGasto(${i.id_movimiento})"><i class="bi bi-pencil"></i></button>`
                : '';
            const botonAnular = corregible
                ? `<button class="btn btn-sm btn-outline-danger ms-1" title="Anular" onclick="anularMovimiento(${i.id_factura})"><i class="bi bi-x-circle"></i></button>`
                : '';
            const insignia = anulado ? ' <span class="badge bg-secondary">Anulado</span>' : '';
            $tbody.append(`<tr class="${anulado ? 'text-decoration-line-through text-muted' : ''}">
                <td class="text-center">${i.id_factura}</td>
                <td>${i.fecha}</td>
                <td>${producto}</td>
                <td>${pb.escaparHtml(descripcionCorta)}</td>
                <td>${pb.moneda(i.total)}</td>
                <td class="${clase}">${etiquetaTipoTransaccion(i.tipo_transaccion)}${insignia}</td>
                <td class="text-center text-nowrap"><button class="btn btn-sm btn-outline-info" onclick="abrirInsumo(${i.id_movimiento})"><i class="bi bi-sticky"></i></button>${botonEditar}${botonAnular}</td>
            </tr>`);
        });
    }).fail(() => pb.error('Error al cargar los movimientos.'));
}

/** Modal "Corregir movimiento" — reusa la fila ya cargada en insumosCache, sin pedirla otra vez. */
function abrirEditarGasto(idMovimiento) {
    const i = insumosCache[idMovimiento];
    if (!i) return;
    $('#eg_descripcion').val(i.descripcion);
    $('#eg_valor').val(i.total);
    $('#eg_motivo').val('');
    const tieneCantidad = !!i.concepto;
    $('#eg_bloque_cantidad').toggleClass('d-none', !tieneCantidad);
    $('#eg_cantidad').val(tieneCantidad ? i.cantidad : '');
    $('#eg_contexto').text(i.concepto ? `Concepto: ${i.concepto}` : (i.tipo_transaccion === 'perdida' ? 'Pérdida sin concepto' : 'Egreso sin concepto'));
    $('#modalEditarGasto').data('id-factura', i.id_factura).data('id-movimiento', idMovimiento);
    bootstrap.Modal.getOrCreateInstance('#modalEditarGasto').show();
}

function guardarEdicionGasto() {
    const $modal = $('#modalEditarGasto');
    const motivo = $('#eg_motivo').val().trim();
    if (motivo === '') {
        pb.error('Indica el motivo de la corrección.');
        return;
    }
    const datos = {
        id_factura: $modal.data('id-factura'),
        valor: $('#eg_valor').val(),
        descripcion: $('#eg_descripcion').val(),
        motivo: motivo,
        csrf_token: pb.csrfToken(),
    };
    if (!$('#eg_bloque_cantidad').hasClass('d-none')) datos.cantidad = $('#eg_cantidad').val();
    $.ajax({
        type: 'POST',
        url: '/controlador/editar_gasto.php',
        data: datos,
        dataType: 'json',
        success: function (r) {
            if (r.ok) {
                pb.success('Movimiento corregido.');
                bootstrap.Modal.getInstance(document.getElementById('modalEditarGasto'))?.hide();
                filtrarPorFecha();
            } else {
                pb.error(r.error || 'No se pudo corregir.');
            }
        },
        error: function () { pb.error('Error en el proceso.'); },
    });
}

async function anularMovimiento(idFactura) {
    const motivo = await pb.preguntar('Motivo de la anulación (queda registrado):', '', 'text');
    if (motivo === null) return;
    if (motivo.trim() === '') {
        pb.error('Indica el motivo de la anulación.');
        return;
    }
    $.ajax({
        type: 'POST',
        url: '/controlador/anular_gasto.php',
        data: { id_factura: idFactura, motivo: motivo, csrf_token: pb.csrfToken() },
        dataType: 'json',
        success: function (r) {
            if (r.ok) {
                pb.success('Movimiento anulado.');
                filtrarPorFecha();
            } else {
                pb.error(r.error || 'No se pudo anular.');
            }
        },
        error: function () { pb.error('Error en el proceso.'); },
    });
}

function abrirInsumo(idMovimiento) {
    $.getJSON('/controlador/insumo_detalle.php', { id_insumo: idMovimiento }, function (i) {
        if (!i) { pb.error('No se encontraron detalles.'); return; }
        const anulado = parseInt(i.anulada, 10) === 1;
        $('#modalDetalleTitulo').text('Movimiento #' + i.id_movimiento);
        $('#modalDetalleContenido').html(`
            ${anulado ? `<div class="alert alert-secondary py-2">Anulado. Motivo: ${pb.escaparHtml(i.anulada_motivo || '—')}</div>` : ''}
            <p><strong>Fecha:</strong> ${i.fecha}</p>
            ${i.nombre_producto ? `<p><strong>Producto:</strong> ${pb.escaparHtml(i.nombre_producto)} (${i.cantidad} ${i.presentacion === 'paquete' ? 'paquete(s)' : 'unidad(es)'} = ${i.unidades_totales} u. de inventario)</p>` : ''}
            ${i.concepto ? `<p><strong>Concepto:</strong> ${pb.escaparHtml(i.concepto)} (${pb.escaparHtml(i.concepto_categoria)}) — cantidad: ${i.cantidad}</p>` : ''}
            <p><strong>Descripción:</strong> ${pb.escaparHtml(i.descripcion)}</p>
            <p><strong>Total:</strong> ${pb.moneda(i.total)}</p>
            <p><strong>Tipo:</strong> ${etiquetaTipoTransaccion(i.tipo_transaccion)}</p>
        `);
        bootstrap.Modal.getOrCreateInstance('#modalDetalle').show();
    }).fail(() => pb.error('Error al cargar los detalles del movimiento.'));
}

<?php if ($puedeConceptos): ?>
// ---- Administrar conceptos ----
function abrirConceptos() {
    cargarConceptos();
    bootstrap.Modal.getOrCreateInstance('#modalConceptos').show();
}

function cargarConceptos() {
    $.getJSON('/controlador/listar_conceptos_gasto.php', function (conceptos) {
        const $tbody = $('#tabla_conceptos tbody').empty();
        conceptos.forEach(function (c) {
            const activo = parseInt(c.estado, 10) === 1;
            const $fila = $(`<tr class="${activo ? '' : 'text-muted'}">
                <td><input type="text" class="form-control form-control-sm c-nombre" maxlength="80"></td>
                <td><select class="form-select form-select-sm c-categoria">
                    <option value="servicio">Servicio</option><option value="insumo">Insumo</option><option value="otro">Otro</option>
                </select></td>
                <td class="text-center"><input type="checkbox" class="form-check-input c-activo"></td>
                <td class="text-center">${parseInt(c.usos, 10)}</td>
                <td class="text-end"><button type="button" class="btn btn-sm btn-outline-primary" title="Guardar"><i class="bi bi-check-lg"></i></button></td>
            </tr>`);
            $fila.find('.c-nombre').val(c.nombre);
            $fila.find('.c-categoria').val(c.categoria);
            $fila.find('.c-activo').prop('checked', activo);
            $fila.find('button').on('click', function () { guardarConcepto(c.id_concepto, $fila); });
            $tbody.append($fila);
        });
    }).fail(() => pb.error('No se pudieron cargar los conceptos.'));
}

function guardarConcepto(idConcepto, $fila) {
    $.ajax({
        type: 'POST',
        url: '/controlador/guardar_concepto_gasto.php',
        data: {
            id_concepto: idConcepto,
            nombre: $fila.find('.c-nombre').val(),
            categoria: $fila.find('.c-categoria').val(),
            estado: $fila.find('.c-activo').is(':checked') ? 1 : 0,
            csrf_token: pb.csrfToken(),
        },
        dataType: 'json',
        success: function (r) {
            if (r.ok) {
                pb.success('Concepto guardado.');
                cargarConceptos();
                filtrarPorFecha();
            } else {
                pb.error(r.error || 'No se pudo guardar.');
            }
        },
        error: function () { pb.error('Error en el proceso.'); },
    });
}

function agregarConcepto() {
    const nombre = $('#concepto_nuevo_nombre').val().trim();
    if (nombre === '') {
        pb.error('Escribe el nombre del concepto.');
        return;
    }
    $.ajax({
        type: 'POST',
        url: '/controlador/crear_concepto_gasto.php',
        data: { nombre: nombre, categoria: $('#concepto_nuevo_categoria').val(), csrf_token: pb.csrfToken() },
        dataType: 'json',
        success: function (r) {
            if (r.ok) {
                pb.success('Concepto agregado.');
                $('#concepto_nuevo_nombre').val('');
                cargarConceptos();
            } else {
                pb.error(r.error || 'No se pudo agregar.');
            }
        },
        error: function () { pb.error('Error en el proceso.'); },
    });
}
<?php endif; ?>
</script>
