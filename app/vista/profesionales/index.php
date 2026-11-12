<?php
require_once "../../modelo/bootstrap.php";

Auth::requerirModulo('profesionales');
$usuarioActual = Auth::requerirRol('admin');
$tituloPagina = 'Profesionales';
$paginaActiva = 'profesionales';

require_once __DIR__ . "/../parciales/head.php";
require_once __DIR__ . "/../parciales/sidebar_inicio.php";
?>

<div class="pb-card">
    <div class="pb-card-body">
        <p class="text-muted small">
            Las personas que realizan los servicios (estilistas, mecánicos, terapeutas…) y el porcentaje de
            comisión de cada una. En Ventas se elige quién realizó cada servicio y la comisión se calcula
            sobre lo cobrado por esa línea (ya con su descuento). Cambiar un porcentaje no altera lo que ya
            se vendió: cada venta guarda el porcentaje que tenía en ese momento.
        </p>
        <div class="table-responsive">
            <table class="table table-sm align-middle" id="tabla_profesionales">
                <thead>
                    <tr>
                        <th>Nombre</th>
                        <th style="width:150px;">Comisión (%)</th>
                        <th class="text-center" style="width:90px;">Activo</th>
                        <th style="width:60px;"></th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
        <div class="border-top pt-3 mt-2">
            <div class="small fw-semibold mb-1">Agregar profesional</div>
            <div class="d-flex gap-2 flex-wrap align-items-end">
                <div>
                    <input type="text" class="form-control" id="prof_nuevo_nombre" placeholder="Ej. Ana Gómez" maxlength="80" style="min-width:240px;">
                </div>
                <div>
                    <div class="input-group" style="width:150px;">
                        <input type="number" class="form-control" id="prof_nuevo_comision" min="0" max="100" step="0.5" value="40">
                        <span class="input-group-text">%</span>
                    </div>
                </div>
                <button type="button" class="btn btn-primary" onclick="agregarProfesional()"><i class="bi bi-plus-lg"></i> Agregar</button>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . "/../parciales/pie.php"; ?>
<script>
$(function () { cargarProfesionales(); });

function cargarProfesionales() {
    $.getJSON('/controlador/listar_profesionales.php', { todos: 1 }, function (lista) {
        const $tbody = $('#tabla_profesionales tbody').empty();
        if (lista.length === 0) {
            $tbody.append('<tr><td colspan="4" class="text-center text-muted">Todavía no hay profesionales. Agrega el primero abajo.</td></tr>');
            return;
        }
        lista.forEach(function (p) {
            const activo = parseInt(p.estado, 10) === 1;
            const $fila = $(`<tr class="${activo ? '' : 'text-muted'}">
                <td><input type="text" class="form-control form-control-sm p-nombre" maxlength="80"></td>
                <td><div class="input-group input-group-sm"><input type="number" class="form-control p-comision" min="0" max="100" step="0.5"><span class="input-group-text">%</span></div></td>
                <td class="text-center"><input type="checkbox" class="form-check-input p-activo"></td>
                <td class="text-end"><button type="button" class="btn btn-sm btn-outline-primary" title="Guardar"><i class="bi bi-check-lg"></i></button></td>
            </tr>`);
            $fila.find('.p-nombre').val(p.nombre);
            $fila.find('.p-comision').val(p.comision_porcentaje);
            $fila.find('.p-activo').prop('checked', activo);
            $fila.find('button').on('click', function () { guardarProfesional(p.id_profesional, $fila); });
            $tbody.append($fila);
        });
    }).fail(() => pb.error('No se pudieron cargar los profesionales.'));
}

function guardarProfesional(id, $fila) {
    $.ajax({
        type: 'POST',
        url: '/controlador/guardar_profesional.php',
        data: {
            id_profesional: id,
            nombre: $fila.find('.p-nombre').val(),
            comision_porcentaje: $fila.find('.p-comision').val(),
            estado: $fila.find('.p-activo').is(':checked') ? 1 : 0,
            csrf_token: pb.csrfToken(),
        },
        dataType: 'json',
        success: function (r) {
            if (r.ok) { pb.success('Profesional guardado.'); cargarProfesionales(); }
            else pb.error(r.error || 'No se pudo guardar.');
        },
        error: function () { pb.error('Error en el proceso.'); },
    });
}

function agregarProfesional() {
    const nombre = $('#prof_nuevo_nombre').val().trim();
    if (nombre === '') {
        pb.error('Escribe el nombre del profesional.');
        return;
    }
    $.ajax({
        type: 'POST',
        url: '/controlador/guardar_profesional.php',
        data: { nombre: nombre, comision_porcentaje: $('#prof_nuevo_comision').val(), csrf_token: pb.csrfToken() },
        dataType: 'json',
        success: function (r) {
            if (r.ok) {
                pb.success('Profesional agregado.');
                $('#prof_nuevo_nombre').val('');
                cargarProfesionales();
            } else {
                pb.error(r.error || 'No se pudo agregar.');
            }
        },
        error: function () { pb.error('Error en el proceso.'); },
    });
}
</script>
