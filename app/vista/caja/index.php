<?php
require_once "../../modelo/bootstrap.php";
require_once "../../modelo/turnos.php";

Auth::requerirModulo('caja_turnos');
$usuarioActual = Auth::requerirPermiso('caja.gestionar');
$tituloPagina = 'Caja';
$paginaActiva = 'caja';

$esAdminCaja = $usuarioActual['rol'] === 'admin';
$turnosObj = new turnos();
$turnoAbierto = $turnosObj->turnoAbierto();

require_once __DIR__ . "/../parciales/head.php";
require_once __DIR__ . "/../parciales/sidebar_inicio.php";
?>

<div class="row g-3">
    <div class="col-lg-6">
        <?php if (!$turnoAbierto): ?>
        <div class="pb-card">
            <div class="pb-card-body">
                <h6 class="mb-3"><i class="bi bi-cash-stack"></i> Abrir turno</h6>
                <div class="mb-3">
                    <label for="monto_apertura" class="form-label">Monto inicial en caja</label>
                    <input type="number" class="form-control" id="monto_apertura" placeholder="Ej. 100000" value="0">
                </div>
                <div class="form-text mb-2" id="aviso_monto_apertura"></div>
                <button type="button" class="btn btn-primary w-100" onclick="abrirTurno()">
                    <i class="bi bi-unlock"></i> Abrir turno
                </button>
            </div>
        </div>
        <?php else: ?>
        <div class="pb-card">
            <div class="pb-card-body">
                <h6 class="mb-3"><i class="bi bi-cash-stack"></i> Turno abierto</h6>
                <p class="text-muted small mb-3">
                    Abierto por <strong><?php echo htmlspecialchars($turnoAbierto['abierto_por'], ENT_QUOTES, 'UTF-8'); ?></strong>
                    el <?php echo htmlspecialchars($turnoAbierto['fecha_apertura'], ENT_QUOTES, 'UTF-8'); ?> a las
                    <?php echo htmlspecialchars($turnoAbierto['hora_apertura'], ENT_QUOTES, 'UTF-8'); ?>
                </p>
                <div id="resumenTurno" class="mb-3">
                    <div class="d-flex justify-content-between"><span class="text-muted">Monto de apertura <button type="button" class="btn btn-link btn-sm p-0 ms-1 align-baseline" id="btnCorregirApertura" onclick="abrirAjusteApertura()"><i class="bi bi-pencil-square"></i> Corregir</button></span><strong id="rMontoApertura">$0</strong></div>
                    <div class="d-flex justify-content-between"><span class="text-muted">Ventas en efectivo</span><strong id="rVentasEfectivo">$0</strong></div>
                    <div class="d-flex justify-content-between"><span class="text-muted">Ventas otros métodos</span><strong id="rVentasOtros">$0</strong></div>
                    <div class="d-flex justify-content-between"><span class="text-muted">Egresos en efectivo</span><strong id="rEgresos">$0</strong></div>
                    <div class="d-flex justify-content-between border-top pt-2 mt-2"><span>Efectivo esperado</span><strong id="rEsperado">$0</strong></div>
                </div>
                <div id="ajustesApertura" class="small border rounded p-2 mb-3 d-none"></div>
                <div class="mb-3">
                    <label for="monto_contado" class="form-label">Monto contado al cerrar</label>
                    <input type="number" class="form-control" id="monto_contado" placeholder="Ej. 150000">
                </div>
                <div class="mb-3">
                    <label for="observaciones_cierre" class="form-label">Observaciones (opcional)</label>
                    <input type="text" class="form-control" id="observaciones_cierre">
                </div>
                <button type="button" class="btn btn-danger w-100" onclick="cerrarTurno()">
                    <i class="bi bi-lock"></i> Cerrar turno
                </button>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <div class="col-lg-6">
        <div class="pb-card">
            <div class="pb-card-body">
                <h6 class="mb-1">Historial de turnos cerrados</h6>
                <p class="text-muted small mb-2">Si se digitó mal el monto de apertura o el monto contado al cerrar (de hoy o de un día anterior), se corrige con los enlaces de la derecha — siempre con motivo y, si no lo hace un administrador, su autorización. Queda registrado y el cuadre de ese día se recalcula.</p>
                <div class="pb-table-wrap">
                    <table class="table table-sm table-bordered pb-table-compact">
                        <thead>
                            <tr>
                                <th>Apertura</th>
                                <th>Cierre</th>
                                <th class="text-end">Monto inicial</th>
                                <th class="text-end">Esperado</th>
                                <th class="text-end">Contado</th>
                                <th class="text-end">Diferencia</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody id="tablaHistorialTurnos"></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="pb-card mt-3" id="cardHistorialAjustes">
    <div class="pb-card-body">
        <div class="d-flex flex-wrap align-items-end gap-2 mb-2">
            <h6 class="mb-0 me-auto"><i class="bi bi-clock-history"></i> Historial de ajustes de apertura y cierre</h6>
            <div>
                <label for="aj_hist_inicio" class="form-label mb-0 small">Desde</label>
                <input type="date" id="aj_hist_inicio" class="form-control form-control-sm">
            </div>
            <div>
                <label for="aj_hist_fin" class="form-label mb-0 small">Hasta</label>
                <input type="date" id="aj_hist_fin" class="form-control form-control-sm">
            </div>
            <button type="button" class="btn btn-sm btn-primary" id="btnFiltrarAjustes" onclick="cargarHistorialAjustes()"><i class="bi bi-search"></i> Buscar</button>
            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="$('#aj_hist_inicio, #aj_hist_fin').val(''); cargarHistorialAjustes();">Ver todo</button>
        </div>
        <p class="text-muted small mb-2">Las fechas filtran por el día en que se HIZO la corrección, no por el día del turno corregido.</p>
        <div class="pb-table-wrap">
            <table class="table table-sm table-bordered pb-table-compact">
                <thead>
                    <tr>
                        <th>Corregido el</th>
                        <th>Turno</th>
                        <th>Campo corregido</th>
                        <th class="text-end">Diferencia del cuadre</th>
                        <th>Motivo</th>
                        <th>Lo hizo</th>
                        <th>Autorizó</th>
                    </tr>
                </thead>
                <tbody id="tablaHistorialAjustes"></tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="modalReporteZ" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header pb-print-hide">
                <h6 class="modal-title">Reporte de cierre (Z)</h6>
            </div>
            <div class="modal-body pb-ticket pb-print-area" id="reporteZ_contenido"></div>
            <div class="modal-footer pb-print-hide">
                <button type="button" class="btn btn-primary" onclick="reimprimirReporteZ()"><i class="bi bi-printer"></i> Imprimir</button>
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Continuar</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalAjusteApertura" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title"><i class="bi bi-exclamation-triangle text-warning"></i> Corregir el monto de apertura</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="small text-muted">
                    Cambia la base del cuadre de caja, así que queda registrado: lo que había, lo que queda, el motivo,
                    quién lo hizo y quién lo autorizó.
                    <?php if (!$esAdminCaja): ?><strong>Necesita la autorización de un administrador.</strong><?php endif; ?>
                </p>
                <div class="small border rounded p-2 mb-2 d-none" id="aj_turno_info"></div>
                <div class="mb-2">Monto de apertura actual: <strong id="aj_actual"></strong></div>
                <div class="mb-2">
                    <label for="aj_monto" class="form-label mb-1">Monto de apertura correcto</label>
                    <input type="number" class="form-control" id="aj_monto" min="0" step="any" placeholder="Ej. 330000">
                    <div class="form-text" id="aj_preview"></div>
                </div>
                <div class="mb-2">
                    <label for="aj_motivo" class="form-label mb-1">Motivo (obligatorio)</label>
                    <input type="text" class="form-control" id="aj_motivo" maxlength="200" placeholder="Ej. Se digitó 330 en vez de 330.000">
                </div>
                <?php if (!$esAdminCaja): ?>
                <div class="border rounded p-2 mt-3">
                    <div class="small fw-semibold mb-1"><i class="bi bi-shield-lock"></i> Autorización del administrador</div>
                    <div class="row g-2">
                        <div class="col-6"><input type="text" class="form-control form-control-sm" id="aj_admin_usuario" placeholder="Usuario del administrador" autocomplete="off"></div>
                        <div class="col-6"><input type="password" class="form-control form-control-sm" id="aj_admin_password" placeholder="Clave" autocomplete="new-password"></div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-warning" onclick="guardarAjusteApertura()"><i class="bi bi-check2-circle"></i> Corregir monto</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalAjusteCierre" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title"><i class="bi bi-exclamation-triangle text-warning"></i> Corregir el monto contado</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="small text-muted">
                    Esto no cambia el efectivo esperado (no depende de lo que se contó), solo la diferencia del cuadre.
                    Queda registrado: lo que había, lo que queda, el motivo, quién lo hizo y quién lo autorizó.
                    <?php if (!$esAdminCaja): ?><strong>Necesita la autorización de un administrador.</strong><?php endif; ?>
                </p>
                <div class="small border rounded p-2 mb-2" id="ac_turno_info"></div>
                <div class="mb-2">Monto contado actual: <strong id="ac_actual"></strong></div>
                <div class="mb-2">
                    <label for="ac_monto" class="form-label mb-1">Monto contado correcto</label>
                    <input type="number" class="form-control" id="ac_monto" min="0" step="any" placeholder="Ej. 450000">
                    <div class="form-text" id="ac_preview"></div>
                </div>
                <div class="mb-2">
                    <label for="ac_motivo" class="form-label mb-1">Motivo (obligatorio)</label>
                    <input type="text" class="form-control" id="ac_motivo" maxlength="200" placeholder="Ej. Se digitó 450 en vez de 450.000">
                </div>
                <?php if (!$esAdminCaja): ?>
                <div class="border rounded p-2 mt-3">
                    <div class="small fw-semibold mb-1"><i class="bi bi-shield-lock"></i> Autorización del administrador</div>
                    <div class="row g-2">
                        <div class="col-6"><input type="text" class="form-control form-control-sm" id="ac_admin_usuario" placeholder="Usuario del administrador" autocomplete="off"></div>
                        <div class="col-6"><input type="password" class="form-control form-control-sm" id="ac_admin_password" placeholder="Clave" autocomplete="new-password"></div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-warning" onclick="guardarAjusteCierre()"><i class="bi bi-check2-circle"></i> Corregir monto</button>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . "/../parciales/pie.php"; ?>
<script>
const TURNO_ID = <?php echo $turnoAbierto ? (int) $turnoAbierto['id_turno'] : 'null'; ?>;
const NOMBRE_CAJERO_TURNO = <?php echo json_encode($usuarioActual['nombre']); ?>;
const ES_ADMIN_CAJA = <?php echo $esAdminCaja ? 'true' : 'false'; ?>;
let aperturaActual = 0;
let ultimoCierre = null; // {fecha, hora, contado} del último turno cerrado
let ultimaApertura = null; // monto con el que se abrió el último turno ya cerrado (para avisar si el de hoy parece muy distinto)

$(function () {
    if (TURNO_ID) cargarResumenTurno();
    cargarHistorial();
    cargarHistorialAjustes();
});

function cargarResumenTurno() {
    $.getJSON('/controlador/turno_estado.php', function (r) {
        if (!r.resumen) return;
        $('#rMontoApertura').text(pb.moneda(r.resumen.monto_apertura));
        $('#rVentasEfectivo').text(pb.moneda(r.resumen.ventas_efectivo));
        $('#rVentasOtros').text(pb.moneda(r.resumen.ventas_otros));
        $('#rEgresos').text(pb.moneda(r.resumen.egresos_efectivo));
        $('#rEsperado').text(pb.moneda(r.resumen.esperado));
        aperturaActual = parseFloat(r.resumen.monto_apertura) || 0;
        pintarAjustesApertura(r.ajustes || []);
    });
}

/** Historial de correcciones del monto de apertura de este turno (no se puede borrar ni editar). */
function pintarAjustesApertura(ajustes) {
    const $caja = $('#ajustesApertura');
    if (!ajustes.length) { $caja.addClass('d-none').empty(); return; }
    $caja.removeClass('d-none').empty().append('<div class="fw-semibold mb-1"><i class="bi bi-clock-history"></i> Correcciones del monto de apertura</div>');
    ajustes.forEach(function (a) {
        const autorizo = a.autorizado_por_nombre && a.autorizado_por_nombre !== a.hecho_por ? ` · autorizó ${a.autorizado_por_nombre}` : '';
        $caja.append($('<div class="mb-1"></div>').text(
            `${a.creado_en}: ${pb.moneda(a.monto_anterior)} → ${pb.moneda(a.monto_nuevo)} · ${a.hecho_por || '—'}${autorizo} · "${a.motivo}"`
        ));
    });
}

// Qué turno se está corrigiendo: el abierto (idTurno = null, el servidor usa el que esté abierto) o uno
// ya cerrado del historial (idTurno = su id; ahí también se recalculan su esperado y su diferencia).
let ajusteCtx = { idTurno: null, actual: 0, cerrado: null };
let turnosHistorial = {};

function abrirAjusteApertura() {
    ajusteCtx = { idTurno: null, actual: aperturaActual, cerrado: null };
    prepararModalAjuste();
}

function abrirAjusteTurnoCerrado(idTurno) {
    const t = turnosHistorial[idTurno];
    if (!t) return;
    ajusteCtx = {
        idTurno: idTurno,
        actual: parseFloat(t.monto_apertura) || 0,
        cerrado: {
            esperado: parseFloat(t.monto_esperado) || 0,
            contado: parseFloat(t.monto_cierre_real) || 0,
            diferencia: parseFloat(t.diferencia) || 0,
            etiqueta: `${t.fecha_apertura} ${t.hora_apertura}`,
        },
    };
    prepararModalAjuste();
}

function prepararModalAjuste() {
    const $info = $('#aj_turno_info');
    if (ajusteCtx.cerrado) {
        const c = ajusteCtx.cerrado;
        $info.removeClass('d-none').empty()
            .append($('<div class="fw-semibold"></div>').text(`Turno ya cerrado — abierto el ${c.etiqueta}`))
            .append($('<div></div>').text(`Esperado ${pb.moneda(c.esperado)} · contado ${pb.moneda(c.contado)} · diferencia ${pb.moneda(c.diferencia)}`))
            .append('<div class="text-muted">Al corregir la apertura, el efectivo esperado y la diferencia de ese día se recalculan.</div>');
    } else {
        $info.addClass('d-none').empty();
    }
    $('#aj_actual').text(pb.moneda(ajusteCtx.actual));
    $('#aj_monto').val('');
    $('#aj_motivo').val('');
    $('#aj_admin_usuario, #aj_admin_password').val('');
    $('#aj_preview').text('');
    bootstrap.Modal.getOrCreateInstance('#modalAjusteApertura').show();
}

/** Texto de "cómo quedaría" según el monto escrito (y, en un turno cerrado, la nueva diferencia del cuadre). */
function textoVistaPreviaAjuste(nuevo) {
    const delta = nuevo - ajusteCtx.actual;
    let texto = `Quedaría en ${pb.moneda(nuevo)} (${delta >= 0 ? '+' : '−'}${pb.moneda(Math.abs(delta))})`;
    if (ajusteCtx.cerrado) {
        const esperadoNuevo = ajusteCtx.cerrado.esperado + delta;
        texto += ` · esperado ${pb.moneda(esperadoNuevo)} · diferencia del cuadre ${pb.moneda(ajusteCtx.cerrado.contado - esperadoNuevo)}`;
    }
    return texto;
}

$(document).on('input', '#aj_monto', function () {
    const v = parseFloat($(this).val());
    $('#aj_preview').text(isNaN(v) ? '' : textoVistaPreviaAjuste(v));
});

async function guardarAjusteApertura() {
    const monto = parseFloat($('#aj_monto').val());
    const motivo = $('#aj_motivo').val().trim();
    if (isNaN(monto) || monto < 0) { pb.error('Escribe el monto de apertura correcto.'); return; }
    if (motivo === '') { pb.error('Escribe el motivo de la corrección.'); return; }
    if (!ES_ADMIN_CAJA && ($('#aj_admin_usuario').val().trim() === '' || $('#aj_admin_password').val() === '')) {
        pb.error('Un administrador debe escribir su usuario y su clave para autorizar.');
        return;
    }
    // Una segunda confirmación con el cambio a la vista: es plata que descuadra la caja si se hace mal.
    let pregunta = `El monto de apertura pasará de ${pb.moneda(ajusteCtx.actual)} a ${pb.moneda(monto)}.`;
    if (ajusteCtx.cerrado) {
        const esperadoNuevo = ajusteCtx.cerrado.esperado + (monto - ajusteCtx.actual);
        pregunta += ` La diferencia de ese turno pasará de ${pb.moneda(ajusteCtx.cerrado.diferencia)} a ${pb.moneda(ajusteCtx.cerrado.contado - esperadoNuevo)}.`;
    }
    const ok = await pb.confirmar(`${pregunta} ¿Confirmas la corrección?`);
    if (!ok) return;
    const datos = {
        monto_nuevo: monto, motivo: motivo,
        admin_usuario: $('#aj_admin_usuario').val(), admin_password: $('#aj_admin_password').val(),
        csrf_token: pb.csrfToken(),
    };
    if (ajusteCtx.idTurno) datos.id_turno = ajusteCtx.idTurno;
    $.ajax({
        type: 'POST',
        url: '/controlador/ajustar_apertura_turno.php',
        data: datos,
        dataType: 'json',
        success: function (r) {
            if (r.ok) {
                pb.success('Monto de apertura corregido.');
                bootstrap.Modal.getInstance(document.getElementById('modalAjusteApertura'))?.hide();
                if (TURNO_ID) cargarResumenTurno();
                cargarHistorial();
                cargarHistorialAjustes();
            } else {
                pb.error(r.error || 'No se pudo corregir el monto.');
            }
        },
        error: function () { pb.error('Error en el proceso.'); },
    });
}

// Corrección del monto CONTADO al cerrar un turno ya cerrado (cualquiera, de cualquier fecha) — el
// caso real que originó esto: "era 450mil y digitó 450". No afecta el efectivo esperado, solo la
// diferencia del cuadre de ese turno.
let ajusteCierreCtx = { idTurno: null, actual: 0, esperado: 0, diferencia: 0, etiqueta: '' };

function abrirAjusteCierre(idTurno) {
    const t = turnosHistorial[idTurno];
    if (!t) return;
    ajusteCierreCtx = {
        idTurno: idTurno,
        actual: parseFloat(t.monto_cierre_real) || 0,
        esperado: parseFloat(t.monto_esperado) || 0,
        diferencia: parseFloat(t.diferencia) || 0,
        etiqueta: `${t.fecha_apertura} ${t.hora_apertura}`,
    };
    $('#ac_turno_info').empty()
        .append($('<div class="fw-semibold"></div>').text(`Turno abierto el ${ajusteCierreCtx.etiqueta}, cerrado el ${t.fecha_cierre} ${t.hora_cierre}`))
        .append($('<div></div>').text(`Esperado ${pb.moneda(ajusteCierreCtx.esperado)} · contado ${pb.moneda(ajusteCierreCtx.actual)} · diferencia ${pb.moneda(ajusteCierreCtx.diferencia)}`));
    $('#ac_actual').text(pb.moneda(ajusteCierreCtx.actual));
    $('#ac_monto').val('');
    $('#ac_motivo').val('');
    $('#ac_admin_usuario, #ac_admin_password').val('');
    $('#ac_preview').text('');
    bootstrap.Modal.getOrCreateInstance('#modalAjusteCierre').show();
}

$(document).on('input', '#ac_monto', function () {
    const v = parseFloat($(this).val());
    if (isNaN(v)) { $('#ac_preview').text(''); return; }
    const diferenciaNueva = v - ajusteCierreCtx.esperado;
    $('#ac_preview').text(`Quedaría en ${pb.moneda(v)} · diferencia del cuadre ${pb.moneda(diferenciaNueva)}`);
});

async function guardarAjusteCierre() {
    const monto = parseFloat($('#ac_monto').val());
    const motivo = $('#ac_motivo').val().trim();
    if (isNaN(monto) || monto < 0) { pb.error('Escribe el monto contado correcto.'); return; }
    if (motivo === '') { pb.error('Escribe el motivo de la corrección.'); return; }
    if (!ES_ADMIN_CAJA && ($('#ac_admin_usuario').val().trim() === '' || $('#ac_admin_password').val() === '')) {
        pb.error('Un administrador debe escribir su usuario y su clave para autorizar.');
        return;
    }
    const diferenciaNueva = monto - ajusteCierreCtx.esperado;
    const pregunta = `El monto contado pasará de ${pb.moneda(ajusteCierreCtx.actual)} a ${pb.moneda(monto)}. La diferencia de ese turno pasará de ${pb.moneda(ajusteCierreCtx.diferencia)} a ${pb.moneda(diferenciaNueva)}. ¿Confirmas la corrección?`;
    const ok = await pb.confirmar(pregunta);
    if (!ok) return;
    const datos = {
        id_turno: ajusteCierreCtx.idTurno, monto_nuevo: monto, motivo: motivo,
        admin_usuario: $('#ac_admin_usuario').val(), admin_password: $('#ac_admin_password').val(),
        csrf_token: pb.csrfToken(),
    };
    $.ajax({
        type: 'POST',
        url: '/controlador/ajustar_cierre_turno.php',
        data: datos,
        dataType: 'json',
        success: function (r) {
            if (r.ok) {
                pb.success('Monto contado corregido.');
                bootstrap.Modal.getInstance(document.getElementById('modalAjusteCierre'))?.hide();
                cargarHistorial();
                cargarHistorialAjustes();
            } else {
                pb.error(r.error || 'No se pudo corregir el monto.');
            }
        },
        error: function () { pb.error('Error en el proceso.'); },
    });
}

/** Historial global de correcciones del monto de apertura (de cualquier turno), con filtro por fecha de la corrección. */
function cargarHistorialAjustes() {
    $.getJSON('/controlador/historial_ajustes_turnos.php', {
        fecha_inicio: $('#aj_hist_inicio').val(),
        fecha_fin: $('#aj_hist_fin').val(),
    }, function (ajustes) {
        const $body = $('#tablaHistorialAjustes').empty();
        if (!Array.isArray(ajustes) || ajustes.length === 0) {
            $body.append('<tr><td colspan="7" class="text-center text-muted">No hay correcciones registradas.</td></tr>');
            return;
        }
        ajustes.forEach(function (a) {
            const esc = pb.escaparHtml;
            const cuadre = a.turno_estado === 'cerrado' && a.diferencia_anterior !== null
                ? `${pb.moneda(a.diferencia_anterior)} → ${pb.moneda(a.diferencia_nueva)}`
                : '<span class="text-muted">turno abierto</span>';
            const autorizo = a.autorizado_por_nombre && a.autorizado_por_nombre !== a.hecho_por
                ? esc(a.autorizado_por_nombre) : '<span class="text-muted">—</span>';
            const campo = a.tipo === 'cierre' ? 'Contado' : 'Apertura';
            $body.append(`<tr>
                <td class="text-nowrap">${esc(a.creado_en)}</td>
                <td class="text-nowrap">#${esc(a.turno_id)} · ${esc(a.fecha_apertura)} ${esc(a.hora_apertura)}</td>
                <td class="text-nowrap">${campo}: ${pb.moneda(a.monto_anterior)} → ${pb.moneda(a.monto_nuevo)}</td>
                <td class="text-end text-nowrap">${cuadre}</td>
                <td>${esc(a.motivo)}</td>
                <td>${esc(a.hecho_por || '—')}</td>
                <td>${autorizo}</td>
            </tr>`);
        });
    }).fail(() => pb.error('Error al cargar el historial de ajustes.'));
}

// Un "330" en vez de "330.000" se ve igual de normal en un campo numérico: por eso antes de abrir se
// muestra el monto YA con formato de dinero, y se avisa si es mucho menor que el de la última apertura.
async function abrirTurno() {
    const monto = parseFloat($('#monto_apertura').val()) || 0;
    let aviso = '';
    if (monto <= 0) {
        aviso = ' Es CERO: el turno se abriría sin dinero en caja.';
    } else if (ultimaApertura !== null && ultimaApertura > 0 && monto < ultimaApertura / 10) {
        aviso = ` Es mucho menos que la última apertura (${pb.moneda(ultimaApertura)}): ¿faltan ceros?`;
    }
    const hoy = new Date();
    const hoyTexto = `${hoy.getFullYear()}-${String(hoy.getMonth() + 1).padStart(2, '0')}-${String(hoy.getDate()).padStart(2, '0')}`;
    if (ultimoCierre && ultimoCierre.fecha === hoyTexto) {
        aviso += ` Hoy ya se cerró un turno a las ${ultimoCierre.hora} con ${pb.moneda(ultimoCierre.contado)} contados.`;
    }
    if (!(await pb.confirmar(`Vas a abrir el turno con ${pb.moneda(monto)} en caja.${aviso} ¿Es correcto?`))) return;
    $.ajax({
        type: 'POST',
        url: '/controlador/abrir_turno.php',
        data: { monto_apertura: monto, csrf_token: pb.csrfToken() },
        dataType: 'json',
        success: function (r) {
            if (r.ok) {
                pb.success('Turno abierto.');
                // Un instante para que se alcance a ver el aviso antes de recargar la pantalla.
                setTimeout(() => location.reload(), 900);
            } else {
                pb.error(r.error || 'Error al abrir el turno.');
            }
        },
        error: function () { pb.error('Error en el proceso.'); }
    });
}

function cerrarTurno() {
    const montoContado = $('#monto_contado').val();
    if (montoContado === '') {
        pb.error('Indica el monto contado.');
        return;
    }
    $.ajax({
        type: 'POST',
        url: '/controlador/cerrar_turno.php',
        data: {
            id_turno: TURNO_ID,
            monto_contado: montoContado,
            observaciones: $('#observaciones_cierre').val(),
            csrf_token: pb.csrfToken(),
        },
        dataType: 'json',
        success: function (r) {
            if (r.ok) {
                pb.success(`Turno cerrado. Diferencia: ${pb.moneda(r.diferencia)}`);
                mostrarReporteZ(r.reporte);
            } else {
                pb.error(r.error || 'Error al cerrar el turno.');
            }
        },
        error: function () { pb.error('Error en el proceso.'); }
    });
}

/**
 * Arma y muestra el ticket del reporte Z. { recargarAlCerrar: true } (el valor por defecto) es para
 * justo después de cerrar un turno: al cerrar el modal se recarga la pantalla para volver a mostrar
 * "Abrir turno". Al REIMPRIMIR el Z de un turno antiguo (reimprimirZTurno) no hay nada que recargar,
 * así que se pasa recargarAlCerrar: false para no refrescar la pantalla sin necesidad.
 */
function mostrarReporteZ(reporte, opciones) {
    const recargarAlCerrar = !opciones || opciones.recargarAlCerrar !== false;
    const negocio = window.PB_NEGOCIO || {};
    const t = reporte.turno;
    const etiquetasPago = { efectivo: 'Efectivo', tarjeta: 'Tarjeta', transferencia: 'Transferencia', credito: 'Crédito' };
    const pagosHtml = (reporte.pagos_por_metodo || []).length > 0
        ? reporte.pagos_por_metodo.map(p => `<div class="d-flex justify-content-between"><span>${etiquetasPago[p.metodo_pago] || p.metodo_pago}</span><span>${pb.moneda(p.total)}</span></div>`).join('')
        : '<div class="text-muted">Sin ventas registradas</div>';

    const html = `
        <div class="text-center mb-2"><strong>${negocio.nombre || ''}</strong><br><strong>CIERRE DE TURNO (REPORTE Z)</strong></div>
        <hr>
        <div class="d-flex justify-content-between"><span>Apertura</span><span>${t.fecha_apertura} ${t.hora_apertura}</span></div>
        <div class="d-flex justify-content-between"><span>Cierre</span><span>${t.fecha_cierre} ${t.hora_cierre}</span></div>
        <div class="d-flex justify-content-between"><span>Abrió</span><span>${$('<div>').text(t.abierto_por || NOMBRE_CAJERO_TURNO).html()}</span></div>
        <div class="d-flex justify-content-between"><span>Cerró</span><span>${$('<div>').text(t.cerrado_por_nombre || NOMBRE_CAJERO_TURNO).html()}</span></div>
        <hr>
        <div class="fw-bold mb-1">Ventas por método de pago</div>
        ${pagosHtml}
        <div class="d-flex justify-content-between fw-bold border-top pt-1 mt-1"><span>Total ventas (${reporte.ventas_cantidad})</span><span>${pb.moneda(reporte.ventas_total)}</span></div>
        ${reporte.ventas_anuladas > 0 ? `<div class="text-muted small">Ventas anuladas en el turno: ${reporte.ventas_anuladas}</div>` : ''}
        <hr>
        <div class="d-flex justify-content-between"><span>Egresos (${reporte.egresos_cantidad})</span><span>${pb.moneda(reporte.egresos_total)}</span></div>
        <hr>
        <div class="d-flex justify-content-between"><span>Monto de apertura</span><span>${pb.moneda(t.monto_apertura)}</span></div>
        ${(reporte.ajustes_apertura || []).map(a => `<div class="small">${a.tipo === 'cierre' ? 'Contado' : 'Apertura'} corregido de ${pb.moneda(a.monto_anterior)} a ${pb.moneda(a.monto_nuevo)} (${$('<div>').text(a.motivo).html()}; ${$('<div>').text(a.hecho_por || '—').html()}${a.autorizado_por_nombre && a.autorizado_por_nombre !== a.hecho_por ? ', autorizó ' + $('<div>').text(a.autorizado_por_nombre).html() : ''})</div>`).join('')}
        <div class="d-flex justify-content-between"><span>Efectivo esperado</span><span>${pb.moneda(t.monto_esperado)}</span></div>
        <div class="d-flex justify-content-between"><span>Efectivo contado</span><span>${pb.moneda(t.monto_cierre_real)}</span></div>
        <div class="d-flex justify-content-between fw-bold"><span>Diferencia</span><span>${pb.moneda(t.diferencia)}</span></div>
        ${t.observaciones ? `<div class="small text-muted mt-1">Obs: ${t.observaciones}</div>` : ''}
    `;
    $('#reporteZ_contenido').html(html);
    pb.prepararTicketParaImprimir(document.getElementById('reporteZ_contenido'));

    // Además del diálogo normal (botón "Imprimir" de arriba), lo manda
    // directo a la impresora de recibos si hay una configurada — mismo
    // criterio que el ticket de venta.
    if (window.PB_IMPRESORA_RECIBO) {
        pb.imprimirTicketSilencioso(html, window.PB_IMPRESORA_RECIBO)
            .then(function (r) {
                if (!r || r.disponible === false || r.ok) return;
                pb.error('No se pudo imprimir el reporte Z: ' + (r.error || 'error desconocido'));
            });
    }

    const modalEl = document.getElementById('modalReporteZ');
    // El turno ya quedó cerrado en el servidor — la página solo se recarga
    // (para volver a mostrar "Abrir turno") cuando el admin ya vio/imprimió
    // el reporte y decide continuar, nunca antes.
    if (recargarAlCerrar) {
        modalEl.addEventListener('hidden.bs.modal', () => location.reload(), { once: true });
    }
    bootstrap.Modal.getOrCreateInstance(modalEl).show();
}

/** Reimprime el reporte Z de un turno ya cerrado (de cualquier fecha) — útil tras corregir su apertura o su contado. */
function reimprimirZTurno(idTurno) {
    $.getJSON('/controlador/reporte_cierre_turno.php', { id_turno: idTurno }, function (r) {
        if (r.ok) {
            mostrarReporteZ(r.reporte, { recargarAlCerrar: false });
        } else {
            pb.error(r.error || 'No se pudo obtener el reporte de cierre.');
        }
    }).fail(() => pb.error('Error al obtener el reporte de cierre.'));
}

/**
 * Botón "Imprimir" del modal: antes usaba window.print() (diálogo de Windows) — distinto del envío
 * automático de arriba, que va directo a la impresora configurada. Por eso la primera vez (automática)
 * salía bien y al pulsar para reimprimir salía distinto o no salía nada. Ahora el botón usa el mismo
 * camino directo, y solo cae al diálogo del sistema si no hay ninguna impresora configurada.
 */
async function reimprimirReporteZ() {
    const html = document.getElementById('reporteZ_contenido').innerHTML;
    if (!window.PB_IMPRESORA_RECIBO) {
        pb.imprimirConDialogo(html);
        return;
    }
    const r = await pb.imprimirTicketSilencioso(html, window.PB_IMPRESORA_RECIBO);
    if (r && r.duplicado) pb.info('Ya se había enviado hace un instante.');
    else if (r && r.ok) pb.success('Reporte enviado a "' + window.PB_IMPRESORA_RECIBO + '".');
    else if (r && r.disponible === false) pb.imprimirConDialogo(html);
    else pb.error('No se pudo imprimir: ' + ((r && r.error) || 'error desconocido'));
}

function cargarHistorial() {
    $.getJSON('/controlador/historial_turnos.php', function (turnos) {
        const $body = $('#tablaHistorialTurnos').empty();
        turnosHistorial = {};
        if (turnos.length === 0) {
            $body.append('<tr><td colspan="7" class="text-center text-muted">Sin turnos cerrados todavía.</td></tr>');
            return;
        }
        ultimaApertura = parseFloat(turnos[0].monto_apertura);
        // Con varios turnos en un mismo día, lo contado al cerrar el anterior es lo que debería haber al abrir el siguiente.
        const ult = turnos[0];
        ultimoCierre = { fecha: ult.fecha_cierre, hora: ult.hora_cierre, contado: parseFloat(ult.monto_cierre_real) || 0 };
        $('#aviso_monto_apertura').text(`La última apertura fue de ${pb.moneda(ultimaApertura)}. El último turno cerró el ${ult.fecha_cierre} a las ${ult.hora_cierre} con ${pb.moneda(ultimoCierre.contado)} contados.`);
        turnos.forEach(function (t) {
            turnosHistorial[t.id_turno] = t;
            const claseDiferencia = t.diferencia < 0 ? 'text-danger' : (t.diferencia > 0 ? 'text-success' : '');
            const nAjustes = parseInt(t.n_ajustes, 10) || 0;
            const corregido = nAjustes > 0
                ? `<div class="small text-warning-emphasis" title="Corregido ${nAjustes} vez/veces. Se abrió con ${pb.moneda(t.apertura_original)}"><i class="bi bi-pencil-square"></i> corregido${nAjustes > 1 ? ' ×' + nAjustes : ''} (antes ${pb.moneda(t.apertura_original)})</div>`
                : '';
            $body.append(`<tr>
                <td>${t.fecha_apertura} ${t.hora_apertura}</td>
                <td>${t.fecha_cierre} ${t.hora_cierre}</td>
                <td class="text-end">${pb.moneda(t.monto_apertura)}${corregido}</td>
                <td class="text-end">${pb.moneda(t.monto_esperado)}</td>
                <td class="text-end">${pb.moneda(t.monto_cierre_real)}</td>
                <td class="text-end ${claseDiferencia}">${pb.moneda(t.diferencia)}</td>
                <td class="text-end text-nowrap">
                    <button type="button" class="btn btn-link btn-sm p-0 d-block btn-corregir-turno" data-turno="${t.id_turno}" onclick="abrirAjusteTurnoCerrado(${parseInt(t.id_turno, 10)})"><i class="bi bi-pencil-square"></i> Apertura</button>
                    <button type="button" class="btn btn-link btn-sm p-0 d-block btn-corregir-contado" data-turno="${t.id_turno}" onclick="abrirAjusteCierre(${parseInt(t.id_turno, 10)})"><i class="bi bi-pencil-square"></i> Contado</button>
                    <button type="button" class="btn btn-link btn-sm p-0 d-block btn-reimprimir-z" data-turno="${t.id_turno}" onclick="reimprimirZTurno(${parseInt(t.id_turno, 10)})"><i class="bi bi-printer"></i> Reimprimir Z</button>
                </td>
            </tr>`);
        });
    }).fail(() => pb.error('Error al cargar el historial.'));
}
</script>
