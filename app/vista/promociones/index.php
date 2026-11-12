<?php
require_once "../../modelo/bootstrap.php";

Auth::requerirModulo('promociones');
$usuarioActual = Auth::requerirRol('admin');
$tituloPagina = 'Promociones';
$paginaActiva = 'promociones';

require_once __DIR__ . "/../parciales/head.php";
require_once __DIR__ . "/../parciales/sidebar_inicio.php";
?>

<div class="row g-3">
    <div class="col-lg-4">
        <div class="pb-card">
            <div class="pb-card-body">
                <h6 class="mb-3" id="tituloFormPromocion"><i class="bi bi-tags"></i> Nueva promoción</h6>
                <form id="formPromocion">
                    <input type="hidden" id="id_promocion" value="">
                    <div class="mb-2">
                        <label for="promo_nombre" class="form-label">Nombre</label>
                        <input type="text" class="form-control" id="promo_nombre" placeholder="Ej. Happy Hour cerveza" required>
                    </div>
                    <div class="mb-2">
                        <label for="promo_producto_buscar" class="form-label">Producto</label>
                        <input type="text" class="form-control" id="promo_producto_buscar" placeholder="Buscar producto…" autocomplete="off">
                        <ul id="promo_producto_resultados" class="list-group mt-1" style="max-height: 140px; overflow-y: auto;"></ul>
                        <div id="promo_producto_seleccionado" class="small text-muted mt-1"></div>
                    </div>
                    <div class="row g-2 mb-2">
                        <div class="col-6">
                            <label for="promo_tipo" class="form-label">Descuento</label>
                            <select class="form-select" id="promo_tipo">
                                <option value="porcentaje">% Porcentaje</option>
                                <option value="fijo">Valor fijo</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label for="promo_valor" class="form-label">Valor</label>
                            <input type="number" class="form-control" id="promo_valor" min="0.01" step="0.01" placeholder="Ej. 20">
                        </div>
                    </div>
                    <div class="mb-2">
                        <label class="form-label d-block">Días de la semana</label>
                        <div class="d-flex flex-wrap gap-2">
                            <?php $dias = ['0' => 'Dom', '1' => 'Lun', '2' => 'Mar', '3' => 'Mié', '4' => 'Jue', '5' => 'Vie', '6' => 'Sáb']; ?>
                            <?php foreach ($dias as $valor => $etiqueta): ?>
                                <div class="form-check form-check-inline m-0">
                                    <input class="form-check-input promo-dia" type="checkbox" value="<?php echo $valor; ?>" id="promo_dia_<?php echo $valor; ?>">
                                    <label class="form-check-label" for="promo_dia_<?php echo $valor; ?>"><?php echo $etiqueta; ?></label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="form-text">Ninguno marcado = todos los días.</div>
                    </div>
                    <div class="row g-2 mb-2">
                        <div class="col-6">
                            <label for="promo_hora_inicio" class="form-label">Desde</label>
                            <input type="time" class="form-control" id="promo_hora_inicio">
                        </div>
                        <div class="col-6">
                            <label for="promo_hora_fin" class="form-label">Hasta</label>
                            <input type="time" class="form-control" id="promo_hora_fin">
                        </div>
                    </div>
                    <div class="form-text mb-3">Deja ambas horas vacías para que aplique todo el día.</div>
                    <div class="mb-3 d-none" id="fila_estado_promocion">
                        <label for="promo_estado" class="form-label">Estado</label>
                        <select class="form-select" id="promo_estado">
                            <option value="1">Activa</option>
                            <option value="0">Inactiva</option>
                        </select>
                    </div>
                    <div class="d-grid gap-2">
                        <button type="button" class="btn btn-primary" id="btnGuardarPromocion" onclick="crearPromocion()">
                            <i class="bi bi-floppy"></i> Guardar promoción
                        </button>
                        <button type="button" class="btn btn-secondary d-none" id="btnActualizarPromocion" onclick="actualizarPromocion()">
                            <i class="bi bi-arrow-repeat"></i> Actualizar promoción
                        </button>
                        <button type="button" class="btn btn-link d-none" id="btnCancelarEdicionPromocion" onclick="cancelarEdicionPromocion()">Cancelar edición</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="pb-card">
            <div class="pb-card-body">
                <h6 class="mb-3">Promociones configuradas</h6>
                <div class="table-responsive">
                    <table class="table table-sm table-hover align-middle">
                        <thead>
                            <tr>
                                <th>Nombre</th>
                                <th>Producto</th>
                                <th>Descuento</th>
                                <th>Días</th>
                                <th>Horario</th>
                                <th class="text-center">Estado</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody id="tablaPromocionesBody"></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . "/../parciales/pie.php"; ?>
<script>
const ETIQUETAS_DIA = { 0: 'Dom', 1: 'Lun', 2: 'Mar', 3: 'Mié', 4: 'Jue', 5: 'Vie', 6: 'Sáb' };
let promoProducto = null;

const buscadorProductoPromocion = pb.buscadorConTeclado({
    inputSelector: '#promo_producto_buscar',
    resultadosSelector: '#promo_producto_resultados',
    renderItem: (p, indice, resaltado) => `<li class="list-group-item ${resaltado ? 'pb-resultado-activo' : ''}" style="cursor:pointer;" data-indice="${indice}">${p.nombre_producto}</li>`,
    onSeleccionar: function (producto) {
        promoProducto = producto;
        $('#promo_producto_buscar').val('');
        $('#promo_producto_seleccionado').text('Producto: ' + producto.nombre_producto);
    },
});

$(function () {
    cargarPromociones();

    $('#promo_producto_buscar').on('input', pb.debounce(buscarProductoPromocion, 200));

    $('#formPromocion').on('submit', function (e) {
        e.preventDefault();
        if ($('#id_promocion').val()) {
            actualizarPromocion();
        } else {
            crearPromocion();
        }
    });
});

function buscarProductoPromocion() {
    const termino = $('#promo_producto_buscar').val().trim();
    if (termino.length < 1) { buscadorProductoPromocion.limpiar(); return; }

    $.ajax({
        type: 'POST',
        url: '/controlador/buscar_producto.php',
        data: { producto_buscar: termino },
        dataType: 'json',
        success: function (productos) {
            buscadorProductoPromocion.establecerResultados(productos);
        }
    });
}

function diasSeleccionados() {
    return $('.promo-dia:checked').map(function () { return this.value; }).get();
}

function limpiarFormularioPromocion() {
    $('#formPromocion')[0].reset();
    $('#id_promocion').val('');
    $('.promo-dia').prop('checked', false);
    promoProducto = null;
    $('#promo_producto_seleccionado').empty();
    $('#fila_estado_promocion').addClass('d-none');
}

function crearPromocion() {
    if (!$('#promo_nombre').val() || !promoProducto || !$('#promo_valor').val()) {
        pb.error('Completa el nombre, el producto y el valor del descuento.');
        return;
    }
    $.ajax({
        type: 'POST',
        url: '/controlador/crear_promocion.php',
        data: {
            nombre: $('#promo_nombre').val(),
            producto_id: promoProducto.id_producto,
            tipo_descuento: $('#promo_tipo').val(),
            valor: $('#promo_valor').val(),
            dias_semana: diasSeleccionados().join(','),
            hora_inicio: $('#promo_hora_inicio').val(),
            hora_fin: $('#promo_hora_fin').val(),
            csrf_token: pb.csrfToken(),
        },
        dataType: 'json',
        success: function (r) {
            if (r.ok) {
                pb.success('Promoción creada con éxito.');
                limpiarFormularioPromocion();
                cargarPromociones();
            } else {
                pb.error(r.error || 'Error al crear la promoción.');
            }
        },
        error: function () { pb.error('Error en el proceso.'); }
    });
}

function cargarPromociones() {
    $.getJSON('/controlador/listar_promociones.php', function (promociones) {
        const $body = $('#tablaPromocionesBody').empty();
        if (promociones.length === 0) {
            $body.append('<tr><td colspan="7" class="text-center text-muted">No hay promociones configuradas.</td></tr>');
            return;
        }
        promociones.forEach(function (p) {
            const dias = p.dias_semana ? p.dias_semana.split(',').map(d => ETIQUETAS_DIA[d]).join(', ') : 'Todos';
            const horario = (p.hora_inicio && p.hora_fin) ? `${p.hora_inicio} – ${p.hora_fin}` : 'Todo el día';
            const descuento = p.tipo_descuento === 'porcentaje' ? `${p.valor}%` : pb.moneda(p.valor);
            $body.append(`<tr class="${p.estado == 0 ? 'pb-fila-inactiva' : ''}">
                <td>${p.nombre}</td>
                <td>${p.nombre_producto}</td>
                <td>${descuento}</td>
                <td>${dias}</td>
                <td>${horario}</td>
                <td class="text-center">${pb.pill(p.estado == 1, 'Activa', 'Inactiva')}</td>
                <td class="text-end">
                    <button class="pb-btn-icon pb-btn-icon-edit" onclick='cargarPromocionParaEditar(${JSON.stringify(p)})' title="Editar"><i class="bi bi-pencil"></i></button>
                    <button class="pb-btn-icon pb-btn-icon-delete" onclick="eliminarPromocion(${p.id_promocion})" title="Eliminar"><i class="bi bi-trash3"></i></button>
                </td>
            </tr>`);
        });
    }).fail(() => pb.error('Error al cargar las promociones.'));
}

function cargarPromocionParaEditar(p) {
    $('#id_promocion').val(p.id_promocion);
    $('#promo_nombre').val(p.nombre);
    $('#promo_tipo').val(p.tipo_descuento);
    $('#promo_valor').val(p.valor);
    $('.promo-dia').prop('checked', false);
    if (p.dias_semana) {
        p.dias_semana.split(',').forEach(d => $('#promo_dia_' + d).prop('checked', true));
    }
    $('#promo_hora_inicio').val(p.hora_inicio || '');
    $('#promo_hora_fin').val(p.hora_fin || '');
    $('#promo_estado').val(p.estado);
    $('#fila_estado_promocion').removeClass('d-none');
    promoProducto = { id_producto: p.producto_id, nombre_producto: p.nombre_producto };
    $('#promo_producto_seleccionado').text('Producto: ' + p.nombre_producto);
    $('#tituloFormPromocion').html('<i class="bi bi-pencil-square"></i> Editar promoción');
    $('#btnGuardarPromocion').addClass('d-none');
    $('#btnActualizarPromocion, #btnCancelarEdicionPromocion').removeClass('d-none');
}

function cancelarEdicionPromocion() {
    limpiarFormularioPromocion();
    $('#tituloFormPromocion').html('<i class="bi bi-tags"></i> Nueva promoción');
    $('#btnGuardarPromocion').removeClass('d-none');
    $('#btnActualizarPromocion, #btnCancelarEdicionPromocion').addClass('d-none');
}

function actualizarPromocion() {
    const id = $('#id_promocion').val();
    if (!id) return;
    $.ajax({
        type: 'POST',
        url: '/controlador/actualizar_promocion.php',
        data: {
            id_promocion: id,
            nombre: $('#promo_nombre').val(),
            tipo_descuento: $('#promo_tipo').val(),
            valor: $('#promo_valor').val(),
            dias_semana: diasSeleccionados().join(','),
            hora_inicio: $('#promo_hora_inicio').val(),
            hora_fin: $('#promo_hora_fin').val(),
            estado: $('#promo_estado').val(),
            csrf_token: pb.csrfToken(),
        },
        dataType: 'json',
        success: function (r) {
            if (r.ok) {
                pb.success('Promoción actualizada con éxito.');
                cancelarEdicionPromocion();
                cargarPromociones();
            } else {
                pb.error(r.error || 'Error al actualizar la promoción.');
            }
        },
        error: function () { pb.error('Error en el proceso.'); }
    });
}

async function eliminarPromocion(id) {
    if (!await pb.confirmar('¿Eliminar esta promoción?')) return;
    $.ajax({
        type: 'POST',
        url: '/controlador/eliminar_promocion.php',
        data: { id_promocion: id, csrf_token: pb.csrfToken() },
        dataType: 'json',
        success: function (r) {
            if (r.ok) {
                pb.success('Promoción eliminada.');
                cargarPromociones();
            } else {
                pb.error(r.error || 'Error al eliminar la promoción.');
            }
        },
        error: function () { pb.error('Error en el proceso.'); }
    });
}
</script>
