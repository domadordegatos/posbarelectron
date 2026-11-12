<?php
require_once "../../modelo/bootstrap.php";
require_once "../../modelo/Licencia.php";

$usuarioActual = Auth::requerirRol('admin');
$tituloPagina = 'Fidelización';
$paginaActiva = 'fidelizacion';
$licencia = Licencia::datos();
$habilitado = !empty($licencia['fidelizacion_habilitada']);

require_once __DIR__ . "/../parciales/head.php";
require_once __DIR__ . "/../parciales/sidebar_inicio.php";
?>
<!-- Solo para previsualizar en este selector cómo se ve cada tipografía de la tarjeta pública (que
     vive en pos.nncoms.com, no aquí). Si el equipo no tiene internet en este momento, el selector
     sigue funcionando normal — simplemente sin la vista previa con la fuente real. -->
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Dancing+Script&family=Caveat&family=Playfair+Display:ital@1&family=Cormorant+Garamond:ital@1&family=Oswald&family=Poppins&display=swap">

<?php if (!$habilitado): ?>
<div class="pb-upsell">
    <p class="mb-1 fw-semibold pb-upsell-titulo"><i class="bi bi-stars"></i> Actualmente no cuentas con esta funcionalidad</p>
    <p class="text-muted small mb-2">
        Con el programa de fidelización, tus clientes se registran una sola vez y suman visitas cada vez
        que vuelven — al llegar a la meta que tú definas (ej. 5 visitas = 10% de descuento) reciben su
        recompensa sola, sin que tengas que llevar cuentas a mano. Fideliza clientes con herramientas a
        la vanguardia.
    </p>
    <p class="small mb-0">
        ¿Quieres activarlo? Llama a soporte:
        <a href="https://nncoms.com" target="_blank" rel="noopener">nncoms.com</a>
        · <a href="mailto:soporte@nncoms.com">soporte@nncoms.com</a>
    </p>
</div>
<?php else: ?>

<div class="row g-3">
    <div class="col-lg-5">
        <div class="pb-card mb-3">
            <div class="pb-card-body">
                <h6 class="mb-2"><i class="bi bi-qr-code"></i> Código QR</h6>
                <p class="text-muted small mb-3">
                    Genera un código de un solo uso para que un cliente sume visita — útil para dejarlo impreso
                    en el mostrador o para casos en los que no puedas mostrar la pantalla.
                </p>
                <button type="button" class="btn btn-success w-100" onclick="pb.generarQrFidelizacion()">
                    <i class="bi bi-qr-code"></i> Generar código QR
                </button>
                <hr>
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" id="fid_qr_auto_venta"
                           <?php echo !empty($configNegocio['fidelizacion_qr_auto_venta']) ? 'checked' : ''; ?>
                           onchange="cambiarQrAutoVenta(this.checked)">
                    <label class="form-check-label" for="fid_qr_auto_venta">
                        Imprimirlo automáticamente en cada venta
                    </label>
                </div>
                <div class="form-text">
                    Con esto activo, cada venta genera su propio código y lo agrega al final del recibo del
                    cliente — nunca a la comanda de cocina. Si el equipo no tiene internet en ese momento, la
                    venta se cobra igual, simplemente ese recibo sale sin código.
                </div>
            </div>
        </div>
        <div class="pb-card mb-3">
            <div class="pb-card-body">
                <h6 class="mb-2"><i class="bi bi-link-45deg"></i> Enlace fijo de tu negocio</h6>
                <p class="text-muted small mb-2">
                    A diferencia del código QR de arriba, este enlace no expira — sirve para que un cliente se
                    registre por primera vez o consulte sus puntos cuando quiera. Ideal para imprimirlo en un
                    recibo o un afiche fijo del local.
                </p>
                <div class="input-group input-group-sm mb-2">
                    <input type="text" class="form-control" id="fid_enlace_fijo" readonly value="Cargando…">
                    <button type="button" class="btn btn-outline-secondary" onclick="copiarEnlaceFijoFidelizacion()"><i class="bi bi-clipboard"></i></button>
                </div>
                <button type="button" class="btn btn-outline-success btn-sm w-100" id="btnVerQrEnlaceFijo" onclick="verQrEnlaceFijoFidelizacion()" disabled>
                    <i class="bi bi-qr-code"></i> Ver / imprimir como QR
                </button>
            </div>
        </div>
        <div class="pb-card">
            <div class="pb-card-body">
                <h6 class="mb-3"><i class="bi bi-palette"></i> Marca del programa</h6>
                <div class="mb-2">
                    <label for="fid_nombre_programa" class="form-label">Nombre del programa</label>
                    <input type="text" class="form-control" id="fid_nombre_programa" placeholder="Ej. Club Cervecero">
                </div>
                <div class="mb-2">
                    <label for="fid_eslogan" class="form-label">Eslogan (opcional)</label>
                    <input type="text" class="form-control" id="fid_eslogan" placeholder="Ej. Cada visita suma">
                </div>
                <div class="mb-3">
                    <label for="fid_color_principal" class="form-label">Color principal</label>
                    <input type="color" class="form-control form-control-color w-100" id="fid_color_principal" value="#4f46e5">
                </div>
                <div class="mb-3">
                    <label for="fid_fuente" class="form-label">Tipografía de la tarjeta</label>
                    <select class="form-select" id="fid_fuente"></select>
                    <div class="form-text">Se aplica al nombre del programa y al eslogan en la tarjeta pública del cliente.</div>
                </div>
                <button type="button" class="btn btn-primary btn-sm mb-3" onclick="guardarMarcaFidelizacion()">
                    <i class="bi bi-floppy"></i> Guardar marca
                </button>
                <hr>
                <div class="mb-3">
                    <label class="form-label">Logo</label>
                    <input type="file" class="form-control mb-2" id="fid_logo" accept="image/png,image/jpeg,image/webp" onchange="subirImagenFidelizacion('logo')">
                    <img id="fid_logo_preview" src="" alt="" class="d-none" style="width:60px;height:60px;border-radius:50%;object-fit:cover;">
                    <div class="form-text">Se muestra circular — sube algo cuadrado o ya circular para que no se corte texto ni bordes.</div>
                </div>
                <div class="mb-2">
                    <label class="form-label">Fondo</label>
                    <input type="file" class="form-control mb-2" id="fid_fondo" accept="image/png,image/jpeg,image/webp" onchange="subirImagenFidelizacion('fondo')">
                    <img id="fid_fondo_preview" src="" alt="" class="d-none" style="max-height:60px;">
                    <div class="form-text">Se usa de fondo a pantalla completa en un celular — sube una foto vertical, idealmente 1080×1920px.</div>
                </div>
                <div class="form-text mb-3">El logo y el fondo se ven en la página que el cliente abre al escanear el QR — no en esta app.</div>
                <hr>
                <div class="mb-3">
                    <label class="form-label">Ícono del sello (opcional)</label>
                    <input type="file" class="form-control mb-2" id="fid_sello" accept="image/png,image/webp" onchange="subirImagenFidelizacion('sello')">
                    <img id="fid_sello_preview" src="" alt="" class="d-none" style="width:50px;height:50px;object-fit:contain;">
                    <div class="form-text">Reemplaza la bolita genérica de la tarjeta de sellos (ej. un cono de helado). PNG o WEBP con transparencia, máx. 5MB. Si no subes uno, se usa el círculo simple de siempre.</div>
                </div>
                <div class="mb-2">
                    <label class="form-label">Ícono de check (opcional)</label>
                    <input type="file" class="form-control mb-2" id="fid_check" accept="image/png,image/webp" onchange="subirImagenFidelizacion('check')">
                    <img id="fid_check_preview" src="" alt="" class="d-none" style="width:40px;height:40px;object-fit:contain;">
                    <div class="form-text">Insignia que marca una visita ya cumplida. Si no subes uno, se usa un chulo verde por defecto.</div>
                </div>
            </div>
        </div>

        <div class="pb-card mt-3">
            <div class="pb-card-body">
                <h6 class="mb-3" id="tituloFormNivel"><i class="bi bi-award"></i> Nuevo nivel</h6>
                <form id="formNivel">
                    <input type="hidden" id="nivel_id" value="">
                    <div class="mb-2">
                        <label for="nivel_visitas" class="form-label">Visitas requeridas</label>
                        <input type="number" class="form-control" id="nivel_visitas" min="1" placeholder="Ej. 5" required>
                    </div>
                    <div class="mb-2">
                        <label for="nivel_tipo" class="form-label">Tipo de recompensa</label>
                        <select class="form-select" id="nivel_tipo" onchange="ajustarPlaceholderValorNivel()">
                            <option value="descuento_pct">% de descuento</option>
                            <option value="descuento_fijo">Descuento de valor fijo</option>
                            <option value="producto_gratis">Producto gratis</option>
                            <option value="texto_libre">Otro (texto libre)</option>
                        </select>
                    </div>
                    <div class="mb-2">
                        <label for="nivel_valor" class="form-label">Valor</label>
                        <input type="number" class="form-control" id="nivel_valor" step="0.01" placeholder="Ej. 10">
                    </div>
                    <div class="mb-3">
                        <label for="nivel_descripcion" class="form-label">Descripción para el cliente</label>
                        <input type="text" class="form-control" id="nivel_descripcion" placeholder="Ej. 10% de descuento en tu próxima compra">
                    </div>
                    <div class="d-grid gap-2">
                        <button type="button" class="btn btn-primary" id="btnGuardarNivel" onclick="crearNivel()">
                            <i class="bi bi-floppy"></i> Guardar nivel
                        </button>
                        <button type="button" class="btn btn-secondary d-none" id="btnActualizarNivel" onclick="actualizarNivel()">
                            <i class="bi bi-arrow-repeat"></i> Actualizar nivel
                        </button>
                        <button type="button" class="btn btn-link d-none" id="btnCancelarEdicionNivel" onclick="cancelarEdicionNivel()">Cancelar edición</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-7">
        <div class="pb-card">
            <div class="pb-card-body">
                <h6 class="mb-3">Niveles configurados</h6>
                <div class="table-responsive">
                    <table class="table table-sm table-hover align-middle">
                        <thead>
                            <tr>
                                <th>Visitas</th>
                                <th>Recompensa</th>
                                <th>Descripción</th>
                                <th class="text-center">Estado</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody id="tablaNivelesBody">
                            <tr><td colspan="5" class="text-center text-muted">Cargando…</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . "/../parciales/modal_qr_fidelizacion.php"; ?>
<?php endif; ?>

<?php require_once __DIR__ . "/../parciales/pie.php"; ?>
<?php if ($habilitado): ?>
<script>
const PB_LICENCIA_URL_BASE = <?php echo json_encode(rtrim(Licencia::URL_BASE, '/')); ?>;
// Las imágenes que sube este formulario (logo/fondo/sello/check) viven en
// pos.nncoms.com — el servidor devuelve su ruta como algo relativo (ej.
// "/fidelizacion_assets/xxx.webp"), pensado para cargarse ahí mismo. Esta
// pantalla corre sobre el servidor LOCAL (http://127.0.0.1:puerto), así que
// si se usa esa ruta tal cual, el navegador la busca en el sitio equivocado
// y la imagen sale rota — hay que anteponerle el origen real primero.
const PB_FIDELIZACION_ORIGEN = <?php echo json_encode(preg_replace('#^(https?://[^/]+).*$#', '$1', Licencia::URL_BASE)); ?>;

function resolverUrlFidelizacion(url) {
    if (!url) return url;
    return /^https?:\/\//i.test(url) ? url : PB_FIDELIZACION_ORIGEN + url;
}

const ETIQUETAS_TIPO_RECOMPENSA = {
    descuento_pct: 'Descuento %',
    descuento_fijo: 'Descuento fijo',
    producto_gratis: 'Producto gratis',
    texto_libre: 'Otro',
};

$(function () {
    cargarMarcaFidelizacion();
    cargarNiveles();

    $('#formNivel').on('submit', function (e) {
        e.preventDefault();
        if ($('#nivel_id').val()) {
            actualizarNivel();
        } else {
            crearNivel();
        }
    });
});

function cambiarQrAutoVenta(habilitado) {
    $.ajax({
        type: 'POST',
        url: '/controlador/actualizar_fidelizacion_qr_auto.php',
        data: { habilitado: habilitado ? 1 : 0, csrf_token: pb.csrfToken() },
        dataType: 'json',
        success: function (r) {
            if (r.ok) {
                pb.success(habilitado ? 'Ahora cada venta imprime su código QR.' : 'Ya no se imprime el código automáticamente.');
            } else {
                pb.error('No se pudo guardar el cambio.');
                $('#fid_qr_auto_venta').prop('checked', !habilitado);
            }
        },
        error: function () {
            pb.error('No se pudo guardar el cambio.');
            $('#fid_qr_auto_venta').prop('checked', !habilitado);
        }
    });
}

function ajustarPlaceholderValorNivel() {
    const tipo = $('#nivel_tipo').val();
    const placeholders = {
        descuento_pct: 'Ej. 10 (10%)',
        descuento_fijo: 'Ej. 5000',
        producto_gratis: '0 (no aplica)',
        texto_libre: '0 (no aplica)',
    };
    $('#nivel_valor').attr('placeholder', placeholders[tipo] || '');
}

let enlaceFijoFidelizacion = null;

// Solo para la vista previa del selector (qué letra mostrar en cada opción) — la clave que se
// guarda es siempre la que manda el servidor en fuentes_disponibles, nunca esta lista a mano.
// Si el servidor agrega una tipografía nueva que todavía no está aquí, la opción simplemente se
// ve en la letra por defecto hasta que se actualice esta app — no rompe nada.
const FUENTE_CSS_PREVIA = {
    dancing_script: "'Dancing Script', cursive",
    caveat: "'Caveat', cursive",
    playfair: "'Playfair Display', serif",
    cormorant: "'Cormorant Garamond', serif",
    oswald: "'Oswald', sans-serif",
    poppins: "'Poppins', sans-serif",
};

function cargarMarcaFidelizacion() {
    $.getJSON('/controlador/fidelizacion_obtener_marca.php', function (r) {
        if (!r.ok) {
            pb.error(r.error || 'Sin conexión con el servidor de fidelización.');
            $('#fid_enlace_fijo').val('No se pudo cargar.');
            return;
        }
        const marca = r.marca || {};
        $('#fid_nombre_programa').val(marca.nombre_programa || '');
        $('#fid_eslogan').val(marca.eslogan || '');
        $('#fid_color_principal').val(marca.color_principal || '#4f46e5');
        const $fuente = $('#fid_fuente').empty();
        (marca.fuentes_disponibles || []).forEach(function (f) {
            $('<option></option>').val(f.clave).text(f.etiqueta)
                .css('font-family', FUENTE_CSS_PREVIA[f.clave] || 'inherit')
                .appendTo($fuente);
        });
        $fuente.val(marca.fuente || 'dancing_script');
        if (marca.logo_url) $('#fid_logo_preview').attr('src', resolverUrlFidelizacion(marca.logo_url)).removeClass('d-none');
        if (marca.fondo_url) $('#fid_fondo_preview').attr('src', resolverUrlFidelizacion(marca.fondo_url)).removeClass('d-none');
        if (marca.sello_icono_url) $('#fid_sello_preview').attr('src', resolverUrlFidelizacion(marca.sello_icono_url)).removeClass('d-none');
        if (marca.sello_check_url) $('#fid_check_preview').attr('src', resolverUrlFidelizacion(marca.sello_check_url)).removeClass('d-none');

        // El servidor arma la URL definitiva (formato corto, ej.
        // pos.nncoms.com/club/<slug>) — no la construimos nosotros para no
        // desincronizarnos si cambian el formato de nuevo más adelante.
        if (marca.url) {
            enlaceFijoFidelizacion = marca.url;
            $('#fid_enlace_fijo').val(enlaceFijoFidelizacion);
            $('#btnVerQrEnlaceFijo').prop('disabled', false);
        } else if (marca.slug) {
            enlaceFijoFidelizacion = `${PB_LICENCIA_URL_BASE}/fidelizacion_mi_cuenta.php?negocio=${marca.slug}`;
            $('#fid_enlace_fijo').val(enlaceFijoFidelizacion);
            $('#btnVerQrEnlaceFijo').prop('disabled', false);
        } else {
            $('#fid_enlace_fijo').val('Aún no configurado — contacta a soporte.');
        }
    }).fail(() => {
        pb.error('Sin conexión con el servidor de fidelización.');
        $('#fid_enlace_fijo').val('Sin conexión.');
    });
}

function copiarEnlaceFijoFidelizacion() {
    if (!enlaceFijoFidelizacion) return;
    navigator.clipboard.writeText(enlaceFijoFidelizacion)
        .then(() => pb.success('Enlace copiado.'))
        .catch(() => pb.error('No se pudo copiar — selecciónalo a mano.'));
}

function verQrEnlaceFijoFidelizacion() {
    if (!enlaceFijoFidelizacion) return;
    pb.mostrarQrUrl(enlaceFijoFidelizacion, 'Enlace fijo — no expira.');
}

function guardarMarcaFidelizacion() {
    const nombre = $('#fid_nombre_programa').val().trim();
    if (!nombre) {
        pb.error('Indica el nombre del programa.');
        return;
    }
    $.ajax({
        type: 'POST',
        url: '/controlador/fidelizacion_guardar_marca.php',
        data: {
            nombre_programa: nombre,
            eslogan: $('#fid_eslogan').val().trim(),
            color_principal: $('#fid_color_principal').val(),
            fuente: $('#fid_fuente').val() || '',
            csrf_token: pb.csrfToken(),
        },
        dataType: 'json',
        success: function (r) {
            if (r.ok) {
                pb.success('Marca guardada.');
            } else {
                pb.error(r.error || 'No se pudo guardar la marca.');
            }
        },
        error: function () { pb.error('Sin conexión con el servidor de fidelización.'); }
    });
}

function subirImagenFidelizacion(tipo) {
    const input = document.getElementById('fid_' + tipo);
    const archivo = input.files[0];
    if (!archivo) return;

    const datos = new FormData();
    datos.append('tipo', tipo);
    datos.append('imagen', archivo);
    datos.append('csrf_token', pb.csrfToken());

    $.ajax({
        type: 'POST',
        url: '/controlador/fidelizacion_subir_imagen.php',
        data: datos,
        processData: false,
        contentType: false,
        dataType: 'json',
        success: function (r) {
            if (r.ok) {
                pb.success('Imagen subida.');
                $('#fid_' + tipo + '_preview').attr('src', resolverUrlFidelizacion(r.url)).removeClass('d-none');
            } else {
                pb.error(r.error || 'No se pudo subir la imagen.');
            }
        },
        error: function () { pb.error('Sin conexión con el servidor de fidelización.'); }
    });
}

function cargarNiveles() {
    $.getJSON('/controlador/fidelizacion_listar_niveles.php', function (r) {
        const $body = $('#tablaNivelesBody').empty();
        if (!r.ok) {
            $body.append(`<tr><td colspan="5" class="text-center text-muted">${r.error || 'Sin conexión con el servidor de fidelización.'}</td></tr>`);
            return;
        }
        if (r.niveles.length === 0) {
            $body.append('<tr><td colspan="5" class="text-center text-muted">No hay niveles configurados todavía.</td></tr>');
            return;
        }
        r.niveles.forEach(function (n) {
            const valorTexto = n.tipo_recompensa === 'descuento_pct' ? `${n.valor}%`
                : n.tipo_recompensa === 'descuento_fijo' ? pb.moneda(n.valor)
                : (ETIQUETAS_TIPO_RECOMPENSA[n.tipo_recompensa] || n.tipo_recompensa);
            $body.append(`<tr class="${n.activo == 0 ? 'pb-fila-inactiva' : ''}">
                <td>${n.visitas_requeridas}</td>
                <td>${valorTexto}</td>
                <td>${n.descripcion || '—'}</td>
                <td class="text-center">${pb.pill(n.activo == 1)}</td>
                <td class="text-end">
                    ${n.activo == 1
                        ? `<button class="pb-btn-icon pb-btn-icon-warn" onclick="cambiarEstadoNivel(${n.id}, false)" title="Desactivar"><i class="bi bi-toggle-on"></i></button>`
                        : `<button class="pb-btn-icon pb-btn-icon-edit" onclick="cambiarEstadoNivel(${n.id}, true)" title="Activar"><i class="bi bi-toggle-off"></i></button>`}
                    <button class="pb-btn-icon pb-btn-icon-edit" onclick='cargarNivelParaEditar(${JSON.stringify(n)})' title="Editar"><i class="bi bi-pencil"></i></button>
                </td>
            </tr>`);
        });
    }).fail(() => {
        $('#tablaNivelesBody').html('<tr><td colspan="5" class="text-center text-muted">Sin conexión con el servidor de fidelización.</td></tr>');
    });
}

function limpiarFormularioNivel() {
    $('#formNivel')[0].reset();
    $('#nivel_id').val('');
}

function crearNivel() {
    const visitas = $('#nivel_visitas').val();
    if (!visitas) {
        pb.error('Indica las visitas requeridas.');
        return;
    }
    $.ajax({
        type: 'POST',
        url: '/controlador/fidelizacion_guardar_nivel.php',
        data: {
            visitas_requeridas: visitas,
            tipo_recompensa: $('#nivel_tipo').val(),
            valor: $('#nivel_valor').val() || 0,
            descripcion: $('#nivel_descripcion').val(),
            csrf_token: pb.csrfToken(),
        },
        dataType: 'json',
        success: function (r) {
            if (r.ok) {
                pb.success('Nivel creado con éxito.');
                limpiarFormularioNivel();
                cargarNiveles();
            } else {
                pb.error(r.error || 'Error al crear el nivel.');
            }
        },
        error: function () { pb.error('Sin conexión con el servidor de fidelización.'); }
    });
}

function cargarNivelParaEditar(n) {
    $('#nivel_id').val(n.id);
    $('#nivel_visitas').val(n.visitas_requeridas);
    $('#nivel_tipo').val(n.tipo_recompensa);
    $('#nivel_valor').val(n.valor);
    $('#nivel_descripcion').val(n.descripcion || '');
    $('#tituloFormNivel').html('<i class="bi bi-pencil-square"></i> Editar nivel');
    $('#btnGuardarNivel').addClass('d-none');
    $('#btnActualizarNivel, #btnCancelarEdicionNivel').removeClass('d-none');
}

function cancelarEdicionNivel() {
    limpiarFormularioNivel();
    $('#tituloFormNivel').html('<i class="bi bi-award"></i> Nuevo nivel');
    $('#btnGuardarNivel').removeClass('d-none');
    $('#btnActualizarNivel, #btnCancelarEdicionNivel').addClass('d-none');
}

function actualizarNivel() {
    const id = $('#nivel_id').val();
    if (!id) return;
    $.ajax({
        type: 'POST',
        url: '/controlador/fidelizacion_guardar_nivel.php',
        data: {
            id: id,
            visitas_requeridas: $('#nivel_visitas').val(),
            tipo_recompensa: $('#nivel_tipo').val(),
            valor: $('#nivel_valor').val() || 0,
            descripcion: $('#nivel_descripcion').val(),
            csrf_token: pb.csrfToken(),
        },
        dataType: 'json',
        success: function (r) {
            if (r.ok) {
                pb.success('Nivel actualizado con éxito.');
                cancelarEdicionNivel();
                cargarNiveles();
            } else {
                pb.error(r.error || 'Error al actualizar el nivel.');
            }
        },
        error: function () { pb.error('Sin conexión con el servidor de fidelización.'); }
    });
}

function cambiarEstadoNivel(id, activar) {
    $.ajax({
        type: 'POST',
        url: '/controlador/fidelizacion_cambiar_estado_nivel.php',
        data: { id: id, activo: activar ? 1 : 0, csrf_token: pb.csrfToken() },
        dataType: 'json',
        success: function (r) {
            if (r.ok) {
                pb.success(activar ? 'Nivel activado.' : 'Nivel desactivado.');
                cargarNiveles();
            } else {
                pb.error(r.error || 'No se pudo cambiar el estado del nivel.');
            }
        },
        error: function () { pb.error('Sin conexión con el servidor de fidelización.'); }
    });
}
</script>
<?php endif; ?>
