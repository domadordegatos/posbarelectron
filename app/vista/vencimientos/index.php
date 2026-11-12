<?php
require_once "../../modelo/bootstrap.php";

Auth::requerirModulo('vencimientos');
$usuarioActual = Auth::requerirPermiso('productos.ver');
$puedeEditar = Auth::tienePermiso('productos.editar');
$tituloPagina = 'Vencimientos';
$paginaActiva = 'vencimientos';

require_once __DIR__ . "/../parciales/head.php";
require_once __DIR__ . "/../parciales/sidebar_inicio.php";
?>

<div class="row g-3">
    <div class="col-12">
        <div class="pb-card">
            <div class="pb-card-body">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2">
                    <h6 class="mb-0"><i class="bi bi-exclamation-diamond"></i> Semaforización de vencimientos</h6>
                    <div class="form-check form-switch mb-0">
                        <input class="form-check-input" type="checkbox" id="soloAtencion" checked onchange="cargarVencimientos()">
                        <label class="form-check-label small" for="soloAtencion">Mostrar solo las que necesitan atención</label>
                    </div>
                </div>
                <p class="text-muted small mb-3">
                    Cada alerta se agrega desde la ficha del producto (en <a href="/vista/registro/index.php">Inventario</a>,
                    con el ícono <i class="bi bi-exclamation-diamond"></i>). Esto es informativo — no separa existencias por
                    lote, solo avisa que algo de ese producto está por vencer para que lo revisen físicamente.
                </p>
                <div class="pb-table-wrap">
                    <table class="table table-sm table-bordered pb-table-compact">
                        <thead>
                            <tr>
                                <th></th>
                                <th>Producto</th>
                                <th>Lote</th>
                                <th>Vence</th>
                                <th class="text-end">Días restantes</th>
                                <th class="text-end">Avisa con</th>
                                <?php if ($puedeEditar): ?><th></th><?php endif; ?>
                            </tr>
                        </thead>
                        <tbody id="tablaVencimientos"></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . "/../parciales/pie.php"; ?>
<script>
const PUEDE_EDITAR_VENCIMIENTOS = <?php echo $puedeEditar ? 'true' : 'false'; ?>;

function badgeEstado(estado) {
    if (estado === 'rojo') return '<span class="badge bg-danger">Vencido</span>';
    if (estado === 'amarillo') return '<span class="badge bg-warning text-dark">Próximo</span>';
    return '<span class="badge bg-success">OK</span>';
}

function cargarVencimientos() {
    const $tabla = $('#tablaVencimientos').empty();
    const soloAtencion = $('#soloAtencion').is(':checked');
    $.getJSON('/controlador/listar_vencimientos.php', function (vencimientos) {
        const lista = soloAtencion ? vencimientos.filter(v => v.estado !== 'verde') : vencimientos;
        if (lista.length === 0) {
            const colspan = PUEDE_EDITAR_VENCIMIENTOS ? 7 : 6;
            $tabla.append(`<tr><td colspan="${colspan}" class="text-center text-muted">${soloAtencion ? 'Nada próximo a vencer.' : 'No hay vencimientos registrados.'}</td></tr>`);
            return;
        }
        lista.forEach(function (v) {
            const esc = pb.escaparHtml;
            const diasTexto = v.dias_restantes < 0 ? `${Math.abs(v.dias_restantes)} días vencido` : `${v.dias_restantes} días`;
            const avisos = v.alertas.map(function (a) {
                const estilo = a.estado === 'rojo' ? 'color:var(--pb-danger);font-weight:600;'
                    : a.estado === 'amarillo' ? 'color:var(--pb-warning);font-weight:600;'
                    : 'color:var(--pb-text-muted);';
                const titulo = a.estado === 'verde' ? 'Todavía no se activa' : 'Esta alerta ya está activa';
                return `<span style="${estilo}" title="${titulo}">${a.dias_antes}</span>`;
            }).join(', ');
            $tabla.append(`<tr>
                <td class="text-center">${badgeEstado(v.estado)}</td>
                <td>${esc(v.nombre_producto)}</td>
                <td>${v.descripcion_lote ? esc(v.descripcion_lote) : '<span class="text-muted">—</span>'}</td>
                <td>${esc(v.fecha_vencimiento)}</td>
                <td class="text-end ${v.dias_restantes < 0 ? 'text-danger' : ''}">${diasTexto}</td>
                <td class="text-end">${avisos} días antes</td>
                ${PUEDE_EDITAR_VENCIMIENTOS ? `<td class="text-end"><button type="button" class="pb-btn-icon pb-btn-icon-delete" onclick="eliminarVencimientoLista(${v.id_vencimiento})" title="Eliminar este vencimiento y todos sus avisos"><i class="bi bi-trash3"></i></button></td>` : ''}
            </tr>`);
        });
    }).fail(() => pb.error('No se pudo cargar la lista de vencimientos.'));
}

async function eliminarVencimientoLista(idVencimiento) {
    const ok = await pb.confirmar('¿Eliminar este vencimiento y todos sus avisos?');
    if (!ok) return;
    $.ajax({
        type: 'POST',
        url: '/controlador/eliminar_vencimiento.php',
        data: { vencimiento_id: idVencimiento, csrf_token: pb.csrfToken() },
        dataType: 'json',
        success: function (r) {
            if (r.ok) { pb.success('Vencimiento eliminado.'); cargarVencimientos(); }
            else pb.error(r.error || 'No se pudo eliminar.');
        },
        error: function () { pb.error('Error en el proceso.'); }
    });
}

cargarVencimientos();
</script>
