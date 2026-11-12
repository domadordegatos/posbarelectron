<?php
require_once "../../modelo/bootstrap.php";

$usuarioActual = Auth::requerirRol('admin');
$tituloPagina = 'Auditoría';
$paginaActiva = 'auditoria';

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
        <button class="btn btn-primary" onclick="cargarAuditoria(1)"><i class="bi bi-search"></i> Buscar</button>
        <button class="btn btn-outline-secondary" onclick="limpiarFiltroAuditoria()"><i class="bi bi-x-lg"></i> Ver todo</button>
        <p class="text-muted small mb-0 ms-auto">
            Registro de cambios administrativos (productos, usuarios, permisos, configuración) — solo visible para administradores.
        </p>
    </div>
</div>

<div class="pb-card">
    <div class="pb-card-body">
        <div class="pb-table-wrap">
            <table class="table table-sm table-bordered pb-table-compact">
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Usuario</th>
                        <th>Acción</th>
                        <th>Entidad</th>
                        <th>Detalle</th>
                    </tr>
                </thead>
                <tbody id="tablaAuditoriaBody"></tbody>
            </table>
        </div>
        <nav>
            <ul class="pagination pagination-sm justify-content-center" id="paginacionAuditoria"></ul>
        </nav>
    </div>
</div>

<?php require_once __DIR__ . "/../parciales/pie.php"; ?>
<script>
const ETIQUETAS_ACCION = {
    'producto.crear': 'Producto creado',
    'producto.editar': 'Producto editado',
    'usuario.crear': 'Usuario creado',
    'usuario.editar': 'Usuario editado',
    'permisos.guardar': 'Permisos del rol cajero',
    'configuracion.actualizar': 'Configuración del negocio',
    'turno.ajustar_apertura': 'Corrección del monto de apertura de caja',
    'factura.cambiar_cliente': 'Cambio de cliente de una venta',
    'configuracion_remota': 'Configuración aplicada a distancia (proveedor)',
};

$(function () {
    cargarAuditoria(1);
});

function limpiarFiltroAuditoria() {
    $('#fecha_inicio, #fecha_fin').val('');
    cargarAuditoria(1);
}

function cargarAuditoria(pagina) {
    $.ajax({
        type: 'POST',
        url: '/controlador/listar_auditoria.php',
        data: {
            pagina: pagina,
            fecha_inicio: $('#fecha_inicio').val(),
            fecha_fin: $('#fecha_fin').val(),
        },
        dataType: 'json',
        success: function (respuesta) {
            const $body = $('#tablaAuditoriaBody').empty();
            if (!respuesta || !Array.isArray(respuesta.filas) || respuesta.filas.length === 0) {
                $body.append('<tr><td colspan="5" class="text-center text-muted">No hay eventos registrados.</td></tr>');
                $('#paginacionAuditoria').empty();
                return;
            }
            respuesta.filas.forEach(function (ev) {
                let detalleTexto = '';
                try {
                    const detalle = JSON.parse(ev.detalle || '{}');
                    // Los valores anidados (ej. cliente anterior/nuevo) se muestran como texto legible, no "[object Object]".
                    detalleTexto = Object.entries(detalle).map(([k, v]) => `${k}: ${(v !== null && typeof v === 'object') ? JSON.stringify(v) : v}`).join(' · ');
                } catch (e) {
                    detalleTexto = ev.detalle || '';
                }
                // El detalle puede traer texto escrito por personas (motivos, nombres): se escapa.
                $body.append(`
                    <tr>
                        <td class="text-nowrap">${ev.creado_en}</td>
                        <td>${pb.escaparHtml(ev.usuario || '— (proveedor)')}</td>
                        <td>${pb.escaparHtml(ETIQUETAS_ACCION[ev.accion] || ev.accion)}</td>
                        <td>${pb.escaparHtml(ev.entidad_tipo)}${ev.entidad_id ? ' #' + ev.entidad_id : ''}</td>
                        <td class="small text-muted">${pb.escaparHtml(detalleTexto)}</td>
                    </tr>
                `);
            });
            renderPaginacionAuditoria(Math.ceil(respuesta.total / 30), pagina);
        },
        error: function () {
            pb.error('Error al cargar la auditoría.');
        }
    });
}

function renderPaginacionAuditoria(totalPaginas, actual) {
    const $pag = $('#paginacionAuditoria').empty();
    for (let i = 1; i <= totalPaginas; i++) {
        $pag.append(`<li class="page-item ${i === actual ? 'active' : ''}">
            <a class="page-link" href="#" onclick="cargarAuditoria(${i}); return false;">${i}</a>
        </li>`);
    }
}
</script>
