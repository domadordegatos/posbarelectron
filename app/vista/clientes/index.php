<?php
require_once "../../modelo/bootstrap.php";

Auth::requerirModulo('clientes_credito');
$usuarioActual = Auth::requerirPermiso('clientes.gestionar');
$tituloPagina = 'Clientes';
$paginaActiva = 'clientes';

require_once __DIR__ . "/../parciales/head.php";
require_once __DIR__ . "/../parciales/sidebar_inicio.php";
?>

<div class="row g-3">
    <div class="col-lg-4">
        <div class="pb-card">
            <div class="pb-card-body">
                <h6 class="mb-3" id="tituloFormCliente"><i class="bi bi-person-plus"></i> Crear cliente</h6>
                <form id="formCliente">
                    <input type="hidden" id="id_cliente" value="">
                    <div class="mb-2">
                        <label for="nombre" class="form-label">Nombre</label>
                        <input type="text" class="form-control" id="nombre" required>
                    </div>
                    <div class="mb-2">
                        <label for="identificacion" class="form-label">Identificación (opcional)</label>
                        <input type="text" class="form-control" id="identificacion">
                    </div>
                    <div class="mb-2">
                        <label for="telefono" class="form-label">Teléfono (opcional)</label>
                        <input type="text" class="form-control" id="telefono">
                    </div>
                    <div class="mb-2">
                        <label for="direccion" class="form-label">Dirección (opcional)</label>
                        <input type="text" class="form-control" id="direccion">
                    </div>
                    <div class="mb-2">
                        <label for="limite_credito" class="form-label">Límite de crédito</label>
                        <input type="number" class="form-control" id="limite_credito" placeholder="0 = sin crédito" value="0">
                    </div>
                    <div class="mb-3" id="fila_estado_cliente" style="display:none;">
                        <label for="estado" class="form-label">Estado</label>
                        <select class="form-select" id="estado">
                            <option value="1">Activo</option>
                            <option value="0">Inactivo</option>
                        </select>
                    </div>
                    <div class="d-grid gap-2">
                        <button type="button" class="btn btn-primary" id="btnGuardarCliente" onclick="crearCliente()">
                            <i class="bi bi-floppy"></i> Guardar cliente
                        </button>
                        <button type="button" class="btn btn-secondary d-none" id="btnActualizarCliente" onclick="actualizarCliente()">
                            <i class="bi bi-arrow-repeat"></i> Actualizar cliente
                        </button>
                        <button type="button" class="btn btn-link d-none" id="btnCancelarEdicionCliente" onclick="cancelarEdicionCliente()">Cancelar edición</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="pb-card mt-3">
            <div class="pb-card-body">
                <h6 class="mb-3"><i class="bi bi-cash-coin"></i> Registrar abono</h6>
                <label for="abono_cliente_buscar" class="form-label">Cliente</label>
                <input type="text" class="form-control mb-2" id="abono_cliente_buscar" placeholder="Buscar cliente…" autocomplete="off">
                <ul id="abono_cliente_resultados" class="list-group mb-2" style="max-height: 140px; overflow-y: auto;"></ul>
                <div id="abono_cliente_seleccionado" class="small text-muted mb-2"></div>
                <div class="mb-3">
                    <label for="abono_valor" class="form-label">Valor del abono</label>
                    <input type="number" class="form-control" id="abono_valor" placeholder="Ej. 20000">
                </div>
                <button type="button" class="btn btn-outline-primary w-100" onclick="registrarAbono()">
                    <i class="bi bi-save"></i> Registrar abono
                </button>
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="pb-card">
            <div class="pb-card-body">
                <h6 class="mb-3">Clientes</h6>
                <div class="table-responsive">
                    <table class="table table-sm table-hover align-middle">
                        <thead>
                            <tr>
                                <th>Nombre</th>
                                <th>Identificación</th>
                                <th>Teléfono</th>
                                <th class="text-end">Límite</th>
                                <th class="text-end">Debe</th>
                                <th class="text-center">Estado</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody id="tablaClientesBody"></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . "/../parciales/pie.php"; ?>
<script>
let abonoCliente = null;

$(function () {
    cargarClientes();
    $('#abono_cliente_buscar').on('input', pb.debounce(buscarClienteAbono, 200));

    // Sin esto, Enter en el formulario de cliente recargaba la página en
    // vez de guardar. Despacha a crear o actualizar según el modo activo.
    $('#formCliente').on('submit', function (e) {
        e.preventDefault();
        if ($('#id_cliente').val()) {
            actualizarCliente();
        } else {
            crearCliente();
        }
    });
});

function cargarClientes() {
    $.getJSON('/controlador/listar_clientes.php', function (clientes) {
        const $body = $('#tablaClientesBody').empty();
        if (clientes.length === 0) {
            $body.append('<tr><td colspan="7" class="text-center text-muted">No hay clientes registrados.</td></tr>');
            return;
        }
        clientes.forEach(function (c) {
            $body.append(`<tr class="${c.estado == 0 ? 'pb-fila-inactiva' : ''}">
                <td>${c.nombre}</td>
                <td>${c.identificacion || '—'}</td>
                <td>${c.telefono || '—'}</td>
                <td class="text-end">${pb.moneda(c.limite_credito)}</td>
                <td class="text-end">${pb.moneda(c.saldo)}</td>
                <td class="text-center">${pb.pill(c.estado == 1)}</td>
                <td class="text-end"><button class="pb-btn-icon pb-btn-icon-edit" onclick='cargarClienteParaEditar(${JSON.stringify(c)})' title="Editar"><i class="bi bi-pencil"></i></button></td>
            </tr>`);
        });
    }).fail(() => pb.error('Error al cargar los clientes.'));
}

function limpiarFormularioCliente() {
    $('#formCliente')[0].reset();
    $('#id_cliente').val('');
    $('#fila_estado_cliente').hide();
}

function crearCliente() {
    if (!$('#nombre').val()) {
        pb.error('El nombre es obligatorio.');
        return;
    }
    $.ajax({
        type: 'POST',
        url: '/controlador/crear_cliente.php',
        data: {
            nombre: $('#nombre').val(),
            identificacion: $('#identificacion').val(),
            telefono: $('#telefono').val(),
            direccion: $('#direccion').val(),
            limite_credito: $('#limite_credito').val() || 0,
            csrf_token: pb.csrfToken(),
        },
        dataType: 'json',
        success: function (r) {
            if (r === 1) {
                pb.success('Cliente creado con éxito.');
                limpiarFormularioCliente();
                cargarClientes();
            } else {
                pb.error((r && r.error) || 'Error al crear el cliente.');
            }
        },
        error: function () { pb.error('Error en el proceso.'); }
    });
}

function cargarClienteParaEditar(c) {
    $('#id_cliente').val(c.id_cliente);
    $('#nombre').val(c.nombre);
    $('#identificacion').val(c.identificacion || '');
    $('#telefono').val(c.telefono || '');
    $('#direccion').val(c.direccion || '');
    $('#limite_credito').val(c.limite_credito);
    $('#estado').val(c.estado);
    $('#fila_estado_cliente').show();
    $('#tituloFormCliente').html('<i class="bi bi-pencil-square"></i> Editar cliente');
    $('#btnGuardarCliente').addClass('d-none');
    $('#btnActualizarCliente, #btnCancelarEdicionCliente').removeClass('d-none');
}

function cancelarEdicionCliente() {
    limpiarFormularioCliente();
    $('#tituloFormCliente').html('<i class="bi bi-person-plus"></i> Crear cliente');
    $('#btnGuardarCliente').removeClass('d-none');
    $('#btnActualizarCliente, #btnCancelarEdicionCliente').addClass('d-none');
}

function actualizarCliente() {
    const id = $('#id_cliente').val();
    if (!id) return;
    $.ajax({
        type: 'POST',
        url: '/controlador/actualizar_cliente.php',
        data: {
            id_cliente: id,
            nombre: $('#nombre').val(),
            identificacion: $('#identificacion').val(),
            telefono: $('#telefono').val(),
            direccion: $('#direccion').val(),
            limite_credito: $('#limite_credito').val() || 0,
            estado: $('#estado').val(),
            csrf_token: pb.csrfToken(),
        },
        dataType: 'json',
        success: function (r) {
            if (r === 1) {
                pb.success('Cliente actualizado con éxito.');
                cancelarEdicionCliente();
                cargarClientes();
            } else {
                pb.error((r && r.error) || 'Error al actualizar el cliente.');
            }
        },
        error: function () { pb.error('Error en el proceso.'); }
    });
}

// Ver el comentario de buscarClienteVenta() en Ventas: una respuesta vieja no debe mezclarse con la nueva.
let busquedaClienteAbonoId = 0;

function buscarClienteAbono() {
    const termino = $('#abono_cliente_buscar').val().trim();
    const idBusqueda = ++busquedaClienteAbonoId;
    const $resultados = $('#abono_cliente_resultados').empty();
    if (termino.length < 1) return;

    $.ajax({
        type: 'POST',
        url: '/controlador/buscar_cliente.php',
        data: { termino: termino },
        dataType: 'json',
        success: function (clientes) {
            if (idBusqueda !== busquedaClienteAbonoId) return;
            $resultados.empty();
            clientes.forEach(function (c) {
                $('<li class="list-group-item" style="cursor:pointer;"></li>')
                    .text(`${c.nombre} — debe ${pb.moneda(c.saldo)}`)
                    .on('click', function () {
                        abonoCliente = c;
                        busquedaClienteAbonoId++;
                        $('#abono_cliente_buscar').val('');
                        $resultados.empty();
                        $('#abono_cliente_seleccionado').empty()
                            .append('Cliente: ')
                            .append($('<strong></strong>').text(c.nombre))
                            .append(document.createTextNode(` — debe ${pb.moneda(c.saldo)}`));
                    })
                    .appendTo($resultados);
            });
        }
    });
}

function registrarAbono() {
    if (!abonoCliente) {
        pb.error('Busca y selecciona un cliente primero.');
        return;
    }
    const valor = parseFloat($('#abono_valor').val()) || 0;
    if (valor <= 0) {
        pb.error('Indica un valor válido.');
        return;
    }
    $.ajax({
        type: 'POST',
        url: '/controlador/registrar_abono.php',
        data: { cliente_id: abonoCliente.id_cliente, valor: valor, csrf_token: pb.csrfToken() },
        dataType: 'json',
        success: function (r) {
            if (r.ok) {
                pb.success('Abono registrado.');
                abonoCliente = null;
                $('#abono_cliente_seleccionado').empty();
                $('#abono_valor').val('');
                cargarClientes();
            } else {
                pb.error(r.error || 'Error al registrar el abono.');
            }
        },
        error: function () { pb.error('Error en el proceso.'); }
    });
}
</script>
