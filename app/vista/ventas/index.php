<?php
require_once "../../modelo/bootstrap.php";
require_once "../../modelo/Licencia.php";

$usuarioActual = Auth::requerirLogin();
$puedeGestionarCaja = Auth::tienePermiso('caja.gestionar');
$modCajaTurnos = Configuracion::moduloActivo('caja_turnos');
$modMesas = Configuracion::moduloActivo('mesas_comandas');
$modClientesCredito = Configuracion::moduloActivo('clientes_credito');
$modModificadores = Configuracion::moduloActivo('modificadores');
$modPropina = Configuracion::moduloActivo('propina');
$modExistencias = Configuracion::moduloActivo('existencias');
$modProfesionales = Configuracion::moduloActivo('profesionales');
$puedeEditarVenta = Auth::tienePermiso('facturas.editar');
$puedeCambiarClienteVenta = $puedeEditarVenta && $modClientesCredito;
$puedeVerFacturasRecientes = Auth::tienePermiso('movimientos.ver');
$fidelizacionHabilitada = !empty(Licencia::datos()['fidelizacion_habilitada']);
$tituloPagina = 'Ventas';
$paginaActiva = 'ventas';

require_once __DIR__ . "/../parciales/head.php";
require_once __DIR__ . "/../parciales/sidebar_inicio.php";
$fidelizacionQrAuto = $fidelizacionHabilitada && !empty($configNegocio['fidelizacion_qr_auto_venta']);
?>

<?php if ($modCajaTurnos): ?>
<div id="aviso_turno" class="alert alert-warning py-2 px-3 d-none mb-3">
    <i class="bi bi-exclamation-triangle"></i>
    No hay un turno de caja abierto — esta venta no entrará en ningún cuadre de caja.
    <?php if ($puedeGestionarCaja): ?>
        <a href="/vista/caja/index.php">Abrir turno</a>
    <?php endif; ?>
</div>
<?php endif; ?>

<script>document.documentElement.classList.add('pb-pagina-ventas');</script>
<div class="row g-3 pb-ventas-fila">
    <div class="col-lg-7 pb-ventas-izq pb-scroll-tema">
        <?php if ($fidelizacionHabilitada || $usuarioActual['rol'] === 'admin' || $puedeVerFacturasRecientes): ?>
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <?php if ($puedeVerFacturasRecientes): ?>
                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="abrirFacturasRecientes()">
                        <i class="bi bi-clock-history"></i> Facturas recientes
                    </button>
                <?php endif; ?>
                <?php if ($fidelizacionHabilitada): ?>
                    <span class="text-muted small"><i class="bi bi-award"></i> Fidelización</span>
                    <button type="button" class="btn btn-sm btn-outline-success" onclick="pb.generarQrFidelizacion(); qrFidelizacionGeneradoManualmente = true;">
                        <i class="bi bi-qr-code"></i> Generar QR
                    </button>
                <?php endif; ?>
            </div>
            <?php if ($usuarioActual['rol'] === 'admin'): ?>
                <div class="d-flex gap-2 ms-auto">
                    <a href="/vista/productos_destacados/index.php" class="btn btn-sm btn-outline-secondary" title="Configurar cuadrícula de productos">
                        <i class="bi bi-grid-3x3-gap-fill"></i> Cuadrícula
                    </a>
                    <?php if ($modMesas): ?>
                        <a href="/vista/mesas/index.php" class="btn btn-sm btn-outline-secondary" title="Configurar mesas">
                            <i class="bi bi-gear"></i> Mesas
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        <?php if ($modMesas): ?>
        <div class="pb-card mb-3">
            <div class="pb-card-body py-2">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h6 class="mb-0"><i class="bi bi-grid-3x3-gap"></i> Mesas</h6>
                    <span class="small text-muted">
                        <span class="pb-mesa-leyenda"><span class="pb-mesa-punto pb-mesa-libre"></span> Libre</span>
                        <span class="pb-mesa-leyenda ms-2"><span class="pb-mesa-punto pb-mesa-ocupada"></span> Ocupada</span>
                    </span>
                </div>
                <div id="mesa_activa_aviso" class="alert alert-info py-1 px-2 small d-none mb-2"></div>
                <div id="lista_mesas" class="d-flex flex-wrap gap-2">
                    <span class="text-muted small">Cargando…</span>
                </div>
            </div>
        </div>
        <?php endif; ?>
        <?php if (!empty($configNegocio['ventas_vista_cuadricula'])): ?>
        <div class="pb-card mb-3">
            <div class="pb-card-body">
                <h6 class="mb-2"><i class="bi bi-grid-3x3-gap-fill"></i> Productos rápidos</h6>
                <div id="cuadricula_productos" class="pb-grid-productos">
                    <span class="text-muted small">Cargando…</span>
                </div>
            </div>
        </div>
        <?php endif; ?>
        <div class="pb-card mb-3">
            <div class="pb-card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <label for="producto_buscar" class="form-label fw-semibold">
                        <i class="bi bi-upc-scan"></i> Escanear o buscar producto
                    </label>
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#modalEgreso">
                        <i class="bi bi-folder-plus"></i> Egreso
                    </button>
                </div>
                <input type="text" class="form-control pb-scan-input" id="producto_buscar"
                       placeholder="Escanea el código de barras o escribe el nombre…" autocomplete="off" autofocus>
                <ul id="productos_resultados" class="list-group mt-2" style="max-height: 260px; overflow-y: auto;"></ul>
            </div>
        </div>
    </div>

    <div class="col-lg-5 pb-ventas-der">
        <div class="pb-card pb-ventas-card">
            <div class="pb-card-body pb-scroll-tema">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h6 class="mb-0"><i class="bi bi-cart3"></i> Carrito</h6>
                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="vaciarCarrito()">
                        <i class="bi bi-trash"></i> Vaciar
                    </button>
                </div>
                <ul id="carrito_compras_lista" class="list-group mb-2 pb-carrito-lista pb-scroll-tema">
                    <li class="list-group-item text-muted text-center" id="carrito_vacio">El carrito está vacío</li>
                </ul>

                <div class="pb-ventas-cobro">
                <div id="descripcion_venta" class="mb-2" style="display:none;">
                    <label for="descripcion" class="form-label small mb-0">Descripción de la venta (opcional)</label>
                    <textarea id="descripcion" class="form-control form-control-sm" rows="1"></textarea>
                </div>

                <div class="d-flex justify-content-between align-items-center d-none" id="fila_subtotal">
                    <span class="text-muted small">Subtotal</span>
                    <span class="small" id="carrito_subtotal">$0</span>
                </div>
                <div class="row g-2 align-items-end mt-1" id="fila_ajuste" style="display:none;">
                    <div class="col-12">
                        <label for="ajuste_total" class="form-label small mb-0">
                            Ajuste al total (opcional) <span class="text-muted">negativo = descuento, positivo = recargo</span>
                        </label>
                        <input type="number" class="form-control form-control-sm" id="ajuste_total" placeholder="Ej. -5000">
                    </div>
                </div>
                <?php if ($modPropina): ?>
                <div class="row g-2 align-items-end mt-1" id="fila_propina" style="display:none;">
                    <div class="col-12">
                        <label class="form-label small mb-0 d-flex justify-content-between align-items-center">
                            <span>Propina (opcional)</span>
                            <span>
                                <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2" id="btn_propina_sin" onclick="aplicarPropinaSugerida(0)">Sin propina</button>
                                <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2" id="btn_propina_sugerida" onclick="aplicarPropinaSugerida(PROPINA_PORCENTAJE / 100)"></button>
                            </span>
                        </label>
                        <input type="number" class="form-control form-control-sm" id="propina_total" placeholder="Ej. 3000" min="0">
                    </div>
                </div>
                <?php endif; ?>
                <div class="d-flex justify-content-between align-items-center border-top pt-2 mt-1">
                    <span class="text-muted">Total a cobrar</span>
                    <span class="pb-cart-total" id="carrito_total">$0</span>
                </div>

                <div class="mt-2" id="fila_pagos" style="display:none;">
                    <div class="d-flex justify-content-between align-items-center">
                        <label class="form-label small mb-0">Pagos</label>
                        <button type="button" class="btn btn-sm btn-link p-0" onclick="agregarLineaPago()">
                            <i class="bi bi-plus-lg"></i> Agregar método
                        </button>
                    </div>
                    <div id="lista_pagos"></div>
                    <div class="d-flex justify-content-between small mt-1">
                        <span class="text-muted">Restante por asignar</span>
                        <span id="pago_restante">$0</span>
                    </div>
                </div>

                <?php if ($modProfesionales): ?>
                <div class="mt-2" id="fila_profesional">
                    <label for="profesional_venta" class="form-label small mb-0">Profesional que atiende</label>
                    <select id="profesional_venta" class="form-select form-select-sm">
                        <option value="">— Sin asignar —</option>
                    </select>
                    <div class="form-text mt-0">Se asigna a los servicios que agregues; puedes cambiarlo en cada línea con <i class="bi bi-person-badge"></i>.</div>
                </div>
                <?php endif; ?>

                <?php if ($modClientesCredito): ?>
                <div class="mt-2" id="fila_cliente_credito">
                    <label for="cliente_buscar" class="form-label small mb-0" id="label_cliente">Cliente (opcional)</label>
                    <input type="text" class="form-control form-control-sm" id="cliente_buscar" placeholder="Buscar por nombre…" autocomplete="off">
                    <ul id="cliente_resultados" class="list-group mt-1" style="max-height: 160px; overflow-y: auto;"></ul>
                    <div id="cliente_seleccionado" class="small text-muted mt-1"></div>
                </div>
                <?php endif; ?>

                <div class="row g-2 mt-1" id="fila_efectivo" style="display:none;">
                    <div class="col-6">
                        <label for="efectivo_recibido" class="form-label small mb-0">Efectivo recibido</label>
                        <input type="number" class="form-control" id="efectivo_recibido" placeholder="0">
                    </div>
                    <div class="col-6">
                        <label class="form-label small mb-0">Vuelto</label>
                        <input type="text" class="form-control" id="vuelto_calculado" disabled>
                    </div>
                </div>

                <div class="pb-ventas-acciones">
                <?php if ($modMesas): ?>
                <div class="d-flex gap-2 mt-2 d-none" id="fila_guardar_mesa">
                    <button type="button" class="btn btn-outline-secondary flex-grow-1" id="btn_guardar_mesa" onclick="guardarMesaActual(false)">
                        <i class="bi bi-save"></i> Guardar sin imprimir
                    </button>
                    <button type="button" class="btn btn-outline-warning flex-grow-1 d-none" id="btn_guardar_mesa_imprimir" onclick="guardarMesaActual(true)">
                        <i class="bi bi-printer"></i> Guardar e imprimir comanda
                    </button>
                </div>
                <?php endif; ?>
                <button type="button" class="btn btn-primary w-100 mt-2 p-2" id="btn_confirmar_venta" onclick="realizarVenta()">
                    <i class="bi bi-cart-check"></i> Confirmar venta
                </button>
                </div>
                </div>
            </div>
        </div>
    </div>
</div>


<!-- Registrar egreso -->
<div class="modal fade" id="modalEgreso" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title"><i class="bi bi-folder-plus"></i> Registrar egreso</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted small mb-2">Gastos generales de caja sin producto asociado (ej. pagar un domicilio, un servicio). Las compras a proveedor y las pérdidas de inventario se registran en Productos.</p>
                <form id="form_carga_insumo">
                    <div class="row g-2">
                        <div class="col-12">
                            <label for="descripcion_insumo" class="form-label">Descripción</label>
                            <input type="text" class="form-control" id="descripcion_insumo" placeholder="Ej. Pago de domicilio">
                        </div>
                        <div class="col-6">
                            <label for="valor_insumo" class="form-label">Valor</label>
                            <input type="number" class="form-control" id="valor_insumo" placeholder="Ej. 50000">
                        </div>
                        <div class="col-6">
                            <label for="fecha_insumo" class="form-label">Fecha</label>
                            <input type="date" class="form-control" id="fecha_insumo">
                        </div>
                        <div class="col-6">
                            <label for="metodo_pago_egreso" class="form-label">Método de pago</label>
                            <select class="form-select" id="metodo_pago_egreso">
                                <option value="efectivo">Efectivo</option>
                                <option value="tarjeta">Tarjeta</option>
                                <option value="transferencia">Transferencia</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label mb-1">¿Se descuenta del efectivo de hoy?</label>
                            <div class="btn-group w-100" role="group">
                                <input type="radio" class="btn-check" name="egreso_afecta_turno" id="egreso_afecta_turno_si" value="1" checked>
                                <label class="btn btn-outline-secondary btn-sm" for="egreso_afecta_turno_si">Sí, del turno de hoy</label>
                                <input type="radio" class="btn-check" name="egreso_afecta_turno" id="egreso_afecta_turno_no" value="0">
                                <label class="btn btn-outline-secondary btn-sm" for="egreso_afecta_turno_no">No, es un gasto general</label>
                            </div>
                            <div class="form-text" id="egreso_aviso_fecha_distinta"></div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cerrar</button>
                <button type="button" class="btn btn-primary" onclick="cargarInsumo()">
                    <i class="bi bi-save"></i> Registrar egreso
                </button>
            </div>
        </div>
    </div>
</div>

<?php if ($modProfesionales): ?>
<div class="modal fade" id="modalProfesionalLinea" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-sm modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title">¿Quién realizó este servicio?</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body d-grid gap-2" id="lista_profesionales_linea"></div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Ticket imprimible -->
<div class="modal fade" id="modalTicket" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable" style="max-width:780px;">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title">Venta registrada — así queda la factura</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <!-- flex-nowrap: dos columnas siempre — la factura a la izquierda, las acciones a la derecha
                     (nunca se acomodan debajo aunque la factura sea ancha). -->
                <div class="d-flex flex-nowrap gap-3 align-items-start">
                    <!-- Izquierda: SOLO la factura tal cual se va a imprimir (con el QR al final). -->
                    <div class="flex-grow-1 d-flex justify-content-center" style="min-width:0;">
                        <div id="ticket_contenido" class="pb-ticket pb-ticket-vista pb-print-area"></div>
                    </div>
                    <!-- Derecha: las acciones y los avisos (nada de esto se imprime). -->
                    <div class="d-grid gap-2 pb-print-hide" style="flex:0 0 210px; width:210px;">
                        <button type="button" class="btn btn-primary pb-btn-imprimir" onclick="imprimirFacturaModal()"><i class="bi bi-printer"></i> Factura</button>
                        <button type="button" class="btn btn-outline-warning pb-btn-imprimir" onclick="imprimirComandaModal()"><i class="bi bi-printer"></i> Comanda</button>
                        <?php if ($fidelizacionHabilitada): ?>
                            <button type="button" class="btn btn-outline-success" onclick="pb.generarQrFidelizacion()"><i class="bi bi-qr-code"></i> QR fidelización</button>
                        <?php endif; ?>
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cerrar</button>
                        <div class="small text-muted" id="ticket_qr_aviso"></div>
                        <div class="small text-muted" id="ticket_estado_impresion"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>


<div class="modal fade" id="modalComandaPreview" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable" style="max-width:560px;">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title" id="modalComandaPreviewTitulo">Comanda enviada a cocina</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="d-flex flex-nowrap gap-3 align-items-start">
                    <div class="flex-grow-1 d-flex justify-content-center" style="min-width:0;">
                        <div id="comanda_preview_contenido" class="pb-ticket pb-ticket-vista pb-print-area"></div>
                    </div>
                    <div class="d-grid gap-2 pb-print-hide" style="flex:0 0 170px; width:170px;">
                        <button type="button" class="btn btn-primary" onclick="reimprimirComandaPreview()"><i class="bi bi-printer"></i> Comanda</button>
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cerrar</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if ($puedeVerFacturasRecientes): ?>
<!-- Facturas recientes: para cuando algo de una venta ya hecha quedó pendiente (no salió la
     comanda, falta reimprimir, se equivocó el ajuste o el cliente) y no se quiere perder la venta
     en curso saliendo hasta la pantalla de Facturas. Mismas acciones, sin salir de Ventas. -->
<div class="modal fade" id="modalFacturasRecientes" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title">Facturas recientes</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted small mb-2">Ventas de hoy. Para días anteriores, usa la pantalla de Facturas.</p>
                <div class="pb-table-wrap">
                    <table class="table table-sm table-hover align-middle">
                        <thead><tr><th>Factura</th><th>Hora</th><th>Vendedor</th><th class="text-end">Total</th><th></th></tr></thead>
                        <tbody id="fr_tabla_cuerpo"><tr><td colspan="5" class="text-muted text-center">Cargando…</td></tr></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalFacturaRecienteDetalle" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable" style="max-width:780px;">
        <div class="modal-content">
            <div class="modal-header pb-print-hide">
                <h6 class="modal-title" id="fr_detalle_titulo">Detalle</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="d-flex flex-nowrap gap-3 align-items-start">
                    <div class="flex-grow-1 d-flex justify-content-center" style="min-width:0;">
                        <div id="fr_detalle_contenido" class="pb-ticket pb-ticket-vista pb-print-area"></div>
                    </div>
                    <div class="d-grid gap-2 pb-print-hide" style="flex:0 0 190px; width:190px;">
                        <button type="button" class="btn btn-primary" onclick="reimprimirFacturaReciente()">
                            <i class="bi bi-printer"></i> Factura
                        </button>
                        <button type="button" class="btn btn-outline-warning d-none" id="fr_btn_comanda" onclick="reimprimirComandaReciente()">
                            <i class="bi bi-printer"></i> Comanda
                        </button>
                        <button type="button" class="btn btn-outline-warning d-none" id="fr_btn_editar" onclick="editarFacturaReciente()">
                            <i class="bi bi-pencil-square"></i> Editar ajuste
                        </button>
                        <button type="button" class="btn btn-outline-secondary d-none" id="fr_btn_cambiar_cliente" onclick="abrirCambiarClienteReciente()">
                            <i class="bi bi-person-gear"></i> Cambiar cliente
                        </button>
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cerrar</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if ($puedeCambiarClienteVenta): ?>
<div class="modal fade" id="modalCambiarClienteReciente" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-sm modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title">Cambiar el cliente de esta venta</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="small text-muted mb-2">Sirve para corregir una venta que quedó a nombre de otra persona. El saldo de crédito de cada cliente se recalcula solo.</p>
                <div class="small mb-1">Ahora: <strong id="fr_cc_cliente_actual"></strong></div>
                <input type="text" class="form-control mb-1" id="fr_cc_buscar" placeholder="Buscar el cliente correcto…" autocomplete="off">
                <ul class="list-group mb-2" id="fr_cc_resultados" style="max-height:160px;overflow-y:auto;"></ul>
                <input type="text" class="form-control" id="fr_cc_motivo" placeholder="Motivo (obligatorio)" maxlength="150">
                <button type="button" class="btn btn-link btn-sm px-0 mt-1" onclick="cambiarClienteVentaReciente(null)">Dejar sin cliente</button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>
<?php endif; ?>

<?php if ($fidelizacionHabilitada): ?>
<?php require_once __DIR__ . "/../parciales/modal_qr_fidelizacion.php"; ?>
<?php endif; ?>

<?php if ($modModificadores): ?>
<div class="modal fade" id="modalModificadores" tabindex="-1">
    <div class="modal-dialog modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalModificadoresTitulo">Personalizar</h5>
                <i class="bi bi-question-circle text-muted ms-1" style="cursor:pointer;" data-pb-ayuda="Marca las opciones en el orden que quieras — las primeras (según cuántas vengan incluidas gratis en cada grupo) quedan gratis, el resto se cobra a su propio precio. Si el producto tiene varios grupos (ej. Toppings y Salsas), cambia de grupo con los botones de arriba — lo que elijas en uno se conserva al pasar al otro."></i>
                <button type="button" class="btn-close ms-auto" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="modalModificadoresTabs" class="mb-2"></div>
                <input type="text" class="form-control form-control-sm mb-2 d-none" id="modalModificadoresFiltro" placeholder="Buscar en este grupo…" autocomplete="off">
                <div id="modalModificadoresBody"></div>
                <div id="modalModificadoresResumen" class="small text-muted border-top pt-2 mt-2"></div>
            </div>
            <div class="modal-footer d-flex justify-content-between align-items-center">
                <span class="text-muted small">Extra a cobrar: <strong id="modalModificadoresExtra">$0</strong></span>
                <button type="button" class="btn btn-primary" onclick="confirmarSeleccionModificadores()">
                    <i class="bi bi-cart-plus"></i> Agregar al carrito
                </button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . "/../parciales/pie.php"; ?>
<script>
const MOD_CAJA_TURNOS = <?php echo $modCajaTurnos ? 'true' : 'false'; ?>;
const MOD_MESAS = <?php echo $modMesas ? 'true' : 'false'; ?>;
const MOD_MODIFICADORES = <?php echo $modModificadores ? 'true' : 'false'; ?>;
const NOMBRE_CAJERO = <?php echo json_encode($usuarioActual['nombre']); ?>;
// let (no const): el admin puede activarlo desde el propio ticket (ver activarQrAutomaticoDesdeTicket).
let FIDELIZACION_QR_AUTO = <?php echo $fidelizacionQrAuto ? 'true' : 'false'; ?>;
const ES_ADMIN = <?php echo $usuarioActual['rol'] === 'admin' ? 'true' : 'false'; ?>;
const CUADRICULA_MODO = <?php echo json_encode(Configuracion::cuadriculaModo()); ?>;
const MESAS_MOSTRAR_CLIENTE = <?php echo Configuracion::mesasMostrarCliente() ? 'true' : 'false'; ?>;
const MOD_PROPINA = <?php echo $modPropina ? 'true' : 'false'; ?>;
const MOD_EXISTENCIAS = <?php echo $modExistencias ? 'true' : 'false'; ?>;
const MOD_PROFESIONALES = <?php echo $modProfesionales ? 'true' : 'false'; ?>;
let profesionalesLista = [];
const PROPINA_PORCENTAJE = <?php echo json_encode(Configuracion::propinaPorcentaje()); ?>;
const PROPINA_POR_DEFECTO = <?php echo Configuracion::propinaPorDefecto() ? 'true' : 'false'; ?>;
// true cuando la persona eligió a mano (escribió un valor, "Sin propina" o el %): ya no se recalcula sola.
let propinaManual = false;
let carrito = [];
let contadorLineaCarrito = 0;
let ventaCliente = null;
let categoriaVentaActivaId = null;
let pagos = [{ metodo_pago: 'efectivo', valor: 0 }];
let mesaActiva = null;
const FIDELIZACION_HABILITADA = <?php echo $fidelizacionHabilitada ? 'true' : 'false'; ?>;
// Promesa con la URL del QR de la venta en curso (o null si no se pudo) —
// se pide EN PARALELO al cobro (ver iniciarQrFidelizacionParaVenta) y el
// ticket la espera en vez de "si llegó, llegó": antes, si el QR tardaba un
// poco más que la venta, la factura salía sin él.
let qrFidelizacionPromesa = null;
// La factura que se está mostrando en el modal, y la promesa que se resuelve
// cuando su QR ya quedó pintado (imprimir espera a que termine).
let ticketActualId = null;
let ticketQrPendiente = null;
// true si el cajero ya generó un QR manual (botón "Generar QR" arriba del
// buscador) DURANTE esta venta en curso — evita que al cobrar se imprima
// un segundo QR automático en el mismo recibo, uno ya fue mostrado/usado.
let qrFidelizacionGeneradoManualmente = false;
// true en cuanto el cajero toca a mano el selector "¿Se descuenta del efectivo de hoy?" del
// modal de Egreso — mientras siga en false, cambiar la fecha ajusta el selector solo (ver abajo).
let egresoTurnoTocadoAMano = false;

function precioFmt(v) { return pb.moneda(v); }

$(function () {
    const hoy = new Date().toISOString().split('T')[0];
    $('#fecha_insumo').val(hoy);

    // Reporte real: un egreso de otro día no debería restarse del efectivo de HOY. Mientras el
    // cajero no toque el selector a mano, elegir una fecha distinta a hoy lo pasa solo a "gasto
    // general" (con aviso); volver a hoy lo regresa a "del turno". En cuanto lo toca a mano, deja
    // de seguir la fecha.
    $('input[name=egreso_afecta_turno]').on('change', function () { egresoTurnoTocadoAMano = true; });
    $('#fecha_insumo').on('change', function () {
        if (egresoTurnoTocadoAMano) return;
        const esHoy = $(this).val() === new Date().toISOString().split('T')[0];
        $(`#egreso_afecta_turno_${esHoy ? 'si' : 'no'}`).prop('checked', true);
        $('#egreso_aviso_fecha_distinta').text(esHoy ? '' : 'Como la fecha es distinta a hoy, se marcó como gasto general: no se restará del efectivo de hoy. Puedes cambiarlo si de verdad quieres que afecte el turno abierto.');
    });

    cargarProfesionales();
    $('#profesional_venta').on('change', function () {
        // Lo elegido aquí se asigna a los servicios que todavía no tienen profesional (los ya asignados no se pisan).
        const id = profesionalPorDefecto();
        if (id === null) return;
        carrito.forEach(function (it) { if (!it.profesional_id) it.profesional_id = id; });
        renderCarrito();
    });

    const $buscar = $('#producto_buscar');
    $buscar.on('input', pb.debounce(function () { buscarProducto(); }, 200));
    $buscar.on('keydown', manejarTeclasBuscador);
    $buscar.trigger('focus');

    $('#efectivo_recibido').on('input', actualizarVuelto);
    $('#ajuste_total, #propina_total').on('input', actualizarTotales);
    $('#propina_total').on('input', function () { propinaManual = true; });
    if (MOD_PROPINA) {
        if (PROPINA_PORCENTAJE > 0) $('#btn_propina_sugerida').text(PROPINA_PORCENTAJE + '%');
        else $('#btn_propina_sugerida').hide();
    }
    $('#cliente_buscar').on('input', pb.debounce(buscarClienteVenta, 200));

    // Sin esto, Enter en el modal de "Registrar egreso" hacía que el
    // navegador enviara el formulario de forma nativa (recarga la página).
    $('#form_carga_insumo').on('submit', function (e) {
        e.preventDefault();
        cargarInsumo();
    });

    if (MOD_CAJA_TURNOS) {
        $.getJSON('/controlador/turno_estado.php', function (r) {
            $('#aviso_turno').toggleClass('d-none', !!r.turno);
        });
    }
    if (MOD_MESAS) {
        cargarListaMesas();
    }
    if ($('#cuadricula_productos').length) {
        if (CUADRICULA_MODO === 'categorias') {
            cargarCategoriasCuadriculaVentas();
        } else {
            cargarProductosDestacados();
        }
    }
});

function cargarProductosDestacados() {
    $.getJSON('/controlador/listar_productos_destacados.php', function (productos) {
        const $grid = $('#cuadricula_productos').empty();
        if (!productos || productos.length === 0) {
            $grid.html('<span class="text-muted small">Sin productos configurados — usa "Configurar" para elegirlos.</span>');
            return;
        }
        productos.forEach(function (p) {
            $grid.append(
                $('<button>', {
                    type: 'button',
                    class: 'pb-tile-producto',
                    html: `${p.nombre_producto}<br><small>${pb.moneda(p.precio_unitario)}</small>`,
                }).on('click', function () { agregarAlCarrito(p); })
            );
        });
    }).fail(() => {
        $('#cuadricula_productos').html('<span class="text-muted small">No se pudo cargar la cuadrícula.</span>');
    });
}

function cargarCategoriasCuadriculaVentas() {
    $.getJSON('/controlador/listar_categorias_cuadricula.php', function (categorias) {
        // #cuadricula_productos trae la clase pb-grid-productos (CSS grid,
        // pensada para el modo de tiles planos) — en modo categorías el
        // contenido es un renglón de botones + el sub-grid de productos
        // debajo, no una cuadrícula de columnas, así que se quita: si no,
        // cada botón queda metido en su propia celda angosta y se apilan.
        const $grid = $('#cuadricula_productos').removeClass('pb-grid-productos').empty();
        if (!categorias || categorias.length === 0) {
            $grid.html('<span class="text-muted small">Sin categorías configuradas — usa "Configurar" para elegirlas.</span>');
            return;
        }
        const $botones = $('<div class="d-flex flex-wrap gap-2"></div>');
        categorias.forEach(function (c) {
            $botones.append(
                $('<button>', {
                    type: 'button',
                    class: 'pb-btn-categoria',
                    text: c.descripcion,
                }).attr('data-categoria', c.id_categoria)
                  .on('click', function () { alternarCategoriaVenta(c.id_categoria); })
            );
        });
        $grid.append($botones);
        $grid.append('<div id="cuadricula_productos_categoria"></div>');
    }).fail(() => {
        $('#cuadricula_productos').html('<span class="text-muted small">No se pudo cargar la cuadrícula.</span>');
    });
}

function alternarCategoriaVenta(idCategoria) {
    const yaActiva = categoriaVentaActivaId === idCategoria;
    categoriaVentaActivaId = yaActiva ? null : idCategoria;

    $('#cuadricula_productos [data-categoria]').removeClass('pb-btn-categoria-activa');
    const $contenedor = $('#cuadricula_productos_categoria').empty();
    if (yaActiva) return;

    $(`#cuadricula_productos [data-categoria="${idCategoria}"]`).addClass('pb-btn-categoria-activa');
    $contenedor.html('<span class="text-muted small">Cargando…</span>');

    $.getJSON('/controlador/listar_productos_categoria_cuadricula.php', { categoria: idCategoria }, function (productos) {
        // El cajero pudo haber tocado otra categoría mientras esta cargaba.
        if (categoriaVentaActivaId !== idCategoria) return;
        $contenedor.empty();
        if (!productos || productos.length === 0) {
            $contenedor.html('<span class="text-muted small">Esta categoría no tiene productos disponibles.</span>');
            return;
        }
        const $subgrid = $('<div class="pb-grid-productos mt-2"></div>');
        productos.forEach(function (p) {
            $subgrid.append(
                $('<button>', {
                    type: 'button',
                    class: 'pb-tile-producto',
                    html: `${p.nombre_producto}<br><small>${pb.moneda(p.precio_unitario)}</small>`,
                }).on('click', function () { agregarAlCarrito(p); })
            );
        });
        $contenedor.append($subgrid);
    }).fail(() => {
        if (categoriaVentaActivaId !== idCategoria) return;
        $contenedor.html('<span class="text-muted small">No se pudo cargar la categoría.</span>');
    });
}

function seleccionarCliente(cliente) {
    ventaCliente = cliente;
    $('#cliente_buscar').val('');
    $('#cliente_resultados').empty();
    // Cualquier respuesta de búsqueda que siga en camino ya no debe pintarse.
    busquedaClienteVentaId++;
    $('#cliente_seleccionado').empty()
        .append('Cliente: ')
        .append($('<strong></strong>').text(ventaCliente.nombre))
        .append(' ')
        .append(
            $('<a href="#" class="ms-1">quitar</a>').on('click', function (e) {
                e.preventDefault();
                ventaCliente = null;
                $('#cliente_seleccionado').empty();
            })
        );
}

// Cada búsqueda lleva un número: si llega la respuesta de una búsqueda anterior
// cuando ya se escribió otra cosa, se descarta. Sin esto, dos respuestas
// distintas se acumulaban en la misma lista y cada fila terminaba con varios
// manejadores de clic ligados a listas distintas — al hacer clic en "Lina"
// podía quedar seleccionada otra persona (la que ocupaba esa posición en la
// lista más reciente).
let busquedaClienteVentaId = 0;

function buscarClienteVenta() {
    const termino = $('#cliente_buscar').val().trim();
    const idBusqueda = ++busquedaClienteVentaId;
    const $resultados = $('#cliente_resultados').empty();
    if (termino.length < 1) return;

    $.ajax({
        type: 'POST',
        url: '/controlador/buscar_cliente.php',
        data: { termino: termino },
        dataType: 'json',
        success: function (clientes) {
            if (idBusqueda !== busquedaClienteVentaId) return;
            $resultados.empty();
            if (clientes.length === 0) {
                $resultados.append('<li class="list-group-item text-muted">No se encontraron clientes.</li>');
            } else {
                clientes.forEach(function (c) {
                    const limite = c.limite_credito > 0 ? ` / límite ${pb.moneda(c.limite_credito)}` : '';
                    // El cliente va dentro del propio manejador (no un índice en una lista
                    // compartida) y el nombre entra como texto, no como HTML.
                    $('<li class="list-group-item" style="cursor:pointer;"></li>')
                        .text(`${c.nombre} — debe ${pb.moneda(c.saldo)}${limite}`)
                        .on('click', function () { seleccionarCliente(c); })
                        .appendTo($resultados);
                });
            }
            $('<li class="list-group-item text-primary" style="cursor:pointer;"></li>')
                .append('<i class="bi bi-plus-lg"></i> ')
                .append(document.createTextNode('Crear cliente nuevo: "' + termino + '"'))
                .on('click', function () { crearClienteRapido(termino); })
                .appendTo($resultados);
        }
    });
}

function crearClienteRapido(nombre) {
    $.ajax({
        type: 'POST',
        url: '/controlador/crear_cliente_rapido.php',
        data: { nombre: nombre, csrf_token: pb.csrfToken() },
        dataType: 'json',
        success: function (r) {
            if (r.ok) {
                pb.success('Cliente creado.');
                seleccionarCliente(r);
            } else {
                pb.error(r.error || 'No se pudo crear el cliente.');
            }
        },
        error: function () { pb.error('Error de conexión.'); }
    });
}

let resultadosActuales = [];
let indiceResaltado = -1;

function buscarProducto() {
    const termino = $('#producto_buscar').val().trim();

    if (termino.length < 1) {
        resultadosActuales = [];
        indiceResaltado = -1;
        renderResultadosBusqueda();
        return;
    }

    $.ajax({
        type: 'POST',
        url: '/controlador/buscar_producto.php',
        data: { producto_buscar: termino, contexto: 'venta' },
        dataType: 'json',
        success: function (productos) {
            if (/^\d+$/.test(termino) && productos.length === 1 && productos[0].codigo === termino) {
                agregarAlCarrito(productos[0]);
                resultadosActuales = [];
                indiceResaltado = -1;
                $('#producto_buscar').val('');
                renderResultadosBusqueda();
                return;
            }

            resultadosActuales = productos;
            indiceResaltado = productos.length > 0 ? 0 : -1;
            renderResultadosBusqueda();
        },
        error: function () {
            pb.error('Error al buscar producto.');
        }
    });
}

function renderResultadosBusqueda() {
    const $resultados = $('#productos_resultados').empty();

    if (resultadosActuales.length === 0) {
        if ($('#producto_buscar').val().trim().length > 0) {
            $resultados.append('<li class="list-group-item text-muted">No se encontraron productos.</li>');
        }
        return;
    }

    resultadosActuales.forEach(function (p, indice) {
        const infoPaquete = p.unidades_por_paquete > 1 && p.precio_paquete
            ? ` · Paquete x${p.unidades_por_paquete}: ${precioFmt(p.precio_paquete)}`
            : '';
        const existenciaTexto = !MOD_EXISTENCIAS ? '' : (p.controla_stock == 0 ? 'Se prepara al momento' : `Existencia: ${p.cantidad_existente}`);
        $resultados.append(
            `<li class="list-group-item ${indice === indiceResaltado ? 'pb-resultado-activo' : ''}" style="cursor:pointer;" data-indice="${indice}">
                <div class="d-flex justify-content-between">
                    <span>${p.nombre_producto}${p.promocion_nombre ? ` <span class="badge text-bg-warning">${p.promocion_nombre}</span>` : ''}</span>
                    <strong>${precioFmt(p.precio_unitario)}</strong>
                </div>
                <small class="text-muted">${existenciaTexto}${infoPaquete}</small>
            </li>`
        );
    });

    $resultados.find('li[data-indice]').on('mouseenter', function () {
        // Ojo: NO se puede llamar renderResultadosBusqueda() aquí — eso
        // destruye y vuelve a crear el <li> justo debajo del cursor a
        // mitad de un clic (o un toque, en pantalla táctil), y el clic
        // se pierde porque el elemento original ya no existe cuando el
        // navegador intenta completar el evento. Con solo cambiar la
        // clase, el elemento nunca se destruye.
        indiceResaltado = parseInt($(this).data('indice'), 10);
        $resultados.find('li[data-indice]').removeClass('pb-resultado-activo');
        $(this).addClass('pb-resultado-activo');
    }).on('click', function () {
        seleccionarResultado(parseInt($(this).data('indice'), 10));
    });
}

function seleccionarResultado(indice) {
    const producto = resultadosActuales[indice];
    if (!producto) return;
    agregarAlCarrito(producto);
    resultadosActuales = [];
    indiceResaltado = -1;
    $('#producto_buscar').val('').trigger('focus');
    renderResultadosBusqueda();
}

function manejarTeclasBuscador(e) {
    if (e.key === 'ArrowDown') {
        if (resultadosActuales.length === 0) return;
        e.preventDefault();
        indiceResaltado = Math.min(indiceResaltado + 1, resultadosActuales.length - 1);
        renderResultadosBusqueda();
    } else if (e.key === 'ArrowUp') {
        if (resultadosActuales.length === 0) return;
        e.preventDefault();
        indiceResaltado = Math.max(indiceResaltado - 1, 0);
        renderResultadosBusqueda();
    } else if (e.key === 'Enter' || e.key === ' ') {
        if (resultadosActuales.length === 0 || indiceResaltado < 0) return;
        e.preventDefault();
        seleccionarResultado(indiceResaltado);
    }
}

function agregarAlCarrito(producto) {
    // Carrito vacío + actualización crítica pendiente = esto sería iniciar
    // una venta NUEVA — se bloquea (agregar a un carrito YA en curso sigue
    // permitido, eso hay que dejarlo terminar).
    if (carrito.length === 0 && window.PB_ACTUALIZACION_FORZADA) {
        pb.error('Hay una actualización crítica pendiente — espera a que termine para iniciar una venta nueva.');
        return;
    }

    // Un producto por peso (báscula) no pasa por el selector de
    // modificadores en esta versión — se pide el peso (y el precio por
    // kilo, si es variable) y se agrega directo, siempre en su propia línea.
    if (producto.se_vende_por_peso == 1) {
        abrirPesoProducto(producto);
        return;
    }

    if (!MOD_MODIFICADORES) {
        agregarAlCarritoDirecto(producto, [], false);
        return;
    }

    // Antes de agregar, se consulta si el producto tiene grupos de
    // modificadores configurados (ej. acompañamientos, extras) — si los tiene, se abre el
    // selector y recién ahí se agrega al carrito con lo que se haya
    // elegido; si no tiene, se agrega directo como siempre.
    $.ajax({
        type: 'POST',
        url: '/controlador/obtener_modificadores.php',
        data: { id_producto: producto.id_producto },
        dataType: 'json',
        success: function (grupos) {
            if (grupos && grupos.length > 0) {
                abrirModalModificadores(producto, grupos, [], null);
            } else {
                agregarAlCarritoDirecto(producto, [], false);
            }
        },
        error: function () { agregarAlCarritoDirecto(producto, [], false); }
    });
}

/**
 * Cada unidad con modificadores propios queda en su PROPIA línea del
 * carrito (nunca se fusiona con otra) — así no hay ambigüedad sobre a cuál
 * producto pertenece cada opción cuando hay más de uno en el carrito. Un
 * producto sin modificadores sigue fusionándose con su línea existente
 * como siempre (solo sube la cantidad).
 *
 * `tieneModificadoresConfigurados` no es lo mismo que "modificadores no
 * vacío" — un producto puede tener grupos configurados y aun así el
 * cliente eligió cero opciones. Se guarda aparte para saber si vale la
 * pena mostrarle al cajero el botón de editar opciones en esa línea.
 */
function agregarAlCarritoDirecto(producto, modificadores, tieneModificadoresConfigurados) {
    const tieneModificadores = modificadores && modificadores.length > 0;
    const profesionalNuevo = MOD_PROFESIONALES ? profesionalPorDefecto() : null;
    const existente = !tieneModificadores
        ? carrito.find(p => p.id_producto == producto.id_producto && p.presentacion === 'unidad' && (!p.modificadores || p.modificadores.length === 0)
            && (p.profesional_id || null) === profesionalNuevo)
        : null;

    if (existente) {
        existente.cantidad += 1;
    } else {
        contadorLineaCarrito += 1;
        carrito.push({
            _lineaId: contadorLineaCarrito,
            id_producto: producto.id_producto,
            nombre_producto: producto.nombre_producto,
            presentacion: 'unidad',
            precio_unitario: parseFloat(producto.precio_unitario),
            precio_paquete: producto.precio_paquete !== null ? parseFloat(producto.precio_paquete) : null,
            unidades_por_paquete: parseInt(producto.unidades_por_paquete, 10) || 1,
            cantidad: 1,
            cantidad_existente: producto.cantidad_existente,
            controla_stock: producto.controla_stock != 0,
            tasa_iva: parseFloat(producto.tasa_iva) || 0,
            modificadores: modificadores || [],
            tieneModificadoresConfigurados: !!tieneModificadoresConfigurados,
            profesional_id: profesionalNuevo,
        });
    }
    renderCarrito();
}

// ---- Profesionales (módulo "profesionales") ----
function cargarProfesionales() {
    if (!MOD_PROFESIONALES) return;
    $.getJSON('/controlador/listar_profesionales.php', function (lista) {
        profesionalesLista = lista || [];
        const $sel = $('#profesional_venta');
        const actual = $sel.val();
        $sel.find('option:not(:first)').remove();
        profesionalesLista.forEach(function (p) {
            $('<option></option>').val(p.id_profesional).text(p.nombre).appendTo($sel);
        });
        if (actual) $sel.val(actual);
    });
}

function profesionalPorDefecto() {
    const v = $('#profesional_venta').val();
    return v ? parseInt(v, 10) : null;
}

function nombreProfesional(id) {
    const p = profesionalesLista.find(x => parseInt(x.id_profesional, 10) === parseInt(id, 10));
    return p ? p.nombre : null;
}

function asignarProfesionalLinea(lineaId) {
    const item = carrito.find(p => p._lineaId === lineaId);
    if (!item) return;
    const $lista = $('#lista_profesionales_linea').empty();
    profesionalesLista.forEach(function (p) {
        const activo = parseInt(item.profesional_id, 10) === parseInt(p.id_profesional, 10);
        $('<button type="button" class="btn"></button>')
            .addClass(activo ? 'btn-primary' : 'btn-outline-secondary')
            .text(p.nombre + ' · ' + p.comision_porcentaje + '%')
            .on('click', function () { fijarProfesionalLinea(lineaId, parseInt(p.id_profesional, 10)); })
            .appendTo($lista);
    });
    $('<button type="button" class="btn btn-link btn-sm"></button>').text('Sin profesional')
        .on('click', function () { fijarProfesionalLinea(lineaId, null); })
        .appendTo($lista);
    bootstrap.Modal.getOrCreateInstance('#modalProfesionalLinea').show();
}

function fijarProfesionalLinea(lineaId, idProfesional) {
    const item = carrito.find(p => p._lineaId === lineaId);
    if (item) item.profesional_id = idProfesional;
    bootstrap.Modal.getInstance(document.getElementById('modalProfesionalLinea'))?.hide();
    renderCarrito();
}

/** Reabre el selector para una línea ya agregada, con lo que ya tenía marcado — para poder cambiarlo. */
function editarModificadoresLinea(lineaId) {
    const item = carrito.find(p => p._lineaId === lineaId);
    if (!item) return;

    $.ajax({
        type: 'POST',
        url: '/controlador/obtener_modificadores.php',
        data: { id_producto: item.id_producto },
        dataType: 'json',
        success: function (grupos) {
            if (!grupos || grupos.length === 0) return;
            abrirModalModificadores(
                { id_producto: item.id_producto, nombre_producto: item.nombre_producto },
                grupos,
                item.modificadores || [],
                lineaId
            );
        }
    });
}

/**
 * Producto por peso (báscula): pide el peso (kg) y, si el catálogo dejó el
 * precio en $0 (varía a diario, ej. carnes/frutas), también pide el precio
 * por kilo de HOY — nunca se confía en un precio manual si ya hay uno fijo
 * configurado en el producto. Siempre en su propia línea, nunca se fusiona.
 */
async function abrirPesoProducto(producto) {
    const pesoTexto = await pb.preguntar(`Peso de "${producto.nombre_producto}" en kg (ej. 4.5):`, '', 'number');
    if (pesoTexto === null) return;
    const peso = parseFloat(pesoTexto);
    if (!peso || peso <= 0) {
        pb.error('Escribe un peso válido en kilos.');
        return;
    }

    let precioKg = parseFloat(producto.precio_unitario) || 0;
    if (precioKg <= 0) {
        const precioTexto = await pb.preguntar(`Precio por kilo HOY para "${producto.nombre_producto}":`, '');
        if (precioTexto === null) return;
        precioKg = parseFloat(precioTexto) || 0;
        if (precioKg <= 0) {
            pb.error('Escribe un precio por kilo válido.');
            return;
        }
    }

    contadorLineaCarrito += 1;
    carrito.push({
        _lineaId: contadorLineaCarrito,
        id_producto: producto.id_producto,
        nombre_producto: producto.nombre_producto,
        presentacion: 'peso',
        precio_unitario: precioKg,
        precio_paquete: null,
        unidades_por_paquete: 1,
        cantidad: Math.round(peso * 1000) / 1000,
        cantidad_existente: 0,
        controla_stock: false,
        tasa_iva: parseFloat(producto.tasa_iva) || 0,
        modificadores: [],
        tieneModificadoresConfigurados: false,
    });
    renderCarrito();
}

async function editarPesoLinea(lineaId) {
    const item = carrito.find(p => p._lineaId === lineaId);
    if (!item) return;

    const pesoTexto = await pb.preguntar(`Nuevo peso para "${item.nombre_producto}" en kg:`, item.cantidad);
    if (pesoTexto === null) return;
    const peso = parseFloat(pesoTexto);
    if (!peso || peso <= 0) {
        pb.error('Escribe un peso válido en kilos.');
        return;
    }
    item.cantidad = Math.round(peso * 1000) / 1000;
    renderCarrito();
}

function precioLinea(item) {
    return item.presentacion === 'paquete' ? item.precio_paquete : item.precio_unitario;
}

/** Suma de los modificadores COBRADOS de la línea (los incluidos gratis no suman). */
function extraModificadoresLinea(item) {
    return (item.modificadores || []).reduce((acc, m) => acc + (m.incluido ? 0 : m.precio_cobrado), 0);
}

function precioLineaConExtras(item) {
    return precioLinea(item) + extraModificadoresLinea(item);
}

/** Monto real del descuento manual de esta línea — nunca supera el total bruto de la línea. */
function descuentoLineaMonto(item) {
    const bruto = item.cantidad * precioLineaConExtras(item);
    if (item.descuento_tipo === 'porcentaje' && item.descuento_valor > 0) {
        return bruto * (Math.min(100, item.descuento_valor) / 100);
    }
    if (item.descuento_tipo === 'fijo' && item.descuento_valor > 0) {
        return Math.min(item.descuento_valor, bruto);
    }
    return 0;
}

function totalLineaFinal(item) {
    return (item.cantidad * precioLineaConExtras(item)) - descuentoLineaMonto(item);
}

/** Botón "Descuento" del carrito — un solo prompt (ej. "15%" o "2000"); vacío quita el descuento. */
async function editarDescuentoLinea(lineaId) {
    const item = carrito.find(p => p._lineaId === lineaId);
    if (!item) return;

    const actual = item.descuento_tipo === 'porcentaje' ? `${item.descuento_valor}%`
        : item.descuento_tipo === 'fijo' ? `${item.descuento_valor}`
        : '';
    const respuesta = await pb.preguntar(`Descuento para "${item.nombre_producto}" (ej. 15% o 2000). Vacío = sin descuento:`, actual);
    if (respuesta === null) return;

    const texto = respuesta.trim();
    if (texto === '') {
        item.descuento_tipo = null;
        item.descuento_valor = 0;
    } else if (texto.endsWith('%')) {
        item.descuento_tipo = 'porcentaje';
        item.descuento_valor = Math.max(0, parseFloat(texto) || 0);
    } else {
        item.descuento_tipo = 'fijo';
        item.descuento_valor = Math.max(0, parseFloat(texto) || 0);
    }
    renderCarrito();
}

function unidadesBaseLinea(item) {
    return item.presentacion === 'paquete' ? item.cantidad * item.unidades_por_paquete : item.cantidad;
}

function cambiarCantidad(lineaId, delta) {
    const item = carrito.find(p => p._lineaId === lineaId);
    if (!item) return;
    item.cantidad = Math.max(1, item.cantidad + delta);
    renderCarrito();
}

function cambiarPresentacion(lineaId, nuevaPresentacion) {
    const item = carrito.find(p => p._lineaId === lineaId);
    if (!item) return;
    item.presentacion = nuevaPresentacion;
    renderCarrito();
}

function eliminarProducto(lineaId) {
    carrito = carrito.filter(p => p._lineaId !== lineaId);
    renderCarrito();
}

<?php if ($modModificadores): ?>
// ---------- Selector de modificadores (ej. acompañamientos, extras) al agregar al carrito ----------
let modalModificadoresContexto = null;

function abrirModalModificadores(producto, grupos, seleccionActual, lineaIdEditar) {
    modalModificadoresContexto = {
        producto, grupos, ordenPorGrupo: {}, lineaIdEditar: lineaIdEditar || null,
        // Con varios grupos (ej. Toppings y Salsas) se muestra uno a la vez
        // con pestañas — lo elegido en cada uno se conserva al cambiar.
        grupoActivoId: grupos[0].id_grupo,
        filtro: '',
    };
    $('#modalModificadoresFiltro').val('');
    grupos.forEach(function (g) { modalModificadoresContexto.ordenPorGrupo[g.id_grupo] = []; });

    // Al reabrir para editar, se respeta el orden que ya tenía esa línea
    // (de eso depende cuáles quedan gratis).
    (seleccionActual || []).forEach(function (sel) {
        const grupo = grupos.find(g => g.modificadores.some(m => m.id_modificador === sel.id_modificador));
        if (grupo) modalModificadoresContexto.ordenPorGrupo[grupo.id_grupo].push(sel.id_modificador);
    });

    $('#modalModificadoresTitulo').text('Personalizar — ' + producto.nombre_producto);
    renderModalModificadores();
    bootstrap.Modal.getOrCreateInstance('#modalModificadores').show();
}

function cambiarGrupoModal(idGrupo) {
    const ctx = modalModificadoresContexto;
    if (!ctx) return;
    ctx.grupoActivoId = idGrupo;
    ctx.filtro = '';
    $('#modalModificadoresFiltro').val('');
    renderModalModificadores();
}

$(function () {
    $('#modalModificadoresFiltro').on('input', function () {
        if (!modalModificadoresContexto) return;
        modalModificadoresContexto.filtro = this.value.toLowerCase().trim();
        renderModalModificadores();
    });
});

function renderModalModificadores() {
    const ctx = modalModificadoresContexto;
    const $body = $('#modalModificadoresBody').empty();
    const $tabs = $('#modalModificadoresTabs').empty();
    const $resumen = $('#modalModificadoresResumen').empty();
    let extra = 0;

    // Lo extra a cobrar y el resumen abarcan TODOS los grupos, aunque solo
    // uno se vea a la vez.
    const resumenPartes = [];
    ctx.grupos.forEach(function (grupo) {
        const orden = ctx.ordenPorGrupo[grupo.id_grupo];
        const nombres = [];
        orden.forEach(function (idMod, posicion) {
            const op = grupo.modificadores.find(m => m.id_modificador === idMod);
            if (!op) return;
            nombres.push(op.nombre);
            if (posicion >= grupo.incluidos_gratis) extra += op.precio_adicional;
        });
        if (nombres.length > 0) resumenPartes.push({ grupo: grupo.nombre, nombres });
    });

    if (ctx.grupos.length > 1) {
        const $nav = $('<div class="d-flex flex-wrap gap-1"></div>');
        ctx.grupos.forEach(function (grupo) {
            const cantidad = ctx.ordenPorGrupo[grupo.id_grupo].length;
            const activo = grupo.id_grupo === ctx.grupoActivoId;
            const $boton = $(`<button type="button" class="btn btn-sm ${activo ? 'btn-primary' : 'btn-outline-secondary'}"></button>`);
            $boton.text(grupo.nombre);
            if (cantidad > 0) {
                $boton.append($(`<span class="badge ms-1 ${activo ? 'text-bg-light' : 'text-bg-secondary'}"></span>`).text(cantidad));
            }
            if (grupo.minimo > cantidad) {
                $boton.append($('<span class="badge ms-1 text-bg-warning"></span>').text('obligatorio'));
            }
            $boton.on('click', function () { cambiarGrupoModal(grupo.id_grupo); });
            $nav.append($boton);
        });
        $tabs.append($nav);
    }

    const grupo = ctx.grupos.find(g => g.id_grupo === ctx.grupoActivoId) || ctx.grupos[0];
    const orden = ctx.ordenPorGrupo[grupo.id_grupo];

    // Un grupo largo (ej. 20 salsas) se puede filtrar escribiendo.
    $('#modalModificadoresFiltro').toggleClass('d-none', grupo.modificadores.length <= 8);

    const $encabezado = $('<div class="fw-semibold mb-1"></div>');
    $encabezado.text(grupo.nombre + ' ');
    $encabezado.append(
        $('<span class="text-muted small fw-normal"></span>').text(
            `(${grupo.incluidos_gratis} incluido${grupo.incluidos_gratis === 1 ? '' : 's'} gratis` +
            `${grupo.maximo ? `, máx. ${grupo.maximo}` : ''}` +
            `${grupo.minimo ? `, mín. ${grupo.minimo}` : ''}` +
            `, elegidos ${orden.length})`
        )
    );
    $body.append($encabezado);

    const $opciones = $('<div class="d-flex flex-column gap-1"></div>');
    const visibles = grupo.modificadores.filter(op => !ctx.filtro || op.nombre.toLowerCase().includes(ctx.filtro));
    if (visibles.length === 0) {
        $opciones.append('<div class="text-muted small">Ninguna opción coincide con la búsqueda.</div>');
    }
    visibles.forEach(function (op) {
        const posicion = orden.indexOf(op.id_modificador);
        const marcado = posicion !== -1;
        const incluido = marcado && posicion < grupo.incluidos_gratis;
        let precioTexto;
        if (marcado) {
            precioTexto = incluido
                ? '<span class="text-success small">incluido</span>'
                : `<span class="text-muted small">+${pb.moneda(op.precio_adicional)}</span>`;
        } else {
            precioTexto = op.precio_adicional > 0
                ? `<span class="text-muted small">+${pb.moneda(op.precio_adicional)}</span>`
                : '<span class="text-muted small">gratis si entra en el cupo</span>';
        }

        const $fila = $(`
            <label class="d-flex align-items-center gap-2 border rounded px-2 py-1" style="cursor:pointer;">
                <input type="checkbox" class="form-check-input m-0" ${marcado ? 'checked' : ''} onchange="alternarModificador(${grupo.id_grupo}, ${op.id_modificador})">
                <span class="flex-grow-1 pb-mod-nombre"></span>
                ${precioTexto}
            </label>
        `);
        $fila.find('.pb-mod-nombre').text(op.nombre);
        $opciones.append($fila);
    });
    $body.append($opciones);

    if (resumenPartes.length === 0) {
        $resumen.text('Todavía no has elegido nada.');
    } else {
        resumenPartes.forEach(function (parte, i) {
            if (i > 0) $resumen.append(document.createTextNode(' · '));
            $resumen.append($('<strong></strong>').text(parte.grupo + ': '));
            $resumen.append(document.createTextNode(parte.nombres.join(', ')));
        });
    }

    $('#modalModificadoresExtra').text(pb.moneda(extra));
}

function alternarModificador(idGrupo, idModificador) {
    const ctx = modalModificadoresContexto;
    const grupo = ctx.grupos.find(g => g.id_grupo === idGrupo);
    const orden = ctx.ordenPorGrupo[idGrupo];
    const posicion = orden.indexOf(idModificador);

    if (posicion === -1) {
        if (grupo.maximo && orden.length >= grupo.maximo) {
            pb.error(`"${grupo.nombre}" acepta máximo ${grupo.maximo} opción(es).`);
            renderModalModificadores();
            return;
        }
        // Se agrega al FINAL del orden de selección — de eso depende cuáles
        // quedan gratis (los primeros "incluidos_gratis" elegidos).
        orden.push(idModificador);
    } else {
        orden.splice(posicion, 1);
    }
    renderModalModificadores();
}

function confirmarSeleccionModificadores() {
    const ctx = modalModificadoresContexto;
    if (!ctx) return;

    for (const grupo of ctx.grupos) {
        const orden = ctx.ordenPorGrupo[grupo.id_grupo];
        if (orden.length < grupo.minimo) {
            // Lleva al cajero directo al grupo que falta (con varios grupos
            // podría estar viendo otra pestaña).
            cambiarGrupoModal(grupo.id_grupo);
            pb.error(`Selecciona al menos ${grupo.minimo} opción(es) de "${grupo.nombre}".`);
            return;
        }
    }

    const seleccionados = [];
    ctx.grupos.forEach(function (grupo) {
        const orden = ctx.ordenPorGrupo[grupo.id_grupo];
        orden.forEach(function (idModificador, indice) {
            const op = grupo.modificadores.find(m => m.id_modificador === idModificador);
            const incluido = indice < grupo.incluidos_gratis;
            seleccionados.push({
                id_modificador: idModificador,
                nombre: op.nombre,
                precio_cobrado: incluido ? 0 : op.precio_adicional,
                incluido: incluido,
            });
        });
    });

    bootstrap.Modal.getInstance(document.getElementById('modalModificadores'))?.hide();

    if (ctx.lineaIdEditar !== null) {
        const item = carrito.find(p => p._lineaId === ctx.lineaIdEditar);
        if (item) {
            item.modificadores = seleccionados;
            renderCarrito();
        }
    } else {
        agregarAlCarritoDirecto(ctx.producto, seleccionados, true);
    }
    modalModificadoresContexto = null;
}
<?php endif; ?>

function vaciarCarrito() {
    carrito = [];
    ventaCliente = null;
    mesaActiva = null;
    qrFidelizacionGeneradoManualmente = false;
    pagos = [{ metodo_pago: 'efectivo', valor: 0 }];
    $('#ajuste_total, #propina_total').val('');
    $('#profesional_venta').val('');
    propinaManual = false;
    $('#cliente_seleccionado').empty();
    $('#mesa_activa_aviso').addClass('d-none').empty();
    $('#fila_guardar_mesa').addClass('d-none');
    $('#btn_confirmar_venta').html('<i class="bi bi-cart-check"></i> Confirmar venta');
    renderCarrito();
}

function renderCarrito() {
    const $lista = $('#carrito_compras_lista').empty();
    if (carrito.length === 0) {
        $lista.append('<li class="list-group-item text-muted text-center" id="carrito_vacio">El carrito está vacío</li>');
        $('#descripcion_venta, #fila_efectivo, #fila_ajuste, #fila_propina, #fila_pagos').hide();
        $('#fila_subtotal').addClass('d-none');
        $('.pb-ventas-card').addClass('pb-ventas-card-vacio');
        $('#carrito_total').text(pb.moneda(0));
        return;
    }

    carrito.forEach(function (item) {
        const esPeso = item.presentacion === 'peso';
        const totalLinea = totalLineaFinal(item);
        const descuentoMonto = descuentoLineaMonto(item);
        const excedeStock = MOD_EXISTENCIAS && item.controla_stock && unidadesBaseLinea(item) > item.cantidad_existente;
        const tienePaquete = !esPeso && item.unidades_por_paquete > 1 && item.precio_paquete !== null;
        const selectorPresentacion = tienePaquete ? `
            <select class="form-select form-select-sm pb-cart-item-presentacion" onchange="cambiarPresentacion(${item._lineaId}, this.value)">
                <option value="unidad" ${item.presentacion === 'unidad' ? 'selected' : ''}>Unidad</option>
                <option value="paquete" ${item.presentacion === 'paquete' ? 'selected' : ''}>Paquete x${item.unidades_por_paquete}</option>
            </select>` : '';

        // Todo en una sola línea muted, truncada con "…" si no cabe — el
        // nombre completo y el detalle siguen disponibles en el title (hover).
        const textoModificadores = (item.modificadores || []).map(m => m.incluido ? m.nombre : `${m.nombre} +${pb.moneda(m.precio_cobrado)}`).join(', ');
        const textoDescuento = descuentoMonto > 0
            ? (item.descuento_tipo === 'porcentaje' ? `-${item.descuento_valor}%` : `-${pb.moneda(descuentoMonto)}`)
            : null;
        const partesMeta = [
            `${precioFmt(precioLineaConExtras(item))} c/${esPeso ? 'kg' : (item.presentacion === 'paquete' ? 'paquete' : 'u')}`,
            textoModificadores || null,
            textoDescuento,
            excedeStock ? `¡supera existencia (${item.cantidad_existente} u.)!` : null,
            (MOD_PROFESIONALES && item.profesional_id && nombreProfesional(item.profesional_id)) ? `con ${nombreProfesional(item.profesional_id)}` : null,
        ].filter(Boolean);
        const metaTexto = partesMeta.join(' · ');

        const controlesCantidad = esPeso ? `
            <span class="pb-cart-item-peso">${item.cantidad} kg</span>
            <button class="pb-btn-icon" onclick="editarPesoLinea(${item._lineaId})" title="Editar peso"><i class="bi bi-pencil"></i></button>
        ` : `
            <div class="pb-cart-item-qty">
                <button class="btn btn-outline-secondary pb-cart-item-qty-btn" onclick="cambiarCantidad(${item._lineaId}, -1)">−</button>
                <input type="number" class="form-control pb-cart-item-qty-input" value="${item.cantidad}" min="1"
                       onchange="fijarCantidad(${item._lineaId}, this.value)">
                <button class="btn btn-outline-secondary pb-cart-item-qty-btn" onclick="cambiarCantidad(${item._lineaId}, 1)">+</button>
            </div>
        `;

        $lista.append(`
            <li class="list-group-item pb-cart-item ${excedeStock ? 'pb-cart-item-warn' : ''}">
                <div class="pb-cart-item-info" title="${item.nombre_producto}${textoModificadores ? ' — ' + textoModificadores : ''}">
                    <div class="pb-cart-item-nombre">${item.nombre_producto}</div>
                    <div class="pb-cart-item-meta">${metaTexto}</div>
                </div>
                ${selectorPresentacion}
                ${controlesCantidad}
                <strong class="pb-cart-item-total">${precioFmt(totalLinea)}</strong>
                <button class="pb-btn-icon ${descuentoMonto > 0 ? 'pb-btn-icon-warn' : ''}" onclick="editarDescuentoLinea(${item._lineaId})" title="Descuento">
                    <i class="bi bi-percent"></i>
                </button>
                ${MOD_PROFESIONALES ? `<button class="pb-btn-icon ${item.profesional_id ? 'pb-btn-icon-edit' : ''}" onclick="asignarProfesionalLinea(${item._lineaId})" title="Profesional"><i class="bi bi-person-badge"></i></button>` : ''}
                ${item.tieneModificadoresConfigurados ? `<button class="pb-btn-icon pb-btn-icon-edit" onclick="editarModificadoresLinea(${item._lineaId})" title="Editar opciones"><i class="bi bi-pencil"></i></button>` : ''}
                <button class="pb-btn-icon pb-btn-icon-delete" onclick="eliminarProducto(${item._lineaId})" title="Quitar">
                    <i class="bi bi-x-lg"></i>
                </button>
            </li>
        `);
    });

    $('#descripcion_venta, #fila_ajuste, #fila_propina, #fila_pagos').show();
    $('#fila_subtotal').removeClass('d-none');
    $('.pb-ventas-card').removeClass('pb-ventas-card-vacio');
    // Con "aplicar propina por defecto" (Configuración) se sugiere sola mientras nadie la haya tocado.
    if (MOD_PROPINA && PROPINA_POR_DEFECTO && !propinaManual && PROPINA_PORCENTAJE > 0) {
        $('#propina_total').val(Math.round(subtotalCarrito() * PROPINA_PORCENTAJE / 100));
    }
    actualizarTotales();
}

function fijarCantidad(lineaId, valor) {
    const cantidad = Math.max(1, parseInt(valor, 10) || 1);
    const item = carrito.find(p => p._lineaId === lineaId);
    if (item) item.cantidad = cantidad;
    renderCarrito();
}

function subtotalCarrito() {
    return carrito.reduce((acc, item) => acc + totalLineaFinal(item), 0);
}

function totalConAjuste() {
    const ajuste = parseFloat($('#ajuste_total').val()) || 0;
    const propina = parseFloat($('#propina_total').val()) || 0;
    return Math.max(0, subtotalCarrito() + ajuste + propina);
}

function aplicarPropinaSugerida(porcentaje) {
    const sugerida = porcentaje > 0 ? Math.round(subtotalCarrito() * porcentaje) : '';
    propinaManual = true; // "Sin propina" o el %: es una decisión de la persona, no se vuelve a pisar
    $('#propina_total').val(sugerida);
    actualizarTotales();
}

function actualizarTotales() {
    const subtotal = subtotalCarrito();
    const ajuste = parseFloat($('#ajuste_total').val()) || 0;
    const propina = parseFloat($('#propina_total').val()) || 0;
    $('#carrito_subtotal').text(pb.moneda(subtotal));
    $('#carrito_total').text(pb.moneda(Math.max(0, subtotal + ajuste + propina)));

    if (pagos.length === 1) {
        pagos[0].valor = totalConAjuste();
    }
    renderPagos();
}

function renderPagos() {
    const $lista = $('#lista_pagos').empty();
    const metodosDisponibles = ['efectivo', 'tarjeta', 'transferencia'<?php echo $modClientesCredito ? ", 'credito'" : ''; ?>];
    const etiquetas = { efectivo: 'Efectivo', tarjeta: 'Tarjeta', transferencia: 'Transferencia', credito: 'Crédito (fiar)' };

    pagos.forEach(function (p, i) {
        const opciones = metodosDisponibles.map(m => `<option value="${m}" ${p.metodo_pago === m ? 'selected' : ''}>${etiquetas[m]}</option>`).join('');
        const botonQuitar = pagos.length > 1
            ? `<button type="button" class="pb-btn-icon pb-btn-icon-delete" onclick="quitarLineaPago(${i})" title="Quitar"><i class="bi bi-x-lg"></i></button>`
            : '';
        $lista.append(`
            <div class="d-flex gap-1 align-items-center mb-1">
                <select class="form-select form-select-sm" style="flex:1;" onchange="cambiarMetodoPago(${i}, this.value)">${opciones}</select>
                <input type="number" class="form-control form-control-sm" style="width:110px;" value="${p.valor || ''}"
                       oninput="cambiarValorPago(${i}, this.value)" placeholder="0">
                ${botonQuitar}
            </div>
        `);
    });

    const total = totalConAjuste();
    const asignado = pagos.reduce((acc, p) => acc + (parseFloat(p.valor) || 0), 0);
    const restante = Math.round((total - asignado) * 100) / 100;
    $('#pago_restante').text(pb.moneda(restante)).toggleClass('text-danger fw-semibold', Math.abs(restante) > 1);

    const tieneCredito = pagos.some(p => p.metodo_pago === 'credito' && (parseFloat(p.valor) || 0) > 0);
    $('#label_cliente').text(tieneCredito ? 'Cliente (obligatorio para la porción a crédito)' : 'Cliente (opcional)');

    const efectivoAsignado = pagos.filter(p => p.metodo_pago === 'efectivo').reduce((acc, p) => acc + (parseFloat(p.valor) || 0), 0);
    $('#fila_efectivo').toggle(efectivoAsignado > 0);
    actualizarVuelto(efectivoAsignado);
}

function agregarLineaPago() {
    pagos.push({ metodo_pago: 'efectivo', valor: 0 });
    renderPagos();
}

function quitarLineaPago(i) {
    pagos.splice(i, 1);
    renderPagos();
}

function cambiarMetodoPago(i, valor) {
    pagos[i].metodo_pago = valor;
    renderPagos();
}

function cambiarValorPago(i, valor) {
    pagos[i].valor = parseFloat(valor) || 0;
    renderPagos();
}

function actualizarVuelto(efectivoAsignado) {
    const recibido = parseFloat($('#efectivo_recibido').val()) || 0;
    const base = efectivoAsignado !== undefined ? efectivoAsignado
        : pagos.filter(p => p.metodo_pago === 'efectivo').reduce((acc, p) => acc + (parseFloat(p.valor) || 0), 0);
    const vuelto = recibido - base;
    $('#vuelto_calculado').val(vuelto >= 0 ? pb.moneda(vuelto) : 'Falta ' + pb.moneda(Math.abs(vuelto)));
}

function cargarInsumo() {
    const descripcion = $('#descripcion_insumo').val().trim();
    const valor = $('#valor_insumo').val();
    const fecha = $('#fecha_insumo').val();
    const metodoPago = $('#metodo_pago_egreso').val();

    if (!descripcion || !valor || !fecha) {
        pb.error('Todos los campos son obligatorios.');
        return;
    }

    $.ajax({
        type: 'POST',
        url: '/controlador/cargar_insumo.php',
        data: {
            fecha: fecha,
            hora: new Date().toTimeString().split(' ')[0],
            tipo_transaccion: 2,
            descripcion: descripcion,
            valor: valor,
            metodo_pago: metodoPago,
            afecta_turno: $('input[name=egreso_afecta_turno]:checked').val(),
            csrf_token: pb.csrfToken(),
        },
        dataType: 'json',
        success: function (respuesta) {
            if (respuesta == 1) {
                pb.success('Egreso registrado con éxito.');
                $('#descripcion_insumo, #valor_insumo').val('');
                $('#egreso_afecta_turno_si').prop('checked', true);
                $('#egreso_aviso_fecha_distinta').text('');
                egresoTurnoTocadoAMano = false;
                bootstrap.Modal.getInstance(document.getElementById('modalEgreso'))?.hide();
            } else {
                pb.error('Error al registrar el egreso.');
            }
        },
        error: function () {
            pb.error('Error al procesar el egreso.');
        }
    });
}

/**
 * Se dispara EN PARALELO con la venta (ver realizarVenta), nunca antes ni
 * después — si esperáramos a que termine para recién ahí vender, un
 * cliente parado en caja notaría el retraso cada vez que no hay internet
 * a mano. Si para cuando la venta ya se confirmó esta llamada no ha
 * vuelto, ese recibo simplemente sale sin código — mejor eso que hacer
 * esperar el cobro por un beneficio opcional.
 */
function iniciarQrFidelizacionParaVenta() {
    qrFidelizacionPromesa = null;
    // Si el cajero ya generó uno a mano para esta misma venta (ver botón
    // "Generar QR" en la tarjeta de Fidelización), no se agrega un segundo
    // en el recibo — ya se mostró/imprimió uno para este cliente.
    if (!FIDELIZACION_QR_AUTO || !navigator.onLine || qrFidelizacionGeneradoManualmente) return;
    qrFidelizacionPromesa = new Promise(function (resolve) {
        $.ajax({
            type: 'POST',
            url: '/controlador/fidelizacion_generar_qr.php',
            data: { csrf_token: pb.csrfToken() },
            dataType: 'json',
        }).done(function (r) {
            resolve(r && r.ok && r.url ? r.url : null);
        }).fail(function () {
            resolve(null);
        });
    });
}

/** Aviso sobre el QR en la columna de la derecha del modal — NUNCA dentro de la factura, así la vista previa es exactamente lo que se imprime. */
function avisoQrTicket(texto) {
    $('#ticket_qr_aviso').text(texto || '');
}

/** Activa el "QR automático en cada venta" y, de una vez, genera y pinta el QR de la factura que se está viendo. */
function activarQrAutomaticoDesdeTicket() {
    const idFactura = ticketActualId;
    avisoQrTicket('Activando…');
    $.ajax({
        type: 'POST',
        url: '/controlador/actualizar_fidelizacion_qr_auto.php',
        data: { habilitado: 1, csrf_token: pb.csrfToken() },
        dataType: 'json',
    }).done(function (r) {
        if (!r || !r.ok) {
            avisoQrTicket('No se pudo activar el QR automático.');
            return;
        }
        FIDELIZACION_QR_AUTO = true;
        pb.success('Listo: desde ahora cada venta lleva su QR.');
        avisoQrTicket('Generando QR…');
        const promesa = new Promise(function (resolve) {
            $.ajax({
                type: 'POST',
                url: '/controlador/fidelizacion_generar_qr.php',
                data: { csrf_token: pb.csrfToken() },
                dataType: 'json',
            }).done(function (q) {
                resolve(q && q.ok && q.url ? q.url : null);
            }).fail(function () {
                resolve(null);
            });
        });
        ticketQrPendiente = promesa.then(function (url) {
            if (ticketActualId !== idFactura) return;
            if (url) {
                pintarQrEnTicket(idFactura, url);
            } else {
                avisoQrTicket('Quedó activado, pero esta vez no se pudo generar el QR (sin conexión con el servidor de fidelización).');
            }
        });
    }).fail(function () {
        avisoQrTicket('No se pudo activar el QR automático.');
    });
}

/** Pinta el QR al final de la factura que se está mostrando y lo guarda contra esa factura (para poder reimprimirlo desde Facturas). */
function pintarQrEnTicket(idFactura, url) {
    const $zona = $('#ticket_qr_zona');
    if (ticketActualId !== idFactura || $zona.length === 0) return;
    const imagenQr = pb.generarImagenQrDataUrl(url, 140);
    if (!imagenQr) {
        avisoQrTicket('No se pudo dibujar el QR.');
        return;
    }
    avisoQrTicket('');
    $zona.html(`
        <hr>
        <div class="text-center">
            <img src="${imagenQr}" style="width:140px;height:140px;">
            <div class="small">Escanea para sumar visita</div>
        </div>
    `);
    // "Best effort": si falla, la venta ya quedó registrada de todas formas.
    $.ajax({
        type: 'POST',
        url: '/controlador/guardar_qr_factura.php',
        data: { id_factura: idFactura, qr_url: url, csrf_token: pb.csrfToken() },
        dataType: 'json',
    });
}

/**
 * Deja lista la zona del QR al final de la factura: el QR si ya llegó; si no,
 * el motivo aparece en la columna de la derecha (Generando…, sin conexión,
 * apagado). Solo va en la factura del cliente, nunca en la comanda.
 */
function prepararQrDelTicket(idFactura, promesaQr) {
    ticketQrPendiente = null;
    $('#ticket_qr_zona').empty();
    avisoQrTicket('');
    if (!FIDELIZACION_HABILITADA) return;
    if (qrFidelizacionGeneradoManualmente) {
        avisoQrTicket('QR: ya se entregó uno manual en esta venta, no se repite en la factura.');
        return;
    }
    if (!FIDELIZACION_QR_AUTO) {
        // La fidelización SÍ está habilitada (por eso llegamos aquí); lo que
        // está apagado es el interruptor aparte "Imprimirlo automáticamente
        // en cada venta" de la pantalla Fidelización (viene apagado de fábrica).
        const $aviso = $('#ticket_qr_aviso').empty();
        $aviso.append(document.createTextNode(
            'Esta factura sale sin QR porque el interruptor "Imprimirlo automáticamente en cada venta" (pantalla Fidelización, tarjeta "Código QR") está apagado.'
        ));
        if (ES_ADMIN) {
            $aviso.append(
                $('<button type="button" class="btn btn-sm btn-outline-success w-100 mt-2"></button>')
                    .text('Activarlo ahora')
                    .on('click', activarQrAutomaticoDesdeTicket)
            );
        } else {
            $aviso.append(document.createTextNode(' Pídele al administrador que lo active.'));
        }
        return;
    }
    if (!promesaQr) {
        avisoQrTicket('Sin QR en la factura: no hay conexión a internet.');
        return;
    }
    avisoQrTicket('Generando QR…');
    ticketQrPendiente = promesaQr.then(function (url) {
        if (ticketActualId !== idFactura) return;
        if (url) {
            pintarQrEnTicket(idFactura, url);
        } else {
            avisoQrTicket('Sin QR en la factura: el servidor de fidelización no respondió.');
        }
    });
}

/** Imprimir no debe salir antes de que el QR termine de aparecer (con un tope, por si el servidor no contesta). */
async function esperarQrDelTicket() {
    if (!ticketQrPendiente) return;
    await Promise.race([ticketQrPendiente, new Promise(function (r) { setTimeout(r, 4000); })]);
}

function realizarVenta() {
    if (carrito.length === 0) {
        pb.error('El carrito está vacío. Agrega productos antes de vender.');
        return;
    }

    iniciarQrFidelizacionParaVenta();

    const productosVenta = carrito.map(item => ({
        id_producto: item.id_producto,
        presentacion: item.presentacion,
        cantidad: item.cantidad,
        // En el mismo orden en que se marcaron — el servidor decide cuáles
        // quedan gratis según ese orden (ver Modificadores::resolverSeleccion).
        modificadores: (item.modificadores || []).map(m => m.id_modificador),
        profesional_id: item.profesional_id || '',
        // El monto real del descuento SIEMPRE se recalcula en el servidor —
        // esto solo le dice qué tipo/valor quiere el cajero.
        descuento_tipo: item.descuento_tipo || null,
        descuento_valor: item.descuento_valor || 0,
        // Solo se usa si el producto quedó con precio por kilo variable
        // ($0 en catálogo) — si el catálogo ya tiene un precio fijo, el
        // servidor lo ignora y usa siempre el suyo.
        precio_kg_manual: item.presentacion === 'peso' ? item.precio_unitario : null,
    }));
    const ajuste = parseFloat($('#ajuste_total').val()) || 0;
    const propina = parseFloat($('#propina_total').val()) || 0;
    const total = totalConAjuste();

    const pagosValidos = pagos
        .map(p => ({ metodo_pago: p.metodo_pago, valor: parseFloat(p.valor) || 0 }))
        .filter(p => p.valor > 0);
    const asignado = pagosValidos.reduce((acc, p) => acc + p.valor, 0);

    if (Math.abs(asignado - total) > 1) {
        pb.error('La suma de los pagos no coincide con el total a cobrar.');
        return;
    }
    if (pagosValidos.some(p => p.metodo_pago === 'credito') && !ventaCliente) {
        pb.error('Selecciona un cliente para la porción a crédito.');
        return;
    }

    const carritoParaTicket = carrito.slice();
    const pagosParaTicket = pagosValidos.slice();

    // Sin esto, un doble clic (o un clic mientras la petición anterior
    // todavía va en camino) manda DOS ventas para el mismo carrito — la
    // segunda cobra y descuenta inventario otra vez, y si había una mesa
    // de por medio, la primera ya la cerró y la segunda revienta al
    // intentar cerrarla de nuevo. Se reactiva en los dos callbacks de abajo.
    const $btnConfirmar = $('#btn_confirmar_venta');
    if ($btnConfirmar.prop('disabled')) return;
    $btnConfirmar.prop('disabled', true);

    $.ajax({
        type: 'POST',
        url: '/controlador/realizar_venta.php',
        data: {
            descripcion: $('#descripcion').val(),
            productos: JSON.stringify(productosVenta),
            ajuste: ajuste,
            propina: propina,
            pagos: JSON.stringify(pagosValidos),
            cliente_id: ventaCliente ? ventaCliente.id_cliente : '',
            comanda_id: mesaActiva ? mesaActiva.id_comanda : '',
            csrf_token: pb.csrfToken(),
        },
        dataType: 'json',
        success: function (respuesta) {
            if (respuesta.ok) {
                pb.success('Venta registrada con éxito.');
                mostrarTicket(respuesta.id_factura, carritoParaTicket, ajuste, pagosParaTicket, propina, respuesta.numero_factura);
                $('#descripcion').val('');
                $('#efectivo_recibido').val('');
                vaciarCarrito();
                if (MOD_MESAS) cargarListaMesas();
            } else {
                pb.error(respuesta.error || 'Error al realizar la venta.');
            }
        },
        error: function (xhr) {
            const msg = xhr.responseJSON && xhr.responseJSON.error ? xhr.responseJSON.error : 'Error al procesar la venta.';
            pb.error(msg);
        },
        complete: function () {
            $btnConfirmar.prop('disabled', false);
        }
    });
}

function mostrarTicket(idFactura, items, ajuste, pagosUsados, propina, numeroFactura) {
    // "Factura #" que se imprime: el id crudo en un solo equipo, "C2-000042" con varias cajas
    // (ver Sincronizacion::numeroFactura()) — si el servidor no lo mandó (build vieja), se cae
    // al id crudo, igual que siempre.
    numeroFactura = numeroFactura || idFactura;
    const fecha = new Date().toLocaleString('es-CO');
    let filas = '';
    let subtotal = 0;
    let totalIva = 0;
    items.forEach(function (item) {
        const totalLinea = totalLineaFinal(item);
        const descuentoMonto = descuentoLineaMonto(item);
        subtotal += totalLinea;
        const tasaIva = item.tasa_iva || 0;
        if (tasaIva > 0) totalIva += totalLinea - (totalLinea / (1 + tasaIva / 100));
        const etiqueta = item.presentacion === 'peso' ? 'kg' : (item.presentacion === 'paquete' ? `paquete x${item.unidades_por_paquete}` : 'u.');
        const ivaTexto = tasaIva > 0 ? ` (IVA ${tasaIva}%)` : '';
        // Un topping por renglón, en negro y con el mismo tamaño que el
        // producto: antes iban juntos en gris y pequeños (.small.text-muted)
        // y en una impresora térmica salían "clarititos".
        // Cada adición va en su propio renglón con su cantidad y su valor (precio x cantidad del producto),
        // y el renglón del producto muestra solo su precio base: así la suma de los renglones es el total
        // de la línea y el cliente ve de dónde sale cada peso.
        const cantidadMod = item.cantidad === 1 ? '' : `${item.cantidad} `;
        const modificadoresTexto = (item.modificadores || []).map(m =>
            `<div class="d-flex justify-content-between" style="margin-left:10px;gap:6px;"><span>+ ${cantidadMod}${m.nombre}</span><span style="white-space:nowrap;">${m.incluido ? 'Incluido' : pb.moneda(m.precio_cobrado * item.cantidad)}</span></div>`
        ).join('');
        const totalBaseLinea = item.cantidad * precioLinea(item);
        const descuentoTexto = descuentoMonto > 0
            ? `<div class="small text-muted">Descuento: -${pb.moneda(descuentoMonto)}</div>`
            : '';
        const profesionalTexto = (MOD_PROFESIONALES && item.profesional_id && nombreProfesional(item.profesional_id))
            ? `<div style="margin-left:10px;">Profesional: ${$('<div>').text(nombreProfesional(item.profesional_id)).html()}</div>` : '';
        filas += `<div class="d-flex justify-content-between"><span>${item.cantidad} ${etiqueta} ${item.nombre_producto}${ivaTexto}</span><span style="white-space:nowrap;">${pb.moneda((item.modificadores || []).length ? totalBaseLinea : totalLinea)}</span></div>${profesionalTexto}${modificadoresTexto}${descuentoTexto}`;
    });
    const ajusteHtml = ajuste
        ? `<div class="d-flex justify-content-between"><span>${ajuste < 0 ? 'Descuento' : 'Recargo'}</span><span>${pb.moneda(ajuste)}</span></div>`
        : '';
    const propinaHtml = propina > 0
        ? `<div class="d-flex justify-content-between"><span>Propina</span><span>${pb.moneda(propina)}</span></div>`
        : '';
    const ivaHtml = totalIva > 0
        ? `<div class="d-flex justify-content-between text-muted"><span>IVA incluido</span><span>${pb.moneda(totalIva)}</span></div>`
        : '';
    // Siempre se muestra la línea: con el nombre si se eligió cliente (o la mesa lo trae), "No aplica" si no.
    const nombreCliente = ventaCliente && ventaCliente.nombre ? $('<div>').text(ventaCliente.nombre).html() : 'No aplica';
    const clienteHtml = `<p class="mb-1">Cliente: <strong>${nombreCliente}</strong></p>`;
    const etiquetasPago = { efectivo: 'Efectivo', tarjeta: 'Tarjeta', transferencia: 'Transferencia', credito: 'Crédito' };
    const pagosHtml = (pagosUsados || []).length > 1
        ? '<hr>' + pagosUsados.map(p => `<div class="d-flex justify-content-between small"><span>${etiquetasPago[p.metodo_pago] || p.metodo_pago}</span><span>${pb.moneda(p.valor)}</span></div>`).join('')
        : '';

    const negocio = window.PB_NEGOCIO || {};
    const lineaDatos = [negocio.direccion, negocio.telefono, negocio.nit ? `NIT ${negocio.nit}` : null].filter(Boolean).join(' · ');
    const logoHtml = negocio.logoFactura ? `<img src="${negocio.logoFactura}" alt="" style="max-height:48px;max-width:80%;object-fit:contain;"><br>` : '';
    const encabezadoHtml = `
        <div class="text-center mb-2">
            ${logoHtml}
            <strong>${negocio.nombre || ''}</strong>
            ${lineaDatos ? `<br><small>${lineaDatos}</small>` : ''}
            ${negocio.lema ? `<br><small><em>${negocio.lema}</em></small>` : ''}
        </div>
        <hr>
    `;
    const mesaHtml = mesaActiva ? `<p class="mb-1">Mesa: <strong>${mesaActiva.mesa_nombre}</strong></p>` : '';

    // El QR va SOLO en la factura del cliente, nunca en la comanda (esa se
    // arma aparte, ver ultimaComandaHtml). Aquí solo queda la zona donde va —
    // se llena en prepararQrDelTicket() apenas llega el código, y aparece en
    // esta misma vista previa para que se vea tal cual saldrá impreso.
    const promesaQr = qrFidelizacionPromesa;

    $('#ticket_contenido').html(`
        ${encabezadoHtml}
        <div class="text-center mb-2"><strong>Factura #${numeroFactura}</strong><br><small>${fecha}</small></div>
        <p class="mb-1">Atendió: <strong>${NOMBRE_CAJERO}</strong></p>
        ${clienteHtml}
        ${mesaHtml}
        <hr>
        ${filas}
        ${ajusteHtml}
        ${propinaHtml}
        ${ivaHtml}
        <hr>
        <div class="d-flex justify-content-between fw-bold"><span>TOTAL</span><span>${pb.moneda(Math.max(0, subtotal + ajuste + propina))}</span></div>
        ${pagosHtml}
        <div id="ticket_qr_zona"></div>
    `);
    pb.prepararTicketParaImprimir(document.getElementById('ticket_contenido'));
    $('#ticket_estado_impresion').empty();
    ticketActualId = idFactura;
    prepararQrDelTicket(idFactura, promesaQr);
    bootstrap.Modal.getOrCreateInstance('#modalTicket').show();

    // Se calcula siempre (no solo cuando hay impresora de cocina
    // configurada) para que "Imprimir comanda" también funcione en un
    // equipo sin impresora dedicada (cae al diálogo normal del sistema).
    ultimaComandaHtml = pb.construirComandaHTML({
        mesa: mesaActiva ? mesaActiva.mesa_nombre : null,
        vendedor: NOMBRE_CAJERO,
        cliente: ventaCliente ? ventaCliente.nombre : null,
        fecha: fecha,
        items: items,
    });

    // A propósito NO se imprime nada solo — antes esto disparaba el
    // recibo (y a veces la comanda) en silencio apenas se generaba la
    // factura, sin que el cajero pidiera nada, y si además le daba clic a
    // "Imprimir" del modal se duplicaba. Ahora imprimir es siempre una
    // acción explícita: los botones "Imprimir" / "Imprimir comanda"
    // de la derecha.
}

let ultimaComandaHtml = null;

/** Deja constancia en el modal de qué se mandó a imprimir (y a cuál impresora) — así el cajero ve cuántos trabajos salieron de verdad. */
function avisoImpresion(texto, esError) {
    const hora = new Date().toLocaleTimeString('es-CO');
    const $linea = $('<div></div>').text(`${hora} — ${texto}`);
    if (esError) $linea.addClass('text-danger');
    $('#ticket_estado_impresion').append($linea);
}

/** Manda un documento a imprimir: silencioso a la impresora que le toca, o por el diálogo del sistema si no hay ninguna configurada. */
async function imprimirDocumento(tipo, html, etiquetaAviso) {
    const impresora = pb.impresoraPara(tipo);
    if (!impresora) {
        pb.imprimirConDialogo(html);
        avisoImpresion(`${etiquetaAviso}: abierto el diálogo de impresión del sistema.`);
        return;
    }
    const r = await pb.imprimirTicketSilencioso(html, impresora, tipo);
    if (r && r.duplicado) {
        avisoImpresion(`${etiquetaAviso}: ya se había enviado hace un instante, no se repite.`);
    } else if (r && r.ok) {
        avisoImpresion(`${etiquetaAviso}: enviada a "${impresora}".`);
    } else if (r && r.disponible === false) {
        pb.imprimirConDialogo(html);
        avisoImpresion(`${etiquetaAviso}: abierto el diálogo de impresión del sistema.`);
    } else {
        avisoImpresion(`${etiquetaAviso}: no se pudo imprimir (${(r && r.error) || 'error desconocido'}).`, true);
        pb.error('No se pudo imprimir: ' + ((r && r.error) || 'error desconocido'));
    }
}

/**
 * Los botones "Imprimir" / "Imprimir comanda" no tenían protección contra el doble clic (a
 * diferencia de "Confirmar venta"): en una pantalla táctil, con el cliente esperando y sin ver de
 * inmediato que ya se mandó, un segundo toque mientras el primero todavía va en camino podía volver
 * a mandar la factura y/o la comanda. Se bloquean los dos botones mientras cualquiera de los dos
 * está imprimiendo (uno solo puede estar en camino a la vez).
 */
function conBotonesDeImpresionBloqueados(fn) {
    return async function (...args) {
        const $botones = $('#modalTicket .pb-btn-imprimir');
        if ($botones.prop('disabled')) return;
        $botones.prop('disabled', true);
        try {
            await fn(...args);
        } finally {
            $botones.prop('disabled', false);
        }
    };
}

/** Imprime solo la factura del cliente (con el QR al final si aplica, tal cual se ve en la vista previa). */
const imprimirFacturaModal = conBotonesDeImpresionBloqueados(async function () {
    await esperarQrDelTicket();
    await imprimirDocumento('factura', document.getElementById('ticket_contenido').innerHTML, 'Factura');
});

/** Imprime SOLO la comanda de cocina (sin factura, sin QR ni precios: lo que hay que preparar). */
const imprimirComandaModal = conBotonesDeImpresionBloqueados(async function () {
    if (!ultimaComandaHtml) return;
    await imprimirDocumento('comanda', ultimaComandaHtml, 'Comanda');
});

let ultimaComandaPreviewHtml = null;

/** "Imprimir de nuevo" de la vista previa de la comanda de una mesa. */
async function reimprimirComandaPreview() {
    if (!ultimaComandaPreviewHtml) return;
    const impresora = pb.impresoraPara('comanda');
    if (!impresora) {
        pb.imprimirConDialogo(ultimaComandaPreviewHtml);
        return;
    }
    const r = await pb.imprimirTicketSilencioso(ultimaComandaPreviewHtml, impresora, 'comanda');
    if (r && r.duplicado) {
        pb.info('Ya se había enviado hace un instante.');
    } else if (r && r.ok) {
        pb.success('Comanda enviada a la impresora.');
    } else if (r && r.disponible === false) {
        pb.imprimirConDialogo(ultimaComandaPreviewHtml);
    } else {
        pb.error('No se pudo imprimir la comanda: ' + ((r && r.error) || 'error desconocido'));
    }
}

<?php if ($puedeVerFacturasRecientes): ?>
// ---------------------------------------------------------------------------------------------
// Facturas recientes: reporte real de un cliente — a veces una comanda no sale al vender (se
// trabó, se apagó la impresora) o falta corregir algo de una venta ya hecha, y no quiere tener
// que salir de Ventas hasta la pantalla de Facturas para arreglarlo. Mismas acciones (reimprimir
// factura/comanda, editar ajuste, cambiar cliente) que allá, sin salir de esta pantalla.
// ---------------------------------------------------------------------------------------------
const PUEDE_EDITAR_VENTA = <?php echo $puedeEditarVenta ? 'true' : 'false'; ?>;
const PUEDE_CAMBIAR_CLIENTE_VENTA = <?php echo $puedeCambiarClienteVenta ? 'true' : 'false'; ?>;
let facturaRecienteActual = null;

function abrirFacturasRecientes() {
    const hoy = new Date().toLocaleDateString('sv-SE');
    const $tbody = $('#fr_tabla_cuerpo');
    $tbody.html('<tr><td colspan="5" class="text-muted text-center">Cargando…</td></tr>');
    bootstrap.Modal.getOrCreateInstance('#modalFacturasRecientes').show();
    $.getJSON('/controlador/ventas_por_fecha.php', { fecha_inicio: hoy, fecha_fin: hoy }, function (facturas) {
        $tbody.empty();
        if (facturas.length === 0) {
            $tbody.append('<tr><td colspan="5" class="text-muted text-center">No hay ventas hoy todavía.</td></tr>');
            return;
        }
        facturas.forEach(function (f) {
            const insignia = f.anulada ? ' <span class="badge bg-secondary">Anulada</span>' : '';
            $tbody.append(`<tr class="${f.anulada ? 'text-decoration-line-through text-muted' : ''}">
                <td>#${f.numero_factura}${insignia}</td>
                <td>${f.hora}</td>
                <td>${pb.escaparHtml(f.vendedor || '—')}</td>
                <td class="text-end">${pb.moneda(f.total_venta)}</td>
                <td class="text-center"><button class="btn btn-sm btn-outline-info" onclick="abrirFacturaReciente(${f.id_factura})"><i class="bi bi-eye"></i> Ver</button></td>
            </tr>`);
        });
    }).fail(() => { $tbody.html('<tr><td colspan="5" class="text-danger text-center">Error al cargar.</td></tr>'); });
}

function abrirFacturaReciente(idFactura) {
    $.getJSON('/controlador/factura_detalle.php', { id_factura: idFactura }, function (f) {
        if (!f) { pb.error('Factura no encontrada.'); return; }
        facturaRecienteActual = f;
        // Se deja la lista abierta DETRÁS (como "cambiar cliente" sobre el detalle, en Facturas) en vez
        // de cerrarla: así, al cerrar el detalle, se puede elegir otra factura sin volver a abrir la lista.
        $('#fr_btn_comanda').toggleClass('d-none', f.anulada);
        $('#fr_btn_editar').toggleClass('d-none', !(PUEDE_EDITAR_VENTA && !f.anulada));
        $('#fr_btn_cambiar_cliente').toggleClass('d-none', !(PUEDE_CAMBIAR_CLIENTE_VENTA && !f.anulada));
        const detalle = pb.construirDetalleFacturaHtml(f, { puedeAnular: false });
        $('#fr_detalle_titulo').text(detalle.titulo);
        $('#fr_detalle_contenido').html(detalle.html);
        pb.prepararTicketParaImprimir(document.getElementById('fr_detalle_contenido'));
        bootstrap.Modal.getOrCreateInstance('#modalFacturaRecienteDetalle').show();
    }).fail(() => pb.error('Error al cargar los detalles de la factura.'));
}

async function reimprimirFacturaReciente() {
    const html = document.getElementById('fr_detalle_contenido').innerHTML;
    const impresora = pb.impresoraPara('factura');
    if (!impresora) { pb.imprimirConDialogo(html); return; }
    const r = await pb.imprimirTicketSilencioso(html, impresora, 'factura');
    if (r && r.duplicado) pb.info('Ya se había enviado hace un instante.');
    else if (r && r.ok) pb.success('Factura enviada a "' + impresora + '".');
    else if (r && r.disponible === false) pb.imprimirConDialogo(html);
    else pb.error('No se pudo imprimir: ' + ((r && r.error) || 'error desconocido'));
}

async function reimprimirComandaReciente() {
    if (!facturaRecienteActual) return;
    const html = pb.construirComandaHTML({
        mesa: null, // la mesa no queda asociada a la factura una vez cobrada
        vendedor: facturaRecienteActual.vendedor,
        cliente: facturaRecienteActual.cliente,
        fecha: `${facturaRecienteActual.fecha} ${facturaRecienteActual.hora}`,
        items: facturaRecienteActual.productos,
    });
    const impresora = pb.impresoraPara('comanda');
    if (!impresora) { pb.imprimirConDialogo(html); return; }
    const r = await pb.imprimirTicketSilencioso(html, impresora, 'comanda');
    if (r && r.duplicado) pb.info('Ya se había enviado hace un instante.');
    else if (r && r.ok) pb.success('Comanda enviada a "' + impresora + '".');
    else if (r && r.disponible === false) pb.imprimirConDialogo(html);
    else pb.error('No se pudo imprimir: ' + ((r && r.error) || 'error desconocido'));
}

async function editarFacturaReciente() {
    if (!facturaRecienteActual) return;
    const nuevoAjusteStr = await pb.preguntar('Nuevo ajuste al total (+/-, ej. -5000 para descuento):', facturaRecienteActual.ajuste, 'number');
    if (nuevoAjusteStr === null) return;
    const nuevoAjuste = parseFloat(nuevoAjusteStr);
    if (isNaN(nuevoAjuste)) { pb.error('Indica un valor numérico válido.'); return; }
    const motivo = await pb.preguntar('Motivo de la edición:');
    if (motivo === null) return;
    if (motivo.trim() === '') { pb.error('Debes indicar un motivo.'); return; }
    $.ajax({
        type: 'POST',
        url: '/controlador/editar_factura.php',
        data: { id_factura: facturaRecienteActual.id_factura, ajuste: nuevoAjuste, motivo: motivo.trim(), csrf_token: pb.csrfToken() },
        dataType: 'json',
        success: function (r) {
            if (r.ok) {
                pb.success('Factura actualizada.');
                bootstrap.Modal.getInstance(document.getElementById('modalFacturaRecienteDetalle'))?.hide();
            } else {
                pb.error(r.error || 'Error al editar la factura.');
            }
        },
        error: function () { pb.error('Error en el proceso.'); },
    });
}

<?php if ($puedeCambiarClienteVenta): ?>
let busquedaClienteRecienteId = 0;

function abrirCambiarClienteReciente() {
    if (!facturaRecienteActual) return;
    $('#fr_cc_cliente_actual').text(facturaRecienteActual.cliente || 'sin cliente');
    $('#fr_cc_buscar, #fr_cc_motivo').val('');
    $('#fr_cc_resultados').empty();
    bootstrap.Modal.getOrCreateInstance('#modalCambiarClienteReciente').show();
}

$(document).on('input', '#fr_cc_buscar', pb.debounce(function () {
    const termino = $('#fr_cc_buscar').val().trim();
    const idBusqueda = ++busquedaClienteRecienteId; // una respuesta vieja no debe pisar a la nueva
    const $lista = $('#fr_cc_resultados').empty();
    if (termino === '') return;
    $.ajax({
        type: 'POST', url: '/controlador/buscar_cliente.php', data: { termino: termino }, dataType: 'json',
        success: function (clientes) {
            if (idBusqueda !== busquedaClienteRecienteId) return;
            $lista.empty();
            clientes.forEach(function (c) {
                $('<li class="list-group-item" style="cursor:pointer;"></li>')
                    .text(c.nombre + ' — debe ' + pb.moneda(c.saldo))
                    .on('click', function () { cambiarClienteVentaReciente(c.id_cliente); })
                    .appendTo($lista);
            });
            if (clientes.length === 0) $lista.append('<li class="list-group-item text-muted">No se encontró.</li>');
        },
    });
}, 200));

function cambiarClienteVentaReciente(idCliente) {
    const motivo = $('#fr_cc_motivo').val().trim();
    if (motivo === '') {
        pb.error('Escribe el motivo del cambio.');
        $('#fr_cc_motivo').trigger('focus');
        return;
    }
    $.ajax({
        type: 'POST',
        url: '/controlador/cambiar_cliente_factura.php',
        data: { id_factura: facturaRecienteActual.id_factura, cliente_id: idCliente === null ? '' : idCliente, motivo: motivo, csrf_token: pb.csrfToken() },
        dataType: 'json',
        success: function (r) {
            if (r.ok) {
                pb.success('Cliente de la venta actualizado.');
                bootstrap.Modal.getInstance(document.getElementById('modalCambiarClienteReciente'))?.hide();
                abrirFacturaReciente(facturaRecienteActual.id_factura);
            } else {
                pb.error(r.error || 'No se pudo cambiar el cliente.');
            }
        },
        error: function () { pb.error('Error en el proceso.'); },
    });
}
<?php endif; ?>
<?php endif; ?>

<?php if ($modMesas): ?>
let mesasCatalogo = [];

function cargarListaMesas() {
    $.getJSON('/controlador/listar_mesas.php', function (mesas) {
        mesasCatalogo = mesas;
        renderMesas();
    });
}

function renderMesas() {
    const $lista = $('#lista_mesas').empty();
    if (mesasCatalogo.length === 0) {
        $lista.append(
            '<span class="text-muted small">No hay mesas configuradas todavía' +
            (<?php echo $usuarioActual['rol'] === 'admin' ? 'true' : 'false'; ?>
                ? ' — <a href="/vista/mesas/index.php">configúralas aquí</a>.'
                : ', pídele al administrador que las configure.') +
            '</span>'
        );
        return;
    }
    mesasCatalogo.forEach(function (m) {
        const ocupada = m.id_comanda !== null;
        const activaEnPantalla = mesaActiva && mesaActiva.id_comanda == m.id_comanda;
        const claseEstado = ocupada ? 'pb-mesa-tile-ocupada' : 'pb-mesa-tile-libre';
        const claseActiva = activaEnPantalla ? 'pb-mesa-tile-actual' : '';
        const detalle = ocupada ? `${m.total_items} · ${pb.moneda(m.total_estimado)}` : 'Libre';
        const clienteHtml = (MESAS_MOSTRAR_CLIENTE && ocupada && m.cliente_nombre)
            ? `<div class="small text-truncate">${$('<div>').text(m.cliente_nombre).html()}</div>` : '';
        $lista.append(`
            <div class="pb-mesa-tile ${claseEstado} ${claseActiva}" onclick="abrirMesa(${m.id_mesa}, ${ocupada})">
                <div class="fw-semibold">${m.nombre}</div>
                ${clienteHtml}
                <small>${detalle}</small>
                ${ocupada ? `<button type="button" class="pb-mesa-tile-cancelar" title="Cancelar mesa" onclick="event.stopPropagation(); cancelarMesa(${m.id_comanda})"><i class="bi bi-x"></i></button>` : ''}
            </div>
        `);
    });
}

async function abrirMesa(idMesa, ocupada) {
    // Si ya hay productos sueltos en el carrito (venta rápida sin mesa) y se elige una mesa, esos productos
    // NO se descartan: se pregunta y, si el cajero acepta, pasan a la cuenta de la mesa (sumándose a lo que
    // ya tuviera, si la mesa estaba abierta). Antes el carrito se reemplazaba sin avisar.
    let carritoSuelto = [];
    let clienteSuelto = null;
    if (!mesaActiva && carrito.length > 0) {
        const mesa = mesasCatalogo.find(m => m.id_mesa == idMesa);
        const nombreMesa = mesa ? mesa.nombre : 'la mesa';
        const cantidad = carrito.length;
        const productos = cantidad === 1 ? '1 producto' : `${cantidad} productos`;
        const mensaje = ocupada
            ? `${nombreMesa} ya tiene una cuenta abierta. ¿Sumar a esa cuenta los ${productos} que tienes en el carrito?`
            : `¿Asociar a ${nombreMesa} los ${productos} que tienes en el carrito?`;
        if (!await pb.confirmar(mensaje)) return;
        carritoSuelto = carrito.slice();
        clienteSuelto = ventaCliente;
    }
    // Una mesa libre es trabajo NUEVO — si hay una actualización crítica
    // pendiente, se deja terminar lo que ya está en curso pero no se deja
    // empezar nada más hasta que se instale (ver preload.js/app.js).
    if (!ocupada && window.PB_ACTUALIZACION_FORZADA) {
        pb.error('Hay una actualización crítica pendiente — espera a que termine para abrir una mesa nueva.');
        return;
    }
    $.ajax({
        type: 'POST',
        url: '/controlador/abrir_mesa.php',
        data: { mesa_id: idMesa, csrf_token: pb.csrfToken() },
        dataType: 'json',
        success: function (data) {
            if (!data.ok) {
                pb.error(data.error || 'No se pudo abrir la mesa.');
                return;
            }
            const itemsMesa = data.items.map(function (it) {
                contadorLineaCarrito += 1;
                return {
                    _lineaId: contadorLineaCarrito,
                    id_producto: it.producto_id,
                    nombre_producto: it.nombre_producto,
                    presentacion: it.presentacion,
                    precio_unitario: parseFloat(it.precio_unitario),
                    precio_paquete: it.precio_paquete !== null ? parseFloat(it.precio_paquete) : null,
                    unidades_por_paquete: parseInt(it.unidades_por_paquete, 10) || 1,
                    cantidad: parseInt(it.cantidad, 10),
                    cantidad_existente: it.cantidad_existente,
                    controla_stock: it.controla_stock != 0,
                    tasa_iva: parseFloat(it.tasa_iva) || 0,
                    modificadores: it.modificadores || [],
                    profesional_id: it.profesional_id ? parseInt(it.profesional_id, 10) : null,
                    // Basta con que el producto tenga grupos configurados
                    // (it.tiene_grupos) — aunque esta línea no tenga nada
                    // elegido todavía, el lápiz para editar debe seguir ahí.
                    tieneModificadoresConfigurados: it.tiene_grupos > 0 || (it.modificadores || []).length > 0,
                    descuento_tipo: it.descuento_tipo || null,
                    descuento_valor: parseFloat(it.descuento_valor) || 0,
                };
            });
            // Los productos sueltos se suman a los de la mesa: el mismo producto (sin adiciones ni descuento)
            // se junta en una sola línea; el resto entra como líneas nuevas.
            carritoSuelto.forEach(function (suelto) {
                const igual = itemsMesa.find(l => l.id_producto === suelto.id_producto && l.presentacion === suelto.presentacion
                    && (l.modificadores || []).length === 0 && (suelto.modificadores || []).length === 0
                    && !l.descuento_tipo && !suelto.descuento_tipo && l.profesional_id === (suelto.profesional_id || null));
                if (igual) {
                    igual.cantidad += suelto.cantidad;
                } else {
                    contadorLineaCarrito += 1;
                    itemsMesa.push(Object.assign({}, suelto, { _lineaId: contadorLineaCarrito }));
                }
            });
            carrito = itemsMesa;
            mesaActiva = { id_comanda: data.id_comanda, mesa_nombre: data.mesa_nombre };
            // El cliente asociado a esta mesa (si el cajero eligió uno la
            // última vez que se guardó) se restaura tal cual — sin esto se
            // "perdía" cada vez que se volvía a entrar a la mesa.
            ventaCliente = data.cliente_id ? { id_cliente: data.cliente_id, nombre: data.cliente_nombre, saldo: 0, limite_credito: 0 } : clienteSuelto;
            $('#cliente_seleccionado').empty();
            if (ventaCliente) seleccionarCliente(ventaCliente);
            $('#mesa_activa_aviso').removeClass('d-none').html(
                `Editando <strong>${mesaActiva.mesa_nombre}</strong> — ` +
                `<a href="#" onclick="vaciarCarrito(); return false;">salir sin guardar</a>`
            );
            $('#fila_guardar_mesa').removeClass('d-none');
            $('#btn_guardar_mesa_imprimir').toggleClass('d-none', !pb.impresoraPara('comanda'));
            $('#btn_confirmar_venta').html('<i class="bi bi-cart-check"></i> Cobrar y cerrar mesa');
            renderCarrito();
            renderMesas();
        },
        error: function () {
            pb.error('No se pudo abrir la mesa.');
        }
    });
}

function guardarMesaActual(imprimir) {
    if (!mesaActiva) return;
    const items = carrito.map(item => ({
        id_producto: item.id_producto,
        presentacion: item.presentacion,
        cantidad: item.cantidad,
        modificadores: (item.modificadores || []).map(m => m.id_modificador),
        profesional_id: item.profesional_id || '',
        descuento_tipo: item.descuento_tipo || null,
        descuento_valor: item.descuento_valor || 0,
    }));
    const mesaNombre = mesaActiva.mesa_nombre;
    $.ajax({
        type: 'POST',
        url: '/controlador/guardar_mesa_items.php',
        data: {
            comanda_id: mesaActiva.id_comanda,
            items: JSON.stringify(items),
            cliente_id: ventaCliente ? ventaCliente.id_cliente : '',
            // Solo se marca lo impreso cuando de verdad se pidió imprimir:
            // "Guardar sin imprimir" deja esos productos pendientes para la
            // próxima comanda.
            imprimir: imprimir ? 1 : 0,
            csrf_token: pb.csrfToken(),
        },
        dataType: 'json',
        success: function (r) {
            if (r.ok) {
                pb.success('Mesa guardada.');
                // Solo se imprime si el cajero lo pidió explícitamente con
                // el botón "Guardar e imprimir" — y solo lo NUEVO desde la
                // última vez que se imprimió esta misma mesa (r.nuevos_para_imprimir
                // ya viene filtrado así del servidor), para no reimprimir
                // productos que cocina ya despachó.
                const nuevos = r.nuevos_para_imprimir || [];
                if (imprimir && nuevos.length > 0) {
                    const comandaHtml = pb.construirComandaHTML({
                        mesa: mesaNombre,
                        vendedor: NOMBRE_CAJERO,
                        cliente: ventaCliente ? ventaCliente.nombre : null,
                        fecha: new Date().toLocaleString('es-CO'),
                        items: nuevos,
                    });
                    // Vista previa SIEMPRE que se pida imprimir — así el
                    // cajero ve en pantalla qué quedó registrado (y qué se
                    // mandó a la impresora silenciosa), en vez de solo un
                    // toast que desaparece sin dejar rastro visual.
                    ultimaComandaPreviewHtml = comandaHtml;
                    $('#modalComandaPreviewTitulo').text('Comanda — ' + mesaNombre);
                    $('#comanda_preview_contenido').html(comandaHtml);
                    pb.prepararTicketParaImprimir(document.getElementById('comanda_preview_contenido'), 'comanda');
                    bootstrap.Modal.getOrCreateInstance('#modalComandaPreview').show();
                    const impresoraComanda = pb.impresoraPara('comanda');
                    if (impresoraComanda) {
                        pb.imprimirTicketSilencioso(comandaHtml, impresoraComanda, 'comanda')
                            .then(function (res) {
                                if (!res || res.disponible === false || res.ok) return;
                                pb.error('No se pudo imprimir la comanda de cocina: ' + (res.error || 'error desconocido'));
                            });
                    }
                } else if (imprimir && nuevos.length === 0) {
                    pb.info('No hay productos nuevos que imprimir — ya se había mandado todo a cocina.');
                }
                vaciarCarrito();
                cargarListaMesas();
            } else {
                pb.error(r.error || 'Error al guardar la mesa.');
            }
        }
    });
}

async function cancelarMesa(idComanda) {
    if (!await pb.confirmar('¿Cancelar esta mesa? Se perderán los productos agregados.')) return;
    $.ajax({
        type: 'POST',
        url: '/controlador/cancelar_mesa.php',
        data: { comanda_id: idComanda, csrf_token: pb.csrfToken() },
        dataType: 'json',
        success: function (r) {
            if (r.ok) {
                if (mesaActiva && mesaActiva.id_comanda == idComanda) vaciarCarrito();
                cargarListaMesas();
            } else {
                pb.error(r.error || 'Error al cancelar la mesa.');
            }
        }
    });
}
<?php endif; ?>
</script>
