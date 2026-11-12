<?php
require_once "../../modelo/bootstrap.php";
require_once "../../modelo/SuperAdmin.php";
require_once "../../modelo/Licencia.php";

Auth::requerirSuperAdmin();

$configNegocio = Configuracion::obtener();
$licencia = Licencia::datos();
$modulosActivos = Configuracion::modulosActivos();
$tipoNegocio = Configuracion::tipoNegocio();
$intentos = SuperAdmin::historialIntentos(15);
$csrfToken = Csrf::token();

$etiquetasModulo = [
    'paquetes' => ['Unidad / paquete', 'Vender por unidad y por paquete (ej. bandeja de cerveza) con precios distintos.'],
    'costeo' => ['Costo y utilidad', 'Costo unitario por producto (promedio ponderado) y reporte de utilidad.'],
    'permisos_rol' => ['Permisos por rol', 'Permite al negocio configurar qué puede hacer el rol Cajero.'],
    'caja_turnos' => ['Caja / turnos', 'Apertura y cierre de caja con cuadre de efectivo esperado vs. contado.'],
    'clientes_credito' => ['Clientes + crédito', 'Registro de clientes y venta a crédito (fiar) con cupo y abonos.'],
    'proveedores' => ['Proveedores', 'Registro de proveedores y su vínculo con las compras de inventario.'],
    'iva' => ['IVA por producto', 'Tasa de IVA configurable por producto, desglosada en ticket y reportes.'],
    'combos' => ['Combos / kits', 'Productos armados a partir de otros productos.'],
    'mesas_comandas' => ['Mesas / comandas', 'Abrir una mesa, agregar productos, guardarla y cobrarla al final.'],
    'promociones' => ['Promociones', 'Descuentos programados por producto y horario (ej. happy hour) — se aplican solos en la venta.'],
    'existencias' => ['Existencias (inventario)', 'Control de existencias, mínimos y alertas de stock, compras y pérdidas de productos. Apágalo en negocios de solo servicios.'],
    'profesionales' => ['Profesionales y comisiones', 'Asignar quién realizó cada servicio (estilista, mecánico…) y calcular su comisión sobre lo vendido.'],
    'propina' => ['Propina', 'Propina voluntaria al cobrar (sugerida por porcentaje). Útil en bares, restaurantes y peluquerías.'],
    'modificadores' => ['Grupos de modificadores', 'Otros productos elegibles al comprar uno (ej. acompañamientos, accesorios, salsas) con un número incluido gratis y extras que sí se cobran.'],
    'vencimientos' => ['Semaforización de vencimientos', 'Alertas por producto (lote opcional + fecha límite + con cuántos días de anticipación avisar) — verde/amarillo/rojo. Útil para perecederos o insumos regulados.'],
];

$presets = json_encode(Configuracion::PRESETS_TIPO_NEGOCIO);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
    <title>Panel del proveedor</title>
    <link rel="stylesheet" href="/assets/vendor/bootstrap/css/bootstrap.min.css">
    <link rel="stylesheet" href="/assets/vendor/icons/bootstrap-icons.css">
    <link rel="stylesheet" href="/assets/css/app.css<?php echo '?v=' . @filemtime(__DIR__ . '/../../assets/css/app.css'); ?>">
    <style>
        :root { --pb-primary: #111827; --pb-primary-dark: #000000; }
        body { background: #f3f4f6; }
        .pb-top-superadmin { background: #111827; color: #fff; }
    </style>
</head>
<body>
    <nav class="pb-top-superadmin px-3 py-2 d-flex justify-content-between align-items-center mb-4">
        <span class="d-flex align-items-center gap-2">
            <img src="/assets/img/nenesoft-logo.png" alt="" style="width:28px;height:28px;border-radius:50%;object-fit:cover;">
            Panel del proveedor — <?php echo htmlspecialchars($configNegocio['nombre_negocio'], ENT_QUOTES, 'UTF-8'); ?>
        </span>
        <a href="/controlador/superadmin_logout.php" class="btn btn-sm btn-outline-light">
            <i class="bi bi-box-arrow-right"></i> Salir
        </a>
    </nav>

    <div class="container" style="max-width:900px;">
        <div class="pb-card mb-3">
            <div class="pb-card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="mb-0"><i class="bi bi-key"></i> Licencia de esta instalación</h6>
                    <button type="button" class="btn btn-sm btn-outline-dark" id="btnVerificarLicencia" onclick="verificarLicenciaAhora()">
                        <i class="bi bi-arrow-clockwise"></i> Verificar ahora
                    </button>
                </div>
                <div id="avisoVerificarLicencia" class="alert py-2 px-3 d-none mb-3"></div>
                <?php if (empty($licencia['token'])): ?>
                    <div class="alert alert-warning py-2 px-3 mb-0">
                        Esta instalación todavía no está activada — no hay ninguna licencia asociada.
                    </div>
                <?php else: ?>
                    <?php if (Licencia::debeBloquearPorRevocacion()): ?>
                        <div class="alert alert-danger py-2 px-3 mb-3">
                            <i class="bi bi-x-octagon"></i>
                            <strong>Esta licencia está revocada/suspendida.</strong>
                            <?php echo htmlspecialchars(Licencia::motivoRevocacion(), ENT_QUOTES, 'UTF-8'); ?>
                            El negocio no puede usar el sistema hasta que reactives esta clave desde tu
                            servidor, o le des una clave nueva abajo.
                        </div>
                    <?php endif; ?>
                    <div class="row g-2 small">
                        <div class="col-md-6">
                            <strong>Negocio (según el servidor):</strong>
                            <?php echo htmlspecialchars($licencia['nombre_negocio'] ?? '—', ENT_QUOTES, 'UTF-8'); ?>
                        </div>
                        <div class="col-md-6">
                            <strong>Activada el:</strong>
                            <?php echo htmlspecialchars($licencia['activada_en'] ?? '—', ENT_QUOTES, 'UTF-8'); ?>
                        </div>
                        <div class="col-md-6">
                            <strong>Identificador de este equipo:</strong>
                            <code><?php echo htmlspecialchars($licencia['install_id'] ?? '—', ENT_QUOTES, 'UTF-8'); ?></code>
                        </div>
                        <div class="col-md-6">
                            <strong>Último contacto exitoso con el servidor:</strong>
                            <?php echo htmlspecialchars($licencia['ultima_conexion_exitosa'] ?? 'nunca (o sin internet desde que se activó)', ENT_QUOTES, 'UTF-8'); ?>
                        </div>
                        <div class="col-md-6">
                            <strong>Tipo de licencia:</strong>
                            <?php echo empty($licencia['pagada_hasta'])
                                ? '<span class="text-success">perpetua</span>'
                                : 'suscripción — pagada hasta <strong>' . htmlspecialchars($licencia['pagada_hasta'], ENT_QUOTES, 'UTF-8') . '</strong>'; ?>
                        </div>
                        <div class="col-md-6">
                            <strong>Respaldo automático:</strong>
                            <?php echo !empty($licencia['respaldo_habilitado']) ? '<span class="text-success">habilitado</span>' : '<span class="text-muted">no incluido en esta licencia</span>'; ?>
                        </div>
                        <div class="col-md-6">
                            <strong>Aviso de actualizaciones:</strong>
                            <?php echo !empty($licencia['actualizaciones_habilitadas']) ? '<span class="text-success">habilitado</span>' : '<span class="text-muted">no incluido en esta licencia</span>'; ?>
                        </div>
                    </div>

                    <div id="avisoActualizacionDisponible" class="alert alert-info py-2 px-3 mb-0 mt-3 d-none">
                        <i class="bi bi-arrow-up-circle"></i>
                        Hay una versión nueva disponible (<strong id="versionDisponibleTexto"></strong>).
                        <div class="mt-2">
                            <button type="button" class="btn btn-primary btn-sm" onclick="pb.iniciarActualizacion()">
                                <i class="bi bi-cloud-download"></i> Iniciar actualización
                            </button>
                            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="pb.rechazarActualizacion()">
                                Rechazar por ahora
                            </button>
                        </div>
                    </div>
                    <div id="cajaProgresoActualizacion" class="pb-log-actualizacion d-none mt-3">
                        <div class="pb-log-actualizacion-lineas" id="lineasProgresoActualizacion"></div>
                    </div>

                    <p class="text-muted small mt-3 mb-0">
                        Esta información viene del último contacto de <strong>este equipo</strong> con el
                        servidor de licencias — no se consulta en vivo al abrir este panel (así funciona
                        incluso sin internet). Para el estado real y actualizado de todas tus licencias,
                        usa el panel de administración del servidor.
                    </p>
                <?php endif; ?>
            </div>
        </div>

        <div class="pb-card mb-3">
            <div class="pb-card-body">
                <h6 class="mb-3"><i class="bi bi-arrow-repeat"></i> Cambiar clave de licencia</h6>
                <p class="text-muted small">
                    Úsalo si esta licencia se revocó sin forma de reactivarla, o si quieres pasar este
                    equipo a otra clave (otras condiciones, otro tipo de negocio, etc.). El identificador
                    de este equipo (arriba) se conserva — solo cambia a qué licencia queda asociado.
                    Necesita internet en el momento de activar.
                </p>
                <div id="avisoCambiarLicencia" class="alert py-2 px-3 d-none"></div>
                <div class="row g-2">
                    <div class="col-md-8">
                        <input type="text" class="form-control" id="nuevaClaveLicencia" placeholder="XXXX-XXXX-XXXX-XXXX" autocomplete="off">
                    </div>
                    <div class="col-md-4">
                        <button type="button" class="btn btn-dark w-100" id="btnCambiarLicencia" onclick="cambiarLicenciaAhora()">
                            <i class="bi bi-shield-check"></i> Activar con esta clave
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div class="pb-card mb-3">
            <div class="pb-card-body">
                <h6 class="mb-3"><i class="bi bi-database-down"></i> Restaurar base de datos</h6>
                <p class="text-muted small">
                    Reemplaza toda la información actual de este equipo (ventas, productos, todo) con un
                    archivo <code>.db</code> — un respaldo descargado del servidor, o cualquiera que le pases
                    a mano. Guarda una copia de lo que había antes de reemplazar, por si acaso. La app se
                    reinicia sola al terminar.
                </p>
                <button type="button" class="btn btn-outline-danger" onclick="pb.restaurarBackupDesdeArchivo()">
                    <i class="bi bi-file-earmark-arrow-up"></i> Restaurar desde archivo…
                </button>
            </div>
        </div>

        <?php if (Licencia::gestionRemota()): ?>
        <div class="alert alert-info" id="gestion_remota_aviso">
            <i class="bi bi-cloud-check"></i>
            <strong>Este equipo se administra a distancia desde el servidor.</strong>
            Revisión aplicada: <strong>#<?php echo (int) ($licencia['config_revision_aplicada'] ?? 0); ?></strong>
            <?php if (!empty($licencia['config_aplicada_en'])): ?>
                (<?php echo htmlspecialchars(date('d/m/Y H:i', strtotime((string) $licencia['config_aplicada_en'])), ENT_QUOTES, 'UTF-8'); ?>)
            <?php endif; ?>.
            <div class="small mt-1">
                Lo que cambies aquí sirve de emergencia (por ejemplo, sin internet), pero <strong>se
                sobrescribe</strong> cuando el proveedor publique una revisión nueva desde el servidor.
                Para cambios permanentes, hazlos en el panel del servidor.
            </div>
        </div>
        <?php endif; ?>

        <div class="pb-card mb-3">
            <div class="pb-card-body">
                <h6 class="mb-3"><i class="bi bi-shop"></i> Tipo de negocio</h6>
                <p class="text-muted small">
                    Elegir un tipo aplica una combinación de módulos sugerida — puedes ajustarla
                    módulo por módulo antes de guardar.
                </p>
                <select class="form-select" id="tipo_negocio" style="max-width:320px;">
                    <option value="personalizado" <?php echo $tipoNegocio === 'personalizado' ? 'selected' : ''; ?>>Personalizado</option>
                    <option value="tienda" <?php echo $tipoNegocio === 'tienda' ? 'selected' : ''; ?>>Tienda</option>
                    <option value="supermercado" <?php echo $tipoNegocio === 'supermercado' ? 'selected' : ''; ?>>Supermercado</option>
                    <option value="bar_restaurante" <?php echo $tipoNegocio === 'bar_restaurante' ? 'selected' : ''; ?>>Bar / Restaurante</option>
                    <option value="servicios" <?php echo $tipoNegocio === 'servicios' ? 'selected' : ''; ?>>Servicios (peluquería, estética, consultorio…)</option>
                    <option value="taller" <?php echo $tipoNegocio === 'taller' ? 'selected' : ''; ?>>Taller (mano de obra + repuestos)</option>
                </select>
            </div>
        </div>

        <div class="pb-card mb-3">
            <div class="pb-card-body">
                <h6 class="mb-3"><i class="bi bi-toggles"></i> Módulos activos</h6>
                <div class="row g-2">
                    <?php foreach ($etiquetasModulo as $clave => [$titulo, $descripcion]): ?>
                        <div class="col-md-6">
                            <div class="form-check border rounded p-2">
                                <input class="form-check-input modulo-check" type="checkbox" value="<?php echo $clave; ?>" id="mod_<?php echo $clave; ?>"
                                       <?php echo !empty($modulosActivos[$clave]) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="mod_<?php echo $clave; ?>">
                                    <strong><?php echo htmlspecialchars($titulo, ENT_QUOTES, 'UTF-8'); ?></strong>
                                    <div class="text-muted small"><?php echo htmlspecialchars($descripcion, ENT_QUOTES, 'UTF-8'); ?></div>
                                </label>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <p class="text-muted small mt-3 mb-0">
                    Venta simple, productos, compras/pérdidas, movimientos, anular venta, pago mixto,
                    devoluciones parciales y formato de ticket están siempre disponibles (no dependen
                    de estos módulos).
                </p>
                <button type="button" class="btn btn-dark mt-3" onclick="guardarModulos()">
                    <i class="bi bi-save"></i> Guardar
                </button>
                <span id="guardadoOk" class="text-success small ms-2 d-none"><i class="bi bi-check-circle"></i> Guardado.</span>
            </div>
        </div>

        <div class="pb-card mb-3">
            <div class="pb-card-body">
                <h6 class="mb-3"><i class="bi bi-people"></i> Usuarios del negocio</h6>

                <div id="avisoSinUsuarios" class="alert alert-warning py-2 px-3 d-none">
                    Esta instalación todavía no tiene ningún usuario — el negocio no puede entrar a su propio
                    sistema hasta que crees el primer administrador aquí abajo.
                </div>

                <div class="pb-table-wrap mb-3">
                    <table class="table table-sm table-bordered">
                        <thead><tr><th>Nombre</th><th>Usuario</th><th>Rol</th><th>Estado</th></tr></thead>
                        <tbody id="tablaUsuariosSuperadmin">
                            <tr><td colspan="4" class="text-center text-muted">Cargando…</td></tr>
                        </tbody>
                    </table>
                </div>

                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="border rounded p-2 h-100">
                            <h6 class="small text-uppercase text-muted mb-2">Crear usuario</h6>
                            <div class="mb-2">
                                <input type="text" class="form-control form-control-sm" id="nuevoUsuarioNombre" placeholder="Nombre completo">
                            </div>
                            <div class="mb-2">
                                <input type="text" class="form-control form-control-sm" id="nuevoUsuarioLogin" placeholder="Usuario de acceso" autocomplete="off">
                            </div>
                            <div class="mb-2">
                                <input type="password" class="form-control form-control-sm" id="nuevoUsuarioPassword" placeholder="Contraseña (mín. 6 caracteres)" autocomplete="new-password">
                            </div>
                            <div class="mb-2">
                                <select class="form-select form-select-sm" id="nuevoUsuarioRol">
                                    <option value="admin">Administrador</option>
                                    <option value="cajero">Cajero</option>
                                </select>
                            </div>
                            <button type="button" class="btn btn-sm btn-dark w-100" onclick="crearUsuarioSuperadmin()">
                                <i class="bi bi-person-plus"></i> Crear
                            </button>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="border rounded p-2 h-100">
                            <h6 class="small text-uppercase text-muted mb-2">Restablecer contraseña</h6>
                            <div class="mb-2">
                                <select class="form-select form-select-sm" id="resetUsuarioSelect">
                                    <option value="">Selecciona un usuario…</option>
                                </select>
                            </div>
                            <div class="mb-2">
                                <input type="password" class="form-control form-control-sm" id="resetUsuarioPassword" placeholder="Contraseña nueva (mín. 6 caracteres)" autocomplete="new-password">
                            </div>
                            <button type="button" class="btn btn-sm btn-outline-dark w-100" onclick="restablecerPasswordSuperadmin()">
                                <i class="bi bi-key"></i> Restablecer
                            </button>
                            <p class="text-muted small mt-2 mb-0">Útil si el único administrador del negocio olvidó su contraseña.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="pb-card mb-4">
            <div class="pb-card-body">
                <h6 class="mb-3"><i class="bi bi-clock-history"></i> Últimos accesos al panel</h6>
                <div class="pb-table-wrap">
                    <table class="table table-sm table-bordered">
                        <thead><tr><th>Fecha</th><th>IP</th><th>Usuario</th><th>Resultado</th></tr></thead>
                        <tbody>
                        <?php if (empty($intentos)): ?>
                            <tr><td colspan="4" class="text-center text-muted">Sin registros.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($intentos as $i): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($i['creado_en'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo htmlspecialchars($i['ip'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo htmlspecialchars($i['usuario'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td>
                                    <?php if ((int) $i['exitoso'] === 1): ?>
                                        <span class="badge bg-success">Correcto</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">Fallido</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script src="/assets/vendor/jquery/jquery.min.js"></script>
    <script src="/assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="/assets/js/app.js<?php echo '?v=' . @filemtime(__DIR__ . '/../../assets/js/app.js'); ?>"></script>
    <script>
    pb.autoformatearClaveLicencia('#nuevaClaveLicencia');

    const PRESETS = <?php echo $presets; ?>;

    $('#tipo_negocio').on('change', function () {
        const preset = PRESETS[this.value];
        if (!preset) return;
        Object.keys(preset).forEach(function (modulo) {
            $('#mod_' + modulo).prop('checked', !!preset[modulo]);
        });
    });

    function verificarLicenciaAhora() {
        const $boton = $('#btnVerificarLicencia').prop('disabled', true);
        const $aviso = $('#avisoVerificarLicencia').removeClass('d-none alert-success alert-danger').addClass('alert-secondary').text('Consultando el servidor de licencias…');
        $.ajax({
            type: 'POST',
            url: '/controlador/superadmin_verificar_licencia.php',
            data: { csrf_token: pb.csrfToken() },
            dataType: 'json',
            success: function (r) {
                if (r.ok) {
                    $aviso.removeClass('alert-secondary').addClass('alert-success').text('Contacto exitoso — recargando con la información más reciente…');
                    setTimeout(() => window.location.reload(), 900);
                } else {
                    $boton.prop('disabled', false);
                    $aviso.removeClass('alert-secondary').addClass('alert-danger').text('No se pudo verificar: ' + (r.error || 'sin conexión con el servidor de licencias.'));
                }
            },
            error: function () {
                $boton.prop('disabled', false);
                $aviso.removeClass('alert-secondary').addClass('alert-danger').text('Error de conexión al intentar verificar la licencia.');
            }
        });
    }

    function cambiarLicenciaAhora() {
        const clave = $('#nuevaClaveLicencia').val().trim();
        if (!clave) {
            pb.error('Escribe la clave de licencia nueva.');
            return;
        }
        const $boton = $('#btnCambiarLicencia').prop('disabled', true);
        const $aviso = $('#avisoCambiarLicencia').removeClass('d-none alert-success alert-danger').addClass('alert-secondary').text('Activando con la nueva clave…');
        $.ajax({
            type: 'POST',
            url: '/controlador/superadmin_cambiar_licencia.php',
            data: { clave_licencia: clave, csrf_token: pb.csrfToken() },
            dataType: 'json',
            success: function (r) {
                if (r.ok) {
                    $aviso.removeClass('alert-secondary').addClass('alert-success').text('Activada correctamente — recargando…');
                    setTimeout(() => window.location.reload(), 900);
                } else {
                    $boton.prop('disabled', false);
                    $aviso.removeClass('alert-secondary').addClass('alert-danger').text(r.error || 'No se pudo activar con esa clave.');
                }
            },
            error: function () {
                $boton.prop('disabled', false);
                $aviso.removeClass('alert-secondary').addClass('alert-danger').text('Error de conexión al intentar activar.');
            }
        });
    }

    function guardarModulos() {
        const modulos = $('.modulo-check:checked').map(function () { return this.value; }).get();
        $.ajax({
            type: 'POST',
            url: '/controlador/superadmin_guardar_modulos.php',
            data: {
                tipo_negocio: $('#tipo_negocio').val(),
                modulos: JSON.stringify(modulos),
                csrf_token: pb.csrfToken(),
            },
            dataType: 'json',
            success: function (r) {
                if (r.ok) {
                    $('#guardadoOk').removeClass('d-none');
                    setTimeout(() => $('#guardadoOk').addClass('d-none'), 2500);
                } else {
                    pb.error(r.error || 'Error al guardar.');
                }
            },
            error: function () { pb.error('Error al guardar los módulos.'); }
        });
    }

    cargarUsuariosSuperadmin();

    function cargarUsuariosSuperadmin() {
        $.getJSON('/controlador/superadmin_listar_usuarios.php', function (usuarios) {
            const $tbody = $('#tablaUsuariosSuperadmin').empty();
            const $select = $('#resetUsuarioSelect').empty().append('<option value="">Selecciona un usuario…</option>');

            $('#avisoSinUsuarios').toggleClass('d-none', usuarios.length > 0);

            if (usuarios.length === 0) {
                $tbody.append('<tr><td colspan="4" class="text-center text-muted">Sin usuarios todavía.</td></tr>');
                return;
            }

            usuarios.forEach(function (u) {
                $tbody.append(`<tr class="${u.estado == 0 ? 'pb-fila-inactiva' : ''}">
                    <td>${u.nombre}</td>
                    <td>${u.usuario}</td>
                    <td>${u.rol === 'admin' ? 'Administrador' : 'Cajero'}</td>
                    <td>${pb.pill(u.estado == 1)}</td>
                </tr>`);
                $select.append(`<option value="${u.id_usuario}">${u.nombre} (${u.usuario})</option>`);
            });
        }).fail(() => pb.error('Error al cargar los usuarios.'));
    }

    window.crearUsuarioSuperadmin = function () {
        const nombre = $('#nuevoUsuarioNombre').val().trim();
        const usuario = $('#nuevoUsuarioLogin').val().trim();
        const password = $('#nuevoUsuarioPassword').val();
        const rol = $('#nuevoUsuarioRol').val();

        if (!nombre || !usuario || password.length < 6) {
            pb.error('Completa nombre, usuario y una contraseña de al menos 6 caracteres.');
            return;
        }

        $.ajax({
            type: 'POST',
            url: '/controlador/superadmin_crear_usuario.php',
            data: { nombre: nombre, usuario: usuario, password: password, rol: rol, csrf_token: pb.csrfToken() },
            dataType: 'json',
            success: function (r) {
                if (r.ok) {
                    pb.success('Usuario creado. Ya puede iniciar sesión en el sistema normal.');
                    $('#nuevoUsuarioNombre, #nuevoUsuarioLogin, #nuevoUsuarioPassword').val('');
                    cargarUsuariosSuperadmin();
                } else {
                    pb.error(r.error || 'Error al crear el usuario.');
                }
            },
            error: function () { pb.error('Error al crear el usuario.'); }
        });
    };

    window.restablecerPasswordSuperadmin = function () {
        const idUsuario = $('#resetUsuarioSelect').val();
        const password = $('#resetUsuarioPassword').val();

        if (!idUsuario || password.length < 6) {
            pb.error('Selecciona un usuario e indica una contraseña de al menos 6 caracteres.');
            return;
        }

        $.ajax({
            type: 'POST',
            url: '/controlador/superadmin_restablecer_password.php',
            data: { id_usuario: idUsuario, password: password, csrf_token: pb.csrfToken() },
            dataType: 'json',
            success: function (r) {
                if (r.ok) {
                    pb.success('Contraseña restablecida.');
                    $('#resetUsuarioPassword').val('');
                } else {
                    pb.error(r.error || 'Error al restablecer la contraseña.');
                }
            },
            error: function () { pb.error('Error al restablecer la contraseña.'); }
        });
    };

    // Mismo bloque que en Configuración (ver vista/configuracion/index.php)
    // — el proveedor debería poder ver/iniciar/rechazar la actualización
    // desde acá también, sin depender de que el dueño del negocio entre
    // como administrador a hacerlo por su cuenta.
    function pintarAvisoActualizacion(estado) {
        const disponible = !!(estado && estado.disponible);
        $('#avisoActualizacionDisponible').toggleClass('d-none', !disponible);
        if (disponible) {
            $('#versionDisponibleTexto').text(estado.version || '');
        }
    }
    document.addEventListener('pb:estado-actualizacion', (e) => pintarAvisoActualizacion(e.detail));
    if (window.electronAPI && window.electronAPI.consultarEstadoActualizacion) {
        window.electronAPI.consultarEstadoActualizacion().then(pintarAvisoActualizacion).catch(() => {});
    } else {
        pintarAvisoActualizacion(pb.estadoActualizacion());
    }

    function agregarLineaProgresoActualizacion(evento) {
        $('#cajaProgresoActualizacion').removeClass('d-none');
        const clase = evento.tipo === 'error' ? 'text-danger fw-semibold' : (evento.tipo === 'exito' ? 'text-success' : '');
        const hora = new Date(evento.ts || Date.now()).toLocaleTimeString('es-CO');
        const $lineas = $('#lineasProgresoActualizacion');
        $lineas.append(`<div class="${clase}">[${hora}] ${$('<div>').text(evento.mensaje).html()}</div>`);
        $lineas.scrollTop($lineas[0].scrollHeight);
    }
    if (window.electronAPI && window.electronAPI.onEventoActualizacion) {
        window.electronAPI.onEventoActualizacion(agregarLineaProgresoActualizacion);
    }
    </script>
</body>
</html>
