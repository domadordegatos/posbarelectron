<?php
require_once "../../modelo/bootstrap.php";
require_once "../../modelo/productos.php";

$usuarioActual = Auth::requerirRol('admin');
$tituloPagina = Configuracion::moduloActivo('existencias') ? 'Inventario' : 'Catálogo';
$paginaActiva = 'registro';

$categorias = (new productos())->listarCategorias();
$modIva = Configuracion::moduloActivo('iva');
$modCombos = Configuracion::moduloActivo('combos');
$modProveedores = Configuracion::moduloActivo('proveedores');
$modModificadores = Configuracion::moduloActivo('modificadores');
$modExistencias = Configuracion::moduloActivo('existencias');
$modVencimientos = Configuracion::moduloActivo('vencimientos');

require_once __DIR__ . "/../parciales/head.php";
require_once __DIR__ . "/../parciales/sidebar_inicio.php";
?>

<div class="row g-3">
    <div class="col-lg-5" id="colFormProducto">
        <ul class="nav nav-tabs mb-3" id="tabsProductos">
            <li class="nav-item">
                <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tabProducto" type="button">
                    <i class="bi bi-box-seam"></i> Catálogo
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabInventario" type="button">
                    <i class="bi bi-arrow-left-right"></i> <?php echo $modExistencias ? 'Compras / Pérdidas' : 'Gastos / Pérdidas'; ?>
                </button>
            </li>
        </ul>

        <div class="tab-content">
            <div class="tab-pane fade show active" id="tabProducto">
                <div class="pb-card">
                    <div class="pb-card-body">
                        <h6 class="mb-3" id="tituloFormProducto"><i class="bi bi-box-seam"></i> Crear producto</h6>
                        <form id="formProducto">
                            <input type="hidden" id="id_producto" value="">
                            <div class="mb-2">
                                <label for="nombre_producto" class="form-label">Nombre del producto</label>
                                <input type="text" class="form-control" id="nombre_producto" placeholder="Ej. Cerveza Águila" required>
                            </div>
                            <div class="mb-2">
                                <label for="codigo_producto" class="form-label">Código de barras (opcional)</label>
                                <input type="text" class="form-control" id="codigo_producto" placeholder="Ej. 7709183732" oninput="revisarCodigoParaEtiqueta(this.value)">
                                <div class="form-text text-warning d-none" id="aviso_codigo_etiqueta">
                                    <i class="bi bi-exclamation-triangle"></i> Tiene tildes, "ñ" u otro símbolo que no se puede imprimir como código de barras — bórralo si vas a usar la etiqueta de este producto.
                                </div>
                            </div>
                            <div class="row g-2 mb-2">
                                <div class="col-6">
                                    <label for="categoria" class="form-label">Categoría</label>
                                    <div class="input-group">
                                        <select class="form-select" id="categoria" required>
                                            <?php foreach ($categorias as $c): ?>
                                                <option value="<?php echo (int) $c['id_categoria']; ?>"><?php echo htmlspecialchars($c['descripcion'], ENT_QUOTES, 'UTF-8'); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <button type="button" class="btn btn-outline-secondary" title="Editar nombre de la categoría seleccionada" onclick="editarCategoriaSeleccionada()">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <button type="button" class="btn btn-outline-secondary" title="Nueva categoría" onclick="nuevaCategoria()">
                                            <i class="bi bi-plus-lg"></i>
                                        </button>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <label for="precio_unitario" class="form-label" id="label_precio_unitario">
                                        Precio por unidad
                                        <i class="bi bi-question-circle text-muted" style="cursor:pointer;" data-pb-ayuda="El precio al que le vendes una sola unidad de este producto al cliente — no es lo que te cuesta a ti, eso va abajo en Costo unitario."></i>
                                    </label>
                                    <input type="number" class="form-control" id="precio_unitario" placeholder="Ej. 3000" required>
                                </div>
                            </div>

                            <div class="form-check mb-2">
                                <input class="form-check-input" type="checkbox" id="se_vende_por_peso" onchange="cambiarSeVendePorPeso(this.checked)">
                                <label class="form-check-label" for="se_vende_por_peso">
                                    Se vende por peso (báscula)
                                    <i class="bi bi-question-circle text-muted" style="cursor:pointer;" data-pb-ayuda="Para carnes, frutas, verduras, etc. — al vender, se escribe el peso (ej. 4.5 kg) y el total se calcula solo. Si el precio por kilo varía a diario, deja el precio arriba en $0 para que el cajero lo escriba en cada venta."></i>
                                </label>
                            </div>

                            <div class="mb-2">
                                <label for="costo_unitario" class="form-label">
                                    Costo unitario
                                    <i class="bi bi-question-circle text-muted" style="cursor:pointer;" data-pb-ayuda="Cuánto te cuesta comprar una unidad. Se actualiza automáticamente como promedio ponderado cada vez que registras una compra — no necesitas editarlo a mano después."></i>
                                </label>
                                <input type="number" class="form-control" id="costo_unitario" placeholder="Ej. 2500" value="0">
                            </div>

                            <?php if ($modIva): ?>
                            <div class="mb-2">
                                <label for="tasa_iva" class="form-label">
                                    Tasa de IVA (%)
                                    <i class="bi bi-question-circle text-muted" style="cursor:pointer;" data-pb-ayuda="El porcentaje de IVA de este producto (ej. 19 para el 19%). El precio que pusiste arriba en 'Precio por unidad' ya debe incluirlo — esto solo sirve para desglosarlo en el ticket y los reportes, no se suma aparte. Si el producto no tiene IVA, déjalo en 0."></i>
                                </label>
                                <input type="number" class="form-control" id="tasa_iva" placeholder="Ej. 19" value="0">
                            </div>
                            <?php endif; ?>

                            <div class="row g-2 mb-2 align-items-end" id="fila_paquete">
                                <div class="col-6">
                                    <label for="unidades_por_paquete" class="form-label">
                                        Unidades por paquete
                                        <i class="bi bi-question-circle text-muted" style="cursor:pointer;" data-pb-ayuda="Ej. una bandeja de cerveza trae 24 unidades. Deja 1 si este producto no se vende por paquete."></i>
                                    </label>
                                    <input type="number" class="form-control" id="unidades_por_paquete" value="1" min="1">
                                </div>
                                <div class="col-6">
                                    <label for="precio_paquete" class="form-label">
                                        Precio por paquete
                                        <i class="bi bi-question-circle text-muted" style="cursor:pointer;" data-pb-ayuda="El precio de venta del paquete completo — no tiene que ser el precio por unidad multiplicado, puedes poner un valor con descuento por llevar el paquete."></i>
                                    </label>
                                    <input type="number" class="form-control" id="precio_paquete" placeholder="Ej. 90000" disabled>
                                </div>
                            </div>

                            <?php if ($modCombos): ?>
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="checkbox" id="es_combo">
                                <label class="form-check-label" for="es_combo">Es un combo/kit (se arma con otros productos)</label>
                            </div>
                            <div id="bloque_combo" class="border rounded p-2 mb-3" style="display:none;">
                                <label for="combo_buscar" class="form-label small">Agregar producto al combo</label>
                                <input type="text" class="form-control form-control-sm mb-2" id="combo_buscar" placeholder="Buscar producto…" autocomplete="off">
                                <ul id="combo_resultados" class="list-group mb-2" style="max-height: 120px; overflow-y: auto;"></ul>
                                <ul id="combo_componentes_lista" class="list-group"></ul>
                            </div>
                            <?php endif; ?>

                            <?php if ($modModificadores): ?>
                            <div class="mb-3">
                                <label class="form-label d-flex justify-content-between align-items-center mb-1">
                                    <span>
                                        Grupos de modificadores (opcional)
                                        <i class="bi bi-question-circle text-muted" style="cursor:pointer;" data-pb-ayuda="Otros productos que el cliente puede elegir al comprar este (ej. acompañamientos de un plato, accesorios de un celular, salsas de una hamburguesa). Defines cuántos vienen incluidos gratis por grupo — el resto se cobra a su propio precio. El cliente los elige en el momento de la venta, marcando en el orden que quiera."></i>
                                    </span>
                                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="agregarGrupoModificador()">
                                        <i class="bi bi-plus-lg"></i> Agregar grupo
                                    </button>
                                </label>
                                <div class="form-text mt-0 mb-2">
                                    Ej. acompañamientos de un plato, accesorios de un celular, salsas de una
                                    hamburguesa. Déjalo vacío si este producto no tiene opciones configurables.
                                </div>
                                <div id="grupos_modificadores_lista"></div>
                            </div>
                            <?php endif; ?>

                            <div class="<?php echo $modExistencias ? '' : 'd-none'; ?>" id="bloque_inventario_producto">
                            <div class="form-check mb-2" id="bloque_controla_stock">
                                <input class="form-check-input" type="checkbox" id="controla_stock" checked>
                                <label class="form-check-label" for="controla_stock">
                                    Controlar inventario de este producto
                                </label>
                                <div class="form-text mt-0">
                                    Desmarca esta opción para platos preparados al momento que no tienen un stock
                                    finito — así no generan alertas de stock bajo falsas.
                                </div>
                            </div>
                            <p class="form-text mt-0 mb-2 d-none" id="aviso_peso_sin_stock">
                                Los productos por peso no manejan inventario todavía — se pesan/sirven a ojo.
                            </p>

                            <div class="row g-2 mb-2" id="fila_stock">
                                <div class="col-6">
                                    <label for="cantidad_existente" class="form-label">Cantidad existente</label>
                                    <input type="number" class="form-control" id="cantidad_existente" placeholder="Ej. 100">
                                </div>
                                <div class="col-6">
                                    <label for="minimo_stock" class="form-label">Mínimo stock</label>
                                    <input type="number" class="form-control" id="minimo_stock" placeholder="Ej. 10">
                                </div>
                            </div>
                            </div>

                            <div class="mb-3">
                                <label for="estado" class="form-label">Estado</label>
                                <select class="form-select" id="estado">
                                    <option value="1">Activo</option>
                                    <option value="0">Inactivo</option>
                                </select>
                            </div>
                            <div class="d-grid gap-2">
                                <button type="button" class="btn btn-primary" id="btnGuardarProducto" onclick="crearProducto()">
                                    <i class="bi bi-floppy"></i> Guardar producto
                                </button>
                                <button type="button" class="btn btn-secondary d-none" id="btnActualizarProducto" onclick="guardarActualizacion()">
                                    <i class="bi bi-arrow-repeat"></i> Actualizar producto
                                </button>
                                <button type="button" class="btn btn-link d-none" id="btnCancelarEdicion" onclick="cancelarEdicion()">Cancelar edición</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <div class="tab-pane fade" id="tabInventario">
                <div class="pb-card mb-3">
                    <div class="pb-card-body">
                        <h6 class="mb-3"><i class="bi bi-box-arrow-in-down"></i> Registrar compra o gasto</h6>
                        <div class="btn-group w-100 mb-3" id="compra_modo">
                            <button type="button" class="btn btn-sm btn-primary" data-modo="producto" onclick="cambiarModoInventario('compra', 'producto')">Producto del inventario</button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" data-modo="otro" onclick="cambiarModoInventario('compra', 'otro')">Otro (gas, luz, insumos…)</button>
                        </div>

                        <div id="compra_bloque_producto">
                            <label for="compra_buscar" class="form-label">Buscar producto</label>
                            <input type="text" class="form-control mb-2" id="compra_buscar" placeholder="Nombre o código de barras" autocomplete="off">
                            <ul id="compra_resultados" class="list-group mb-2" style="max-height: 160px; overflow-y: auto;"></ul>
                            <div id="compra_seleccionado" class="small text-muted mb-2"></div>
                        </div>

                        <div id="compra_bloque_otro" class="d-none">
                            <label for="compra_concepto_buscar" class="form-label">¿Qué compraste o pagaste?</label>
                            <input type="text" class="form-control mb-2" id="compra_concepto_buscar" placeholder="Ej. gas, luz, bolsas plásticas…" autocomplete="off">
                            <ul id="compra_concepto_resultados" class="list-group mb-2" style="max-height: 200px; overflow-y: auto;"></ul>
                            <div id="compra_concepto_seleccionado" class="small text-muted mb-2"></div>
                            <div class="form-text mb-2">Elige uno de la lista o créalo al instante si no existe. No suma ni resta inventario: queda como gasto.</div>
                        </div>

                        <div class="row g-2 mb-2">
                            <div class="col-6" id="compra_fila_presentacion">
                                <label class="form-label">Presentación</label>
                                <select class="form-select" id="compra_presentacion" disabled>
                                    <option value="unidad">Unidad</option>
                                    <option value="paquete">Paquete</option>
                                </select>
                            </div>
                            <div class="col-6 d-none compra-solo-otro">
                                <label for="compra_metodo_pago" class="form-label">Se pagó con</label>
                                <select class="form-select" id="compra_metodo_pago">
                                    <option value="efectivo">Efectivo</option>
                                    <option value="tarjeta">Tarjeta</option>
                                    <option value="transferencia">Transferencia</option>
                                </select>
                            </div>
                            <div class="col-6">
                                <label for="compra_cantidad" class="form-label">Cantidad</label>
                                <input type="number" class="form-control" id="compra_cantidad" min="1" value="1">
                            </div>
                        </div>
                        <?php if ($modProveedores): ?>
                        <div class="mb-2">
                            <label for="compra_proveedor_buscar" class="form-label">Proveedor (opcional)</label>
                            <input type="text" class="form-control" id="compra_proveedor_buscar" placeholder="Buscar proveedor…" autocomplete="off">
                            <ul id="compra_proveedor_resultados" class="list-group mt-1" style="max-height: 120px; overflow-y: auto;"></ul>
                            <div id="compra_proveedor_seleccionado" class="small text-muted mt-1"></div>
                        </div>
                        <?php endif; ?>
                        <div class="mb-2">
                            <label for="compra_costo" class="form-label">Costo total pagado</label>
                            <input type="number" class="form-control" id="compra_costo" placeholder="Ej. 90000">
                        </div>
                        <div class="mb-3 d-none compra-solo-otro">
                            <label for="compra_nota" class="form-label">Nota (opcional)</label>
                            <input type="text" class="form-control" id="compra_nota" placeholder="Ej. recibo de octubre" maxlength="120">
                        </div>
                        <div class="mb-3">
                            <label for="compra_fecha" class="form-label">Fecha</label>
                            <input type="date" class="form-control" id="compra_fecha">
                            <div class="form-text">Por si se registra hoy algo que en realidad se pagó otro día.</div>
                        </div>
                        <div class="mb-3 d-none compra-solo-otro">
                            <label class="form-label mb-1">¿Se descuenta del efectivo de hoy?</label>
                            <div class="btn-group w-100" role="group">
                                <input type="radio" class="btn-check" name="compra_afecta_turno" id="compra_afecta_turno_si" value="1" checked>
                                <label class="btn btn-outline-secondary btn-sm" for="compra_afecta_turno_si">Sí, del turno de hoy</label>
                                <input type="radio" class="btn-check" name="compra_afecta_turno" id="compra_afecta_turno_no" value="0">
                                <label class="btn btn-outline-secondary btn-sm" for="compra_afecta_turno_no">No, es un gasto general</label>
                            </div>
                            <div class="form-text" id="compra_aviso_fecha_distinta"></div>
                        </div>

                        <button type="button" class="btn btn-primary w-100" onclick="registrarCompra()">
                            <i class="bi bi-save"></i> Registrar
                        </button>
                    </div>
                </div>

                <div class="pb-card">
                    <div class="pb-card-body">
                        <h6 class="mb-3"><i class="bi bi-exclamation-triangle"></i> Registrar pérdida / daño</h6>
                        <div class="btn-group w-100 mb-3" id="perdida_modo">
                            <button type="button" class="btn btn-sm btn-primary" data-modo="producto" onclick="cambiarModoInventario('perdida', 'producto')">Producto del inventario</button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" data-modo="otro" onclick="cambiarModoInventario('perdida', 'otro')">Otro (insumos, gas…)</button>
                        </div>

                        <div id="perdida_bloque_producto">
                            <label for="perdida_buscar" class="form-label">Buscar producto</label>
                            <input type="text" class="form-control mb-2" id="perdida_buscar" placeholder="Nombre o código de barras" autocomplete="off">
                            <ul id="perdida_resultados" class="list-group mb-2" style="max-height: 160px; overflow-y: auto;"></ul>
                            <div id="perdida_seleccionado" class="small text-muted mb-2"></div>
                        </div>

                        <div id="perdida_bloque_otro" class="d-none">
                            <label for="perdida_concepto_buscar" class="form-label">¿Qué se perdió o dañó?</label>
                            <input type="text" class="form-control mb-2" id="perdida_concepto_buscar" placeholder="Ej. vasos, bolsas plásticas…" autocomplete="off">
                            <ul id="perdida_concepto_resultados" class="list-group mb-2" style="max-height: 200px; overflow-y: auto;"></ul>
                            <div id="perdida_concepto_seleccionado" class="small text-muted mb-2"></div>
                            <div class="form-text mb-2">Elige uno de la lista o créalo al instante. Queda registrada con su valor, sin tocar el inventario ni la caja.</div>
                        </div>

                        <div class="row g-2 mb-2">
                            <div class="col-6" id="perdida_fila_presentacion">
                                <label class="form-label">Presentación</label>
                                <select class="form-select" id="perdida_presentacion" disabled>
                                    <option value="unidad">Unidad</option>
                                    <option value="paquete">Paquete</option>
                                </select>
                            </div>
                            <div class="col-6">
                                <label for="perdida_cantidad" class="form-label">Cantidad</label>
                                <input type="number" class="form-control" id="perdida_cantidad" min="1" value="1">
                            </div>
                        </div>
                        <div class="mb-2">
                            <label for="perdida_valor" class="form-label">Valor estimado de la pérdida</label>
                            <input type="number" class="form-control" id="perdida_valor" placeholder="Se sugiere automáticamente">
                        </div>
                        <div class="mb-3">
                            <label for="perdida_descripcion" class="form-label">Motivo (opcional)</label>
                            <input type="text" class="form-control" id="perdida_descripcion" placeholder="Ej. Vencidas, se cayeron...">
                        </div>
                        <div class="mb-3">
                            <label for="perdida_fecha" class="form-label">Fecha</label>
                            <input type="date" class="form-control" id="perdida_fecha">
                            <div class="form-text">Por si se registra hoy algo que en realidad pasó otro día.</div>
                        </div>

                        <button type="button" class="btn btn-outline-danger w-100" onclick="registrarPerdida()">
                            <i class="bi bi-dash-circle"></i> Registrar pérdida
                        </button>
                    </div>
                </div>

                <?php if ($modExistencias): ?>
                <div class="pb-card">
                    <div class="pb-card-body">
                        <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
                            <h6 class="mb-1"><i class="bi bi-arrow-left-right"></i> Salida/entrada sin pérdida ni ganancia</h6>
                            <button type="button" class="btn btn-outline-secondary btn-sm text-nowrap" data-bs-toggle="modal" data-bs-target="#modalCargaMasivaAjustes">
                                <i class="bi bi-file-earmark-spreadsheet"></i> Carga masiva
                            </button>
                        </div>
                        <p class="form-text mt-0 mb-3">
                            Para cuando algo sale (o vuelve a entrar) del inventario sin ser una venta, una compra ni una
                            pérdida real — un canje con otra empresa, una donación, una muestra, o un lote por vencer
                            que no quieres contar como pérdida. Solo mueve existencia, no afecta caja ni los reportes
                            de pérdidas/ganancias; queda como trazabilidad en el historial.
                        </p>
                        <div class="btn-group w-100 mb-3" id="ajuste_direccion">
                            <button type="button" class="btn btn-sm btn-primary" data-direccion="salida" onclick="cambiarDireccionAjuste('salida')">Sale del inventario</button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" data-direccion="entrada" onclick="cambiarDireccionAjuste('entrada')">Vuelve a entrar</button>
                        </div>

                        <label for="ajuste_buscar" class="form-label">Buscar producto</label>
                        <input type="text" class="form-control mb-2" id="ajuste_buscar" placeholder="Nombre o código de barras" autocomplete="off">
                        <ul id="ajuste_resultados" class="list-group mb-2" style="max-height: 160px; overflow-y: auto;"></ul>
                        <div id="ajuste_seleccionado" class="small text-muted mb-2"></div>

                        <div class="row g-2 mb-2">
                            <div class="col-6">
                                <label class="form-label">Presentación</label>
                                <select class="form-select" id="ajuste_presentacion" disabled>
                                    <option value="unidad">Unidad</option>
                                    <option value="paquete">Paquete</option>
                                </select>
                            </div>
                            <div class="col-6">
                                <label for="ajuste_cantidad" class="form-label">Cantidad</label>
                                <input type="number" class="form-control" id="ajuste_cantidad" min="1" value="1">
                            </div>
                        </div>
                        <div class="mb-2">
                            <div class="d-flex justify-content-between align-items-center">
                                <label for="ajuste_motivo" class="form-label mb-1">Motivo</label>
                                <a href="javascript:void(0)" class="small" onclick="abrirMotivosAjuste()">Administrar motivos</a>
                            </div>
                            <div class="d-flex gap-2">
                                <select class="form-select" id="ajuste_motivo"></select>
                                <button type="button" class="btn btn-outline-secondary text-nowrap" title="Nuevo motivo" onclick="nuevoMotivoAjuste()">
                                    <i class="bi bi-plus-lg"></i>
                                </button>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="ajuste_descripcion" class="form-label">Detalle (opcional, pero recomendado)</label>
                            <input type="text" class="form-control" id="ajuste_descripcion" placeholder="Ej. Intercambio con Droguería XYZ, lote L2024-08" maxlength="200">
                        </div>
                        <div class="mb-3">
                            <label for="ajuste_fecha" class="form-label">Fecha</label>
                            <input type="date" class="form-control" id="ajuste_fecha">
                        </div>

                        <button type="button" class="btn btn-outline-primary w-100" onclick="registrarAjusteInventario()">
                            <i class="bi bi-save"></i> Registrar
                        </button>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-lg-7 pb-columna-sticky" id="colCatalogoProductos">
        <div class="pb-card">
            <div class="pb-card-body">
                <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                    <h6 class="mb-0">Catálogo de productos</h6>
                    <div class="d-flex gap-1 align-items-center">
                        <button type="button" class="pb-btn-icon pb-btn-icon-ancho-libre d-none text-danger" id="btnLimpiarFiltrosProductos" onclick="limpiarFiltrosColumnasProductos()" title="Quitar todos los filtros de columna">
                            <i class="bi bi-funnel-fill"></i><span id="contadorFiltrosProductos" class="small"></span>
                        </button>
                        <button type="button" class="pb-btn-icon" id="btnOrdenProductos" onclick="toggleOrdenProductos()" title="Ordenar Z-A">
                            <i class="bi bi-sort-alpha-down"></i>
                        </button>
                        <button type="button" class="pb-btn-icon" id="btnVistaCompletaProductos" onclick="toggleVistaCompletaProductos()" title="Ver datos completos">
                            <i class="bi bi-arrows-angle-expand"></i>
                        </button>
                        <button type="button" class="pb-btn-icon" data-bs-toggle="modal" data-bs-target="#modalCargaMasiva" title="Carga masiva desde Excel">
                            <i class="bi bi-file-earmark-spreadsheet"></i>
                        </button>
                        <a class="pb-btn-icon" href="/controlador/exportar_productos_xlsx.php" title="Exportar catálogo a Excel">
                            <i class="bi bi-file-earmark-arrow-down"></i>
                        </a>
                        <input type="text" class="form-control form-control-sm" id="tabla_buscar" placeholder="Buscar…" style="max-width:160px;">
                    </div>
                </div>
                <div class="pb-table-wrap">
                    <table class="table table-sm table-hover align-middle pb-table-compact pb-cols-extra-ocultas" id="tablaProductos">
                        <thead>
                            <tr id="filaEncabezadosProductos"></tr>
                        </thead>
                        <tbody id="tablaProductosBody"></tbody>
                    </table>
                </div>
                <nav id="navPaginacionProductos">
                    <ul class="pagination pagination-sm justify-content-center" id="paginacionProductos"></ul>
                </nav>
            </div>
        </div>
    </div>
</div>

<div id="panelFiltroColumna" class="pb-panel-filtro d-none"></div>

<?php if ($modModificadores): ?>
<div class="modal fade" id="modalModificadoresProducto" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalModificadoresProductoTitulo">
                    Modificadores
                    <i class="bi bi-question-circle text-muted" style="cursor:pointer;" data-pb-ayuda="Otros productos que el cliente puede elegir al comprar este (ej. acompañamientos, accesorios, salsas). Defines cuántos vienen incluidos gratis por grupo; el resto se cobra a su propio precio."></i>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
                    <p class="text-muted small mb-0">Otros productos elegibles al comprar este, con su precio extra.</p>
                    <div class="d-flex gap-2 flex-wrap">
                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="togglePickerCopiarModificadores()" title="Traer los grupos de modificadores de OTRO producto hacia este — se agregan a los que ya tiene, nada se borra">
                            <i class="bi bi-box-arrow-in-down"></i> Copiar DESDE otro producto
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="togglePickerExportarModificadores()" title="Mandar los modificadores de ESTE producto hacia varios productos a la vez — se agregan a los que ya tengan, nada se borra">
                            <i class="bi bi-box-arrow-up"></i> Exportar A otros productos
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="agregarGrupoModificadorModal()">
                            <i class="bi bi-plus-lg"></i> Agregar grupo
                        </button>
                    </div>
                </div>
                <div id="copiarModificadoresPicker" class="mb-3 d-none border rounded p-2">
                    <label class="form-label small mb-1">Buscar el producto del que quieres traer sus modificadores hacia este</label>
                    <input type="text" class="form-control form-control-sm mb-1" id="copiar_modificadores_buscar" placeholder="Buscar producto…" autocomplete="off">
                    <ul class="list-group" id="copiar_modificadores_resultados" style="max-height:160px; overflow-y:auto;"></ul>
                </div>
                <div id="exportarModificadoresPicker" class="mb-3 d-none border rounded p-2">
                    <label class="form-label small mb-1">Busca y marca los productos a los que quieres mandar estos modificadores</label>
                    <input type="text" class="form-control form-control-sm mb-1" id="exportar_modificadores_buscar" placeholder="Buscar producto…" autocomplete="off">
                    <ul class="list-group mb-2" id="exportar_modificadores_resultados" style="max-height:120px; overflow-y:auto;"></ul>
                    <label class="form-label small mb-1">Seleccionados</label>
                    <ul class="list-group mb-2" id="exportar_modificadores_seleccionados"></ul>
                    <button type="button" class="btn btn-sm btn-primary" onclick="ejecutarExportarModificadores()">
                        <i class="bi bi-send"></i> Exportar a los seleccionados
                    </button>
                    <div id="exportar_modificadores_resultado" class="mt-2 small" style="max-height:160px; overflow-y:auto;"></div>
                </div>
                <div id="modalModificadoresProductoBody"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cerrar</button>
                <button type="button" class="btn btn-primary" onclick="guardarModificadoresProducto()">
                    <i class="bi bi-floppy"></i> Guardar
                </button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="modal fade" id="modalCargaMasiva" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-file-earmark-spreadsheet"></i> Carga masiva de productos</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="cargaMasivaPasoInicio">
                    <p class="small text-muted">
                        Sube un Excel (.xlsx) con varios productos a la vez (productos simples — sin combos ni
                        modificadores, esos se siguen creando uno por uno). Descarga la plantilla, complétala en
                        Excel y súbela aquí, o arranca una tabla vacía y llénala aquí mismo. Nada se guarda hasta
                        que confirmes, y vas a poder corregir errores y elegir qué filas crear antes de eso.
                    </p>
                    <a href="/controlador/plantilla_productos_xlsx.php" class="btn btn-outline-secondary btn-sm mb-3">
                        <i class="bi bi-file-earmark-excel"></i> Descargar plantilla
                    </a>
                    <div class="mb-2">
                        <label class="form-label" for="cargaMasivaArchivo">Archivo (.xlsx)</label>
                        <input type="file" class="form-control" id="cargaMasivaArchivo" accept=".xlsx">
                    </div>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-primary" onclick="validarCargaMasiva()">
                            <i class="bi bi-search"></i> Revisar archivo
                        </button>
                        <button type="button" class="btn btn-outline-secondary" onclick="iniciarTablaVaciaCargaMasiva()">
                            <i class="bi bi-table"></i> O empezar una tabla vacía
                        </button>
                    </div>
                </div>

                <div id="cargaMasivaPasoPrevisualizacion" hidden>
                    <div id="cargaMasivaResumen" class="mb-2"></div>
                    <div id="cargaMasivaCategoriasNuevas" class="mb-2" hidden></div>
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-1">
                        <p class="small text-muted mb-0">
                            Vista previa — nada se ha guardado todavía. Puedes editar cualquier celda para corregir un
                            error o un dato, y elegir con los checkbox qué filas quieres crear.
                        </p>
                        <div class="d-flex gap-2">
                            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="agregarFilaCargaMasiva()">
                                <i class="bi bi-plus-lg"></i> Agregar fila
                            </button>
                            <button type="button" class="btn btn-outline-primary btn-sm d-none" id="cargaMasivaBtnRevisarCambios" onclick="revisarCambiosCargaMasiva()">
                                <i class="bi bi-arrow-repeat"></i> Revisar cambios
                            </button>
                        </div>
                    </div>
                    <div class="pb-table-wrap" style="max-height:360px; overflow-y:auto;">
                        <table class="table table-sm align-middle">
                            <thead>
                                <tr>
                                    <th><input type="checkbox" class="form-check-input" id="cargaMasivaSeleccionarTodo" onchange="seleccionarTodoCargaMasiva(this.checked)"></th>
                                    <th>Fila</th>
                                    <th style="min-width:160px;">Producto</th>
                                    <th style="min-width:120px;">Código</th>
                                    <th style="min-width:140px;">Categoría</th>
                                    <th style="min-width:100px;">Precio</th>
                                    <th>Estado</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody id="cargaMasivaTablaPreview"></tbody>
                        </table>
                    </div>
                </div>

                <div id="cargaMasivaPasoResultado" hidden>
                    <div id="cargaMasivaResultadoResumen" class="mb-2 fw-semibold"></div>
                    <p class="small text-muted mb-1">Registro de la carga (qué se creó y qué se omitió, y por qué):</p>
                    <div class="pb-table-wrap" style="max-height:320px; overflow-y:auto;">
                        <table class="table table-sm">
                            <thead>
                                <tr><th>Fila</th><th>Producto</th><th>Resultado</th></tr>
                            </thead>
                            <tbody id="cargaMasivaTablaLog"></tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" id="cargaMasivaBtnVolver" onclick="cargaMasivaVolverInicio()" hidden>
                    <i class="bi bi-arrow-left"></i> Subir otro archivo
                </button>
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cerrar</button>
                <button type="button" class="btn btn-success" id="cargaMasivaBtnConfirmar" onclick="confirmarCargaMasiva()" hidden>
                    <i class="bi bi-check2-circle"></i> Confirmar carga
                </button>
            </div>
        </div>
    </div>
</div>

<?php if ($modVencimientos): ?>
<div class="modal fade" id="modalVencimientosProducto" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalVencimientosProductoTitulo"><i class="bi bi-exclamation-diamond"></i> Semaforización</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted small">
                    Cada lote vence una sola vez — agrégalo con su fecha y puedes sumarle cuantos avisos
                    quieras ("avisar con N días de anticipación") sin repetir la fecha cada vez.
                </p>
                <div id="vencimientos_lista_vacio" class="text-muted small mb-2 d-none">Sin vencimientos todavía.</div>
                <div id="vencimientos_lista" class="mb-3"></div>
                <div class="border-top pt-3">
                    <div class="small fw-semibold mb-2"><i class="bi bi-plus-lg"></i> Nuevo vencimiento</div>
                    <div class="row g-2">
                        <div class="col-12">
                            <label class="form-label small mb-1" for="venc_lote">Descripción del lote (opcional)</label>
                            <input type="text" class="form-control form-control-sm" id="venc_lote" placeholder="Ej. Caja recibida el 10 de octubre">
                        </div>
                        <div class="col-7">
                            <label class="form-label small mb-1" for="venc_fecha">Fecha de vencimiento</label>
                            <input type="date" class="form-control form-control-sm" id="venc_fecha">
                        </div>
                        <div class="col-5">
                            <label class="form-label small mb-1" for="venc_dias">Avisar con (días antes)</label>
                            <input type="number" class="form-control form-control-sm" id="venc_dias" min="0" step="1" value="30">
                        </div>
                    </div>
                    <button type="button" class="btn btn-sm btn-primary mt-2" onclick="agregarVencimientoModal()">
                        <i class="bi bi-plus-lg"></i> Agregar vencimiento
                    </button>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if ($modExistencias): ?>
<div class="modal fade" id="modalMotivosAjuste" tabindex="-1">
    <div class="modal-dialog modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title">Motivos de salida/entrada sin pérdida ni ganancia</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted small">
                    Renombrar un motivo también cambia cómo se ve en los movimientos anteriores.
                    Desactivarlo lo quita del selector sin borrar su historial.
                </p>
                <div class="table-responsive">
                    <table class="table table-sm align-middle" id="tabla_motivos_ajuste">
                        <thead><tr><th>Nombre</th><th class="text-center" style="width:80px;">Activo</th><th class="text-center" style="width:70px;">Usos</th><th style="width:60px;"></th></tr></thead>
                        <tbody></tbody>
                    </table>
                </div>
                <div class="border-top pt-3 mt-2">
                    <div class="small fw-semibold mb-1">Agregar motivo</div>
                    <div class="d-flex gap-2">
                        <input type="text" class="form-control" id="motivo_ajuste_nuevo_nombre" placeholder="Ej. Préstamo entre sucursales" maxlength="80">
                        <button type="button" class="btn btn-primary text-nowrap" onclick="agregarMotivoAjuste()"><i class="bi bi-plus-lg"></i> Agregar</button>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalCargaMasivaAjustes" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-file-earmark-spreadsheet"></i> Carga masiva de salidas/entradas</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="cmaPasoInicio">
                    <p class="small text-muted">
                        Sube un Excel (.xlsx) con varios movimientos a la vez — útil, por ejemplo, para registrar de
                        una sola vez un vencimiento masivo con varias referencias. Cada fila tiene que usar un motivo
                        que ya exista (si hace falta uno nuevo, créalo antes con "Administrar motivos").
                    </p>
                    <a href="/controlador/plantilla_ajustes_inventario_xlsx.php" class="btn btn-outline-secondary btn-sm mb-3">
                        <i class="bi bi-file-earmark-excel"></i> Descargar plantilla
                    </a>
                    <div class="mb-2">
                        <label class="form-label" for="cmaArchivo">Archivo (.xlsx)</label>
                        <input type="file" class="form-control" id="cmaArchivo" accept=".xlsx">
                    </div>
                    <button type="button" class="btn btn-primary" onclick="validarCargaMasivaAjustes()">
                        <i class="bi bi-search"></i> Revisar archivo
                    </button>
                </div>

                <div id="cmaPasoPreview" hidden>
                    <div id="cmaResumen" class="mb-2"></div>
                    <p class="small text-muted mb-1">Vista previa — nada se ha guardado todavía:</p>
                    <div class="pb-table-wrap" style="max-height:320px; overflow-y:auto;">
                        <table class="table table-sm">
                            <thead><tr><th>Fila</th><th>Producto</th><th>Dirección</th><th class="text-end">Cantidad</th><th>Motivo</th><th>Estado</th></tr></thead>
                            <tbody id="cmaTablaPreview"></tbody>
                        </table>
                    </div>
                </div>

                <div id="cmaPasoResultado" hidden>
                    <div id="cmaResultadoResumen" class="mb-2 fw-semibold"></div>
                    <div class="pb-table-wrap" style="max-height:320px; overflow-y:auto;">
                        <table class="table table-sm">
                            <thead><tr><th>Fila</th><th>Producto</th><th>Resultado</th></tr></thead>
                            <tbody id="cmaTablaLog"></tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" id="cmaBtnVolver" onclick="cargaMasivaAjustesVolverInicio()" hidden>
                    <i class="bi bi-arrow-left"></i> Subir otro archivo
                </button>
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cerrar</button>
                <button type="button" class="btn btn-success" id="cmaBtnConfirmar" onclick="confirmarCargaMasivaAjustes()" hidden>
                    <i class="bi bi-check2-circle"></i> Confirmar carga
                </button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="modal fade" id="modalEtiquetaProducto" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title" id="modalEtiquetaProductoTitulo"><i class="bi bi-upc-scan"></i> Etiqueta</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="etiqueta_sin_codigo" class="alert alert-warning d-none">
                    Este producto no tiene código asignado — agrégaselo editándolo (campo "Código de barras")
                    para poder generar su etiqueta.
                </div>
                <div id="etiqueta_con_codigo">
                    <p class="text-muted small mb-2">Así va a salir impresa (tamaño real según lo configurado en Configuración):</p>
                    <div class="d-flex justify-content-center mb-3">
                        <div id="etiqueta_vista_previa" style="border:1px dashed var(--pb-border); background:#fff;"></div>
                    </div>
                    <div class="mb-0" style="max-width:160px;">
                        <label for="etiqueta_copias" class="form-label">Copias a imprimir</label>
                        <input type="number" class="form-control" id="etiqueta_copias" min="1" max="50" value="1">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cerrar</button>
                <button type="button" class="btn btn-primary" id="etiqueta_btn_imprimir" onclick="imprimirEtiquetaProducto()">
                    <i class="bi bi-printer"></i> Imprimir
                </button>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . "/../parciales/pie.php"; ?>
<script src="/assets/vendor/jsbarcode/JsBarcode.all.min.js"></script>
<script>
const MOD_MODIFICADORES = <?php echo $modModificadores ? 'true' : 'false'; ?>;
const MOD_VENCIMIENTOS = <?php echo $modVencimientos ? 'true' : 'false'; ?>;
const MOD_IVA = <?php echo $modIva ? 'true' : 'false'; ?>;
const MOD_COMBOS = <?php echo $modCombos ? 'true' : 'false'; ?>;

let paginaActualProductos = 1;
let vistaCompletaProductos = false;
const MOD_EXISTENCIAS = <?php echo $modExistencias ? 'true' : 'false'; ?>;

function tipoDeProducto(p) {
    return (MOD_COMBOS && p.es_combo == 1) ? 'Combo' : (p.se_vende_por_peso == 1 ? 'Por peso' : 'Unidad');
}

// Columnas del catálogo (modo "Ver datos completos") — una sola fuente de verdad para el
// encabezado, y para el orden/filtro tipo Excel por columna, que trabaja sobre el catálogo
// completo ya cargado en el navegador (no tiene sentido en la vista paginada normal).
const COLUMNAS_PRODUCTOS = [
    { key: 'nombre_producto', titulo: 'Producto', extra: false, mostrar: () => true, valor: p => p.nombre_producto, texto: p => p.nombre_producto },
    { key: 'codigo', titulo: 'Código', extra: true, mostrar: () => true, valor: p => p.codigo || '', texto: p => p.codigo ? String(p.codigo) : '—' },
    { key: 'categoria', titulo: 'Categoría', extra: false, mostrar: () => true, valor: p => p.categoria, texto: p => p.categoria },
    { key: 'precio_unitario', titulo: 'Precio', extra: false, align: 'end', mostrar: () => true, valor: p => Number(p.precio_unitario), texto: p => pb.moneda(p.precio_unitario) },
    { key: 'costo_unitario', titulo: 'Costo', extra: true, align: 'end', mostrar: () => true, valor: p => Number(p.costo_unitario), texto: p => pb.moneda(p.costo_unitario) },
    { key: 'tasa_iva', titulo: 'IVA', extra: true, align: 'end', mostrar: () => MOD_IVA, valor: p => Number(p.tasa_iva), texto: p => p.tasa_iva + '%' },
    { key: 'cantidad_existente', titulo: 'Existencia', extra: false, align: 'center', mostrar: () => MOD_EXISTENCIAS, valor: p => p.controla_stock == 0 ? -1 : Number(p.cantidad_existente), texto: p => p.controla_stock == 0 ? 'Sin control' : String(p.cantidad_existente) },
    { key: 'minimo_stock', titulo: 'Mínimo', extra: true, align: 'center', mostrar: () => MOD_EXISTENCIAS, valor: p => p.controla_stock == 0 ? -1 : Number(p.minimo_stock), texto: p => p.controla_stock == 0 ? '—' : String(p.minimo_stock) },
    { key: 'paquete', titulo: 'Paquete', extra: true, mostrar: () => true, valor: p => Number(p.unidades_por_paquete) || 1, texto: p => p.unidades_por_paquete > 1 ? `x${p.unidades_por_paquete} (${pb.moneda(p.precio_paquete)})` : '—' },
    { key: 'tipo', titulo: 'Tipo', extra: true, mostrar: () => true, valor: tipoDeProducto, texto: tipoDeProducto },
    { key: 'estado', titulo: 'Estado', extra: false, align: 'center', mostrar: () => true, valor: p => Number(p.estado), texto: p => p.estado == 1 ? 'Activo' : 'Inactivo' },
];

let productosCache = [];
let filtrosColumnas = {};
let ordenColumnaActiva = null;
let ordenDireccionActiva = 'asc';
let columnaFiltroAbierta = null;

let compraProducto = null;
let perdidaProducto = null;
let ajusteProducto = null;
let direccionAjusteActual = 'salida';
let compraProveedor = null;
let comboComponentes = [];
let gruposModificadores = [];

const buscadorProductoCombo = pb.buscadorConTeclado({
    inputSelector: '#combo_buscar',
    resultadosSelector: '#combo_resultados',
    renderItem: (p, indice, resaltado) => `<li class="list-group-item ${resaltado ? 'pb-resultado-activo' : ''}" style="cursor:pointer;" data-indice="${indice}">${p.nombre_producto}</li>`,
    onSeleccionar: function (producto) {
        agregarComponenteCombo(producto);
        $('#combo_buscar').val('').trigger('focus');
    },
});

const buscadorProveedorCompra = pb.buscadorConTeclado({
    inputSelector: '#compra_proveedor_buscar',
    resultadosSelector: '#compra_proveedor_resultados',
    renderItem: (p, indice, resaltado) => `<li class="list-group-item ${resaltado ? 'pb-resultado-activo' : ''}" style="cursor:pointer;" data-indice="${indice}">${p.nombre}</li>`,
    onSeleccionar: function (proveedor) {
        compraProveedor = proveedor;
        $('#compra_proveedor_buscar').val('').trigger('focus');
        $('#compra_proveedor_seleccionado').text('Proveedor: ' + proveedor.nombre);
    },
});

let categoriasParaImportarModificadores = [];
// true en cuanto se toca a mano el selector "¿Se descuenta del efectivo de hoy?" del gasto con
// concepto — mientras siga en false, cambiar la fecha lo ajusta solo (ver más abajo).
let compraTurnoTocadoAMano = false;

$(function () {
    construirEncabezadosProductos();
    cargarTablaProductos(1);
    $('#tabla_buscar').on('input', pb.debounce(() => cargarTablaProductos(1), 250));

    const hoy = new Date().toISOString().split('T')[0];
    $('#compra_fecha, #perdida_fecha').val(hoy).attr('max', hoy);
    // Reporte real: un gasto con concepto de otro día (o uno que se marque como general aunque sea
    // de hoy) no debe restarse del efectivo de HOY. Solo aplica al gasto con concepto (tipo "Otro"):
    // las compras de producto y las pérdidas nunca se ligan al turno.
    $('input[name=compra_afecta_turno]').on('change', function () { compraTurnoTocadoAMano = true; });
    $('#compra_fecha').on('change', function () {
        if (compraTurnoTocadoAMano) return;
        const esHoy = $(this).val() === hoy;
        $(`#compra_afecta_turno_${esHoy ? 'si' : 'no'}`).prop('checked', true);
        $('#compra_aviso_fecha_distinta').text(esHoy ? '' : 'Como la fecha es distinta a hoy, se marcó como gasto general: no se restará del efectivo de hoy. Puedes cambiarlo si de verdad quieres que afecte el turno abierto.');
    });

    if (MOD_MODIFICADORES) {
        $.getJSON('/controlador/listar_categorias.php', function (categorias) {
            categoriasParaImportarModificadores = categorias || [];
        });
    }

    // Sin esto, Enter en cualquier campo del formulario de producto hacía
    // que el navegador lo enviara de forma nativa (recarga la página, no
    // guarda nada). Despacha a crear o actualizar según el modo activo.
    $('#formProducto').on('submit', function (e) {
        e.preventDefault();
        if ($('#id_producto').val()) {
            guardarActualizacion();
        } else {
            crearProducto();
        }
    });

    $('#controla_stock').on('change', function () {
        const controla = this.checked;
        $('#fila_stock').toggle(controla);
        if (!controla) {
            $('#cantidad_existente, #minimo_stock').val(0);
        }
    });

    $('#unidades_por_paquete').on('input', function () {
        const esPaquete = parseInt($(this).val(), 10) > 1;
        $('#precio_paquete').prop('disabled', !esPaquete);
        if (!esPaquete) $('#precio_paquete').val('');
    });

    $('#es_combo').on('change', function () {
        $('#bloque_combo').toggle(this.checked);
    });
    $('#combo_buscar').on('input', pb.debounce(buscarProductoCombo, 200));

    configurarBuscadorInventario('compra');
    configurarBuscadorInventario('perdida');
    if (MOD_EXISTENCIAS) {
        configurarBuscadorInventario('ajuste');
        cargarMotivosAjusteSelector();
    }
    configurarBuscadorConcepto('compra');
    configurarBuscadorConcepto('perdida');
    // Sin el módulo "existencias" no hay compras ni pérdidas de productos: solo gastos y pérdidas por concepto.
    if (!MOD_EXISTENCIAS) {
        $('#compra_modo, #perdida_modo').addClass('d-none');
        cambiarModoInventario('compra', 'otro');
        cambiarModoInventario('perdida', 'otro');
    }

    $('#compra_proveedor_buscar').on('input', pb.debounce(buscarProveedorCompra, 200));

    ['#perdida_cantidad', '#perdida_presentacion'].forEach(sel => $(sel).on('input change', sugerirValorPerdida));
});

// ---------- Categorías ----------
async function nuevaCategoria() {
    const nombre = await pb.preguntar('Nombre de la categoría nueva:');
    if (!nombre || !nombre.trim()) return;

    $.ajax({
        type: 'POST',
        url: '/controlador/crear_categoria.php',
        data: { descripcion: nombre.trim(), csrf_token: pb.csrfToken() },
        dataType: 'json',
        success: function (r) {
            if (r.ok) {
                $('#categoria').append(
                    $('<option>').val(r.id_categoria).text(r.descripcion)
                ).val(r.id_categoria);
                pb.success('Categoría creada.');
            } else {
                pb.error(r.error || 'No se pudo crear la categoría.');
            }
        },
        error: function () { pb.error('Error de conexión.'); }
    });
}

async function editarCategoriaSeleccionada() {
    const $opcion = $('#categoria option:selected');
    const idCategoria = $opcion.val();
    if (!idCategoria) {
        pb.error('Primero elige una categoría en la lista.');
        return;
    }

    const nombreActual = $opcion.text();
    const nombre = await pb.preguntar('Nuevo nombre para esta categoría:', nombreActual);
    if (!nombre || !nombre.trim() || nombre.trim() === nombreActual) return;

    $.ajax({
        type: 'POST',
        url: '/controlador/editar_categoria.php',
        data: { id_categoria: idCategoria, descripcion: nombre.trim(), csrf_token: pb.csrfToken() },
        dataType: 'json',
        success: function (r) {
            if (r.ok) {
                $opcion.text(r.descripcion);
                pb.success('Categoría renombrada.');
            } else {
                pb.error(r.error || 'No se pudo renombrar la categoría.');
            }
        },
        error: function () { pb.error('Error de conexión.'); }
    });
}

// ---------- Combos/kits ----------
function buscarProductoCombo() {
    const termino = $('#combo_buscar').val().trim();
    if (termino.length < 1) { buscadorProductoCombo.limpiar(); return; }

    $.ajax({
        type: 'POST',
        url: '/controlador/buscar_producto.php',
        data: { producto_buscar: termino },
        dataType: 'json',
        success: function (productos) {
            const idActual = parseInt($('#id_producto').val(), 10) || 0;
            buscadorProductoCombo.establecerResultados(productos.filter(p => p.id_producto != idActual));
        }
    });
}

function agregarComponenteCombo(producto) {
    if (comboComponentes.find(c => c.producto_base_id == producto.id_producto)) {
        pb.error('Ese producto ya está en el combo.');
        return;
    }
    comboComponentes.push({ producto_base_id: producto.id_producto, nombre_producto: producto.nombre_producto, cantidad: 1 });
    renderComponentesCombo();
}

function renderComponentesCombo() {
    const $lista = $('#combo_componentes_lista').empty();
    comboComponentes.forEach(function (c, i) {
        $lista.append(`<li class="list-group-item d-flex justify-content-between align-items-center py-1">
            <span>${c.nombre_producto}</span>
            <span class="d-flex align-items-center gap-2">
                <input type="number" class="form-control form-control-sm text-center" style="width:60px;" value="${c.cantidad}" min="1" onchange="cambiarCantidadCombo(${i}, this.value)">
                <button type="button" class="pb-btn-icon pb-btn-icon-delete" onclick="quitarComponenteCombo(${i})" title="Quitar"><i class="bi bi-x-lg"></i></button>
            </span>
        </li>`);
    });
}

function cambiarCantidadCombo(indice, valor) {
    comboComponentes[indice].cantidad = Math.max(1, parseInt(valor, 10) || 1);
}

function quitarComponenteCombo(indice) {
    comboComponentes.splice(indice, 1);
    renderComponentesCombo();
}

// ---------- Grupos de modificadores (ej. acompañamientos, extras) ----------
// Cada opción es un PRODUCTO real del catálogo (se busca y se elige, no se
// escribe a mano) — así su nombre y precio quedan siempre en vivo, y ese
// mismo producto lo puede seguir vendiendo suelto si el tendero lo agrega
// a la cuadrícula. Si además controla inventario, usarlo como modificador
// descuenta su stock igual que un componente de combo.
function renderGruposModificadores() {
    const $lista = $('#grupos_modificadores_lista').empty();
    gruposModificadores.forEach(function (grupo, i) {
        const $grupo = $(`<div class="border rounded p-2 mb-2">
            <div class="d-flex gap-2 mb-2 align-items-center">
                <input type="text" class="form-control form-control-sm" placeholder="Nombre del grupo (ej. Acompañamientos)" value="${grupo.nombre}" onchange="actualizarGrupoModificador(${i}, 'nombre', this.value)">
                <button type="button" class="pb-btn-icon pb-btn-icon-delete" onclick="quitarGrupoModificador(${i})" title="Quitar grupo"><i class="bi bi-x-lg"></i></button>
            </div>
            <div class="d-flex flex-wrap gap-2 mb-2 align-items-center">
                <div class="d-flex align-items-center gap-1">
                    <label class="form-label small mb-0">Incluidos gratis</label>
                    <input type="number" class="form-control form-control-sm" style="width:70px;" min="0" value="${grupo.incluidos_gratis}" onchange="actualizarGrupoModificador(${i}, 'incluidos_gratis', this.value)">
                    <i class="bi bi-question-circle text-muted" style="cursor:pointer;" data-pb-ayuda="Cuántas opciones de este grupo vienen gratis dentro del precio de este producto. El cliente elige en el orden que quiera — las primeras que marque (hasta este número) quedan gratis, el resto se cobra a su propio precio. Deja 0 si ninguna es gratis (todo se cobra)."></i>
                </div>
                <div class="d-flex align-items-center gap-1">
                    <label class="form-label small mb-0">Mínimo a elegir</label>
                    <input type="number" class="form-control form-control-sm" style="width:70px;" min="0" value="${grupo.minimo || 0}" onchange="actualizarGrupoModificador(${i}, 'minimo', this.value)">
                    <i class="bi bi-question-circle text-muted" style="cursor:pointer;" data-pb-ayuda="Cuántas opciones como mínimo debe elegir el cliente en este grupo antes de poder agregar el producto al carrito. Deja 0 si es completamente opcional (ej. 1 para forzar 'elige tu bebida')."></i>
                </div>
                <div class="d-flex align-items-center gap-1">
                    <label class="form-label small mb-0">Máximo</label>
                    <input type="number" class="form-control form-control-sm" style="width:70px;" min="0" placeholder="Sin límite" value="${grupo.maximo ?? ''}" onchange="actualizarGrupoModificador(${i}, 'maximo', this.value)">
                    <i class="bi bi-question-circle text-muted" style="cursor:pointer;" data-pb-ayuda="Cuántas opciones como máximo puede elegir el cliente en este grupo. Déjalo vacío si no quieres límite."></i>
                </div>
            </div>
            <ul class="list-group mb-2" id="opciones_grupo_${i}"></ul>
            <label class="form-label small mb-1">Agregar producto a este grupo</label>
            <input type="text" class="form-control form-control-sm mb-1" id="buscar_opcion_${i}" placeholder="Buscar producto…" autocomplete="off">
            <ul class="list-group mb-2" id="resultados_opcion_${i}" style="max-height:140px; overflow-y:auto;"></ul>
            <div class="d-flex gap-1">
                <select class="form-select form-select-sm" id="importar_categoria_${i}">
                    <option value="">Importar todos de una categoría…</option>
                    ${categoriasParaImportarModificadores.map(c => `<option value="${c.id_categoria}">${c.descripcion}</option>`).join('')}
                </select>
                <button type="button" class="btn btn-sm btn-outline-secondary text-nowrap" onclick="importarModificadoresDeCategoria(${i})" title="Agrega todos los productos activos de esa categoría como opciones — se salta los que ya estén, no duplica.">
                    <i class="bi bi-download"></i> Importar
                </button>
            </div>
        </div>`);
        $lista.append($grupo);

        const $opciones = $(`#opciones_grupo_${i}`);
        if (grupo.modificadores.length === 0) {
            $opciones.append('<li class="list-group-item text-muted small py-1">Todavía no hay opciones en este grupo.</li>');
        }
        grupo.modificadores.forEach(function (op, j) {
            $opciones.append(`<li class="list-group-item d-flex justify-content-between align-items-center py-1">
                <span>${op.nombre_producto} <span class="text-muted small">${pb.moneda(op.precio_unitario)}</span></span>
                <button type="button" class="pb-btn-icon pb-btn-icon-delete" onclick="quitarOpcionModificador(${i}, ${j})" title="Quitar"><i class="bi bi-x-lg"></i></button>
            </li>`);
        });

        const buscadorOpcion = pb.buscadorConTeclado({
            inputSelector: `#buscar_opcion_${i}`,
            resultadosSelector: `#resultados_opcion_${i}`,
            renderItem: (p, indice, resaltado) => `<li class="list-group-item ${resaltado ? 'pb-resultado-activo' : ''}" style="cursor:pointer;" data-indice="${indice}">${p.nombre_producto} — ${pb.moneda(p.precio_unitario)}</li>`,
            onSeleccionar: function (producto) {
                agregarOpcionModificador(i, producto);
                $(`#buscar_opcion_${i}`).val('').trigger('focus');
            },
            textoSinResultados: 'No se encontraron productos (o ya están todos agregados).',
        });
        $(`#buscar_opcion_${i}`).on('input', pb.debounce(function () {
            const termino = $(this).val().trim();
            if (termino.length < 1) { buscadorOpcion.limpiar(); return; }
            $.ajax({
                type: 'POST',
                url: '/controlador/buscar_producto.php',
                data: { producto_buscar: termino },
                dataType: 'json',
                success: function (productos) {
                    console.log('buscar_producto.php (modificadores) respondió:', productos);
                    const idPropio = parseInt($('#id_producto').val(), 10) || 0;
                    const yaAgregados = grupo.modificadores.map(m => m.producto_base_id);
                    buscadorOpcion.establecerResultados(
                        (productos || []).filter(p => p.id_producto != idPropio && !yaAgregados.includes(p.id_producto))
                    );
                },
                error: function (xhr) {
                    console.error('buscar_producto.php (modificadores) falló:', xhr.status, xhr.responseText);
                    pb.error('Error al buscar productos — mira la consola (Ctrl+Shift+I).');
                }
            });
        }, 200));
    });
    pb.inicializarAyudas();
}

function agregarGrupoModificador() {
    gruposModificadores.push({ nombre: '', incluidos_gratis: 0, minimo: 0, maximo: null, modificadores: [] });
    renderGruposModificadores();
}

function actualizarGrupoModificador(indice, campo, valor) {
    if (campo === 'incluidos_gratis' || campo === 'minimo') {
        gruposModificadores[indice][campo] = parseInt(valor, 10) || 0;
    } else if (campo === 'maximo') {
        gruposModificadores[indice][campo] = valor.trim() === '' ? null : Math.max(0, parseInt(valor, 10) || 0);
    } else {
        gruposModificadores[indice][campo] = valor;
    }
}

function quitarGrupoModificador(indice) {
    gruposModificadores.splice(indice, 1);
    renderGruposModificadores();
}

function agregarOpcionModificador(indiceGrupo, producto) {
    gruposModificadores[indiceGrupo].modificadores.push({
        producto_base_id: producto.id_producto,
        nombre_producto: producto.nombre_producto,
        precio_unitario: parseFloat(producto.precio_unitario),
    });
    renderGruposModificadores();
}

async function importarModificadoresDeCategoria(indiceGrupo) {
    const idCategoria = $(`#importar_categoria_${indiceGrupo}`).val();
    if (!idCategoria) {
        pb.error('Elige primero una categoría de la lista.');
        return;
    }
    const nombreCategoria = $(`#importar_categoria_${indiceGrupo} option:selected`).text();
    const confirmado = await pb.confirmar(
        `Esto va a agregar TODOS los productos activos de "${nombreCategoria}" como opciones de este grupo — ` +
        'revisa después y quita con la X los que no quieras dejar. ¿Continuar?'
    );
    if (!confirmado) return;

    $.getJSON('/controlador/listar_productos_categoria_cuadricula.php', { categoria: idCategoria }, function (productos) {
        const idPropio = parseInt($('#id_producto').val(), 10) || 0;
        const yaAgregados = gruposModificadores[indiceGrupo].modificadores.map(m => m.producto_base_id);
        const nuevos = (productos || []).filter(p => p.id_producto != idPropio && !yaAgregados.includes(p.id_producto));
        nuevos.forEach(p => agregarOpcionModificador(indiceGrupo, p));
        const total = (productos || []).length;
        pb.success(nuevos.length > 0
            ? `Se importaron ${nuevos.length} de ${total} producto(s) — los demás ya estaban agregados o son este mismo producto.`
            : 'No había nada nuevo que importar — ya estaban todos agregados.');
    }).fail(() => pb.error('No se pudo cargar la categoría.'));
}

function quitarOpcionModificador(indiceGrupo, indiceOpcion) {
    gruposModificadores[indiceGrupo].modificadores.splice(indiceOpcion, 1);
    renderGruposModificadores();
}

// ---------- Editor rápido de modificadores desde la tabla (sin abrir el formulario completo) ----------
let gruposModificadoresModal = [];
let productoModificadoresActualId = null;

function abrirModalModificadoresProducto(idProducto, nombreProducto) {
    productoModificadoresActualId = idProducto;
    $('#modalModificadoresProductoTitulo').text('Modificadores — ' + nombreProducto);
    $('#copiarModificadoresPicker').addClass('d-none');
    $('#exportarModificadoresPicker').addClass('d-none');
    productosParaExportar = [];
    $('#exportar_modificadores_resultado').empty();
    gruposModificadoresModal = [];
    renderGruposModificadoresModal();
    bootstrap.Modal.getOrCreateInstance('#modalModificadoresProducto').show();
    cargarModificadoresModal(idProducto);
}

function cargarModificadoresModal(idProducto) {
    $.ajax({
        type: 'POST',
        url: '/controlador/obtener_modificadores.php',
        data: { id_producto: idProducto },
        dataType: 'json',
        success: function (grupos) {
            gruposModificadoresModal = (grupos || []).map(g => ({
                // id_grupo se manda de vuelta al guardar: así el servidor
                // sabe cuál es el mismo grupo (aunque se renombre) y no lo
                // recrea — lo que ya eligió una mesa abierta sigue válido.
                id_grupo: g.id_grupo,
                nombre: g.nombre,
                incluidos_gratis: parseInt(g.incluidos_gratis, 10) || 0,
                minimo: parseInt(g.minimo, 10) || 0,
                maximo: (g.maximo === null || g.maximo === undefined || g.maximo === '') ? null : parseInt(g.maximo, 10),
                modificadores: g.modificadores.map(m => ({
                    producto_base_id: m.producto_id,
                    nombre_producto: m.nombre,
                    precio_unitario: parseFloat(m.precio_adicional) || 0,
                })),
            }));
            renderGruposModificadoresModal();
        }
    });
}

function togglePickerCopiarModificadores() {
    const $picker = $('#copiarModificadoresPicker');
    $('#exportarModificadoresPicker').addClass('d-none');
    $picker.toggleClass('d-none');
    $('#copiar_modificadores_buscar').val('');
    $('#copiar_modificadores_resultados').empty();
    if (!$picker.hasClass('d-none')) $('#copiar_modificadores_buscar').trigger('focus');
}

const buscadorCopiarModificadores = pb.buscadorConTeclado({
    inputSelector: '#copiar_modificadores_buscar',
    resultadosSelector: '#copiar_modificadores_resultados',
    renderItem: (p, indice, resaltado) => `<li class="list-group-item ${resaltado ? 'pb-resultado-activo' : ''}" style="cursor:pointer;" data-indice="${indice}">${p.nombre_producto}</li>`,
    onSeleccionar: function (producto) {
        copiarModificadoresDeProducto(producto.id_producto, producto.nombre_producto);
    },
    textoSinResultados: 'No se encontraron productos.',
});
$('#copiar_modificadores_buscar').on('input', pb.debounce(function () {
    const termino = $(this).val().trim();
    if (termino.length < 1) { buscadorCopiarModificadores.limpiar(); return; }
    $.ajax({
        type: 'POST',
        url: '/controlador/buscar_producto.php',
        data: { producto_buscar: termino },
        dataType: 'json',
        success: function (productos) {
            buscadorCopiarModificadores.establecerResultados(
                (productos || []).filter(p => p.id_producto != productoModificadoresActualId)
            );
        }
    });
}, 200));

async function copiarModificadoresDeProducto(idOrigen, nombreOrigen) {
    if (!await pb.confirmar(`¿Copiar los modificadores de "${nombreOrigen}"? Se agregan a los que ya tiene este producto — no se borra nada de lo que ya está configurado.`)) return;
    $.ajax({
        type: 'POST',
        url: '/controlador/copiar_modificadores.php',
        data: { producto_origen: idOrigen, producto_destino: productoModificadoresActualId, csrf_token: pb.csrfToken() },
        dataType: 'json',
        success: function (r) {
            if (!r.ok) {
                pb.error(r.error || 'No se pudo copiar.');
                return;
            }
            const partes = [];
            if (r.grupos_creados > 0) partes.push(`${r.grupos_creados} grupo(s) nuevo(s)`);
            if (r.grupos_fusionados > 0) partes.push(`${r.opciones_agregadas} opción(es) agregada(s) en ${r.grupos_fusionados} grupo(s) existente(s)`);
            pb.success(partes.length > 0 ? ('Copiado: ' + partes.join(', ') + '.') : 'Ese producto no tenía nada nuevo que copiar — ya estaba todo igual.');
            $('#copiarModificadoresPicker').addClass('d-none');
            cargarModificadoresModal(productoModificadoresActualId);
        },
        error: function () { pb.error('Error en el proceso.'); }
    });
}

// ---------- Exportar A varios productos a la vez ----------
let productosParaExportar = [];

function togglePickerExportarModificadores() {
    const $picker = $('#exportarModificadoresPicker');
    $('#copiarModificadoresPicker').addClass('d-none');
    $picker.toggleClass('d-none');
    productosParaExportar = [];
    renderProductosParaExportar();
    $('#exportar_modificadores_buscar').val('');
    $('#exportar_modificadores_resultados').empty();
    $('#exportar_modificadores_resultado').empty();
    if (!$picker.hasClass('d-none')) $('#exportar_modificadores_buscar').trigger('focus');
}

const buscadorExportarModificadores = pb.buscadorConTeclado({
    inputSelector: '#exportar_modificadores_buscar',
    resultadosSelector: '#exportar_modificadores_resultados',
    renderItem: (p, indice, resaltado) => `<li class="list-group-item ${resaltado ? 'pb-resultado-activo' : ''}" style="cursor:pointer;" data-indice="${indice}">${p.nombre_producto}</li>`,
    onSeleccionar: function (producto) {
        if (!productosParaExportar.some(p => p.id_producto == producto.id_producto)) {
            productosParaExportar.push({ id_producto: producto.id_producto, nombre_producto: producto.nombre_producto });
            renderProductosParaExportar();
        }
        $('#exportar_modificadores_buscar').val('').trigger('focus');
    },
    textoSinResultados: 'No se encontraron productos (o ya están todos seleccionados).',
});
$('#exportar_modificadores_buscar').on('input', pb.debounce(function () {
    const termino = $(this).val().trim();
    if (termino.length < 1) { buscadorExportarModificadores.limpiar(); return; }
    $.ajax({
        type: 'POST',
        url: '/controlador/buscar_producto.php',
        data: { producto_buscar: termino },
        dataType: 'json',
        success: function (productos) {
            const yaElegidos = productosParaExportar.map(p => p.id_producto);
            buscadorExportarModificadores.establecerResultados(
                (productos || []).filter(p => p.id_producto != productoModificadoresActualId && !yaElegidos.includes(p.id_producto))
            );
        }
    });
}, 200));

function renderProductosParaExportar() {
    const $lista = $('#exportar_modificadores_seleccionados').empty();
    if (productosParaExportar.length === 0) {
        $lista.append('<li class="list-group-item text-muted small py-1">Todavía no has elegido ningún producto.</li>');
        return;
    }
    productosParaExportar.forEach(function (p, i) {
        $lista.append(`<li class="list-group-item d-flex justify-content-between align-items-center py-1">
            <span>${p.nombre_producto}</span>
            <button type="button" class="pb-btn-icon pb-btn-icon-delete" onclick="quitarProductoParaExportar(${i})" title="Quitar"><i class="bi bi-x-lg"></i></button>
        </li>`);
    });
}

function quitarProductoParaExportar(indice) {
    productosParaExportar.splice(indice, 1);
    renderProductosParaExportar();
}

function ejecutarExportarModificadores() {
    if (productosParaExportar.length === 0) {
        pb.error('Elige al menos un producto destino.');
        return;
    }
    const $resultado = $('#exportar_modificadores_resultado').html('<span class="text-muted">Exportando…</span>');
    // Uno por uno (no todos a la vez) — así el resultado de cada producto
    // queda claro y en orden, en vez de una carrera de varias peticiones
    // sueltas que podrían llegar desordenadas.
    const pendientes = productosParaExportar.slice();
    const resultados = [];

    const iconoOk = '<i class="bi bi-check-circle-fill"></i>';
    const iconoError = '<i class="bi bi-x-circle-fill"></i>';

    function siguiente() {
        if (pendientes.length === 0) {
            $resultado.html(resultados.map((r) => `<div class="${r.clase}">${r.icono} ${r.texto}</div>`).join(''));
            productosParaExportar = [];
            renderProductosParaExportar();
            return;
        }
        const destino = pendientes.shift();
        $.ajax({
            type: 'POST',
            url: '/controlador/copiar_modificadores.php',
            data: { producto_origen: productoModificadoresActualId, producto_destino: destino.id_producto, csrf_token: pb.csrfToken() },
            dataType: 'json',
            success: function (r) {
                if (!r.ok) {
                    resultados.push({ clase: 'text-danger', icono: iconoError, texto: `${destino.nombre_producto}: ${r.error || 'error desconocido'}` });
                } else {
                    const partes = [];
                    if (r.grupos_creados > 0) partes.push(`${r.grupos_creados} grupo(s) nuevo(s)`);
                    if (r.grupos_fusionados > 0) partes.push(`${r.opciones_agregadas} opción(es) en ${r.grupos_fusionados} grupo(s)`);
                    resultados.push({
                        clase: 'text-success',
                        icono: iconoOk,
                        texto: `${destino.nombre_producto}: ${partes.length > 0 ? partes.join(', ') : 'ya estaba todo igual, sin cambios'}`,
                    });
                }
                siguiente();
            },
            error: function () {
                resultados.push({ clase: 'text-danger', icono: iconoError, texto: `${destino.nombre_producto}: error de conexión` });
                siguiente();
            }
        });
    }
    siguiente();
}

function renderGruposModificadoresModal() {
    const $lista = $('#modalModificadoresProductoBody').empty();
    if (gruposModificadoresModal.length === 0) {
        $lista.append('<p class="text-muted small mb-2">Todavía no hay grupos configurados para este producto.</p>');
    }
    gruposModificadoresModal.forEach(function (grupo, i) {
        const $grupo = $(`<div class="border rounded p-2 mb-2">
            <div class="d-flex gap-2 mb-2 align-items-center">
                <input type="text" class="form-control form-control-sm" placeholder="Nombre del grupo (ej. Acompañamientos)" value="${grupo.nombre}" onchange="actualizarGrupoModificadorModal(${i}, 'nombre', this.value)">
                <button type="button" class="pb-btn-icon pb-btn-icon-delete" onclick="quitarGrupoModificadorModal(${i})" title="Quitar grupo"><i class="bi bi-x-lg"></i></button>
            </div>
            <div class="d-flex flex-wrap gap-2 mb-2 align-items-center">
                <div class="d-flex align-items-center gap-1">
                    <label class="form-label small mb-0">Incluidos gratis</label>
                    <input type="number" class="form-control form-control-sm" style="width:70px;" min="0" value="${grupo.incluidos_gratis}" onchange="actualizarGrupoModificadorModal(${i}, 'incluidos_gratis', this.value)">
                    <i class="bi bi-question-circle text-muted" style="cursor:pointer;" data-pb-ayuda="Cuántas opciones de este grupo vienen gratis dentro del precio de este producto. El cliente elige en el orden que quiera — las primeras que marque (hasta este número) quedan gratis, el resto se cobra a su propio precio."></i>
                </div>
                <div class="d-flex align-items-center gap-1">
                    <label class="form-label small mb-0">Mínimo a elegir</label>
                    <input type="number" class="form-control form-control-sm" style="width:70px;" min="0" value="${grupo.minimo || 0}" onchange="actualizarGrupoModificadorModal(${i}, 'minimo', this.value)">
                    <i class="bi bi-question-circle text-muted" style="cursor:pointer;" data-pb-ayuda="Cuántas opciones como mínimo debe elegir el cliente en este grupo antes de poder agregar el producto al carrito. Deja 0 si es opcional."></i>
                </div>
                <div class="d-flex align-items-center gap-1">
                    <label class="form-label small mb-0">Máximo</label>
                    <input type="number" class="form-control form-control-sm" style="width:70px;" min="0" placeholder="Sin límite" value="${grupo.maximo ?? ''}" onchange="actualizarGrupoModificadorModal(${i}, 'maximo', this.value)">
                    <i class="bi bi-question-circle text-muted" style="cursor:pointer;" data-pb-ayuda="Cuántas opciones como máximo puede elegir el cliente en este grupo. Déjalo vacío si no quieres límite."></i>
                </div>
            </div>
            <ul class="list-group mb-2" id="opciones_grupo_modal_${i}"></ul>
            <label class="form-label small mb-1">Agregar producto a este grupo</label>
            <input type="text" class="form-control form-control-sm mb-1" id="buscar_opcion_modal_${i}" placeholder="Buscar producto…" autocomplete="off">
            <ul class="list-group mb-2" id="resultados_opcion_modal_${i}" style="max-height:140px; overflow-y:auto;"></ul>
            <div class="d-flex gap-1">
                <select class="form-select form-select-sm" id="importar_categoria_modal_${i}">
                    <option value="">Importar todos de una categoría…</option>
                    ${categoriasParaImportarModificadores.map(c => `<option value="${c.id_categoria}">${c.descripcion}</option>`).join('')}
                </select>
                <button type="button" class="btn btn-sm btn-outline-secondary text-nowrap" onclick="importarModificadoresDeCategoriaModal(${i})" title="Agrega todos los productos activos de esa categoría como opciones — se salta los que ya estén, no duplica.">
                    <i class="bi bi-download"></i> Importar
                </button>
            </div>
        </div>`);
        $lista.append($grupo);

        const $opciones = $(`#opciones_grupo_modal_${i}`);
        if (grupo.modificadores.length === 0) {
            $opciones.append('<li class="list-group-item text-muted small py-1">Todavía no hay opciones en este grupo.</li>');
        }
        grupo.modificadores.forEach(function (op, j) {
            $opciones.append(`<li class="list-group-item d-flex justify-content-between align-items-center py-1">
                <span>${op.nombre_producto} <span class="text-muted small">${pb.moneda(op.precio_unitario)}</span></span>
                <button type="button" class="pb-btn-icon pb-btn-icon-delete" onclick="quitarOpcionModificadorModal(${i}, ${j})" title="Quitar"><i class="bi bi-x-lg"></i></button>
            </li>`);
        });

        const buscadorOpcionModal = pb.buscadorConTeclado({
            inputSelector: `#buscar_opcion_modal_${i}`,
            resultadosSelector: `#resultados_opcion_modal_${i}`,
            renderItem: (p, indice, resaltado) => `<li class="list-group-item ${resaltado ? 'pb-resultado-activo' : ''}" style="cursor:pointer;" data-indice="${indice}">${p.nombre_producto} — ${pb.moneda(p.precio_unitario)}</li>`,
            onSeleccionar: function (producto) {
                agregarOpcionModificadorModal(i, producto);
                $(`#buscar_opcion_modal_${i}`).val('').trigger('focus');
            },
            textoSinResultados: 'No se encontraron productos (o ya están todos agregados).',
        });
        $(`#buscar_opcion_modal_${i}`).on('input', pb.debounce(function () {
            const termino = $(this).val().trim();
            if (termino.length < 1) { buscadorOpcionModal.limpiar(); return; }
            $.ajax({
                type: 'POST',
                url: '/controlador/buscar_producto.php',
                data: { producto_buscar: termino },
                dataType: 'json',
                success: function (productos) {
                    console.log('buscar_producto.php (modificadores modal) respondió:', productos);
                    const yaAgregados = grupo.modificadores.map(m => m.producto_base_id);
                    buscadorOpcionModal.establecerResultados(
                        (productos || []).filter(p => p.id_producto != productoModificadoresActualId && !yaAgregados.includes(p.id_producto))
                    );
                },
                error: function (xhr) {
                    console.error('buscar_producto.php (modificadores modal) falló:', xhr.status, xhr.responseText);
                    pb.error('Error al buscar productos — mira la consola (Ctrl+Shift+I).');
                }
            });
        }, 200));
    });
    pb.inicializarAyudas();
}

function agregarGrupoModificadorModal() {
    gruposModificadoresModal.push({ nombre: '', incluidos_gratis: 0, minimo: 0, maximo: null, modificadores: [] });
    renderGruposModificadoresModal();
}

function actualizarGrupoModificadorModal(indice, campo, valor) {
    if (campo === 'incluidos_gratis' || campo === 'minimo') {
        gruposModificadoresModal[indice][campo] = parseInt(valor, 10) || 0;
    } else if (campo === 'maximo') {
        gruposModificadoresModal[indice][campo] = valor.trim() === '' ? null : Math.max(0, parseInt(valor, 10) || 0);
    } else {
        gruposModificadoresModal[indice][campo] = valor;
    }
}

function quitarGrupoModificadorModal(indice) {
    gruposModificadoresModal.splice(indice, 1);
    renderGruposModificadoresModal();
}

function agregarOpcionModificadorModal(indiceGrupo, producto) {
    gruposModificadoresModal[indiceGrupo].modificadores.push({
        producto_base_id: producto.id_producto,
        nombre_producto: producto.nombre_producto,
        precio_unitario: parseFloat(producto.precio_unitario),
    });
    renderGruposModificadoresModal();
}

async function importarModificadoresDeCategoriaModal(indiceGrupo) {
    const idCategoria = $(`#importar_categoria_modal_${indiceGrupo}`).val();
    if (!idCategoria) {
        pb.error('Elige primero una categoría de la lista.');
        return;
    }
    const nombreCategoria = $(`#importar_categoria_modal_${indiceGrupo} option:selected`).text();
    const confirmado = await pb.confirmar(
        `Esto va a agregar TODOS los productos activos de "${nombreCategoria}" como opciones de este grupo — ` +
        'revisa después y quita con la X los que no quieras dejar. ¿Continuar?'
    );
    if (!confirmado) return;

    $.getJSON('/controlador/listar_productos_categoria_cuadricula.php', { categoria: idCategoria }, function (productos) {
        const yaAgregados = gruposModificadoresModal[indiceGrupo].modificadores.map(m => m.producto_base_id);
        const nuevos = (productos || []).filter(p => p.id_producto != productoModificadoresActualId && !yaAgregados.includes(p.id_producto));
        nuevos.forEach(p => agregarOpcionModificadorModal(indiceGrupo, p));
        const total = (productos || []).length;
        pb.success(nuevos.length > 0
            ? `Se importaron ${nuevos.length} de ${total} producto(s) — los demás ya estaban agregados o son este mismo producto.`
            : 'No había nada nuevo que importar — ya estaban todos agregados.');
    }).fail(() => pb.error('No se pudo cargar la categoría.'));
}

function quitarOpcionModificadorModal(indiceGrupo, indiceOpcion) {
    gruposModificadoresModal[indiceGrupo].modificadores.splice(indiceOpcion, 1);
    renderGruposModificadoresModal();
}

function guardarModificadoresProducto() {
    $.ajax({
        type: 'POST',
        url: '/controlador/guardar_modificadores_producto.php',
        data: {
            id_producto: productoModificadoresActualId,
            grupos_modificadores: JSON.stringify(gruposModificadoresModal),
            csrf_token: pb.csrfToken(),
        },
        dataType: 'json',
        success: function (r) {
            if (r.ok) {
                pb.success('Modificadores guardados.');
                bootstrap.Modal.getInstance(document.getElementById('modalModificadoresProducto'))?.hide();
            } else {
                pb.error(r.error || 'No se pudo guardar.');
            }
        },
        error: function () { pb.error('Error en el proceso.'); }
    });
}

// ---------- Proveedor en la compra ----------
function buscarProveedorCompra() {
    const termino = $('#compra_proveedor_buscar').val().trim();
    if (termino.length < 1) { buscadorProveedorCompra.limpiar(); return; }

    $.ajax({
        type: 'POST',
        url: '/controlador/buscar_proveedor.php',
        data: { termino: termino },
        dataType: 'json',
        success: function (proveedores) {
            buscadorProveedorCompra.establecerResultados(proveedores);
        }
    });
}

function construirEncabezadosProductos() {
    let html = '';
    COLUMNAS_PRODUCTOS.forEach(function (col) {
        if (!col.mostrar()) return;
        const claseExtra = col.extra ? ' pb-col-extra' : '';
        const claseAlign = col.align ? ` text-${col.align}` : '';
        html += `
            <th class="${claseExtra}${claseAlign}">
                <span class="pb-th-filtro">
                    <span>${col.titulo}</span>
                    <button type="button" class="pb-th-filtro-btn" id="btnFiltro_${col.key}" onclick="abrirPanelFiltroColumna(event, '${col.key}')" title="Ordenar y filtrar">
                        <i class="bi bi-caret-down-fill"></i>
                    </button>
                </span>
            </th>`;
    });
    html += '<th></th>';
    $('#filaEncabezadosProductos').html(html);
}

function toggleVistaCompletaProductos() {
    vistaCompletaProductos = !vistaCompletaProductos;
    $('#colFormProducto').toggleClass('d-none', vistaCompletaProductos);
    $('#colCatalogoProductos').toggleClass('col-lg-7', !vistaCompletaProductos).toggleClass('col-lg-12', vistaCompletaProductos);
    $('#tablaProductos').toggleClass('pb-cols-extra-ocultas', !vistaCompletaProductos);
    $('#navPaginacionProductos').toggleClass('d-none', vistaCompletaProductos);
    $('#btnOrdenProductos').toggleClass('d-none', vistaCompletaProductos);
    $('body').toggleClass('pb-vista-completa-activa', vistaCompletaProductos);
    $('#btnVistaCompletaProductos')
        .attr('title', vistaCompletaProductos ? 'Vista normal' : 'Ver datos completos')
        .html(vistaCompletaProductos ? '<i class="bi bi-arrows-angle-contract"></i>' : '<i class="bi bi-arrows-angle-expand"></i>');
    if (!vistaCompletaProductos) {
        filtrosColumnas = {};
        ordenColumnaActiva = null;
        actualizarIndicadoresFiltro();
    }
    cargarTablaProductos(1);
}

let ordenProductos = 'asc';

function toggleOrdenProductos() {
    ordenProductos = ordenProductos === 'asc' ? 'desc' : 'asc';
    $('#btnOrdenProductos')
        .attr('title', ordenProductos === 'asc' ? 'Ordenar Z-A' : 'Ordenar A-Z')
        .html(ordenProductos === 'asc' ? '<i class="bi bi-sort-alpha-down"></i>' : '<i class="bi bi-sort-alpha-up"></i>');
    cargarTablaProductos(1);
}

// ---------- Orden/filtro tipo Excel por columna (solo en "Ver datos completos") ----------
function abrirPanelFiltroColumna(event, key) {
    event.stopPropagation();
    if (columnaFiltroAbierta === key) { cerrarPanelFiltroColumna(); return; }
    const columna = COLUMNAS_PRODUCTOS.find(c => c.key === key);
    const esc = pb.escaparHtml;
    const valoresUnicos = [...new Set(productosCache.map(columna.texto))].sort((a, b) => a.localeCompare(b, 'es', { numeric: true }));
    const seleccionActual = filtrosColumnas[key];

    let html = `
        <div class="d-flex gap-1 mb-2">
            <button type="button" class="btn btn-sm btn-outline-secondary flex-fill" onclick="ordenarColumnaProductos('${key}','asc')"><i class="bi bi-sort-alpha-down"></i> A-Z</button>
            <button type="button" class="btn btn-sm btn-outline-secondary flex-fill" onclick="ordenarColumnaProductos('${key}','desc')"><i class="bi bi-sort-alpha-up"></i> Z-A</button>
        </div>
        <div class="form-check border-bottom pb-1 mb-1">
            <input class="form-check-input" type="checkbox" id="chkTodoFiltroColumna" ${!seleccionActual ? 'checked' : ''} onchange="marcarTodoFiltroColumna(this.checked)">
            <label class="form-check-label small fw-semibold" for="chkTodoFiltroColumna">(Seleccionar todo)</label>
        </div>
        <div class="pb-panel-filtro-valores">`;
    valoresUnicos.forEach(function (valorTexto, i) {
        const marcado = !seleccionActual || seleccionActual.has(valorTexto);
        html += `
            <div class="form-check">
                <input class="form-check-input pb-chk-valor-filtro" type="checkbox" id="chkFiltroValor_${i}" value="${esc(valorTexto)}" ${marcado ? 'checked' : ''}>
                <label class="form-check-label small" for="chkFiltroValor_${i}">${esc(valorTexto)}</label>
            </div>`;
    });
    html += `
        </div>
        <div class="d-flex gap-1 mt-2 pt-2 border-top">
            <button type="button" class="btn btn-sm btn-primary flex-fill" onclick="aplicarFiltroColumna('${key}')">Aplicar</button>
            <button type="button" class="btn btn-sm btn-outline-secondary flex-fill" onclick="quitarFiltroColumna('${key}')">Quitar filtro</button>
        </div>`;

    const $panel = $('#panelFiltroColumna').html(html).removeClass('d-none');
    columnaFiltroAbierta = key;

    const rect = document.getElementById(`btnFiltro_${key}`).getBoundingClientRect();
    const anchoPanel = $panel.outerWidth();
    let izquierda = Math.min(rect.left, window.innerWidth - anchoPanel - 10);
    $panel.css({ top: (rect.bottom + 4) + 'px', left: Math.max(10, izquierda) + 'px' });
}

function cerrarPanelFiltroColumna() {
    $('#panelFiltroColumna').addClass('d-none');
    columnaFiltroAbierta = null;
}

$(document).on('click', function (e) {
    if (columnaFiltroAbierta && !$(e.target).closest('#panelFiltroColumna').length) {
        cerrarPanelFiltroColumna();
    }
});
$(document).on('keydown', function (e) {
    if (e.key === 'Escape' && columnaFiltroAbierta) cerrarPanelFiltroColumna();
});

function marcarTodoFiltroColumna(marcado) {
    $('.pb-chk-valor-filtro').prop('checked', marcado);
}

function aplicarFiltroColumna(key) {
    const $checks = $('.pb-chk-valor-filtro');
    const marcados = $checks.filter(':checked').map(function () { return this.value; }).get();
    if (marcados.length === $checks.length) {
        delete filtrosColumnas[key];
    } else {
        filtrosColumnas[key] = new Set(marcados);
    }
    cerrarPanelFiltroColumna();
    actualizarIndicadoresFiltro();
    renderizarTablaCompleta();
}

function quitarFiltroColumna(key) {
    delete filtrosColumnas[key];
    cerrarPanelFiltroColumna();
    actualizarIndicadoresFiltro();
    renderizarTablaCompleta();
}

function limpiarFiltrosColumnasProductos() {
    filtrosColumnas = {};
    actualizarIndicadoresFiltro();
    renderizarTablaCompleta();
}

function actualizarIndicadoresFiltro() {
    COLUMNAS_PRODUCTOS.forEach(function (col) {
        const activo = !!filtrosColumnas[col.key];
        const $btn = $(`#btnFiltro_${col.key}`);
        $btn.toggleClass('pb-th-filtro-activo', activo);
        $btn.find('i').attr('class', activo ? 'bi bi-funnel-fill' : 'bi bi-caret-down-fill');
    });
    const total = Object.keys(filtrosColumnas).length;
    $('#btnLimpiarFiltrosProductos').toggleClass('d-none', total === 0);
    $('#contadorFiltrosProductos').text(total > 0 ? total : '');
}

function ordenarColumnaProductos(key, direccion) {
    ordenColumnaActiva = key;
    ordenDireccionActiva = direccion;
    cerrarPanelFiltroColumna();
    renderizarTablaCompleta();
}

function renderizarTablaCompleta() {
    let lista = productosCache.slice();
    Object.keys(filtrosColumnas).forEach(function (key) {
        const columna = COLUMNAS_PRODUCTOS.find(c => c.key === key);
        const permitidos = filtrosColumnas[key];
        lista = lista.filter(p => permitidos.has(columna.texto(p)));
    });
    if (ordenColumnaActiva) {
        const columna = COLUMNAS_PRODUCTOS.find(c => c.key === ordenColumnaActiva);
        lista.sort(function (a, b) {
            const va = columna.valor(a);
            const vb = columna.valor(b);
            const cmp = (typeof va === 'number' && typeof vb === 'number')
                ? va - vb
                : String(va).localeCompare(String(vb), 'es', { numeric: true });
            return ordenDireccionActiva === 'asc' ? cmp : -cmp;
        });
    }
    renderizarFilasProductos(lista);
}

function claseVencimientoIcono(estado) {
    if (estado === 'rojo') return 'pb-btn-icon-venc-rojo';
    if (estado === 'amarillo') return 'pb-btn-icon-venc-amarillo';
    if (estado === 'verde') return 'pb-btn-icon-venc-activo';
    return '';
}

function tituloVencimientoIcono(estado) {
    if (estado === 'rojo') return 'Semaforización de vencimientos — ¡hay un lote vencido!';
    if (estado === 'amarillo') return 'Semaforización de vencimientos — hay un lote próximo a vencer';
    if (estado === 'verde') return 'Semaforización de vencimientos — activa, sin alertas por ahora';
    return 'Semaforización de vencimientos — sin activar';
}

let productosParaEtiqueta = {};

function construirFilaProductoHtml(p) {
    productosParaEtiqueta[p.id_producto] = p;
    const sinControlStock = p.controla_stock == 0;
    const agotado = !sinControlStock && p.cantidad_existente <= 0;
    const bajoMinimo = !sinControlStock && !agotado && p.cantidad_existente <= p.minimo_stock;
    let badge = '';
    if (agotado) badge = '<span class="badge bg-danger ms-1">Agotado</span>';
    else if (bajoMinimo) badge = '<span class="badge bg-warning text-dark ms-1">Bajo mínimo</span>';

    const infoPaquete = p.unidades_por_paquete > 1
        ? `<br><small class="text-muted">Paquete x${p.unidades_por_paquete}${p.precio_paquete ? ': ' + pb.moneda(p.precio_paquete) : ''}</small>`
        : '';
    const tipoProducto = tipoDeProducto(p);

    return `
        <tr class="${p.estado == 0 ? 'pb-fila-inactiva' : ''} ${(MOD_EXISTENCIAS && (agotado || bajoMinimo)) ? 'pb-stock-bajo' : ''}">
            <td>${p.nombre_producto}${p.codigo ? '<br><small class="text-muted">' + p.codigo + '</small>' : ''}${infoPaquete}</td>
            <td class="pb-col-extra">${p.codigo ? p.codigo : '<span class="text-muted">—</span>'}</td>
            <td>${p.categoria}</td>
            <td class="text-end">${pb.moneda(p.precio_unitario)}</td>
            <td class="pb-col-extra text-end">${pb.moneda(p.costo_unitario)}</td>
            ${MOD_IVA ? `<td class="pb-col-extra text-end">${p.tasa_iva}%</td>` : ''}
            ${MOD_EXISTENCIAS ? `<td class="text-center">${sinControlStock ? '<span class="text-muted">Sin control</span>' : p.cantidad_existente + badge}</td>` : ''}
            ${MOD_EXISTENCIAS ? `<td class="pb-col-extra text-center">${sinControlStock ? '<span class="text-muted">—</span>' : p.minimo_stock}</td>` : ''}
            <td class="pb-col-extra">${p.unidades_por_paquete > 1 ? `x${p.unidades_por_paquete} (${pb.moneda(p.precio_paquete)})` : '<span class="text-muted">—</span>'}</td>
            <td class="pb-col-extra">${tipoProducto}</td>
            <td class="text-center">${pb.pill(p.estado == 1)}</td>
            <td class="text-end">
                <button class="pb-btn-icon pb-btn-icon-edit" onclick="cargarDatosProductoActualizar(${p.id_producto})" title="Editar">
                    <i class="bi bi-pencil"></i>
                </button>
                ${MOD_MODIFICADORES ? `<button class="pb-btn-icon" onclick="abrirModalModificadoresProducto(${p.id_producto}, '${String(p.nombre_producto).replace(/'/g, "\\'")}')" title="Editar modificadores (acompañamientos, extras, opciones...)"><i class="bi bi-stars"></i></button>` : ''}
                ${MOD_VENCIMIENTOS ? `<button class="pb-btn-icon ${claseVencimientoIcono(p.estado_vencimiento)}" onclick="abrirModalVencimientosProducto(${p.id_producto}, '${String(p.nombre_producto).replace(/'/g, "\\'")}')" title="${tituloVencimientoIcono(p.estado_vencimiento)}"><i class="bi bi-exclamation-diamond"></i></button>` : ''}
                ${p.codigo ? `<button class="pb-btn-icon" onclick="abrirModalEtiquetaProducto(${p.id_producto})" title="Imprimir etiqueta con código de barras"><i class="bi bi-upc-scan"></i></button>` : ''}
            </td>
        </tr>`;
}

function renderizarFilasProductos(lista) {
    const $body = $('#tablaProductosBody').empty();
    const colspan = $('#tablaProductos thead th:visible').length;
    if (lista.length === 0) {
        const mensaje = Object.keys(filtrosColumnas).length > 0 ? 'Ningún producto cumple los filtros aplicados.' : 'No hay productos.';
        $body.append(`<tr><td colspan="${colspan}" class="text-center text-muted">${mensaje}</td></tr>`);
        return;
    }
    lista.forEach(function (p) {
        $body.append(construirFilaProductoHtml(p));
    });
}

function cargarTablaProductos(pagina) {
    paginaActualProductos = pagina;
    $.ajax({
        type: 'POST',
        url: '/controlador/cargar_tabla_productos.php',
        data: {
            pagina: pagina,
            termino: $('#tabla_buscar').val(),
            orden: ordenProductos,
            por_pagina: vistaCompletaProductos ? 500 : 10,
        },
        dataType: 'json',
        success: function (respuesta) {
            try {
                if (!respuesta || !Array.isArray(respuesta.filas)) {
                    const colspan = $('#tablaProductos thead th:visible').length;
                    $('#tablaProductosBody').empty().append(`<tr><td colspan="${colspan}" class="text-center text-danger">Respuesta inesperada del servidor — revisa la consola (Ctrl+Shift+I).</td></tr>`);
                    pb.error('Respuesta inesperada al cargar productos — mira la consola.');
                    return;
                }
                if (vistaCompletaProductos) {
                    productosCache = respuesta.filas;
                    renderizarTablaCompleta();
                } else {
                    renderizarFilasProductos(respuesta.filas);
                }
                renderPaginacion(respuesta.total_paginas, pagina);
            } catch (err) {
                console.error('Error dibujando la tabla de productos:', err);
                pb.error('Error al mostrar los productos: ' + err.message);
            }
        },
        error: function (xhr) {
            console.error('cargar_tabla_productos.php falló:', xhr.status, xhr.responseText);
            pb.error(`Error al cargar los productos (HTTP ${xhr.status}). Mira la consola (Ctrl+Shift+I) para más detalle.`);
        }
    });
}

function renderPaginacion(totalPaginas, actual) {
    const $pag = $('#paginacionProductos').empty();
    for (let i = 1; i <= totalPaginas; i++) {
        $pag.append(`<li class="page-item ${i === actual ? 'active' : ''}">
            <a class="page-link" href="#" onclick="cargarTablaProductos(${i}); return false;">${i}</a>
        </li>`);
    }
}

// ---- Compras/gastos y pérdidas de algo que NO es un producto (gas, luz, bolsas...) ----
// Cada tarjeta (compra / pérdida) tiene dos modos: "producto" (el de siempre, mueve inventario) y
// "otro" (un concepto del catálogo conceptos_gasto, que se busca o se crea al instante, como el
// cliente en Ventas; no toca inventario).
const modoInventario = { compra: 'producto', perdida: 'producto' };
const conceptoElegido = { compra: null, perdida: null };
const busquedaConceptoId = { compra: 0, perdida: 0 };
const ETIQUETA_CATEGORIA_CONCEPTO = { servicio: 'servicio', insumo: 'insumo', otro: 'otro' };

function cambiarModoInventario(prefijo, modo) {
    modoInventario[prefijo] = modo;
    $(`#${prefijo}_modo button`).each(function () {
        const activo = $(this).data('modo') === modo;
        $(this).toggleClass('btn-primary', activo).toggleClass('btn-outline-secondary', !activo);
    });
    $(`#${prefijo}_bloque_producto, #${prefijo}_fila_presentacion`).toggleClass('d-none', modo !== 'producto');
    $(`#${prefijo}_bloque_otro, .${prefijo}-solo-otro`).toggleClass('d-none', modo !== 'otro');
    if (modo === 'otro') {
        // Al abrir el modo se muestran de una vez las opciones del catálogo.
        $(`#${prefijo}_concepto_buscar`).trigger('focus');
        buscarConcepto(prefijo);
    }
}

function cambiarDireccionAjuste(direccion) {
    direccionAjusteActual = direccion;
    $('#ajuste_direccion button').each(function () {
        const activo = $(this).data('direccion') === direccion;
        $(this).toggleClass('btn-primary', activo).toggleClass('btn-outline-secondary', !activo);
    });
}

function configurarBuscadorConcepto(prefijo) {
    $(`#${prefijo}_concepto_buscar`).on('input', pb.debounce(function () { buscarConcepto(prefijo); }, 200));
}

function buscarConcepto(prefijo) {
    const termino = $(`#${prefijo}_concepto_buscar`).val().trim();
    const idBusqueda = ++busquedaConceptoId[prefijo];
    $.ajax({
        type: 'POST',
        url: '/controlador/buscar_concepto_gasto.php',
        data: { termino: termino },
        dataType: 'json',
        success: function (conceptos) {
            // Una respuesta vieja no debe pisar a la más reciente (ver buscarClienteVenta en Ventas).
            if (idBusqueda !== busquedaConceptoId[prefijo]) return;
            pintarConceptos(prefijo, conceptos, termino);
        },
        error: function () { pb.error('No se pudo buscar.'); },
    });
}

function pintarConceptos(prefijo, conceptos, termino) {
    const $lista = $(`#${prefijo}_concepto_resultados`).empty();
    conceptos.forEach(function (c) {
        $('<li class="list-group-item" style="cursor:pointer;"></li>')
            .append($('<span></span>').text(c.nombre))
            .append($('<small class="text-muted ms-2"></small>').text(ETIQUETA_CATEGORIA_CONCEPTO[c.categoria] || 'otro'))
            .on('click', function () { seleccionarConcepto(prefijo, c); })
            .appendTo($lista);
    });

    // Si lo escrito no existe todavía, se ofrece crearlo ahí mismo eligiendo de qué tipo es.
    const existeExacto = conceptos.some(function (c) { return c.nombre.toLowerCase() === termino.toLowerCase(); });
    if (termino !== '' && !existeExacto) {
        const $crear = $('<li class="list-group-item text-primary"></li>')
            .append('<i class="bi bi-plus-lg"></i> ')
            .append(document.createTextNode('Crear "' + termino + '" como: '));
        [['servicio', 'Servicio'], ['insumo', 'Insumo'], ['otro', 'Otro']].forEach(function (par) {
            $('<button type="button" class="btn btn-sm btn-outline-primary ms-1"></button>')
                .text(par[1])
                .on('click', function () { crearConceptoRapido(prefijo, termino, par[0]); })
                .appendTo($crear);
        });
        $lista.append($crear);
    } else if (conceptos.length === 0) {
        $lista.append('<li class="list-group-item text-muted">Escribe para buscar o crear un concepto.</li>');
    }
}

function crearConceptoRapido(prefijo, nombre, categoria) {
    $.ajax({
        type: 'POST',
        url: '/controlador/crear_concepto_gasto.php',
        data: { nombre: nombre, categoria: categoria, csrf_token: pb.csrfToken() },
        dataType: 'json',
        success: function (r) {
            if (r.ok) {
                pb.success('Concepto creado.');
                seleccionarConcepto(prefijo, r);
            } else {
                pb.error(r.error || 'No se pudo crear el concepto.');
            }
        },
        error: function () { pb.error('Error de conexión.'); },
    });
}

function seleccionarConcepto(prefijo, concepto) {
    conceptoElegido[prefijo] = concepto;
    busquedaConceptoId[prefijo]++;
    $(`#${prefijo}_concepto_buscar`).val('');
    $(`#${prefijo}_concepto_resultados`).empty();
    $(`#${prefijo}_concepto_seleccionado`).empty()
        .append('Seleccionado: ')
        .append($('<strong></strong>').text(concepto.nombre))
        .append(document.createTextNode(' (' + (ETIQUETA_CATEGORIA_CONCEPTO[concepto.categoria] || 'otro') + ')'));
}

/** Registra la compra/gasto ('compra') o la pérdida ('perdida') de un concepto. No mueve inventario. */
function registrarPorConcepto(prefijo) {
    const concepto = conceptoElegido[prefijo];
    if (!concepto) {
        pb.error('Elige o crea primero qué fue lo que compraste o se perdió.');
        return;
    }
    const cantidad = parseInt($(`#${prefijo}_cantidad`).val(), 10) || 0;
    const valor = parseFloat($(prefijo === 'compra' ? '#compra_costo' : '#perdida_valor').val());
    if (cantidad <= 0 || !(valor > 0)) {
        pb.error(prefijo === 'compra' ? 'Indica la cantidad y cuánto se pagó.' : 'Indica la cantidad y el valor estimado de la pérdida.');
        return;
    }

    $.ajax({
        type: 'POST',
        url: '/controlador/registrar_gasto_concepto.php',
        data: {
            tipo: prefijo,
            concepto_id: concepto.id_concepto,
            cantidad: cantidad,
            valor: valor,
            metodo_pago: $('#compra_metodo_pago').val(),
            proveedor_id: prefijo === 'compra' && compraProveedor ? compraProveedor.id_proveedor : '',
            nota: prefijo === 'compra' ? $('#compra_nota').val() : $('#perdida_descripcion').val(),
            fecha: $(`#${prefijo}_fecha`).val(),
            afecta_turno: prefijo === 'compra' ? $('input[name=compra_afecta_turno]:checked').val() : '1',
            csrf_token: pb.csrfToken(),
        },
        dataType: 'json',
        success: function (r) {
            if (r.ok) {
                pb.success(prefijo === 'compra' ? 'Gasto registrado.' : 'Pérdida registrada (no descuenta inventario).');
                conceptoElegido[prefijo] = null;
                $(`#${prefijo}_concepto_seleccionado`).empty();
                $(`#${prefijo}_cantidad`).val(1);
                if (prefijo === 'compra') {
                    compraProveedor = null;
                    $('#compra_proveedor_seleccionado').empty();
                    $('#compra_costo, #compra_nota').val('');
                    $('#compra_afecta_turno_si').prop('checked', true);
                    $('#compra_aviso_fecha_distinta').text('');
                    compraTurnoTocadoAMano = false;
                } else {
                    $('#perdida_valor, #perdida_descripcion').val('');
                }
                buscarConcepto(prefijo);
            } else {
                pb.error(r.error || 'No se pudo registrar.');
            }
        },
        error: function () { pb.error('Error en el proceso.'); },
    });
}

// Un contador por buscador (compra / pérdida): una respuesta vieja no debe mezclarse con la nueva
// (ver buscarClienteVenta() en Ventas) — aquí eso podía registrar la compra o la pérdida en OTRO producto.
const busquedaInventarioId = { compra: 0, perdida: 0, ajuste: 0 };

function configurarBuscadorInventario(prefijo) {
    $(`#${prefijo}_buscar`).on('input', pb.debounce(function () {
        const termino = $(`#${prefijo}_buscar`).val().trim();
        const idBusqueda = ++busquedaInventarioId[prefijo];
        const $resultados = $(`#${prefijo}_resultados`).empty();
        if (termino.length < 1) return;

        $.ajax({
            type: 'POST',
            url: '/controlador/buscar_producto.php',
            data: { producto_buscar: termino },
            dataType: 'json',
            success: function (productos) {
                if (idBusqueda !== busquedaInventarioId[prefijo]) return;
                $resultados.empty();
                if (productos.length === 0) {
                    $resultados.append('<li class="list-group-item text-muted">No se encontraron productos.</li>');
                    return;
                }
                productos.forEach(function (p) {
                    $(`<li class="list-group-item" style="cursor:pointer;">
                        ${p.nombre_producto} ${p.codigo ? '- ' + p.codigo : ''} — ${pb.moneda(p.precio_unitario)}
                        ${p.unidades_por_paquete > 1 ? ' <span class="text-muted">(paquete x' + p.unidades_por_paquete + ')</span>' : ''}
                        ${p.controla_stock == 0 ? ' <span class="badge bg-secondary">sin control de stock</span>' : ''}
                    </li>`)
                        .on('click', function () {
                            busquedaInventarioId[prefijo]++;
                            seleccionarProductoInventario(prefijo, p);
                        })
                        .appendTo($resultados);
                });
            }
        });
    }, 200));
}

function seleccionarProductoInventario(prefijo, producto) {
    if (producto.controla_stock == 0) {
        const etiquetas = { compra: 'compras', perdida: 'pérdidas', ajuste: 'salidas/entradas' };
        pb.error(`"${producto.nombre_producto}" no controla inventario; no es necesario registrar ${etiquetas[prefijo] || 'movimientos'} para él.`);
        return;
    }

    if (prefijo === 'compra') compraProducto = producto;
    else if (prefijo === 'perdida') perdidaProducto = producto;
    else ajusteProducto = producto;

    $(`#${prefijo}_buscar`).val('');
    $(`#${prefijo}_resultados`).empty();
    $(`#${prefijo}_seleccionado`).html(`Seleccionado: <strong>${producto.nombre_producto}</strong> — existencia actual: ${producto.cantidad_existente}`);

    const $presentacion = $(`#${prefijo}_presentacion`);
    $presentacion.prop('disabled', false);
    if (producto.unidades_por_paquete > 1) {
        $presentacion.html(`<option value="unidad">Unidad</option><option value="paquete">Paquete (x${producto.unidades_por_paquete})</option>`);
    } else {
        $presentacion.html('<option value="unidad">Unidad</option>');
    }

    if (prefijo === 'perdida') sugerirValorPerdida();
}

function sugerirValorPerdida() {
    if (!perdidaProducto) return;
    const cantidad = parseInt($('#perdida_cantidad').val(), 10) || 0;
    const esPaquete = $('#perdida_presentacion').val() === 'paquete';
    const precio = esPaquete ? (perdidaProducto.precio_paquete || 0) : perdidaProducto.precio_unitario;
    $('#perdida_valor').val((cantidad * precio) || '');
}

function registrarCompra() {
    if (modoInventario.compra === 'otro') {
        registrarPorConcepto('compra');
        return;
    }
    if (!compraProducto) {
        pb.error('Busca y selecciona un producto primero.');
        return;
    }
    const cantidad = parseInt($('#compra_cantidad').val(), 10) || 0;
    const costo = $('#compra_costo').val();

    if (cantidad <= 0 || !costo || parseFloat(costo) < 0) {
        pb.error('Especifica una cantidad y el costo total.');
        return;
    }

    $.ajax({
        type: 'POST',
        url: '/controlador/registrar_compra.php',
        data: {
            id_producto: compraProducto.id_producto,
            presentacion: $('#compra_presentacion').val(),
            cantidad: cantidad,
            costo_total: costo,
            proveedor_id: compraProveedor ? compraProveedor.id_proveedor : '',
            fecha: $('#compra_fecha').val(),
            csrf_token: pb.csrfToken(),
        },
        dataType: 'json',
        success: function (r) {
            if (r.ok) {
                pb.success('Compra registrada. El inventario ya fue actualizado.');
                compraProducto = null;
                compraProveedor = null;
                $('#compra_seleccionado, #compra_proveedor_seleccionado').empty();
                $('#compra_cantidad').val(1);
                $('#compra_costo').val('');
                $('#compra_presentacion').prop('disabled', true).html('<option value="unidad">Unidad</option>');
                cargarTablaProductos(paginaActualProductos);
            } else {
                pb.error(r.error || 'Error al registrar la compra.');
            }
        },
        error: function () { pb.error('Error en el proceso.'); }
    });
}

function registrarPerdida() {
    if (modoInventario.perdida === 'otro') {
        registrarPorConcepto('perdida');
        return;
    }
    if (!perdidaProducto) {
        pb.error('Busca y selecciona un producto primero.');
        return;
    }
    const cantidad = parseInt($('#perdida_cantidad').val(), 10) || 0;
    const valor = $('#perdida_valor').val();

    if (cantidad <= 0) {
        pb.error('Especifica una cantidad válida.');
        return;
    }

    $.ajax({
        type: 'POST',
        url: '/controlador/registrar_perdida.php',
        data: {
            id_producto: perdidaProducto.id_producto,
            presentacion: $('#perdida_presentacion').val(),
            cantidad: cantidad,
            valor_estimado: valor || 0,
            descripcion: $('#perdida_descripcion').val(),
            fecha: $('#perdida_fecha').val(),
            csrf_token: pb.csrfToken(),
        },
        dataType: 'json',
        success: function (r) {
            if (r.ok) {
                pb.success('Pérdida registrada. El inventario ya fue descontado.');
                perdidaProducto = null;
                $('#perdida_seleccionado').empty();
                $('#perdida_cantidad').val(1);
                $('#perdida_valor, #perdida_descripcion').val('');
                $('#perdida_presentacion').prop('disabled', true).html('<option value="unidad">Unidad</option>');
                cargarTablaProductos(paginaActualProductos);
            } else {
                pb.error(r.error || 'Error al registrar la pérdida.');
            }
        },
        error: function () { pb.error('Error en el proceso.'); }
    });
}

function registrarAjusteInventario() {
    if (!ajusteProducto) {
        pb.error('Busca y selecciona un producto primero.');
        return;
    }
    const cantidad = parseInt($('#ajuste_cantidad').val(), 10) || 0;
    if (cantidad <= 0) {
        pb.error('Especifica una cantidad válida.');
        return;
    }
    if (!$('#ajuste_motivo').val()) {
        pb.error('Elige o crea un motivo primero.');
        return;
    }

    $.ajax({
        type: 'POST',
        url: '/controlador/registrar_ajuste_inventario.php',
        data: {
            id_producto: ajusteProducto.id_producto,
            direccion: direccionAjusteActual,
            presentacion: $('#ajuste_presentacion').val(),
            cantidad: cantidad,
            id_motivo: $('#ajuste_motivo').val(),
            descripcion: $('#ajuste_descripcion').val(),
            fecha: $('#ajuste_fecha').val(),
            csrf_token: pb.csrfToken(),
        },
        dataType: 'json',
        success: function (r) {
            if (r.ok) {
                pb.success(direccionAjusteActual === 'entrada' ? 'Entrada registrada. El inventario ya fue actualizado.' : 'Salida registrada. El inventario ya fue actualizado.');
                ajusteProducto = null;
                $('#ajuste_seleccionado').empty();
                $('#ajuste_cantidad').val(1);
                $('#ajuste_descripcion').val('');
                $('#ajuste_presentacion').prop('disabled', true).html('<option value="unidad">Unidad</option>');
                cargarTablaProductos(paginaActualProductos);
            } else {
                pb.error(r.error || 'No se pudo registrar el movimiento.');
            }
        },
        error: function () { pb.error('Error en el proceso.'); }
    });
}

// ---------- Motivos de "salida/entrada sin pérdida ni ganancia" (catálogo editable) ----------
function cargarMotivosAjusteSelector(seleccionarId) {
    $.getJSON('/controlador/listar_motivos_ajuste_activos.php', function (motivos) {
        const $select = $('#ajuste_motivo').empty();
        motivos.forEach(function (m) {
            $select.append($('<option>').val(m.id_motivo).text(m.nombre));
        });
        if (seleccionarId) $select.val(seleccionarId);
    }).fail(() => pb.error('No se pudieron cargar los motivos.'));
}

async function nuevoMotivoAjuste() {
    const nombre = await pb.preguntar('Nombre del motivo nuevo:');
    if (!nombre || !nombre.trim()) return;

    $.ajax({
        type: 'POST',
        url: '/controlador/crear_motivo_ajuste.php',
        data: { nombre: nombre.trim(), csrf_token: pb.csrfToken() },
        dataType: 'json',
        success: function (r) {
            if (r.ok) {
                pb.success('Motivo creado.');
                cargarMotivosAjusteSelector(r.id_motivo);
            } else {
                pb.error(r.error || 'No se pudo crear el motivo.');
            }
        },
        error: function () { pb.error('Error de conexión.'); }
    });
}

function abrirMotivosAjuste() {
    cargarMotivosAjuste();
    bootstrap.Modal.getOrCreateInstance('#modalMotivosAjuste').show();
}

function cargarMotivosAjuste() {
    $.getJSON('/controlador/listar_motivos_ajuste.php', function (motivos) {
        const $tbody = $('#tabla_motivos_ajuste tbody').empty();
        motivos.forEach(function (m) {
            const activo = parseInt(m.estado, 10) === 1;
            const $fila = $(`<tr class="${activo ? '' : 'text-muted'}">
                <td><input type="text" class="form-control form-control-sm m-nombre" maxlength="80"></td>
                <td class="text-center"><input type="checkbox" class="form-check-input m-activo"></td>
                <td class="text-center">${parseInt(m.usos, 10)}</td>
                <td class="text-end"><button type="button" class="btn btn-sm btn-outline-primary" title="Guardar"><i class="bi bi-check-lg"></i></button></td>
            </tr>`);
            $fila.find('.m-nombre').val(m.nombre);
            $fila.find('.m-activo').prop('checked', activo);
            $fila.find('button').on('click', function () { guardarMotivoAjusteFila(m.id_motivo, $fila); });
            $tbody.append($fila);
        });
    }).fail(() => pb.error('No se pudieron cargar los motivos.'));
}

function guardarMotivoAjusteFila(idMotivo, $fila) {
    $.ajax({
        type: 'POST',
        url: '/controlador/guardar_motivo_ajuste.php',
        data: {
            id_motivo: idMotivo,
            nombre: $fila.find('.m-nombre').val(),
            estado: $fila.find('.m-activo').is(':checked') ? 1 : 0,
            csrf_token: pb.csrfToken(),
        },
        dataType: 'json',
        success: function (r) {
            if (r.ok) {
                pb.success('Motivo guardado.');
                cargarMotivosAjuste();
                cargarMotivosAjusteSelector();
            } else {
                pb.error(r.error || 'No se pudo guardar.');
            }
        },
        error: function () { pb.error('Error en el proceso.'); }
    });
}

function agregarMotivoAjuste() {
    const nombre = $('#motivo_ajuste_nuevo_nombre').val().trim();
    if (nombre === '') {
        pb.error('Escribe el nombre del motivo.');
        return;
    }
    $.ajax({
        type: 'POST',
        url: '/controlador/crear_motivo_ajuste.php',
        data: { nombre: nombre, csrf_token: pb.csrfToken() },
        dataType: 'json',
        success: function (r) {
            if (r.ok) {
                pb.success('Motivo agregado.');
                $('#motivo_ajuste_nuevo_nombre').val('');
                cargarMotivosAjuste();
                cargarMotivosAjusteSelector();
            } else {
                pb.error(r.error || 'No se pudo agregar.');
            }
        },
        error: function () { pb.error('Error en el proceso.'); }
    });
}

// ---------- Carga masiva de salidas/entradas sin pérdida ni ganancia ----------
// Mismo patrón previsualizar → confirmar que la carga masiva de productos, pero sin grilla
// editable (alcance más chico: si algo sale mal, se corrige en el Excel y se vuelve a subir).
let cmaFilas = [];

function cmaMostrarPaso(paso) {
    $('#cmaPasoInicio').prop('hidden', paso !== 'inicio');
    $('#cmaPasoPreview').prop('hidden', paso !== 'preview');
    $('#cmaPasoResultado').prop('hidden', paso !== 'resultado');
    $('#cmaBtnConfirmar').prop('hidden', paso !== 'preview');
    $('#cmaBtnVolver').prop('hidden', paso === 'inicio');
}

$('#modalCargaMasivaAjustes').on('show.bs.modal', function () {
    cmaFilas = [];
    $('#cmaArchivo').val('');
    cmaMostrarPaso('inicio');
});

function cargaMasivaAjustesVolverInicio() {
    cmaFilas = [];
    $('#cmaArchivo').val('');
    cmaMostrarPaso('inicio');
}

function validarCargaMasivaAjustes() {
    const archivo = $('#cmaArchivo')[0].files[0];
    if (!archivo) {
        pb.error('Selecciona un archivo primero.');
        return;
    }
    const datos = new FormData();
    datos.append('archivo', archivo);
    datos.append('csrf_token', pb.csrfToken());

    $.ajax({
        type: 'POST',
        url: '/controlador/previsualizar_carga_ajustes.php',
        data: datos,
        processData: false,
        contentType: false,
        dataType: 'json',
        success: function (r) {
            if (r.error) { pb.error(r.error); return; }
            cmaFilas = r.filas;
            cmaRenderPreview(r);
            cmaMostrarPaso('preview');
        },
        error: function () { pb.error('No se pudo revisar el archivo.'); }
    });
}

function cmaRenderPreview(r) {
    const esc = pb.escaparHtml;
    $('#cmaResumen').html(
        `<strong>${r.total}</strong> filas leídas — ` +
        `<span class="text-success">${r.validas} listas para registrar</span>` +
        (r.con_error > 0 ? `, <span class="text-danger">${r.con_error} con error (se omitirán si confirmas así)</span>` : '')
    );

    const filasHtml = r.filas.map(function (f) {
        const estado = f.valida
            ? '<span class="badge text-bg-success">OK</span>'
            : '<span class="badge text-bg-danger">Error</span><div class="small text-danger">' + esc(f.errores.join(' ')) + '</div>';
        return `<tr class="${f.valida ? '' : 'table-danger'}">
            <td>${f.fila}</td>
            <td>${esc(f.datos.producto_nombre || '(sin nombre)')}</td>
            <td>${esc(f.datos.direccion || '—')}</td>
            <td class="text-end">${f.datos.cantidad ?? '—'}</td>
            <td>${esc(f.datos.motivo || '—')}</td>
            <td>${estado}</td>
        </tr>`;
    }).join('');
    $('#cmaTablaPreview').html(filasHtml);
    $('#cmaBtnConfirmar').prop('disabled', r.validas === 0);
}

function confirmarCargaMasivaAjustes() {
    if (!cmaFilas.length) return;
    const crudos = cmaFilas.map(function (f) { return f.crudo; });

    $.ajax({
        type: 'POST',
        url: '/controlador/confirmar_carga_ajustes.php',
        data: { filas: JSON.stringify(crudos), csrf_token: pb.csrfToken() },
        dataType: 'json',
        success: function (r) {
            if (r.error) { pb.error(r.error); return; }
            cmaRenderResultado(r);
            cmaMostrarPaso('resultado');
            cargarTablaProductos(paginaActualProductos);
        },
        error: function () { pb.error('No se pudo confirmar la carga.'); }
    });
}

function cmaRenderResultado(r) {
    const esc = pb.escaparHtml;
    $('#cmaResultadoResumen').html(
        '<span class="text-success">' + r.creados + ' registrados</span>' +
        (r.omitidos > 0 ? ', <span class="text-danger">' + r.omitidos + ' filas omitidas</span>' : '')
    );
    const filasHtml = r.log.map(function (l) {
        const claseFila = l.estado === 'ok' ? '' : 'table-danger';
        const claseTexto = l.estado === 'ok' ? 'text-success' : 'text-danger';
        return `<tr class="${claseFila}">
            <td>${l.fila}</td>
            <td>${esc(l.producto)}</td>
            <td class="${claseTexto}">${esc(l.mensaje)}</td>
        </tr>`;
    }).join('');
    $('#cmaTablaLog').html(filasHtml);

    if (r.creados > 0) pb.success(r.creados + ' movimiento(s) registrado(s) correctamente.');
}

/** Tildes, "ñ" u otro carácter fuera de ASCII no se pueden codificar como CODE128 (ver construirEtiquetaHtml en app.js). */
function revisarCodigoParaEtiqueta(valor) {
    $('#aviso_codigo_etiqueta').toggleClass('d-none', !/[^\x00-\x7F]/.test(valor || ''));
}

function limpiarFormularioProducto() {
    $('#formProducto')[0].reset();
    $('#id_producto').val('');
    $('#aviso_codigo_etiqueta').addClass('d-none');
    $('#fila_stock').show();
    $('#precio_paquete').prop('disabled', true);
    comboComponentes = [];
    $('#combo_componentes_lista').empty();
    $('#bloque_combo').hide();
    gruposModificadores = [];
    renderGruposModificadores();
    $('#se_vende_por_peso').prop('checked', false).trigger('change');
}

/**
 * Un producto por peso no se vende por paquete ni controla inventario
 * (todavía) — se oculta lo que no aplica y se relaja el precio (puede
 * quedar en $0 si el precio por kilo varía y lo escribe el cajero en cada
 * venta, ej. carnes/frutas/verduras).
 */
function cambiarSeVendePorPeso(esPorPeso) {
    $('#label_precio_unitario').contents().first().replaceWith(esPorPeso ? 'Precio por kilo ' : 'Precio por unidad ');
    $('#precio_unitario').attr('placeholder', esPorPeso ? 'Ej. 3800, o 0 si varía a diario' : 'Ej. 3000');
    $('#fila_paquete').toggle(!esPorPeso);
    $('#bloque_controla_stock').toggle(!esPorPeso);
    $('#aviso_peso_sin_stock').toggleClass('d-none', !esPorPeso);
    if (esPorPeso) {
        $('#controla_stock').prop('checked', false).trigger('change');
    }
}

function crearProducto() {
    const controlaStock = $('#controla_stock').is(':checked');
    if (!$('#nombre_producto').val() || !$('#precio_unitario').val()
        || (controlaStock && ($('#cantidad_existente').val() === '' || $('#minimo_stock').val() === ''))) {
        pb.error('Completa los campos obligatorios.');
        return;
    }

    $.ajax({
        type: 'POST',
        url: '/controlador/crear_producto.php',
        data: {
            nombre_producto: $('#nombre_producto').val(),
            codigo_producto: $('#codigo_producto').val(),
            categoria: $('#categoria').val(),
            precio_unitario: $('#precio_unitario').val(),
            cantidad_existente: $('#cantidad_existente').val() || 0,
            minimo_stock: $('#minimo_stock').val() || 0,
            estado: $('#estado').val(),
            controla_stock: controlaStock ? 1 : 0,
            unidades_por_paquete: $('#unidades_por_paquete').val() || 1,
            precio_paquete: $('#precio_paquete').val(),
            costo_unitario: $('#costo_unitario').val() || 0,
            tasa_iva: $('#tasa_iva').val() || 0,
            es_combo: $('#es_combo').is(':checked') ? 1 : 0,
            se_vende_por_peso: $('#se_vende_por_peso').is(':checked') ? 1 : 0,
            componentes: JSON.stringify(comboComponentes),
            grupos_modificadores: JSON.stringify(gruposModificadores),
            csrf_token: pb.csrfToken(),
        },
        dataType: 'json',
        success: function (r) {
            if (r === 1) {
                pb.success('Producto creado con éxito.');
                limpiarFormularioProducto();
                cargarTablaProductos(1);
            } else {
                pb.error((r && r.error) || 'Error al crear el producto.');
            }
        },
        error: function () { pb.error('Error en el proceso al insertar el producto.'); }
    });
}

function cargarDatosProductoActualizar(id) {
    $.ajax({
        type: 'POST',
        url: '/controlador/editar_producto.php',
        data: { id_producto: id },
        dataType: 'json',
        success: function (dato) {
            $('#id_producto').val(id);
            $('#nombre_producto').val(dato.nombre_producto);
            $('#categoria').val(dato.categoria);
            $('#precio_unitario').val(dato.precio_unitario);
            $('#cantidad_existente').val(dato.cantidad_existente);
            $('#minimo_stock').val(dato.minimo_stock);
            $('#estado').val(dato.estado);
            $('#codigo_producto').val(dato.codigo || '');
            revisarCodigoParaEtiqueta(dato.codigo || '');
            $('#controla_stock').prop('checked', dato.controla_stock == 1).trigger('change');
            $('#unidades_por_paquete').val(dato.unidades_por_paquete || 1);
            $('#precio_paquete').prop('disabled', !(dato.unidades_por_paquete > 1)).val(dato.precio_paquete || '');
            $('#costo_unitario').val(dato.costo_unitario || 0);
            $('#tasa_iva').val(dato.tasa_iva || 0);
            $('#es_combo').prop('checked', dato.es_combo == 1).trigger('change');
            $('#se_vende_por_peso').prop('checked', dato.se_vende_por_peso == 1).trigger('change');
            comboComponentes = [];
            $('#combo_componentes_lista').empty();
            if (dato.es_combo == 1) {
                $.ajax({
                    type: 'POST',
                    url: '/controlador/obtener_combo.php',
                    data: { id_producto: id },
                    dataType: 'json',
                    success: function (componentes) {
                        comboComponentes = componentes.map(c => ({
                            producto_base_id: c.producto_base_id,
                            nombre_producto: c.nombre_producto,
                            cantidad: c.cantidad,
                        }));
                        renderComponentesCombo();
                    }
                });
            }
            gruposModificadores = [];
            renderGruposModificadores();
            if (MOD_MODIFICADORES) {
                $.ajax({
                    type: 'POST',
                    url: '/controlador/obtener_modificadores.php',
                    data: { id_producto: id },
                    dataType: 'json',
                    success: function (grupos) {
                        gruposModificadores = grupos.map(g => ({
                            id_grupo: g.id_grupo,
                            nombre: g.nombre,
                            incluidos_gratis: parseInt(g.incluidos_gratis, 10) || 0,
                            minimo: parseInt(g.minimo, 10) || 0,
                            maximo: (g.maximo === null || g.maximo === undefined || g.maximo === '') ? null : parseInt(g.maximo, 10),
                            modificadores: g.modificadores.map(m => ({
                                producto_base_id: m.producto_id,
                                nombre_producto: m.nombre,
                                precio_unitario: parseFloat(m.precio_adicional) || 0,
                            })),
                        }));
                        renderGruposModificadores();
                    }
                });
            }
            $('#tituloFormProducto').html('<i class="bi bi-pencil-square"></i> Editar producto');
            $('#btnGuardarProducto').addClass('d-none');
            $('#btnActualizarProducto, #btnCancelarEdicion').removeClass('d-none');
            document.querySelector('#tabsProductos button[data-bs-target="#tabProducto"]').click();
            $('#nombre_producto').trigger('focus');
        },
        error: function () { pb.error('Error al cargar los datos del producto.'); }
    });
}

function cancelarEdicion() {
    limpiarFormularioProducto();
    $('#tituloFormProducto').html('<i class="bi bi-box-seam"></i> Crear producto');
    $('#btnGuardarProducto').removeClass('d-none');
    $('#btnActualizarProducto, #btnCancelarEdicion').addClass('d-none');
}

function guardarActualizacion() {
    const idProducto = $('#id_producto').val();
    if (!idProducto) return;

    $.ajax({
        type: 'POST',
        url: '/controlador/guardar_actualizacion.php',
        data: {
            id_producto: idProducto,
            nombre_producto: $('#nombre_producto').val(),
            codigo_producto: $('#codigo_producto').val(),
            categoria: $('#categoria').val(),
            precio_unitario: $('#precio_unitario').val(),
            cantidad_existente: $('#cantidad_existente').val() || 0,
            minimo_stock: $('#minimo_stock').val() || 0,
            estado: $('#estado').val(),
            controla_stock: $('#controla_stock').is(':checked') ? 1 : 0,
            unidades_por_paquete: $('#unidades_por_paquete').val() || 1,
            precio_paquete: $('#precio_paquete').val(),
            costo_unitario: $('#costo_unitario').val() || 0,
            tasa_iva: $('#tasa_iva').val() || 0,
            es_combo: $('#es_combo').is(':checked') ? 1 : 0,
            se_vende_por_peso: $('#se_vende_por_peso').is(':checked') ? 1 : 0,
            componentes: JSON.stringify(comboComponentes),
            grupos_modificadores: JSON.stringify(gruposModificadores),
            csrf_token: pb.csrfToken(),
        },
        dataType: 'json',
        success: function (r) {
            if (r === 1) {
                pb.success('Producto actualizado con éxito.');
                cancelarEdicion();
                cargarTablaProductos(paginaActualProductos);
            } else {
                pb.error((r && r.error) || 'Error al actualizar el producto.');
            }
        },
        error: function () { pb.error('Error en la solicitud.'); }
    });
}

function escaparHtmlCarga(texto) {
    return $('<div>').text(texto ?? '').html();
}

function escaparAtributoCarga(texto) {
    return escaparHtmlCarga(texto).replace(/"/g, '&quot;').replace(/'/g, '&#39;');
}

function normalizarCategoriaCarga(texto) {
    return String(texto || '').trim().toLowerCase();
}

// cargaMasivaFilas: cada elemento es lo que devuelve ImportacionProductos::validar() para esa
// fila (fila, valida, errores, datos, crudo) más un campo "seleccionada" que solo vive en el
// navegador — decide qué filas se mandan a confirmar_carga_productos.php al final.
let cargaMasivaFilas = [];
let cargaMasivaCategoriasRechazadas = new Set();
let cargaMasivaCategoriasExistentesCache = [];

function categoriaEstaRechazada(categoriaTexto) {
    return cargaMasivaCategoriasRechazadas.has(normalizarCategoriaCarga(categoriaTexto));
}

function cargaMasivaMostrarPaso(paso) {
    $('#cargaMasivaPasoInicio').prop('hidden', paso !== 'inicio');
    $('#cargaMasivaPasoPrevisualizacion').prop('hidden', paso !== 'preview');
    $('#cargaMasivaPasoResultado').prop('hidden', paso !== 'resultado');
    $('#cargaMasivaBtnConfirmar').prop('hidden', paso !== 'preview');
    $('#cargaMasivaBtnVolver').prop('hidden', paso === 'inicio');
}

$('#modalCargaMasiva').on('show.bs.modal', function () {
    cargaMasivaFilas = [];
    cargaMasivaCategoriasRechazadas = new Set();
    $('#cargaMasivaArchivo').val('');
    $('#cargaMasivaBtnRevisarCambios').addClass('d-none');
    $('#cargaMasivaCategoriasNuevas').prop('hidden', true).empty();
    cargaMasivaMostrarPaso('inicio');
    $.getJSON('/controlador/listar_categorias.php', function (categorias) {
        cargaMasivaCategoriasExistentesCache = (categorias || []).map(c => c.descripcion);
        renderCargaMasivaTabla();
    });
});

function cargaMasivaVolverInicio() {
    cargaMasivaFilas = [];
    cargaMasivaCategoriasRechazadas = new Set();
    $('#cargaMasivaArchivo').val('');
    $('#cargaMasivaBtnRevisarCambios').addClass('d-none');
    $('#cargaMasivaCategoriasNuevas').prop('hidden', true).empty();
    cargaMasivaMostrarPaso('inicio');
}

function validarCargaMasiva() {
    const archivo = $('#cargaMasivaArchivo')[0].files[0];
    if (!archivo) {
        pb.error('Selecciona un archivo primero.');
        return;
    }
    const datos = new FormData();
    datos.append('archivo', archivo);
    datos.append('csrf_token', pb.csrfToken());

    $.ajax({
        type: 'POST',
        url: '/controlador/previsualizar_carga_productos.php',
        data: datos,
        processData: false,
        contentType: false,
        dataType: 'json',
        success: function (r) {
            if (r.error) {
                pb.error(r.error);
                return;
            }
            renderCargaMasivaPreview(r);
            cargaMasivaMostrarPaso('preview');
        },
        error: function () { pb.error('No se pudo revisar el archivo.'); }
    });
}

function iniciarTablaVaciaCargaMasiva() {
    cargaMasivaFilas = [];
    cargaMasivaMostrarPaso('preview');
    renderCargaMasivaTabla();
    agregarFilaCargaMasiva();
}

function filaCargaMasivaVacia(numero) {
    const crudo = {
        _fila: numero, nombre_producto: '', categoria: '', precio_unitario: '', codigo_producto: '',
        cantidad_existente: '', minimo_stock: '', controla_stock: 'si', se_vende_por_peso: 'no',
        unidades_por_paquete: '1', precio_paquete: '', costo_unitario: '', tasa_iva: '',
    };
    return {
        fila: numero, valida: false, errores: ['Sin revisar — completa los datos y pulsa "Revisar cambios".'],
        datos: { nombre_producto: '', categoria: '', precio_unitario: 0 }, crudo: crudo, seleccionada: false,
    };
}

function agregarFilaCargaMasiva() {
    const siguiente = cargaMasivaFilas.length ? Math.max(...cargaMasivaFilas.map(f => f.fila)) + 1 : 2;
    cargaMasivaFilas.push(filaCargaMasivaVacia(siguiente));
    renderCargaMasivaTabla();
    marcarCambiosPendientesCargaMasiva();
}

function eliminarFilaCargaMasiva(indice) {
    cargaMasivaFilas.splice(indice, 1);
    renderCargaMasivaTabla();
    marcarCambiosPendientesCargaMasiva();
}

// Cada tecleo solo actualiza el dato en memoria (si se redibujara la fila completa en cada
// tecla, el cursor del input saltaría) — el estado (OK/Error) de esa fila queda desactualizado
// hasta que el usuario pulse "Revisar cambios", que es cuando se vuelve a validar de verdad.
function editarCeldaCargaMasiva(indice, campo, valor) {
    cargaMasivaFilas[indice].crudo[campo] = valor;
    cargaMasivaFilas[indice]._editada = true;
    marcarCambiosPendientesCargaMasiva();
}

function marcarCambiosPendientesCargaMasiva() {
    $('#cargaMasivaBtnRevisarCambios').removeClass('d-none');
}

function revisarCambiosCargaMasiva() {
    if (!cargaMasivaFilas.length) {
        pb.error('Agrega al menos una fila primero.');
        return;
    }
    const crudos = cargaMasivaFilas.map(function (f) { return f.crudo; });
    $.ajax({
        type: 'POST',
        url: '/controlador/revalidar_carga_productos.php',
        data: { filas: JSON.stringify(crudos), csrf_token: pb.csrfToken() },
        dataType: 'json',
        success: function (r) {
            if (r.error) { pb.error(r.error); return; }
            renderCargaMasivaPreview(r);
        },
        error: function () { pb.error('No se pudo revisar los cambios.'); }
    });
}

function toggleSeleccionFilaCargaMasiva(indice, marcado) {
    cargaMasivaFilas[indice].seleccionada = marcado;
    renderCargaMasivaTabla();
}

function seleccionarTodoCargaMasiva(marcado) {
    cargaMasivaFilas.forEach(function (f) {
        if (f.valida && !categoriaEstaRechazada(f.crudo.categoria)) f.seleccionada = marcado;
    });
    renderCargaMasivaTabla();
}

function toggleCategoriaNuevaCargaMasiva(input) {
    const clave = input.dataset.clave;
    if (input.checked) cargaMasivaCategoriasRechazadas.delete(clave);
    else cargaMasivaCategoriasRechazadas.add(clave);
    // Rechazar una categoría nueva deselecciona (sin volver a llamar al servidor) solo las
    // filas que la usan — no se crean esas filas si confirmas así. Las demás filas conservan
    // la selección que ya tenían.
    cargaMasivaFilas.forEach(function (f) {
        if (normalizarCategoriaCarga(f.crudo.categoria) !== clave) return;
        f.seleccionada = f.valida && !categoriaEstaRechazada(f.crudo.categoria);
    });
    renderCargaMasivaTabla();
}

function renderCategoriasNuevasCargaMasiva(categoriasNuevas) {
    const $cont = $('#cargaMasivaCategoriasNuevas');
    if (!categoriasNuevas || categoriasNuevas.length === 0) {
        $cont.prop('hidden', true).empty();
        return;
    }
    const esc = escaparHtmlCarga;
    let html = `<div class="alert alert-info small py-2 px-3 mb-0">
        <i class="bi bi-info-circle"></i> Se van a crear estas categorías nuevas — desmarca las que NO quieras crear
        (las filas que las usan no se importarán):
        <div class="mt-1 d-flex flex-wrap gap-3">`;
    categoriasNuevas.forEach(function (c, i) {
        const clave = normalizarCategoriaCarga(c);
        const marcado = !cargaMasivaCategoriasRechazadas.has(clave);
        html += `
            <div class="form-check mb-0">
                <input class="form-check-input" type="checkbox" id="chkCatNueva_${i}" data-clave="${escaparAtributoCarga(clave)}" ${marcado ? 'checked' : ''} onchange="toggleCategoriaNuevaCargaMasiva(this)">
                <label class="form-check-label" for="chkCatNueva_${i}">${esc(c)}</label>
            </div>`;
    });
    html += '</div></div>';
    $cont.html(html).prop('hidden', false);
}

// Celda de categoría: un <select> con las categorías que ya existen en el negocio — para
// escribir una nueva hay que elegir a propósito "+ Escribir una categoría nueva…", que revela
// un campo de texto. Así no es tan fácil crear "Bebidas" y "bebidas" como dos categorías
// distintas por una diferencia de tipeo.
function categoriaCeldaHtml(f, indice) {
    const esc = escaparHtmlCarga;
    const escAttr = escaparAtributoCarga;
    const valorActual = f.crudo.categoria || '';
    const claveActual = normalizarCategoriaCarga(valorActual);
    const coincideExistente = valorActual !== '' && cargaMasivaCategoriasExistentesCache.some(c => normalizarCategoriaCarga(c) === claveActual);
    const mostrarNueva = valorActual !== '' && !coincideExistente;

    let opciones = `<option value="" ${valorActual === '' ? 'selected' : ''}>Elige…</option>`;
    cargaMasivaCategoriasExistentesCache.forEach(function (c) {
        const sel = coincideExistente && normalizarCategoriaCarga(c) === claveActual ? 'selected' : '';
        opciones += `<option value="${escAttr(c)}" ${sel}>${esc(c)}</option>`;
    });
    opciones += `<option value="__nueva__" ${mostrarNueva ? 'selected' : ''}>+ Escribir una categoría nueva…</option>`;

    return `
        <select class="form-select form-select-sm" onchange="cambiarCategoriaSelectCargaMasiva(${indice}, this)">
            ${opciones}
        </select>
        <input type="text" class="form-control form-control-sm mt-1 ${mostrarNueva ? '' : 'd-none'}" data-indice-cat-nueva="${indice}" placeholder="Nombre de la categoría nueva" maxlength="60" value="${escAttr(valorActual)}" oninput="editarCeldaCargaMasiva(${indice}, 'categoria', this.value)">`;
}

function cambiarCategoriaSelectCargaMasiva(indice, selectEl) {
    if (selectEl.value === '__nueva__') {
        cargaMasivaFilas[indice].crudo.categoria = '';
        cargaMasivaFilas[indice]._editada = true;
        marcarCambiosPendientesCargaMasiva();
        renderCargaMasivaTabla();
        setTimeout(function () { $(`input[data-indice-cat-nueva="${indice}"]`).trigger('focus'); }, 0);
        return;
    }
    editarCeldaCargaMasiva(indice, 'categoria', selectEl.value);
    renderCargaMasivaTabla();
}

function filaPreviewHtml(f, indice) {
    const esc = escaparHtmlCarga;
    const escAttr = escaparAtributoCarga;
    const rechazadaPorCategoria = categoriaEstaRechazada(f.crudo.categoria);
    const puedeSeleccionar = f.valida && !rechazadaPorCategoria;
    let estadoHtml;
    if (rechazadaPorCategoria) {
        estadoHtml = '<span class="badge text-bg-secondary">No se crea</span><div class="small text-muted">No autorizaste crear la categoría "' + esc(f.crudo.categoria) + '".</div>';
    } else if (f.valida) {
        estadoHtml = '<span class="badge text-bg-success">OK</span>';
    } else {
        estadoHtml = '<span class="badge text-bg-danger">Error</span><div class="small text-danger">' + esc(f.errores.join(' ')) + '</div>';
    }
    const avisoEditada = f._editada ? '<div class="small text-primary"><i class="bi bi-pencil"></i> Editada — revisa los cambios</div>' : '';

    return `
        <tr class="${!f.valida || rechazadaPorCategoria ? 'table-danger' : ''}">
            <td><input type="checkbox" class="form-check-input" ${f.seleccionada ? 'checked' : ''} ${puedeSeleccionar ? '' : 'disabled'} onchange="toggleSeleccionFilaCargaMasiva(${indice}, this.checked)"></td>
            <td>${f.fila}</td>
            <td><input type="text" class="form-control form-control-sm" maxlength="120" value="${escAttr(f.crudo.nombre_producto)}" oninput="editarCeldaCargaMasiva(${indice}, 'nombre_producto', this.value)"></td>
            <td><input type="text" class="form-control form-control-sm" maxlength="60" value="${escAttr(f.crudo.codigo_producto)}" oninput="editarCeldaCargaMasiva(${indice}, 'codigo_producto', this.value)"></td>
            <td>${categoriaCeldaHtml(f, indice)}</td>
            <td><input type="number" class="form-control form-control-sm" min="0" step="1" value="${escAttr(f.crudo.precio_unitario)}" oninput="editarCeldaCargaMasiva(${indice}, 'precio_unitario', this.value)"></td>
            <td>${estadoHtml}${avisoEditada}</td>
            <td><button type="button" class="pb-btn-icon pb-btn-icon-delete" onclick="eliminarFilaCargaMasiva(${indice})" title="Quitar esta fila"><i class="bi bi-trash3"></i></button></td>
        </tr>`;
}

function renderCargaMasivaTabla() {
    const html = cargaMasivaFilas.map(function (f, i) { return filaPreviewHtml(f, i); }).join('');
    $('#cargaMasivaTablaPreview').html(html || '<tr><td colspan="8" class="text-center text-muted">Sin filas — usa "Agregar fila" o sube un archivo.</td></tr>');

    const seleccionables = cargaMasivaFilas.filter(f => f.valida && !categoriaEstaRechazada(f.crudo.categoria));
    $('#cargaMasivaSeleccionarTodo').prop('checked', seleccionables.length > 0 && seleccionables.every(f => f.seleccionada));

    const total = cargaMasivaFilas.length;
    const seleccionadas = cargaMasivaFilas.filter(f => f.seleccionada).length;
    const conError = cargaMasivaFilas.filter(f => !f.valida).length;
    $('#cargaMasivaResumen').html(
        `<strong>${total}</strong> fila(s) — ` +
        `<span class="text-success">${seleccionadas} seleccionada(s) para crear</span>` +
        (conError > 0 ? `, <span class="text-danger">${conError} con error</span>` : '')
    );
    $('#cargaMasivaBtnConfirmar').prop('disabled', seleccionadas === 0);
}

function renderCargaMasivaPreview(r) {
    cargaMasivaFilas = r.filas.map(function (f) {
        return Object.assign({}, f, { seleccionada: f.valida && !categoriaEstaRechazada(f.datos.categoria) });
    });
    renderCategoriasNuevasCargaMasiva(r.categorias_nuevas);
    $('#cargaMasivaBtnRevisarCambios').addClass('d-none');
    renderCargaMasivaTabla();
}

function confirmarCargaMasiva() {
    const seleccionadas = cargaMasivaFilas.filter(function (f) { return f.seleccionada; });
    if (!seleccionadas.length) {
        pb.error('Selecciona al menos una fila para crear.');
        return;
    }
    const crudos = seleccionadas.map(function (f) { return f.crudo; });

    $.ajax({
        type: 'POST',
        url: '/controlador/confirmar_carga_productos.php',
        data: { filas: JSON.stringify(crudos), csrf_token: pb.csrfToken() },
        dataType: 'json',
        success: function (r) {
            if (r.error) {
                pb.error(r.error);
                return;
            }
            renderCargaMasivaResultado(r);
            cargaMasivaMostrarPaso('resultado');
            cargarTablaProductos(paginaActualProductos);
        },
        error: function () { pb.error('No se pudo confirmar la carga.'); }
    });
}

function renderCargaMasivaResultado(r) {
    $('#cargaMasivaResultadoResumen').html(
        '<span class="text-success">' + r.creados + ' productos creados</span>' +
        (r.omitidos > 0 ? ', <span class="text-danger">' + r.omitidos + ' filas omitidas</span>' : '')
    );
    const filasHtml = r.log.map(function (l) {
        const claseFila = l.estado === 'ok' ? '' : 'table-danger';
        const claseTexto = l.estado === 'ok' ? 'text-success' : 'text-danger';
        return '<tr class="' + claseFila + '">'
            + '<td>' + l.fila + '</td>'
            + '<td>' + escaparHtmlCarga(l.producto) + '</td>'
            + '<td class="' + claseTexto + '">' + escaparHtmlCarga(l.mensaje) + '</td>'
            + '</tr>';
    }).join('');
    $('#cargaMasivaTablaLog').html(filasHtml);

    if (r.creados > 0) pb.success(r.creados + ' productos creados correctamente.');
}

// ---------- Etiqueta de código de barras ----------
let productoEtiquetaActual = null;

function abrirModalEtiquetaProducto(idProducto) {
    const producto = productosParaEtiqueta[idProducto];
    if (!producto) { pb.error('No se encontró el producto.'); return; }
    productoEtiquetaActual = producto;
    $('#modalEtiquetaProductoTitulo').text('Etiqueta — ' + producto.nombre_producto);
    $('#etiqueta_copias').val(1);

    const tieneCodigo = !!producto.codigo;
    $('#etiqueta_sin_codigo').toggleClass('d-none', tieneCodigo);
    $('#etiqueta_con_codigo').toggleClass('d-none', !tieneCodigo);
    $('#etiqueta_btn_imprimir').prop('disabled', !tieneCodigo);

    if (tieneCodigo) renderizarVistaPreviaEtiqueta(producto);

    bootstrap.Modal.getOrCreateInstance('#modalEtiquetaProducto').show();
}

function renderizarVistaPreviaEtiqueta(producto) {
    const html = pb.construirEtiquetaHtml(producto);
    const anchoMm = window.PB_ETIQUETA_ANCHO_MM || 50;
    const altoMm = window.PB_ETIQUETA_ALTO_MM || 30;
    $('#etiqueta_vista_previa').css({ width: anchoMm + 'mm', height: altoMm + 'mm' })
        .html(html || '<div class="p-2 small text-danger">No se pudo generar el código de barras para este valor.</div>');
}

/**
 * Mismo criterio de "silenciosa primero, diálogo si no hay puente de Electron disponible" que
 * imprimirTicketSilencioso()/imprimirConDialogo() usan en Ventas (ver registrarVenta() ahí) —
 * así una impresora configurada en Configuración pero probada fuera de Electron (ej. en el
 * navegador) cae sola al diálogo del sistema en vez de quedarse sin imprimir.
 */
async function imprimirEtiquetaProducto() {
    if (!productoEtiquetaActual || !productoEtiquetaActual.codigo) return;
    const copias = Math.max(1, Math.min(50, parseInt($('#etiqueta_copias').val(), 10) || 1));
    const htmlUna = pb.construirEtiquetaHtml(productoEtiquetaActual);
    if (!htmlUna) {
        pb.error('No se pudo generar el código de barras para este producto.');
        return;
    }
    const deviceName = window.PB_IMPRESORA_ETIQUETAS;

    if (!deviceName) {
        // window.print() abre UN diálogo del sistema, que ya trae su propio campo "Copias".
        if (copias > 1) pb.info(`No tienes impresora de etiquetas configurada — en el diálogo que se abre, pon "${copias}" en el campo Copias.`);
        pb.imprimirEtiquetaConDialogo(htmlUna);
        bootstrap.Modal.getInstance('#modalEtiquetaProducto')?.hide();
        return;
    }

    const primera = await pb.imprimirEtiquetaSilenciosa(htmlUna, deviceName);
    if (primera && primera.disponible === false) {
        if (copias > 1) pb.info(`En el diálogo que se abre, pon "${copias}" en el campo Copias.`);
        pb.imprimirEtiquetaConDialogo(htmlUna);
        bootstrap.Modal.getInstance('#modalEtiquetaProducto')?.hide();
        return;
    }
    if (!primera || !primera.ok) {
        pb.error((primera && primera.error) || 'No se pudo imprimir la etiqueta.');
        return;
    }

    for (let i = 1; i < copias; i++) {
        await pb.imprimirEtiquetaSilenciosa(htmlUna, deviceName);
    }
    pb.success(copias > 1 ? `${copias} etiquetas enviadas a imprimir.` : 'Etiqueta enviada a imprimir.');
    bootstrap.Modal.getInstance('#modalEtiquetaProducto')?.hide();
}

// ---------- Semaforización de vencimientos ----------
// Dos niveles: un vencimiento (lote + fecha, una sola vez) con N avisos (días antes) colgando. Cada
// acción se guarda sola al momento (no se arma un array en memoria para guardar todo junto) — más
// simple, y nada se pierde si el modal se cierra a mitad.
let productoVencimientosActualId = null;

function abrirModalVencimientosProducto(idProducto, nombreProducto) {
    productoVencimientosActualId = idProducto;
    $('#modalVencimientosProductoTitulo').text('Semaforización — ' + nombreProducto);
    $('#venc_lote').val('');
    $('#venc_fecha').val('');
    $('#venc_dias').val(30);
    bootstrap.Modal.getOrCreateInstance('#modalVencimientosProducto').show();
    cargarVencimientosModal();
}

function badgeEstadoVencimiento(estado) {
    if (estado === 'rojo') return '<span class="badge bg-danger">Vencido</span>';
    if (estado === 'amarillo') return '<span class="badge bg-warning text-dark">Próximo</span>';
    return '<span class="badge bg-success">OK</span>';
}

function cargarVencimientosModal() {
    $.getJSON('/controlador/listar_alertas_vencimiento.php', { producto_id: productoVencimientosActualId }, function (vencimientos) {
        const esc = pb.escaparHtml;
        const $lista = $('#vencimientos_lista').empty();
        $('#vencimientos_lista_vacio').toggleClass('d-none', vencimientos.length > 0);
        vencimientos.forEach(function (v) {
            const diasTexto = v.dias_restantes < 0 ? `${Math.abs(v.dias_restantes)} días vencido` : `${v.dias_restantes} días restantes`;
            const avisos = v.alertas.map((a) => `
                <span class="badge rounded-pill text-bg-light border me-1 mb-1">
                    avisa ${a.dias_antes} días antes
                    <a href="javascript:void(0)" class="text-danger ms-1" onclick="eliminarAvisoModal(${a.id_alerta})" title="Quitar este aviso">&times;</a>
                </span>`).join('');
            $lista.append(`
                <div class="border rounded p-2 mb-2">
                    <div class="d-flex justify-content-between align-items-start">
                        <div id="venc_vista_${v.id_vencimiento}">
                            ${badgeEstadoVencimiento(v.estado)}
                            <strong class="ms-1">${esc(v.fecha_vencimiento)}</strong>
                            ${v.descripcion_lote ? ' — ' + esc(v.descripcion_lote) : ''}
                            <div class="small text-muted">${diasTexto}</div>
                        </div>
                        <div>
                            <button type="button" class="pb-btn-icon pb-btn-icon-edit" onclick="mostrarEdicionVencimiento(${v.id_vencimiento})" title="Editar lote y fecha"><i class="bi bi-pencil"></i></button>
                            <button type="button" class="pb-btn-icon pb-btn-icon-delete" onclick="eliminarVencimientoCompleto(${v.id_vencimiento})" title="Eliminar este vencimiento y todos sus avisos"><i class="bi bi-trash3"></i></button>
                        </div>
                    </div>
                    <div id="venc_edicion_${v.id_vencimiento}" class="d-none mt-2">
                        <div class="row g-2">
                            <div class="col-12">
                                <input type="text" class="form-control form-control-sm" id="venc_edit_lote_${v.id_vencimiento}" placeholder="Descripción del lote (opcional)" value="${v.descripcion_lote ? esc(v.descripcion_lote) : ''}">
                            </div>
                            <div class="col-12">
                                <input type="date" class="form-control form-control-sm" id="venc_edit_fecha_${v.id_vencimiento}" value="${esc(v.fecha_vencimiento)}">
                            </div>
                        </div>
                        <div class="mt-2">
                            <button type="button" class="btn btn-sm btn-primary" onclick="guardarEdicionVencimiento(${v.id_vencimiento})">Guardar</button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="ocultarEdicionVencimiento(${v.id_vencimiento})">Cancelar</button>
                        </div>
                    </div>
                    <div class="mt-2">${avisos}</div>
                    <div class="input-group input-group-sm mt-2" style="max-width:260px;">
                        <input type="number" class="form-control" min="0" step="1" placeholder="Días antes" id="dias_nuevo_${v.id_vencimiento}">
                        <button type="button" class="btn btn-outline-secondary" onclick="agregarAvisoModal(${v.id_vencimiento})"><i class="bi bi-plus-lg"></i> Agregar aviso</button>
                    </div>
                </div>`);
        });
    }).fail(() => pb.error('No se pudieron cargar los vencimientos.'));
}

function agregarVencimientoModal() {
    const fecha = $('#venc_fecha').val();
    const dias = parseInt($('#venc_dias').val(), 10);
    if (!fecha) { pb.error('Elige la fecha de vencimiento.'); return; }
    if (isNaN(dias) || dias < 0) { pb.error('Los días de anticipación deben ser un número válido.'); return; }
    $.ajax({
        type: 'POST',
        url: '/controlador/crear_alerta_vencimiento.php',
        data: {
            producto_id: productoVencimientosActualId,
            descripcion_lote: $('#venc_lote').val(),
            fecha_vencimiento: fecha,
            dias_antes: dias,
            csrf_token: pb.csrfToken(),
        },
        dataType: 'json',
        success: function (r) {
            if (r.ok) {
                pb.success('Vencimiento agregado.');
                $('#venc_lote').val('');
                $('#venc_fecha').val('');
                $('#venc_dias').val(30);
                cargarVencimientosModal();
            } else {
                pb.error(r.error || 'No se pudo agregar el vencimiento.');
            }
        },
        error: function () { pb.error('Error en el proceso.'); }
    });
}

function mostrarEdicionVencimiento(idVencimiento) {
    $(`#venc_vista_${idVencimiento}`).addClass('d-none');
    $(`#venc_edicion_${idVencimiento}`).removeClass('d-none');
}

function ocultarEdicionVencimiento(idVencimiento) {
    $(`#venc_edicion_${idVencimiento}`).addClass('d-none');
    $(`#venc_vista_${idVencimiento}`).removeClass('d-none');
}

function guardarEdicionVencimiento(idVencimiento) {
    const lote = $(`#venc_edit_lote_${idVencimiento}`).val();
    const fecha = $(`#venc_edit_fecha_${idVencimiento}`).val();
    if (!fecha) { pb.error('Elige la fecha de vencimiento.'); return; }
    $.ajax({
        type: 'POST',
        url: '/controlador/editar_vencimiento.php',
        data: { vencimiento_id: idVencimiento, descripcion_lote: lote, fecha_vencimiento: fecha, csrf_token: pb.csrfToken() },
        dataType: 'json',
        success: function (r) {
            if (r.ok) { pb.success('Vencimiento actualizado.'); cargarVencimientosModal(); }
            else pb.error(r.error || 'No se pudo actualizar.');
        },
        error: function () { pb.error('Error en el proceso.'); }
    });
}

function agregarAvisoModal(idVencimiento) {
    const dias = parseInt($(`#dias_nuevo_${idVencimiento}`).val(), 10);
    if (isNaN(dias) || dias < 0) { pb.error('Escribe con cuántos días de anticipación avisar.'); return; }
    $.ajax({
        type: 'POST',
        url: '/controlador/agregar_dias_vencimiento.php',
        data: { vencimiento_id: idVencimiento, dias_antes: dias, csrf_token: pb.csrfToken() },
        dataType: 'json',
        success: function (r) {
            if (r.ok) { pb.success('Aviso agregado.'); cargarVencimientosModal(); }
            else pb.error(r.error || 'No se pudo agregar el aviso.');
        },
        error: function () { pb.error('Error en el proceso.'); }
    });
}

async function eliminarAvisoModal(idAlerta) {
    const ok = await pb.confirmar('¿Quitar este aviso?');
    if (!ok) return;
    $.ajax({
        type: 'POST',
        url: '/controlador/eliminar_alerta_vencimiento.php',
        data: { id_alerta: idAlerta, csrf_token: pb.csrfToken() },
        dataType: 'json',
        success: function (r) {
            if (r.ok) { pb.success('Aviso eliminado.'); cargarVencimientosModal(); }
            else pb.error(r.error || 'No se pudo eliminar.');
        },
        error: function () { pb.error('Error en el proceso.'); }
    });
}

async function eliminarVencimientoCompleto(idVencimiento) {
    const ok = await pb.confirmar('¿Eliminar este vencimiento y todos sus avisos?');
    if (!ok) return;
    $.ajax({
        type: 'POST',
        url: '/controlador/eliminar_vencimiento.php',
        data: { vencimiento_id: idVencimiento, csrf_token: pb.csrfToken() },
        dataType: 'json',
        success: function (r) {
            if (r.ok) { pb.success('Vencimiento eliminado.'); cargarVencimientosModal(); }
            else pb.error(r.error || 'No se pudo eliminar.');
        },
        error: function () { pb.error('Error en el proceso.'); }
    });
}
</script>
