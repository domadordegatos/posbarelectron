<?php
require_once "../../modelo/bootstrap.php";
require_once "../../modelo/Licencia.php";

$usuarioActual = Auth::requerirPermiso('movimientos.ver');
$puedeAnular = Auth::tienePermiso('ventas.anular');
$puedeEditar = Auth::tienePermiso('facturas.editar');
$puedeCambiarCliente = $puedeEditar && Configuracion::moduloActivo('clientes_credito');
$fidelizacionHabilitada = !empty(Licencia::datos()['fidelizacion_habilitada']);
$tituloPagina = 'Facturas';
$paginaActiva = 'facturas';

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
    </div>
</div>

<div class="pb-card">
    <div class="pb-card-body">
        <div class="pb-table-wrap">
            <table class="table table-bordered table-sm pb-table-compact" id="tabla_facturas">
                <thead>
                    <tr class="text-center">
                        <th>Factura</th>
                        <th>Fecha</th>
                        <th>Hora</th>
                        <th>Vendedor</th>
                        <th>Total</th>
                        <th>Devuelto</th>
                        <th>Utilidad</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="modalDetalle" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable" style="max-width:780px;">
        <div class="modal-content">
            <div class="modal-header pb-print-hide">
                <h6 class="modal-title" id="modalDetalleTitulo">Detalle</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <!-- Dos columnas siempre: la factura a la izquierda, las acciones en columna a la derecha
                     (igual que la ventana de la factura en Ventas). -->
                <div class="d-flex flex-nowrap gap-3 align-items-start">
                    <div class="flex-grow-1 d-flex justify-content-center" style="min-width:0;">
                        <div id="modalDetalleContenido" class="pb-ticket pb-ticket-vista pb-print-area"></div>
                    </div>
                    <div class="d-grid gap-2 pb-print-hide" style="flex:0 0 190px; width:190px;">
                        <button type="button" class="btn btn-primary" id="btnReimprimir" onclick="reimprimirFactura()">
                            <i class="bi bi-printer"></i> Factura
                        </button>
                        <button type="button" class="btn btn-outline-warning d-none" id="btnReimprimirComanda" onclick="reimprimirComandaDetalle()">
                            <i class="bi bi-printer"></i> Comanda
                        </button>
                        <button type="button" class="btn btn-outline-warning d-none" id="btnEditarFactura" onclick="editarFacturaActual()">
                            <i class="bi bi-pencil-square"></i> Editar ajuste
                        </button>
                        <button type="button" class="btn btn-outline-secondary d-none" id="btnCambiarCliente" onclick="abrirCambiarCliente()">
                            <i class="bi bi-person-gear"></i> Cambiar cliente
                        </button>
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cerrar</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if ($puedeAnular): ?>
<div class="modal fade" id="modalAnular" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title">Anular venta <span id="an_numero"></span></h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <label class="form-label small mb-1" for="an_motivo">Motivo de la anulación</label>
                <input type="text" class="form-control mb-3" id="an_motivo" placeholder="Ej. error de digitación" maxlength="150" autocomplete="off">
                <div id="an_lineas_bloque" class="d-none">
                    <p class="small text-muted mb-2">¿Qué pasó con cada producto? Si volvió a estar disponible se repone al inventario; si se dañó o ya se entregó, se descarta y queda como pérdida.</p>
                    <div id="an_lineas" class="d-flex flex-column gap-2"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-danger" id="an_confirmar" onclick="confirmarAnulacion()"><i class="bi bi-x-circle"></i> Anular venta</button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if ($puedeCambiarCliente): ?>
<div class="modal fade" id="modalCambiarCliente" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-sm modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title">Cambiar el cliente de esta venta</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="small text-muted mb-2">Sirve para corregir una venta que quedó a nombre de otra persona. El saldo de crédito de cada cliente se recalcula solo.</p>
                <div class="small mb-1">Ahora: <strong id="cc_cliente_actual"></strong></div>
                <input type="text" class="form-control mb-1" id="cc_buscar" placeholder="Buscar el cliente correcto…" autocomplete="off">
                <ul class="list-group mb-2" id="cc_resultados" style="max-height:160px;overflow-y:auto;"></ul>
                <input type="text" class="form-control" id="cc_motivo" placeholder="Motivo (obligatorio)" maxlength="150">
                <button type="button" class="btn btn-link btn-sm px-0 mt-1" onclick="cambiarClienteVenta(null)">Dejar sin cliente</button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if ($fidelizacionHabilitada): ?>
<script src="/assets/vendor/qrcodejs/qrcode.min.js"></script>
<?php endif; ?>

<?php require_once __DIR__ . "/../parciales/pie.php"; ?>
<script>
const PUEDE_ANULAR = <?php echo $puedeAnular ? 'true' : 'false'; ?>;
const PUEDE_EDITAR = <?php echo $puedeEditar ? 'true' : 'false'; ?>;
const PUEDE_CAMBIAR_CLIENTE = <?php echo $puedeCambiarCliente ? 'true' : 'false'; ?>;

$(function () {
    const hoy = new Date().toLocaleDateString('sv-SE');
    $('#fecha_inicio, #fecha_fin').val(hoy);
    filtrarPorFecha();
});

function filtrarPorFecha() {
    const fechaInicio = $('#fecha_inicio').val();
    const fechaFin = $('#fecha_fin').val();
    if (!fechaInicio || !fechaFin) {
        pb.error('Selecciona ambas fechas.');
        return;
    }
    cargarVentas(fechaInicio, fechaFin);
}

function cargarVentas(fechaInicio, fechaFin) {
    $.getJSON('/controlador/ventas_por_fecha.php', { fecha_inicio: fechaInicio, fecha_fin: fechaFin }, function (facturas) {
        const $tbody = $('#tabla_facturas tbody').empty();
        if (facturas.length === 0) {
            $tbody.append('<tr><td colspan="8" class="text-center text-muted">No hay ventas en este rango.</td></tr>');
            return;
        }
        let total = 0, utilidad = 0, devuelto = 0;
        facturas.forEach(function (f) {
            if (f.anulada == 1) return;
            total += parseFloat(f.total_venta);
            utilidad += parseFloat(f.utilidad);
            devuelto += parseFloat(f.total_devuelto || 0);
        });
        $tbody.append(`<tr class="table-info"><td colspan="4" class="text-end"><strong>Total ventas</strong></td><td><strong>${pb.moneda(total)}</strong></td><td><strong>${pb.moneda(devuelto)}</strong></td><td><strong>${pb.moneda(utilidad)}</strong></td><td></td></tr>`);
        facturas.forEach(function (f) {
            const anulada = f.anulada == 1;
            const botonAnular = (PUEDE_ANULAR && !anulada)
                ? `<button class="pb-btn-icon pb-btn-icon-delete" onclick="anularVenta(${f.id_factura})" title="Anular venta"><i class="bi bi-x-circle"></i></button>`
                : '';
            $tbody.append(`<tr class="${anulada ? 'pb-fila-inactiva' : ''}">
                <td class="text-center">${f.numero_factura || f.id_factura}${anulada ? ' <span class="badge bg-secondary">Anulada</span>' : ''}</td>
                <td>${f.fecha}</td>
                <td>${f.hora}</td>
                <td>${f.vendedor || '—'}</td>
                <td>${pb.moneda(f.total_venta)}</td>
                <td>${f.total_devuelto > 0 ? pb.moneda(f.total_devuelto) : '—'}</td>
                <td>${pb.moneda(f.utilidad)}</td>
                <td class="text-center d-flex gap-1 justify-content-center">
                    <button class="pb-btn-icon pb-btn-icon-edit" onclick="abrirFactura(${f.id_factura})" title="Ver / reimprimir"><i class="bi bi-receipt"></i></button>
                    ${botonAnular}
                </td>
            </tr>`);
        });
    }).fail(() => pb.error('Error al cargar las ventas.'));
}

let anulacionIdFactura = null;

function anularVenta(idFactura) {
    $.getJSON('/controlador/factura_detalle.php', { id_factura: idFactura }, function (f) {
        if (!f) { pb.error('No se pudo cargar la venta.'); return; }
        anulacionIdFactura = idFactura;
        $('#an_numero').text('#' + (f.numero_factura || idFactura));
        $('#an_motivo').val('');
        const $lineas = $('#an_lineas').empty();
        let hayLineas = false;
        f.productos.forEach(function (p) {
            const restante = p.cantidad - p.cantidad_devuelta;
            if (!Number(p.afecto_stock) || restante <= 0) return;
            hayLineas = true;
            const nombre = 'an_destino_' + p.id_movimiento;
            $lineas.append(`
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 border rounded px-2 py-2">
                    <div class="small"><strong>${pb.escaparHtml(p.nombre_producto)}</strong> <span class="text-muted">x ${restante}</span></div>
                    <div class="btn-group btn-group-sm" role="group">
                        <input type="radio" class="btn-check" name="${nombre}" id="${nombre}_inv" value="inventario" checked>
                        <label class="btn btn-outline-success" for="${nombre}_inv"><i class="bi bi-box-arrow-in-down"></i> Volvió al inventario</label>
                        <input type="radio" class="btn-check" name="${nombre}" id="${nombre}_desc" value="descartado" data-movimiento="${p.id_movimiento}">
                        <label class="btn btn-outline-danger" for="${nombre}_desc"><i class="bi bi-trash3"></i> Se descartó</label>
                    </div>
                </div>`);
        });
        $('#an_lineas_bloque').toggleClass('d-none', !hayLineas);
        bootstrap.Modal.getOrCreateInstance('#modalAnular').show();
        setTimeout(() => $('#an_motivo').trigger('focus'), 300);
    }).fail(() => pb.error('No se pudo cargar la venta.'));
}

function confirmarAnulacion() {
    const motivo = $('#an_motivo').val().trim();
    if (motivo === '') {
        pb.error('Debes indicar un motivo.');
        $('#an_motivo').trigger('focus');
        return;
    }
    const descartar = $('#an_lineas input[value="descartado"]:checked').map(function () { return Number($(this).data('movimiento')); }).get();
    $('#an_confirmar').prop('disabled', true);
    $.ajax({
        type: 'POST',
        url: '/controlador/anular_venta.php',
        data: { id_factura: anulacionIdFactura, motivo: motivo, descartar: JSON.stringify(descartar), csrf_token: pb.csrfToken() },
        dataType: 'json',
        success: function (r) {
            if (r.ok) {
                const modalAnular = bootstrap.Modal.getInstance(document.getElementById('modalAnular'));
                modalAnular?.hide();
                // Si se confirmó mientras aún corría la animación de apertura, Bootstrap ignora el hide().
                setTimeout(function () { if ($('#modalAnular').hasClass('show')) modalAnular?.hide(); }, 400);
                pb.success(descartar.length > 0 ? 'Venta anulada. Lo descartado quedó como pérdida; el resto volvió al inventario.' : 'Venta anulada. El inventario fue repuesto.');
                filtrarPorFecha();
            } else {
                pb.error(r.error || 'Error al anular la venta.');
            }
        },
        error: function () { pb.error('Error en el proceso.'); },
        complete: function () { $('#an_confirmar').prop('disabled', false); }
    });
}

let facturaActual = null;

// ---- Cambiar el cliente de una venta (corregir una venta que quedó a nombre de otra persona) ----
let busquedaClienteFacturaId = 0;

function abrirCambiarCliente() {
    if (!facturaActual) return;
    $('#cc_cliente_actual').text(facturaActual.cliente || 'sin cliente');
    $('#cc_buscar, #cc_motivo').val('');
    $('#cc_resultados').empty();
    bootstrap.Modal.getOrCreateInstance('#modalCambiarCliente').show();
}

$(document).on('input', '#cc_buscar', pb.debounce(function () {
    const termino = $('#cc_buscar').val().trim();
    const idBusqueda = ++busquedaClienteFacturaId; // una respuesta vieja no debe pisar a la nueva
    const $lista = $('#cc_resultados').empty();
    if (termino === '') return;
    $.ajax({
        type: 'POST', url: '/controlador/buscar_cliente.php', data: { termino: termino }, dataType: 'json',
        success: function (clientes) {
            if (idBusqueda !== busquedaClienteFacturaId) return;
            $lista.empty();
            clientes.forEach(function (c) {
                $('<li class="list-group-item" style="cursor:pointer;"></li>')
                    .text(c.nombre + ' — debe ' + pb.moneda(c.saldo))
                    .on('click', function () { cambiarClienteVenta(c.id_cliente); })
                    .appendTo($lista);
            });
            if (clientes.length === 0) $lista.append('<li class="list-group-item text-muted">No se encontró.</li>');
        },
    });
}, 200));

function cambiarClienteVenta(idCliente) {
    const motivo = $('#cc_motivo').val().trim();
    if (motivo === '') {
        pb.error('Escribe el motivo del cambio.');
        $('#cc_motivo').trigger('focus');
        return;
    }
    $.ajax({
        type: 'POST',
        url: '/controlador/cambiar_cliente_factura.php',
        data: { id_factura: facturaActual.id_factura, cliente_id: idCliente === null ? '' : idCliente, motivo: motivo, csrf_token: pb.csrfToken() },
        dataType: 'json',
        success: function (r) {
            if (r.ok) {
                pb.success('Cliente de la venta actualizado.');
                bootstrap.Modal.getInstance(document.getElementById('modalCambiarCliente'))?.hide();
                abrirFactura(facturaActual.id_factura);
                filtrarPorFecha();
            } else {
                pb.error(r.error || 'No se pudo cambiar el cliente.');
            }
        },
        error: function () { pb.error('Error en el proceso.'); },
    });
}

function abrirFactura(idFactura) {
    $.getJSON('/controlador/factura_detalle.php', { id_factura: idFactura }, function (f) {
        if (!f) { pb.error('Factura no encontrada.'); return; }
        facturaActual = f;
        $('#btnReimprimirComanda').toggleClass('d-none', f.anulada);
        $('#btnEditarFactura').toggleClass('d-none', !(PUEDE_EDITAR && !f.anulada));
        $('#btnCambiarCliente').toggleClass('d-none', !(PUEDE_CAMBIAR_CLIENTE && !f.anulada));

        const detalle = pb.construirDetalleFacturaHtml(f, { puedeAnular: PUEDE_ANULAR });
        $('#modalDetalleTitulo').text(detalle.titulo);
        $('#modalDetalleContenido').html(detalle.html);
        pb.prepararTicketParaImprimir(document.getElementById('modalDetalleContenido'));
        bootstrap.Modal.getOrCreateInstance('#modalDetalle').show();
    }).fail(() => pb.error('Error al cargar los detalles de la factura.'));
}

/**
 * Botón "Factura" del detalle: antes usaba window.print() (diálogo de Windows), distinto del envío
 * automático al vender, que va directo a la impresora configurada — por eso la factura salía bien al
 * cobrar y al reimprimir desde aquí salía distinto o no salía nada. Ahora usa el mismo camino directo,
 * y solo cae al diálogo del sistema si no hay ninguna impresora de recibos configurada.
 */
async function reimprimirFactura() {
    const html = document.getElementById('modalDetalleContenido').innerHTML;
    const impresora = pb.impresoraPara('factura');
    if (!impresora) {
        pb.imprimirConDialogo(html);
        return;
    }
    const r = await pb.imprimirTicketSilencioso(html, impresora, 'factura');
    if (r && r.duplicado) pb.info('Ya se había enviado hace un instante.');
    else if (r && r.ok) pb.success('Factura enviada a "' + impresora + '".');
    else if (r && r.disponible === false) pb.imprimirConDialogo(html);
    else pb.error('No se pudo imprimir: ' + ((r && r.error) || 'error desconocido'));
}

/**
 * Botón "Comanda" del detalle: para cuando la comanda no salió al momento de vender (se trabó, se
 * apagó la impresora, el cajero no la imprimió) y el usuario no quiere tener que recrear la venta
 * para mandarla — la reconstruye a partir de lo que ya quedó guardado en esta factura.
 */
async function reimprimirComandaDetalle() {
    if (!facturaActual) return;
    const html = pb.construirComandaHTML({
        mesa: null, // la mesa no queda asociada a la factura una vez cobrada
        vendedor: facturaActual.vendedor,
        cliente: facturaActual.cliente,
        fecha: `${facturaActual.fecha} ${facturaActual.hora}`,
        items: facturaActual.productos,
    });
    const impresora = pb.impresoraPara('comanda');
    if (!impresora) {
        pb.imprimirConDialogo(html);
        return;
    }
    const r = await pb.imprimirTicketSilencioso(html, impresora, 'comanda');
    if (r && r.duplicado) pb.info('Ya se había enviado hace un instante.');
    else if (r && r.ok) pb.success('Comanda enviada a "' + impresora + '".');
    else if (r && r.disponible === false) pb.imprimirConDialogo(html);
    else pb.error('No se pudo imprimir: ' + ((r && r.error) || 'error desconocido'));
}

async function devolverLinea(idMovimiento, restante) {
    const cantidadStr = await pb.preguntar(`¿Cuánto devolver? (máximo ${restante})`, restante, 'number');
    if (cantidadStr === null) return;
    const cantidad = parseFloat(cantidadStr);
    if (!cantidad || cantidad <= 0 || cantidad > restante + 0.001) {
        pb.error('Cantidad inválida.');
        return;
    }
    const motivo = await pb.preguntar('Motivo de la devolución:');
    if (motivo === null) return;
    if (motivo.trim() === '') {
        pb.error('Debes indicar un motivo.');
        return;
    }

    $.ajax({
        type: 'POST',
        url: '/controlador/devolver_linea.php',
        data: { id_movimiento: idMovimiento, cantidad: cantidad, motivo: motivo.trim(), csrf_token: pb.csrfToken() },
        dataType: 'json',
        success: function (r) {
            if (r.ok) {
                pb.success('Devolución registrada. El inventario fue repuesto.');
                bootstrap.Modal.getInstance(document.getElementById('modalDetalle'))?.hide();
                filtrarPorFecha();
            } else {
                pb.error(r.error || 'Error al procesar la devolución.');
            }
        },
        error: function () { pb.error('Error en el proceso.'); }
    });
}

async function editarFacturaActual() {
    if (!facturaActual) return;

    const nuevoAjusteStr = await pb.preguntar('Nuevo ajuste al total (+/-, ej. -5000 para descuento):', facturaActual.ajuste, 'number');
    if (nuevoAjusteStr === null) return;
    const nuevoAjuste = parseFloat(nuevoAjusteStr);
    if (isNaN(nuevoAjuste)) {
        pb.error('Indica un valor numérico válido.');
        return;
    }

    const motivo = await pb.preguntar('Motivo de la edición:');
    if (motivo === null) return;
    if (motivo.trim() === '') {
        pb.error('Debes indicar un motivo.');
        return;
    }

    $.ajax({
        type: 'POST',
        url: '/controlador/editar_factura.php',
        data: { id_factura: facturaActual.id_factura, ajuste: nuevoAjuste, motivo: motivo.trim(), csrf_token: pb.csrfToken() },
        dataType: 'json',
        success: function (r) {
            if (r.ok) {
                pb.success('Factura actualizada.');
                bootstrap.Modal.getInstance(document.getElementById('modalDetalle'))?.hide();
                filtrarPorFecha();
            } else {
                pb.error(r.error || 'Error al editar la factura.');
            }
        },
        error: function () { pb.error('Error en el proceso.'); }
    });
}
</script>
