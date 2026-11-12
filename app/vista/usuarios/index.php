<?php
require_once "../../modelo/bootstrap.php";

$usuarioActual = Auth::requerirRol('admin');
$modPermisosRol = Configuracion::moduloActivo('permisos_rol');
$tituloPagina = 'Usuarios';
$paginaActiva = 'usuarios';

require_once __DIR__ . "/../parciales/head.php";
require_once __DIR__ . "/../parciales/sidebar_inicio.php";
?>

<div class="row g-3">
    <div class="col-lg-4">
        <div class="pb-card">
            <div class="pb-card-body">
                <h6 class="mb-3" id="tituloFormUsuario"><i class="bi bi-person-plus"></i> Crear usuario</h6>
                <form id="formUsuario">
                    <input type="hidden" id="id_usuario" value="">
                    <div class="mb-2">
                        <label for="nombre" class="form-label">Nombre completo</label>
                        <input type="text" class="form-control" id="nombre" required>
                    </div>
                    <div class="mb-2">
                        <label for="usuario" class="form-label">Usuario de acceso</label>
                        <input type="text" class="form-control" id="usuario" required autocomplete="off">
                    </div>
                    <div class="mb-2">
                        <label for="password" class="form-label">Contraseña <small class="text-muted" id="passwordHint">(mín. 6 caracteres)</small></label>
                        <input type="password" class="form-control" id="password" autocomplete="new-password">
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label for="rol" class="form-label">Rol</label>
                            <select class="form-select" id="rol">
                                <option value="cajero">Cajero</option>
                                <option value="admin">Administrador</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label for="estado" class="form-label">Estado</label>
                            <select class="form-select" id="estado">
                                <option value="1">Activo</option>
                                <option value="0">Inactivo</option>
                            </select>
                        </div>
                    </div>
                    <div class="d-grid gap-2">
                        <button type="button" class="btn btn-primary" id="btnGuardarUsuario" onclick="crearUsuario()">
                            <i class="bi bi-floppy"></i> Guardar usuario
                        </button>
                        <button type="button" class="btn btn-secondary d-none" id="btnActualizarUsuario" onclick="actualizarUsuario()">
                            <i class="bi bi-arrow-repeat"></i> Actualizar usuario
                        </button>
                        <button type="button" class="btn btn-link d-none" id="btnCancelarEdicionUsuario" onclick="cancelarEdicionUsuario()">Cancelar edición</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="pb-card">
            <div class="pb-card-body">
                <h6 class="mb-3">Usuarios del sistema</h6>
                <div class="table-responsive">
                    <table class="table table-sm table-hover align-middle">
                        <thead>
                            <tr>
                                <th>Nombre</th>
                                <th>Usuario</th>
                                <th>Rol</th>
                                <th class="text-center">Estado</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody id="tablaUsuariosBody"></tbody>
                    </table>
                </div>
            </div>
        </div>

        <?php if ($modPermisosRol): ?>
        <div class="pb-card mt-3">
            <div class="pb-card-body">
                <h6 class="mb-3"><i class="bi bi-shield-check"></i> Permisos del rol Cajero</h6>
                <p class="text-muted small">El rol Administrador siempre tiene acceso completo. Marca aquí qué puede hacer además un usuario con rol Cajero.</p>
                <div id="listaPermisos" class="mb-3"></div>
                <button type="button" class="btn btn-primary" onclick="guardarPermisos()">
                    <i class="bi bi-floppy"></i> Guardar permisos
                </button>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . "/../parciales/pie.php"; ?>
<script>
const MOD_PERMISOS_ROL = <?php echo $modPermisosRol ? 'true' : 'false'; ?>;

$(function () {
    cargarUsuarios();
    if (MOD_PERMISOS_ROL) cargarPermisos();

    // Sin esto, Enter en el formulario de usuario recargaba la página en
    // vez de guardar. Despacha a crear o actualizar según el modo activo.
    $('#formUsuario').on('submit', function (e) {
        e.preventDefault();
        if ($('#id_usuario').val()) {
            actualizarUsuario();
        } else {
            crearUsuario();
        }
    });
});

function cargarPermisos() {
    $.getJSON('/controlador/listar_permisos.php', function (r) {
        const $lista = $('#listaPermisos').empty();
        r.permisos.forEach(function (p) {
            const marcado = r.concedidos_cajero.includes(p.clave) ? 'checked' : '';
            $lista.append(`
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" value="${p.clave}" id="permiso_${p.clave}" ${marcado}>
                    <label class="form-check-label" for="permiso_${p.clave}">${p.descripcion}</label>
                </div>
            `);
        });
    }).fail(() => pb.error('Error al cargar los permisos.'));
}

function guardarPermisos() {
    const claves = [];
    $('#listaPermisos input:checked').each(function () { claves.push(this.value); });

    $.ajax({
        type: 'POST',
        url: '/controlador/guardar_permisos.php',
        data: { claves: JSON.stringify(claves), csrf_token: pb.csrfToken() },
        dataType: 'json',
        success: function (r) {
            if (r.ok) {
                pb.success('Permisos guardados.');
            } else {
                pb.error(r.error || 'Error al guardar los permisos.');
            }
        },
        error: function () { pb.error('Error en el proceso.'); }
    });
}

function cargarUsuarios() {
    $.getJSON('/controlador/listar_usuarios.php', function (usuarios) {
        const $body = $('#tablaUsuariosBody').empty();
        usuarios.forEach(function (u) {
            $body.append(`<tr class="${u.estado == 0 ? 'pb-fila-inactiva' : ''}">
                <td>${u.nombre}</td>
                <td>${u.usuario}</td>
                <td>${u.rol === 'admin' ? 'Administrador' : 'Cajero'}</td>
                <td class="text-center">${pb.pill(u.estado == 1)}</td>
                <td class="text-end"><button class="pb-btn-icon pb-btn-icon-edit" onclick='cargarUsuarioParaEditar(${JSON.stringify(u)})' title="Editar"><i class="bi bi-pencil"></i></button></td>
            </tr>`);
        });
    }).fail(() => pb.error('Error al cargar los usuarios.'));
}

function limpiarFormularioUsuario() {
    $('#formUsuario')[0].reset();
    $('#id_usuario').val('');
}

function crearUsuario() {
    if (!$('#nombre').val() || !$('#usuario').val() || !$('#password').val()) {
        pb.error('Completa nombre, usuario y contraseña.');
        return;
    }
    $.ajax({
        type: 'POST',
        url: '/controlador/crear_usuario.php',
        data: {
            nombre: $('#nombre').val(),
            usuario: $('#usuario').val(),
            password: $('#password').val(),
            rol: $('#rol').val(),
            estado: $('#estado').val(),
            csrf_token: pb.csrfToken(),
        },
        dataType: 'json',
        success: function (r) {
            if (r === 1) {
                pb.success('Usuario creado con éxito.');
                limpiarFormularioUsuario();
                cargarUsuarios();
            } else {
                pb.error((r && r.error) || 'Error al crear el usuario.');
            }
        },
        error: function () { pb.error('Error en el proceso.'); }
    });
}

function cargarUsuarioParaEditar(u) {
    $('#id_usuario').val(u.id_usuario);
    $('#nombre').val(u.nombre);
    $('#usuario').val(u.usuario);
    $('#password').val('');
    $('#passwordHint').text('(dejar en blanco para no cambiarla)');
    $('#rol').val(u.rol);
    $('#estado').val(u.estado);
    $('#tituloFormUsuario').html('<i class="bi bi-pencil-square"></i> Editar usuario');
    $('#btnGuardarUsuario').addClass('d-none');
    $('#btnActualizarUsuario, #btnCancelarEdicionUsuario').removeClass('d-none');
}

function cancelarEdicionUsuario() {
    limpiarFormularioUsuario();
    $('#passwordHint').text('(mín. 6 caracteres)');
    $('#tituloFormUsuario').html('<i class="bi bi-person-plus"></i> Crear usuario');
    $('#btnGuardarUsuario').removeClass('d-none');
    $('#btnActualizarUsuario, #btnCancelarEdicionUsuario').addClass('d-none');
}

function actualizarUsuario() {
    const id = $('#id_usuario').val();
    if (!id) return;
    $.ajax({
        type: 'POST',
        url: '/controlador/actualizar_usuario.php',
        data: {
            id_usuario: id,
            nombre: $('#nombre').val(),
            usuario: $('#usuario').val(),
            password: $('#password').val(),
            rol: $('#rol').val(),
            estado: $('#estado').val(),
            csrf_token: pb.csrfToken(),
        },
        dataType: 'json',
        success: function (r) {
            if (r === 1) {
                pb.success('Usuario actualizado con éxito.');
                cancelarEdicionUsuario();
                cargarUsuarios();
            } else {
                pb.error((r && r.error) || 'Error al actualizar el usuario.');
            }
        },
        error: function () { pb.error('Error en el proceso.'); }
    });
}
</script>
