<?php
require_once "../../modelo/bootstrap.php";
require_once "../../modelo/productos.php";
require_once "../../modelo/motivos_ajuste.php";

Auth::requerirModulo('existencias');
$usuarioActual = Auth::requerirPermiso('conteo_inventario.registrar');
$tituloPagina = 'Conteo de inventario';
$paginaActiva = 'conteo_inventario';

$categorias = (new productos())->listarCategorias();
$motivos = (new motivos_ajuste())->listarActivos();

require_once __DIR__ . "/../parciales/head.php";
require_once __DIR__ . "/../parciales/sidebar_inicio.php";
?>

<div class="row g-3">
    <div class="col-12">
        <div id="bloqueIniciarConteo" class="pb-card">
            <div class="pb-card-body">
                <h6 class="mb-1"><i class="bi bi-clipboard-check"></i> Iniciar conteo de inventario</h6>
                <p class="text-muted small">
                    El día que cuentan físicamente lo que hay, para compararlo con lo que dice el sistema.
                    Al abrir el conteo se toma una "foto" de la existencia actual de cada producto en el
                    alcance elegido — después vas escribiendo lo que realmente cuentas, y al cerrar decides
                    cómo registrar lo que falte o sobre.
                </p>
                <div class="row g-2 align-items-end">
                    <div class="col-sm-5">
                        <label for="conteo_alcance" class="form-label">Alcance</label>
                        <select class="form-select" id="conteo_alcance">
                            <option value="">Todo el catálogo</option>
                            <?php foreach ($categorias as $c): ?>
                            <option value="<?php echo (int) $c['id_categoria']; ?>"><?php echo htmlspecialchars($c['descripcion']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-sm-5">
                        <label for="conteo_nota" class="form-label">Nota (opcional)</label>
                        <input type="text" class="form-control" id="conteo_nota" placeholder="Ej. Conteo mensual de bodega" maxlength="200">
                    </div>
                    <div class="col-sm-2">
                        <button type="button" class="btn btn-primary w-100" onclick="iniciarConteo()">
                            <i class="bi bi-play-fill"></i> Iniciar
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div id="bloqueConteoAbierto" class="pb-card d-none">
            <div class="pb-card-body">
                <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-2">
                    <div>
                        <h6 class="mb-1"><i class="bi bi-clipboard-check"></i> <span id="conteo_titulo"></span></h6>
                        <div class="small text-muted" id="conteo_subtitulo"></div>
                    </div>
                    <button type="button" class="btn btn-success" onclick="abrirModalCerrarConteo()">
                        <i class="bi bi-check2-circle"></i> Cerrar conteo
                    </button>
                </div>
                <div class="small mb-2" id="conteo_progreso"></div>
                <input type="text" class="form-control form-control-sm mb-2" id="conteo_buscar" placeholder="Buscar producto o código…" style="max-width:260px;">
                <div class="pb-table-wrap" style="max-height:480px; overflow-y:auto;">
                    <table class="table table-sm align-middle">
                        <thead>
                            <tr>
                                <th>Producto</th>
                                <th>Categoría</th>
                                <th class="text-end">Sistema</th>
                                <th style="width:120px;">Contado</th>
                                <th class="text-end">Diferencia</th>
                            </tr>
                        </thead>
                        <tbody id="conteo_tabla_lineas"></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12">
        <div class="pb-card mt-1">
            <div class="pb-card-body">
                <h6 class="mb-2"><i class="bi bi-clock-history"></i> Historial de conteos</h6>
                <div class="pb-table-wrap" style="max-height:360px; overflow-y:auto;">
                    <table class="table table-sm align-middle">
                        <thead>
                            <tr>
                                <th>Fecha</th>
                                <th>Alcance</th>
                                <th>Abrió</th>
                                <th class="text-center">Estado</th>
                                <th class="text-end">Líneas</th>
                                <th class="text-end">Con diferencia</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody id="historial_conteos_tabla"></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalCerrarConteo" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title"><i class="bi bi-check2-circle"></i> Cerrar conteo</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="small text-muted" id="cerrar_conteo_resumen"></p>
                <div class="mb-3" id="cerrar_bloque_faltantes">
                    <label class="form-label mb-1">Los faltantes se registran como…</label>
                    <div class="btn-group w-100 mb-2" id="cerrar_tratamiento_faltante">
                        <button type="button" class="btn btn-sm btn-primary" data-tratamiento="perdida" onclick="cambiarTratamientoFaltante('perdida')">Pérdida real</button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" data-tratamiento="ajuste" onclick="cambiarTratamientoFaltante('ajuste')">Ajuste sin pérdida</button>
                    </div>
                    <div class="form-text mb-2">
                        "Pérdida real" afecta tus reportes de pérdidas/ganancias. "Ajuste sin pérdida" solo mueve el
                        inventario (ej. si ya sabes que fue un error de digitación anterior, no una pérdida de verdad).
                    </div>
                    <div id="cerrar_bloque_motivo_faltante" class="d-none">
                        <label for="cerrar_motivo_faltante" class="form-label">Motivo del faltante</label>
                        <select class="form-select form-select-sm" id="cerrar_motivo_faltante">
                            <?php foreach ($motivos as $m): ?>
                            <option value="<?php echo (int) $m['id_motivo']; ?>"><?php echo htmlspecialchars($m['nombre']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="mb-0" id="cerrar_bloque_sobrantes">
                    <label for="cerrar_motivo_sobrante" class="form-label">Motivo de los sobrantes</label>
                    <select class="form-select form-select-sm" id="cerrar_motivo_sobrante">
                        <?php foreach ($motivos as $m): ?>
                        <option value="<?php echo (int) $m['id_motivo']; ?>"><?php echo htmlspecialchars($m['nombre']); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div class="form-text">Los sobrantes siempre quedan como ajuste de entrada (no hay "ganancia" financiera que reconocer de una vez — si se vende, la utilidad sale sola en esa venta).</div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-success" onclick="confirmarCerrarConteo()">
                    <i class="bi bi-check2-circle"></i> Cerrar y aplicar
                </button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalDetalleConteo" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title" id="detalleConteoTitulo">Detalle del conteo</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="pb-table-wrap" style="max-height:400px; overflow-y:auto;">
                    <table class="table table-sm align-middle">
                        <thead>
                            <tr><th>Producto</th><th class="text-end">Sistema</th><th class="text-end">Contado</th><th class="text-end">Diferencia</th></tr>
                        </thead>
                        <tbody id="detalle_conteo_tabla"></tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . "/../parciales/pie.php"; ?>
<script>
let conteoDetalleCache = [];
let tratamientoFaltanteActual = 'perdida';

$(function () {
    cargarEstadoConteo();
    cargarHistorialConteos();
    $('#conteo_buscar').on('input', pb.debounce(filtrarLineasConteo, 150));
});

function iniciarConteo() {
    $.ajax({
        type: 'POST',
        url: '/controlador/iniciar_conteo.php',
        data: {
            categoria_id: $('#conteo_alcance').val(),
            nota: $('#conteo_nota').val(),
            csrf_token: pb.csrfToken(),
        },
        dataType: 'json',
        success: function (r) {
            if (r.ok) {
                pb.success('Conteo iniciado.');
                cargarEstadoConteo();
            } else {
                pb.error(r.error || 'No se pudo iniciar el conteo.');
            }
        },
        error: function () { pb.error('Error en el proceso.'); }
    });
}

function cargarEstadoConteo() {
    $.getJSON('/controlador/obtener_conteo_abierto.php', function (r) {
        if (!r.conteo) {
            $('#bloqueIniciarConteo').removeClass('d-none');
            $('#bloqueConteoAbierto').addClass('d-none');
            return;
        }
        $('#bloqueIniciarConteo').addClass('d-none');
        $('#bloqueConteoAbierto').removeClass('d-none');

        const c = r.conteo;
        $('#conteo_titulo').text('Conteo #' + c.id_conteo + ' — ' + (c.categoria_nombre || 'Todo el catálogo'));
        $('#conteo_subtitulo').text('Abierto ' + c.fecha_inicio + ' por ' + (c.usuario_nombre || '—') + (c.nota ? ' — ' + c.nota : ''));
        $('#bloqueConteoAbierto').data('id-conteo', c.id_conteo);

        conteoDetalleCache = r.detalle;
        renderizarProgresoConteo(r.resumen);
        renderizarLineasConteo(conteoDetalleCache);
    }).fail(() => pb.error('No se pudo cargar el estado del conteo.'));
}

function renderizarProgresoConteo(resumen) {
    $('#conteo_progreso').html(
        `<strong>${resumen.contadas}</strong> de <strong>${resumen.total}</strong> contados — ` +
        `<span class="${resumen.con_diferencia > 0 ? 'text-warning' : 'text-success'}">${resumen.con_diferencia} con diferencia</span> ` +
        `(<span class="text-danger">${resumen.faltantes} faltan</span>, <span class="text-primary">${resumen.sobrantes} sobran</span>)`
    );
}

function filaLineaConteoHtml(linea) {
    const esc = pb.escaparHtml;
    const sinContar = linea.cantidad_contada === null;
    const diferencia = sinContar ? 0 : (linea.cantidad_contada - linea.cantidad_sistema);
    let claseDif = 'text-muted';
    let textoDif = sinContar ? '—' : '0';
    if (!sinContar && diferencia < 0) { claseDif = 'text-danger fw-semibold'; textoDif = diferencia.toString(); }
    else if (!sinContar && diferencia > 0) { claseDif = 'text-primary fw-semibold'; textoDif = '+' + diferencia; }

    return `
        <tr data-producto-id="${linea.producto_id}" data-busqueda="${esc((linea.nombre_producto + ' ' + (linea.codigo || '')).toLowerCase())}">
            <td>${esc(linea.nombre_producto)}${linea.codigo ? '<br><small class="text-muted">' + esc(linea.codigo) + '</small>' : ''}</td>
            <td>${esc(linea.categoria)}</td>
            <td class="text-end">${linea.cantidad_sistema}</td>
            <td>
                <input type="number" class="form-control form-control-sm text-end" min="0" step="1"
                       value="${sinContar ? '' : linea.cantidad_contada}"
                       onchange="guardarLineaConteo(${linea.producto_id}, this)">
            </td>
            <td class="text-end ${claseDif}">${textoDif}</td>
        </tr>`;
}

function renderizarLineasConteo(lineas) {
    $('#conteo_tabla_lineas').html(lineas.map(filaLineaConteoHtml).join(''));
}

function filtrarLineasConteo() {
    const termino = $('#conteo_buscar').val().trim().toLowerCase();
    $('#conteo_tabla_lineas tr').each(function () {
        const coincide = !termino || $(this).data('busqueda').includes(termino);
        $(this).toggle(coincide);
    });
}

function guardarLineaConteo(idProducto, input) {
    const idConteo = $('#bloqueConteoAbierto').data('id-conteo');
    const valor = $(input).val().trim();
    $.ajax({
        type: 'POST',
        url: '/controlador/guardar_linea_conteo.php',
        data: { conteo_id: idConteo, producto_id: idProducto, cantidad: valor, csrf_token: pb.csrfToken() },
        dataType: 'json',
        success: function (r) {
            if (!r.ok) {
                pb.error(r.error || 'No se pudo guardar.');
                return;
            }
            const linea = conteoDetalleCache.find((l) => l.producto_id === idProducto);
            if (linea) linea.cantidad_contada = valor === '' ? null : parseInt(valor, 10);
            const $fila = $(input).closest('tr');
            $fila.replaceWith(filaLineaConteoHtml(linea));
            filtrarLineasConteo();
            $.getJSON('/controlador/obtener_conteo_abierto.php', (r2) => { if (r2.conteo) renderizarProgresoConteo(r2.resumen); });
        },
        error: function () { pb.error('Error en el proceso.'); }
    });
}

function cambiarTratamientoFaltante(tratamiento) {
    tratamientoFaltanteActual = tratamiento;
    $('#cerrar_tratamiento_faltante button').each(function () {
        const activo = $(this).data('tratamiento') === tratamiento;
        $(this).toggleClass('btn-primary', activo).toggleClass('btn-outline-secondary', !activo);
    });
    $('#cerrar_bloque_motivo_faltante').toggleClass('d-none', tratamiento !== 'ajuste');
}

function abrirModalCerrarConteo() {
    const resumenTexto = $('#conteo_progreso').text();
    $('#cerrar_conteo_resumen').text(resumenTexto || 'Revisa las diferencias antes de cerrar.');
    cambiarTratamientoFaltante('perdida');
    bootstrap.Modal.getOrCreateInstance('#modalCerrarConteo').show();
}

function confirmarCerrarConteo() {
    const idConteo = $('#bloqueConteoAbierto').data('id-conteo');
    $.ajax({
        type: 'POST',
        url: '/controlador/cerrar_conteo.php',
        data: {
            conteo_id: idConteo,
            tratamiento_faltante: tratamientoFaltanteActual,
            id_motivo_faltante: $('#cerrar_motivo_faltante').val(),
            id_motivo_sobrante: $('#cerrar_motivo_sobrante').val(),
            csrf_token: pb.csrfToken(),
        },
        dataType: 'json',
        success: function (r) {
            if (r.ok) {
                pb.success('Conteo cerrado — ' + r.diferencias_aplicadas + ' diferencia(s) aplicada(s).');
                bootstrap.Modal.getInstance('#modalCerrarConteo')?.hide();
                cargarEstadoConteo();
                cargarHistorialConteos();
            } else {
                pb.error(r.error || 'No se pudo cerrar el conteo.');
            }
        },
        error: function () { pb.error('Error en el proceso.'); }
    });
}

function cargarHistorialConteos() {
    $.getJSON('/controlador/listar_historial_conteos.php', function (conteos) {
        const $tbody = $('#historial_conteos_tabla').empty();
        if (conteos.length === 0) {
            $tbody.append('<tr><td colspan="7" class="text-center text-muted">Sin conteos todavía.</td></tr>');
            return;
        }
        conteos.forEach(function (c) {
            const esc = pb.escaparHtml;
            const estadoBadge = c.estado === 'abierto'
                ? '<span class="badge bg-warning text-dark">Abierto</span>'
                : '<span class="badge bg-success">Cerrado</span>';
            $tbody.append(`<tr>
                <td>${esc(c.fecha_inicio)}</td>
                <td>${esc(c.categoria_nombre || 'Todo el catálogo')}</td>
                <td>${esc(c.usuario_nombre || '—')}</td>
                <td class="text-center">${estadoBadge}</td>
                <td class="text-end">${c.total_lineas}</td>
                <td class="text-end">${c.con_diferencia}</td>
                <td class="text-end"><button class="btn btn-sm btn-outline-info" onclick="verDetalleConteo(${c.id_conteo})"><i class="bi bi-eye"></i></button></td>
            </tr>`);
        });
    }).fail(() => pb.error('No se pudo cargar el historial.'));
}

function verDetalleConteo(idConteo) {
    $.getJSON('/controlador/detalle_conteo.php', { conteo_id: idConteo }, function (r) {
        $('#detalleConteoTitulo').text('Conteo #' + idConteo + ' — detalle');
        const esc = pb.escaparHtml;
        const $tbody = $('#detalle_conteo_tabla').empty();
        r.detalle.forEach(function (l) {
            const sinContar = l.cantidad_contada === null;
            const diferencia = sinContar ? null : (l.cantidad_contada - l.cantidad_sistema);
            let claseDif = 'text-muted';
            let textoDif = sinContar ? 'sin contar' : '0';
            if (diferencia < 0) { claseDif = 'text-danger fw-semibold'; textoDif = diferencia.toString(); }
            else if (diferencia > 0) { claseDif = 'text-primary fw-semibold'; textoDif = '+' + diferencia; }
            $tbody.append(`<tr>
                <td>${esc(l.nombre_producto)}</td>
                <td class="text-end">${l.cantidad_sistema}</td>
                <td class="text-end">${sinContar ? '—' : l.cantidad_contada}</td>
                <td class="text-end ${claseDif}">${textoDif}</td>
            </tr>`);
        });
        bootstrap.Modal.getOrCreateInstance('#modalDetalleConteo').show();
    }).fail(() => pb.error('No se pudo cargar el detalle.'));
}
</script>
