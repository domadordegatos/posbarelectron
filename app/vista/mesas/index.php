<?php
require_once "../../modelo/bootstrap.php";

Auth::requerirModulo('mesas_comandas');
$usuarioActual = Auth::requerirRol('admin');
$tituloPagina = 'Mesas';
$paginaActiva = 'mesas';

require_once __DIR__ . "/../parciales/head.php";
require_once __DIR__ . "/../parciales/sidebar_inicio.php";
?>

<div class="row g-3">
    <div class="col-lg-4">
        <div class="pb-card">
            <div class="pb-card-body">
                <h6 class="mb-3" id="tituloFormMesa"><i class="bi bi-grid-3x3-gap"></i> Nueva mesa</h6>
                <p class="text-muted small">
                    Configura aquí las mesas de tu negocio una sola vez (ej. "Mesa 1", "Barra 2",
                    "Terraza 3"). En Ventas aparecerán como una cuadrícula fija — nadie tiene que
                    escribir el nombre cada vez que atiende una mesa.
                </p>
                <div class="form-check form-switch mb-3">
                    <input class="form-check-input" type="checkbox" id="mesas_mostrar_cliente"
                           <?php echo Configuracion::mesasMostrarCliente() ? 'checked' : ''; ?>
                           onchange="cambiarMesasMostrarCliente(this.checked)">
                    <label class="form-check-label" for="mesas_mostrar_cliente">
                        Mostrar el nombre del cliente en la cuadrícula de mesas
                    </label>
                    <p class="text-muted small mb-0">
                        Si en Ventas se elige un cliente (del buscador de "Cliente opcional") mientras
                        se atiende una mesa, ese nombre queda asociado a esa mesa. Esta opción decide si
                        se muestra junto al número de mesa o no — no crea ningún campo nuevo, reutiliza
                        el mismo selector de siempre.
                        En la comanda y en la factura el cliente siempre aparece (con su nombre, o
                        "No aplica" si no se eligió); esta opción solo afecta la cuadrícula.
                    </p>
                </div>
                <form id="formMesa">
                    <input type="hidden" id="id_mesa" value="">
                    <div class="mb-2">
                        <label for="nombre_mesa" class="form-label">Nombre</label>
                        <input type="text" class="form-control" id="nombre_mesa" placeholder="Ej. Mesa 1" required>
                    </div>
                    <div class="mb-3" id="fila_activa_mesa" style="display:none;">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="activa_mesa" checked>
                            <label class="form-check-label" for="activa_mesa">Mesa activa (disponible en Ventas)</label>
                        </div>
                    </div>
                    <div class="d-grid gap-2">
                        <button type="button" class="btn btn-primary" id="btnGuardarMesa" onclick="crearMesaCatalogo()">
                            <i class="bi bi-floppy"></i> Guardar mesa
                        </button>
                        <button type="button" class="btn btn-secondary d-none" id="btnActualizarMesa" onclick="actualizarMesaCatalogo()">
                            <i class="bi bi-arrow-repeat"></i> Actualizar mesa
                        </button>
                        <button type="button" class="btn btn-link d-none" id="btnCancelarEdicionMesa" onclick="cancelarEdicionMesa()">Cancelar edición</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="pb-card">
            <div class="pb-card-body">
                <h6 class="mb-3">Mesas configuradas</h6>
                <div class="table-responsive">
                    <table class="table table-sm table-hover align-middle">
                        <thead>
                            <tr>
                                <th>Nombre</th>
                                <th class="text-center">Estado</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody id="tablaMesasBody"></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . "/../parciales/pie.php"; ?>
<script>
$(function () {
    cargarMesasCatalogo();
});

function cambiarMesasMostrarCliente(mostrar) {
    $.ajax({
        type: 'POST',
        url: '/controlador/actualizar_mesas_mostrar_cliente.php',
        data: { mostrar: mostrar ? 1 : 0, csrf_token: pb.csrfToken() },
        dataType: 'json',
        success: function (r) {
            if (r.ok) {
                pb.success(mostrar ? 'Ahora se muestra el nombre del cliente en las mesas.' : 'Ya no se muestra el nombre del cliente en las mesas.');
            } else {
                pb.error('No se pudo guardar el cambio.');
                $('#mesas_mostrar_cliente').prop('checked', !mostrar);
            }
        },
        error: function () {
            pb.error('No se pudo guardar el cambio.');
            $('#mesas_mostrar_cliente').prop('checked', !mostrar);
        }
    });
}

// El formulario no tenía nada escuchando 'submit' — al darle Enter en el
// campo de nombre, el navegador hacía el envío nativo (recarga la página,
// nunca llega a guardar nada). Se despacha a crear o actualizar según cuál
// modo esté activo (ver cargarMesaParaEditar/cancelarEdicionMesa).
$('#formMesa').on('submit', function (e) {
    e.preventDefault();
    if ($('#id_mesa').val()) {
        actualizarMesaCatalogo();
    } else {
        crearMesaCatalogo();
    }
});

function cargarMesasCatalogo() {
    $.getJSON('/controlador/listar_catalogo_mesas.php', function (mesas) {
        const $body = $('#tablaMesasBody').empty();
        if (mesas.length === 0) {
            $body.append('<tr><td colspan="3" class="text-center text-muted">No hay mesas configuradas todavía.</td></tr>');
            return;
        }
        mesas.forEach(function (m) {
            $body.append(`<tr class="${m.activa == 0 ? 'pb-fila-inactiva' : ''}">
                <td>${m.nombre}</td>
                <td class="text-center">${pb.pill(m.activa == 1, 'Activa', 'Inactiva')}</td>
                <td class="text-end"><button class="pb-btn-icon pb-btn-icon-edit" onclick='cargarMesaParaEditar(${JSON.stringify(m)})' title="Editar"><i class="bi bi-pencil"></i></button></td>
            </tr>`);
        });
    }).fail(() => pb.error('Error al cargar las mesas.'));
}

function limpiarFormularioMesa() {
    $('#formMesa')[0].reset();
    $('#id_mesa').val('');
    $('#fila_activa_mesa').hide();
}

function crearMesaCatalogo() {
    if (!$('#nombre_mesa').val()) {
        pb.error('El nombre es obligatorio.');
        return;
    }
    $.ajax({
        type: 'POST',
        url: '/controlador/crear_mesa.php',
        data: { nombre: $('#nombre_mesa').val(), csrf_token: pb.csrfToken() },
        dataType: 'json',
        success: function (r) {
            if (r.ok) {
                pb.success('Mesa creada con éxito.');
                limpiarFormularioMesa();
                cargarMesasCatalogo();
            } else {
                pb.error(r.error || 'Error al crear la mesa.');
            }
        },
        error: function () { pb.error('Error en el proceso.'); }
    });
}

function cargarMesaParaEditar(m) {
    $('#id_mesa').val(m.id_mesa);
    $('#nombre_mesa').val(m.nombre);
    $('#activa_mesa').prop('checked', m.activa == 1);
    $('#fila_activa_mesa').show();
    $('#tituloFormMesa').html('<i class="bi bi-pencil-square"></i> Editar mesa');
    $('#btnGuardarMesa').addClass('d-none');
    $('#btnActualizarMesa, #btnCancelarEdicionMesa').removeClass('d-none');
}

function cancelarEdicionMesa() {
    limpiarFormularioMesa();
    $('#tituloFormMesa').html('<i class="bi bi-grid-3x3-gap"></i> Nueva mesa');
    $('#btnGuardarMesa').removeClass('d-none');
    $('#btnActualizarMesa, #btnCancelarEdicionMesa').addClass('d-none');
}

function actualizarMesaCatalogo() {
    const id = $('#id_mesa').val();
    if (!id) return;
    $.ajax({
        type: 'POST',
        url: '/controlador/actualizar_mesa.php',
        data: {
            id_mesa: id,
            nombre: $('#nombre_mesa').val(),
            activa: $('#activa_mesa').is(':checked') ? 1 : 0,
            csrf_token: pb.csrfToken(),
        },
        dataType: 'json',
        success: function (r) {
            if (r.ok) {
                pb.success('Mesa actualizada con éxito.');
                cancelarEdicionMesa();
                cargarMesasCatalogo();
            } else {
                pb.error(r.error || 'Error al actualizar la mesa.');
            }
        },
        error: function () { pb.error('Error en el proceso.'); }
    });
}
</script>
