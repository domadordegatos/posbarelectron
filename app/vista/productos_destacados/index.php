<?php
require_once "../../modelo/bootstrap.php";

$usuarioActual = Auth::requerirRol('admin');
$tituloPagina = 'Cuadrícula de ventas';
$paginaActiva = 'ventas';
$config = Configuracion::obtener();

require_once __DIR__ . "/../parciales/head.php";
require_once __DIR__ . "/../parciales/sidebar_inicio.php";
?>

<div class="row g-3">
    <div class="col-lg-5">
        <div class="pb-card mb-3">
            <div class="pb-card-body">
                <h6 class="mb-2"><i class="bi bi-grid-3x3-gap-fill"></i> Cuadrícula de productos</h6>
                <p class="text-muted small mb-3">
                    Además del buscador de siempre, Ventas puede mostrar una cuadrícula de íconos grandes para
                    tocar los productos más pedidos sin escribir nada — útil para negocios con pocos productos
                    muy repetidos (ej. bebidas, combos, platos del día). El buscador nunca se quita, esto solo se le suma.
                </p>
                <div class="form-check form-switch mb-3">
                    <input class="form-check-input" type="checkbox" id="cuadricula_habilitada"
                           <?php echo !empty($config['ventas_vista_cuadricula']) ? 'checked' : ''; ?>
                           onchange="cambiarCuadriculaHabilitada(this.checked)">
                    <label class="form-check-label" for="cuadricula_habilitada">
                        Mostrar la cuadrícula en Ventas
                    </label>
                </div>

                <div id="selectorModoCuadricula">
                    <label class="form-label d-block mb-1">Modo de cuadrícula</label>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="modo_cuadricula" id="modo_productos" value="productos"
                               <?php echo ($config['ventas_cuadricula_modo'] ?? 'productos') === 'productos' ? 'checked' : ''; ?>
                               onchange="cambiarModoCuadricula('productos')">
                        <label class="form-check-label" for="modo_productos">
                            Productos elegidos a mano — como hoy, uno por uno.
                        </label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="modo_cuadricula" id="modo_categorias" value="categorias"
                               <?php echo ($config['ventas_cuadricula_modo'] ?? 'productos') === 'categorias' ? 'checked' : ''; ?>
                               onchange="cambiarModoCuadricula('categorias')">
                        <label class="form-check-label" for="modo_categorias">
                            Por categorías — se toca una categoría (ej. Helados) y se abren sus productos.
                        </label>
                    </div>
                </div>
            </div>
        </div>

        <div id="panelBuscarProductos" class="pb-card">
            <div class="pb-card-body">
                <h6 class="mb-3"><i class="bi bi-plus-square"></i> Agregar producto</h6>
                <input type="text" class="form-control mb-2" id="destacado_buscar" placeholder="Buscar producto…" autocomplete="off">
                <ul id="destacado_resultados" class="list-group" style="max-height: 420px; overflow-y: auto;">
                    <li class="list-group-item text-muted text-center">Cargando…</li>
                </ul>
            </div>
        </div>

        <div id="panelBuscarCategorias" class="pb-card" style="display:none;">
            <div class="pb-card-body">
                <h6 class="mb-3"><i class="bi bi-plus-square"></i> Agregar categoría</h6>
                <input type="text" class="form-control mb-2" id="categoria_cuadricula_buscar" placeholder="Buscar categoría…" autocomplete="off">
                <ul id="categoria_cuadricula_resultados" class="list-group" style="max-height: 420px; overflow-y: auto;">
                    <li class="list-group-item text-muted text-center">Cargando…</li>
                </ul>
            </div>
        </div>
    </div>

    <div class="col-lg-7">
        <div id="panelListaProductos" class="pb-card">
            <div class="pb-card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="mb-0">Productos en la cuadrícula (en este orden)</h6>
                    <button type="button" class="btn btn-primary btn-sm" onclick="guardarOrdenDestacados()">
                        <i class="bi bi-floppy"></i> Guardar orden
                    </button>
                </div>
                <p class="text-muted small mb-2">Así se van a ver en Ventas. Arrastra <i class="bi bi-grip-horizontal"></i> para reordenar.</p>
                <div id="lista_destacados" class="pb-grid-productos">
                    <span class="text-muted small">Cargando…</span>
                </div>
            </div>
        </div>

        <div id="panelListaCategorias" class="pb-card" style="display:none;">
            <div class="pb-card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="mb-0">Categorías en la cuadrícula (en este orden)</h6>
                    <button type="button" class="btn btn-primary btn-sm" onclick="guardarOrdenCategoriasCuadricula()">
                        <i class="bi bi-floppy"></i> Guardar orden
                    </button>
                </div>
                <p class="text-muted small mb-2">
                    Así aparecen los botones en Ventas. Arrastra <i class="bi bi-grip-horizontal"></i> para reordenar;
                    haz clic en una categoría para ordenar sus productos.
                </p>
                <div id="lista_categorias_cuadricula">
                    <span class="text-muted small">Cargando…</span>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . "/../parciales/pie.php"; ?>
<script>
const MODO_CUADRICULA_INICIAL = <?php echo json_encode($config['ventas_cuadricula_modo'] ?? 'productos'); ?>;

let destacados = [];
let catalogoCompleto = [];

let categoriasCuadricula = [];
let todasCategorias = [];
let categoriaExpandidaId = null;
let productosCategoriaExpandida = [];

$(function () {
    cargarDestacados();
    cargarCatalogoDisponible();
    $('#destacado_buscar').on('input', pb.debounce(renderListaDisponibles, 150));

    cargarCategoriasCuadricula();
    cargarTodasCategorias();
    $('#categoria_cuadricula_buscar').on('input', pb.debounce(renderCategoriasDisponibles, 150));

    mostrarPanelesSegunModo(MODO_CUADRICULA_INICIAL);
});

function mostrarPanelesSegunModo(modo) {
    const esCategorias = modo === 'categorias';
    $('#panelBuscarProductos, #panelListaProductos').toggle(!esCategorias);
    $('#panelBuscarCategorias, #panelListaCategorias').toggle(esCategorias);
}

function cambiarModoCuadricula(modo) {
    mostrarPanelesSegunModo(modo);
    $.ajax({
        type: 'POST',
        url: '/controlador/actualizar_cuadricula_modo.php',
        data: { modo: modo, csrf_token: pb.csrfToken() },
        dataType: 'json',
        success: function (r) {
            if (r.ok) {
                pb.success('Modo de cuadrícula actualizado.');
            } else {
                pb.error(r.error || 'No se pudo guardar el modo.');
            }
        },
        error: function () { pb.error('Error en el proceso.'); }
    });
}

function cargarCatalogoDisponible() {
    $.getJSON('/controlador/listar_productos_simple.php', function (r) {
        catalogoCompleto = r || [];
        renderListaDisponibles();
    }).fail(() => pb.error('Error al cargar el catálogo.'));
}

function renderListaDisponibles() {
    const termino = $('#destacado_buscar').val().trim().toLowerCase();
    const $lista = $('#destacado_resultados').empty();
    const filtrados = termino
        ? catalogoCompleto.filter(p => p.nombre_producto.toLowerCase().includes(termino))
        : catalogoCompleto;

    if (filtrados.length === 0) {
        $lista.append('<li class="list-group-item text-muted text-center">No hay productos.</li>');
        return;
    }

    filtrados.forEach(function (p) {
        const yaAgregado = destacados.some(d => d.id_producto == p.id_producto);
        $lista.append(`<li class="list-group-item d-flex justify-content-between align-items-center">
            <span>${p.nombre_producto} <span class="text-muted small">${pb.moneda(p.precio_unitario)}</span></span>
            ${yaAgregado
                ? '<span class="text-success small"><i class="bi bi-check-lg"></i> Agregado</span>'
                : `<button class="pb-btn-icon" onclick="agregarDestacado(${p.id_producto})" title="Agregar a la cuadrícula"><i class="bi bi-plus-lg"></i></button>`}
        </li>`);
    });
}

function agregarDestacado(idProducto) {
    if (destacados.some(d => d.id_producto == idProducto)) return;
    const producto = catalogoCompleto.find(p => p.id_producto == idProducto);
    if (!producto) return;
    destacados.push(producto);
    renderListaDestacados();
    renderListaDisponibles();
}

function cargarDestacados() {
    $.getJSON('/controlador/listar_productos_destacados.php', function (r) {
        destacados = r || [];
        renderListaDestacados();
        renderListaDisponibles();
    }).fail(() => pb.error('Error al cargar la cuadrícula.'));
}

function renderListaDestacados() {
    const $lista = $('#lista_destacados').empty();
    if (destacados.length === 0) {
        $lista.append('<span class="text-muted small">Todavía no hay productos en la cuadrícula.</span>');
        return;
    }
    destacados.forEach(function (p, i) {
        $lista.append(`<div class="pb-tile-producto pb-fila-arrastrable" draggable="true" data-indice="${i}">
            <button type="button" class="pb-tile-quitar" onclick="quitarDestacado(${i})" title="Quitar"><i class="bi bi-x-lg"></i></button>
            <i class="bi bi-grip-horizontal pb-tile-grip"></i>
            <span>${p.nombre_producto}</span>
            <small>${pb.moneda(p.precio_unitario)}</small>
        </div>`);
    });
    activarArrastreDestacados();
}

function activarArrastreDestacados() {
    let indiceArrastrado = null;
    $('#lista_destacados .pb-tile-producto[data-indice]').on('dragstart', function (e) {
        indiceArrastrado = parseInt($(this).data('indice'), 10);
        e.originalEvent.dataTransfer.effectAllowed = 'move';
        $(this).addClass('pb-arrastrando');
    }).on('dragend', function () {
        $(this).removeClass('pb-arrastrando');
    }).on('dragover', function (e) {
        e.preventDefault();
    }).on('drop', function (e) {
        e.preventDefault();
        const indiceDestino = parseInt($(this).data('indice'), 10);
        if (indiceArrastrado === null || indiceArrastrado === indiceDestino) return;
        const [movido] = destacados.splice(indiceArrastrado, 1);
        destacados.splice(indiceDestino, 0, movido);
        renderListaDestacados();
    });
}

function quitarDestacado(indice) {
    destacados.splice(indice, 1);
    renderListaDestacados();
    renderListaDisponibles();
}

function guardarOrdenDestacados() {
    const ids = destacados.map(p => p.id_producto);
    $.ajax({
        type: 'POST',
        url: '/controlador/guardar_productos_destacados.php',
        data: { ids: JSON.stringify(ids), csrf_token: pb.csrfToken() },
        dataType: 'json',
        success: function (r) {
            if (r.ok) {
                pb.success('Cuadrícula guardada.');
            } else {
                pb.error(r.error || 'No se pudo guardar.');
            }
        },
        error: function () { pb.error('Error en el proceso.'); }
    });
}

function cambiarCuadriculaHabilitada(habilitado) {
    $.ajax({
        type: 'POST',
        url: '/controlador/actualizar_ventas_vista_cuadricula.php',
        data: { habilitado: habilitado ? 1 : 0, csrf_token: pb.csrfToken() },
        dataType: 'json',
        success: function (r) {
            if (r.ok) {
                pb.success(habilitado ? 'Cuadrícula activada en Ventas.' : 'Cuadrícula desactivada.');
            } else {
                pb.error('No se pudo guardar el cambio.');
                $('#cuadricula_habilitada').prop('checked', !habilitado);
            }
        },
        error: function () {
            pb.error('No se pudo guardar el cambio.');
            $('#cuadricula_habilitada').prop('checked', !habilitado);
        }
    });
}

// ---------------------------------------------------------------------
// Modo "por categorías"
// ---------------------------------------------------------------------

function cargarCategoriasCuadricula() {
    $.getJSON('/controlador/listar_categorias_cuadricula.php', function (r) {
        categoriasCuadricula = r || [];
        renderListaCategoriasCuadricula();
        renderCategoriasDisponibles();
    }).fail(() => pb.error('Error al cargar las categorías de la cuadrícula.'));
}

function cargarTodasCategorias() {
    $.getJSON('/controlador/listar_categorias.php', function (r) {
        todasCategorias = r || [];
        renderCategoriasDisponibles();
    }).fail(() => pb.error('Error al cargar las categorías.'));
}

function renderCategoriasDisponibles() {
    const termino = $('#categoria_cuadricula_buscar').val().trim().toLowerCase();
    const $lista = $('#categoria_cuadricula_resultados').empty();
    const filtradas = termino
        ? todasCategorias.filter(c => c.descripcion.toLowerCase().includes(termino))
        : todasCategorias;

    if (filtradas.length === 0) {
        $lista.append('<li class="list-group-item text-muted text-center">No hay categorías.</li>');
        return;
    }

    filtradas.forEach(function (c) {
        const yaAgregada = categoriasCuadricula.some(x => x.id_categoria == c.id_categoria);
        $lista.append(`<li class="list-group-item d-flex justify-content-between align-items-center">
            <span>${c.descripcion}</span>
            ${yaAgregada
                ? '<span class="text-success small"><i class="bi bi-check-lg"></i> Agregada</span>'
                : `<button class="pb-btn-icon" onclick="agregarCategoriaCuadricula(${c.id_categoria})" title="Agregar a la cuadrícula"><i class="bi bi-plus-lg"></i></button>`}
        </li>`);
    });
}

function agregarCategoriaCuadricula(idCategoria) {
    if (categoriasCuadricula.some(c => c.id_categoria == idCategoria)) return;
    const categoria = todasCategorias.find(c => c.id_categoria == idCategoria);
    if (!categoria) return;
    categoriasCuadricula.push({ id_categoria: categoria.id_categoria, descripcion: categoria.descripcion, total_productos: null });
    renderListaCategoriasCuadricula();
    renderCategoriasDisponibles();
}

function quitarCategoriaCuadricula(indice) {
    const quitada = categoriasCuadricula.splice(indice, 1)[0];
    if (quitada && quitada.id_categoria === categoriaExpandidaId) {
        categoriaExpandidaId = null;
        productosCategoriaExpandida = [];
    }
    renderListaCategoriasCuadricula();
    renderCategoriasDisponibles();
}

function renderListaCategoriasCuadricula() {
    const $lista = $('#lista_categorias_cuadricula').empty();
    if (categoriasCuadricula.length === 0) {
        $lista.append('<span class="text-muted small">Todavía no hay categorías en la cuadrícula.</span>');
        return;
    }
    categoriasCuadricula.forEach(function (c, i) {
        const expandida = c.id_categoria === categoriaExpandidaId;
        const $fila = $(`<div class="pb-categoria-cuadricula pb-fila-arrastrable mb-2" draggable="true" data-indice="${i}">
            <div class="pb-categoria-cuadricula-header d-flex align-items-center gap-2 p-2" style="cursor:pointer;">
                <i class="bi bi-grip-horizontal text-muted"></i>
                <span class="flex-grow-1 fw-semibold">${c.descripcion}</span>
                ${c.total_productos !== null ? `<span class="badge text-bg-light text-muted">${c.total_productos} productos</span>` : ''}
                <i class="bi ${expandida ? 'bi-chevron-up' : 'bi-chevron-down'} text-muted"></i>
                <button type="button" class="pb-btn-icon" title="Quitar" data-quitar><i class="bi bi-x-lg"></i></button>
            </div>
            <div class="pb-categoria-cuadricula-body" style="${expandida ? '' : 'display:none;'} padding: 0 .75rem .75rem 2rem;"></div>
        </div>`);

        $fila.find('[data-quitar]').on('click', function (e) {
            e.stopPropagation();
            quitarCategoriaCuadricula(i);
        });
        $fila.children('.pb-categoria-cuadricula-header').on('click', function () {
            alternarCategoriaCuadricula(c.id_categoria);
        });

        $lista.append($fila);

        if (expandida) {
            renderProductosCategoriaExpandida($fila.children('.pb-categoria-cuadricula-body'));
        }
    });
    activarArrastreCategoriasCuadricula();
}

function activarArrastreCategoriasCuadricula() {
    let indiceArrastrado = null;
    $('#lista_categorias_cuadricula > .pb-categoria-cuadricula[data-indice]').on('dragstart', function (e) {
        indiceArrastrado = parseInt($(this).data('indice'), 10);
        e.originalEvent.dataTransfer.effectAllowed = 'move';
        $(this).addClass('pb-arrastrando');
    }).on('dragend', function () {
        $(this).removeClass('pb-arrastrando');
    }).on('dragover', function (e) {
        e.preventDefault();
    }).on('drop', function (e) {
        e.preventDefault();
        const indiceDestino = parseInt($(this).data('indice'), 10);
        if (indiceArrastrado === null || indiceArrastrado === indiceDestino) return;
        const [movida] = categoriasCuadricula.splice(indiceArrastrado, 1);
        categoriasCuadricula.splice(indiceDestino, 0, movida);
        renderListaCategoriasCuadricula();
    });
}

function guardarOrdenCategoriasCuadricula() {
    const ids = categoriasCuadricula.map(c => c.id_categoria);
    $.ajax({
        type: 'POST',
        url: '/controlador/guardar_categorias_cuadricula.php',
        data: { ids: JSON.stringify(ids), csrf_token: pb.csrfToken() },
        dataType: 'json',
        success: function (r) {
            if (r.ok) {
                pb.success('Orden de categorías guardado.');
            } else {
                pb.error(r.error || 'No se pudo guardar.');
            }
        },
        error: function () { pb.error('Error en el proceso.'); }
    });
}

function alternarCategoriaCuadricula(idCategoria) {
    if (categoriaExpandidaId === idCategoria) {
        categoriaExpandidaId = null;
        productosCategoriaExpandida = [];
        renderListaCategoriasCuadricula();
        return;
    }
    categoriaExpandidaId = idCategoria;
    $.getJSON('/controlador/listar_productos_categoria_cuadricula.php', { categoria: idCategoria }, function (r) {
        productosCategoriaExpandida = r || [];
        renderListaCategoriasCuadricula();
    }).fail(() => pb.error('No se pudieron cargar los productos de la categoría.'));
}

function renderProductosCategoriaExpandida($contenedor) {
    if (productosCategoriaExpandida.length === 0) {
        $contenedor.html('<span class="text-muted small">Esta categoría no tiene productos activos.</span>');
        return;
    }
    const $lista = $('<div class="pb-grid-productos mb-2"></div>');
    productosCategoriaExpandida.forEach(function (p, i) {
        $lista.append(`<div class="pb-tile-producto pb-fila-arrastrable" draggable="true" data-indice-prod="${i}">
            <i class="bi bi-grip-horizontal pb-tile-grip"></i>
            <span>${p.nombre_producto}</span>
            <small>${pb.moneda(p.precio_unitario)}</small>
        </div>`);
    });
    const $boton = $(`<button type="button" class="btn btn-outline-primary btn-sm">
        <i class="bi bi-floppy"></i> Guardar orden de productos
    </button>`).on('click', function (e) {
        e.stopPropagation();
        guardarOrdenProductosCategoriaCuadricula(categoriaExpandidaId);
    });

    $contenedor.empty().append($lista).append($boton);
    activarArrastreProductosCategoriaExpandida($lista);
}

function activarArrastreProductosCategoriaExpandida($lista) {
    let indiceArrastrado = null;
    $lista.find('.pb-tile-producto[data-indice-prod]').on('dragstart', function (e) {
        e.stopPropagation();
        indiceArrastrado = parseInt($(this).data('indice-prod'), 10);
        e.originalEvent.dataTransfer.effectAllowed = 'move';
        $(this).addClass('pb-arrastrando');
    }).on('dragend', function (e) {
        e.stopPropagation();
        $(this).removeClass('pb-arrastrando');
    }).on('dragover', function (e) {
        e.preventDefault();
        e.stopPropagation();
    }).on('drop', function (e) {
        e.preventDefault();
        e.stopPropagation();
        const indiceDestino = parseInt($(this).data('indice-prod'), 10);
        if (indiceArrastrado === null || indiceArrastrado === indiceDestino) return;
        const [movido] = productosCategoriaExpandida.splice(indiceArrastrado, 1);
        productosCategoriaExpandida.splice(indiceDestino, 0, movido);
        renderListaCategoriasCuadricula();
    });
}

function guardarOrdenProductosCategoriaCuadricula(idCategoria) {
    const ids = productosCategoriaExpandida.map(p => p.id_producto);
    $.ajax({
        type: 'POST',
        url: '/controlador/guardar_orden_productos_categoria.php',
        data: { categoria: idCategoria, ids: JSON.stringify(ids), csrf_token: pb.csrfToken() },
        dataType: 'json',
        success: function (r) {
            if (r.ok) {
                pb.success('Orden de productos guardado.');
            } else {
                pb.error(r.error || 'No se pudo guardar.');
            }
        },
        error: function () { pb.error('Error en el proceso.'); }
    });
}
</script>
