<?php
require_once "../../modelo/bootstrap.php";

Auth::requerirModulo('proveedores');
$usuarioActual = Auth::requerirPermiso('proveedores.gestionar');
$tituloPagina = 'Proveedores';
$paginaActiva = 'proveedores';

require_once __DIR__ . "/../parciales/head.php";
require_once __DIR__ . "/../parciales/sidebar_inicio.php";
?>

<div class="row g-3">
    <div class="col-lg-4">
        <div class="pb-card">
            <div class="pb-card-body">
                <h6 class="mb-3" id="tituloFormProveedor"><i class="bi bi-truck"></i> Crear proveedor</h6>
                <form id="formProveedor">
                    <input type="hidden" id="id_proveedor" value="">
                    <div class="mb-2">
                        <label for="nombre" class="form-label">Nombre</label>
                        <input type="text" class="form-control" id="nombre" required>
                    </div>
                    <div class="mb-2">
                        <label for="nit" class="form-label">NIT (opcional)</label>
                        <input type="text" class="form-control" id="nit">
                    </div>
                    <div class="mb-2">
                        <label for="telefono" class="form-label">Teléfono (opcional)</label>
                        <input type="text" class="form-control" id="telefono">
                    </div>
                    <div class="mb-2">
                        <label for="direccion" class="form-label">Dirección (opcional)</label>
                        <input type="text" class="form-control" id="direccion">
                    </div>
                    <div class="mb-3" id="fila_estado_proveedor" style="display:none;">
                        <label for="estado" class="form-label">Estado</label>
                        <select class="form-select" id="estado">
                            <option value="1">Activo</option>
                            <option value="0">Inactivo</option>
                        </select>
                    </div>
                    <div class="d-grid gap-2">
                        <button type="button" class="btn btn-primary" id="btnGuardarProveedor" onclick="crearProveedor()">
                            <i class="bi bi-floppy"></i> Guardar proveedor
                        </button>
                        <button type="button" class="btn btn-secondary d-none" id="btnActualizarProveedor" onclick="actualizarProveedor()">
                            <i class="bi bi-arrow-repeat"></i> Actualizar proveedor
                        </button>
                        <button type="button" class="btn btn-link d-none" id="btnCancelarEdicionProveedor" onclick="cancelarEdicionProveedor()">Cancelar edición</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="pb-card">
            <div class="pb-card-body">
                <h6 class="mb-3">Proveedores</h6>
                <div class="table-responsive">
                    <table class="table table-sm table-hover align-middle">
                        <thead>
                            <tr>
                                <th>Nombre</th>
                                <th>NIT</th>
                                <th>Teléfono</th>
                                <th class="text-center">Estado</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody id="tablaProveedoresBody"></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . "/../parciales/pie.php"; ?>
<script>
$(function () {
    cargarProveedores();

    // Sin esto, Enter en el formulario de proveedor recargaba la página en
    // vez de guardar. Despacha a crear o actualizar según el modo activo.
    $('#formProveedor').on('submit', function (e) {
        e.preventDefault();
        if ($('#id_proveedor').val()) {
            actualizarProveedor();
        } else {
            crearProveedor();
        }
    });
});

function cargarProveedores() {
    $.getJSON('/controlador/listar_proveedores.php', function (proveedores) {
        const $body = $('#tablaProveedoresBody').empty();
        if (proveedores.length === 0) {
            $body.append('<tr><td colspan="5" class="text-center text-muted">No hay proveedores registrados.</td></tr>');
            return;
        }
        proveedores.forEach(function (p) {
            $body.append(`<tr class="${p.estado == 0 ? 'pb-fila-inactiva' : ''}">
                <td>${p.nombre}</td>
                <td>${p.nit || '—'}</td>
                <td>${p.telefono || '—'}</td>
                <td class="text-center">${pb.pill(p.estado == 1)}</td>
                <td class="text-end"><button class="pb-btn-icon pb-btn-icon-edit" onclick='cargarProveedorParaEditar(${JSON.stringify(p)})' title="Editar"><i class="bi bi-pencil"></i></button></td>
            </tr>`);
        });
    }).fail(() => pb.error('Error al cargar los proveedores.'));
}

function limpiarFormularioProveedor() {
    $('#formProveedor')[0].reset();
    $('#id_proveedor').val('');
    $('#fila_estado_proveedor').hide();
}

function crearProveedor() {
    if (!$('#nombre').val()) {
        pb.error('El nombre es obligatorio.');
        return;
    }
    $.ajax({
        type: 'POST',
        url: '/controlador/crear_proveedor.php',
        data: {
            nombre: $('#nombre').val(),
            nit: $('#nit').val(),
            telefono: $('#telefono').val(),
            direccion: $('#direccion').val(),
            csrf_token: pb.csrfToken(),
        },
        dataType: 'json',
        success: function (r) {
            if (r === 1) {
                pb.success('Proveedor creado con éxito.');
                limpiarFormularioProveedor();
                cargarProveedores();
            } else {
                pb.error((r && r.error) || 'Error al crear el proveedor.');
            }
        },
        error: function () { pb.error('Error en el proceso.'); }
    });
}

function cargarProveedorParaEditar(p) {
    $('#id_proveedor').val(p.id_proveedor);
    $('#nombre').val(p.nombre);
    $('#nit').val(p.nit || '');
    $('#telefono').val(p.telefono || '');
    $('#direccion').val(p.direccion || '');
    $('#estado').val(p.estado);
    $('#fila_estado_proveedor').show();
    $('#tituloFormProveedor').html('<i class="bi bi-pencil-square"></i> Editar proveedor');
    $('#btnGuardarProveedor').addClass('d-none');
    $('#btnActualizarProveedor, #btnCancelarEdicionProveedor').removeClass('d-none');
}

function cancelarEdicionProveedor() {
    limpiarFormularioProveedor();
    $('#tituloFormProveedor').html('<i class="bi bi-truck"></i> Crear proveedor');
    $('#btnGuardarProveedor').removeClass('d-none');
    $('#btnActualizarProveedor, #btnCancelarEdicionProveedor').addClass('d-none');
}

function actualizarProveedor() {
    const id = $('#id_proveedor').val();
    if (!id) return;
    $.ajax({
        type: 'POST',
        url: '/controlador/actualizar_proveedor.php',
        data: {
            id_proveedor: id,
            nombre: $('#nombre').val(),
            nit: $('#nit').val(),
            telefono: $('#telefono').val(),
            direccion: $('#direccion').val(),
            estado: $('#estado').val(),
            csrf_token: pb.csrfToken(),
        },
        dataType: 'json',
        success: function (r) {
            if (r === 1) {
                pb.success('Proveedor actualizado con éxito.');
                cancelarEdicionProveedor();
                cargarProveedores();
            } else {
                pb.error((r && r.error) || 'Error al actualizar el proveedor.');
            }
        },
        error: function () { pb.error('Error en el proceso.'); }
    });
}
</script>
