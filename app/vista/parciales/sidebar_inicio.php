<?php
// Variables esperadas antes de incluir: $usuarioActual (array de Auth), $paginaActiva (string), $basePath (string)
require_once __DIR__ . '/../../modelo/Licencia.php';
$esAdmin = $usuarioActual['rol'] === 'admin';
$diasGraciaRestantes = Licencia::diasGraciaRestantes();

$enlaces = [
    ['clave' => 'ventas', 'href' => '/vista/ventas/index.php', 'icono' => 'bi-cart-plus', 'texto' => 'Ventas', 'visible' => true],
    ['clave' => 'home', 'href' => '/vista/home/index.php', 'icono' => 'bi-speedometer2', 'texto' => 'Panel', 'visible' => $esAdmin],
    ['clave' => 'registro', 'href' => '/vista/registro/index.php', 'icono' => 'bi-box-seam', 'texto' => Configuracion::moduloActivo('existencias') ? 'Inventario' : 'Catálogo', 'visible' => Auth::tienePermiso('productos.ver')],
    ['clave' => 'vencimientos', 'href' => '/vista/vencimientos/index.php', 'icono' => 'bi-exclamation-diamond', 'texto' => 'Vencimientos', 'visible' => Auth::tienePermiso('productos.ver') && Configuracion::moduloActivo('vencimientos')],
    ['clave' => 'conteo_inventario', 'href' => '/vista/conteo_inventario/index.php', 'icono' => 'bi-clipboard-check', 'texto' => 'Conteo de inventario', 'visible' => Auth::tienePermiso('conteo_inventario.registrar') && Configuracion::moduloActivo('existencias')],
    ['clave' => 'facturas', 'href' => '/vista/facturas/index.php', 'icono' => 'bi-receipt', 'texto' => 'Facturas', 'visible' => Auth::tienePermiso('movimientos.ver')],
    ['clave' => 'reportes', 'href' => '/vista/reportes/index.php', 'icono' => 'bi-bar-chart-line', 'texto' => 'Reportes', 'visible' => Auth::tienePermiso('movimientos.ver')],
    ['clave' => 'compras_gastos', 'href' => '/vista/compras_gastos/index.php', 'icono' => 'bi-cart-dash', 'texto' => 'Compras y gastos', 'visible' => Auth::tienePermiso('movimientos.ver')],
    ['clave' => 'caja', 'href' => '/vista/caja/index.php', 'icono' => 'bi-cash-stack', 'texto' => 'Caja', 'visible' => Auth::tienePermiso('caja.gestionar')],
    ['clave' => 'clientes', 'href' => '/vista/clientes/index.php', 'icono' => 'bi-person-vcard', 'texto' => 'Clientes', 'visible' => Auth::tienePermiso('clientes.gestionar')],
    ['clave' => 'proveedores', 'href' => '/vista/proveedores/index.php', 'icono' => 'bi-truck', 'texto' => 'Proveedores', 'visible' => Auth::tienePermiso('proveedores.gestionar')],
    ['clave' => 'mesas', 'href' => '/vista/mesas/index.php', 'icono' => 'bi-grid-3x3-gap', 'texto' => 'Mesas', 'visible' => $esAdmin && Configuracion::moduloActivo('mesas_comandas')],
    ['clave' => 'promociones', 'href' => '/vista/promociones/index.php', 'icono' => 'bi-tags', 'texto' => 'Promociones', 'visible' => $esAdmin && Configuracion::moduloActivo('promociones')],
    ['clave' => 'profesionales', 'href' => '/vista/profesionales/index.php', 'icono' => 'bi-person-badge', 'texto' => 'Profesionales', 'visible' => $esAdmin && Configuracion::moduloActivo('profesionales')],
    ['clave' => 'comisiones', 'href' => '/vista/comisiones/index.php', 'icono' => 'bi-percent', 'texto' => 'Comisiones', 'visible' => Auth::tienePermiso('movimientos.ver') && Configuracion::moduloActivo('profesionales')],
    ['clave' => 'fidelizacion', 'href' => '/vista/fidelizacion/index.php', 'icono' => 'bi-award', 'texto' => 'Fidelización', 'visible' => $esAdmin],
    ['clave' => 'usuarios', 'href' => '/vista/usuarios/index.php', 'icono' => 'bi-people', 'texto' => 'Usuarios', 'visible' => $esAdmin],
    ['clave' => 'auditoria', 'href' => '/vista/auditoria/index.php', 'icono' => 'bi-clock-history', 'texto' => 'Auditoría', 'visible' => $esAdmin],
    ['clave' => 'configuracion', 'href' => '/vista/configuracion/index.php', 'icono' => 'bi-gear', 'texto' => 'Configuración', 'visible' => $esAdmin],
];

$configNegocio = $configNegocio ?? Configuracion::obtener();
?>
<div class="pb-app">
    <aside class="pb-sidebar" id="pbSidebar">
        <div class="pb-sidebar-brand">
            <div class="pb-sidebar-brand-logo">
                <?php if (!empty($configNegocio['logo_path'])): ?>
                    <img src="<?php echo $basePath . htmlspecialchars($configNegocio['logo_path'], ENT_QUOTES, 'UTF-8'); ?>" alt="">
                <?php else: ?>
                    <i class="bi bi-upc-scan"></i>
                <?php endif; ?>
            </div>
            <div class="pb-sidebar-brand-text">
                <span><?php echo htmlspecialchars($nombreNegocio, ENT_QUOTES, 'UTF-8'); ?></span>
                <small><?php echo htmlspecialchars(POS_PRODUCT_NAME, ENT_QUOTES, 'UTF-8'); ?></small>
            </div>
        </div>
        <button type="button" class="pb-sidebar-pin" id="pbSidebarPin" title="Contraer / expandir el panel" aria-label="Contraer o expandir el panel">
            <i class="bi bi-chevron-left"></i>
        </button>
        <nav class="pb-nav">
            <?php foreach ($enlaces as $enlace): if (!$enlace['visible']) continue; ?>
                <a class="pb-nav-link <?php echo $paginaActiva === $enlace['clave'] ? 'active' : ''; ?>"
                   href="<?php echo $basePath . $enlace['href']; ?>"
                   title="<?php echo htmlspecialchars($enlace['texto'], ENT_QUOTES, 'UTF-8'); ?>">
                    <i class="bi <?php echo $enlace['icono']; ?>"></i>
                    <span><?php echo $enlace['texto']; ?></span>
                    <?php if ($enlace['clave'] === 'configuracion'): ?>
                        <span id="pb-badge-actualizacion" class="badge text-bg-danger rounded-pill ms-auto d-none" title="Hay una actualización disponible">1</span>
                    <?php endif; ?>
                    <?php if ($enlace['clave'] === 'vencimientos'): ?>
                        <span id="pb-badge-vencimientos" class="badge text-bg-warning rounded-pill ms-auto d-none" title="Productos próximos a vencer o ya vencidos">0</span>
                    <?php endif; ?>
                </a>
            <?php endforeach; ?>
        </nav>
        <div class="pb-sidebar-footer">
            <div class="pb-sidebar-user">
                <div class="pb-sidebar-user-info">
                    <div><?php echo htmlspecialchars($usuarioActual['nombre'], ENT_QUOTES, 'UTF-8'); ?></div>
                    <div class="pb-role-badge"><?php echo $esAdmin ? 'Administrador' : 'Cajero'; ?></div>
                </div>
                <a class="pb-logout-btn" href="<?php echo $basePath; ?>/controlador/logout.php" title="Cerrar sesión">
                    <i class="bi bi-box-arrow-right"></i>
                    <span>Salir</span>
                </a>
            </div>
        </div>
    </aside>
    <div class="pb-main">
        <header class="pb-topbar">
            <button type="button" class="pb-sidebar-toggle" onclick="pb.toggleSidebar()">
                <i class="bi bi-list"></i>
            </button>
            <h1><?php echo htmlspecialchars($tituloPagina ?? '', ENT_QUOTES, 'UTF-8'); ?></h1>
            <?php if ($diasGraciaRestantes !== null): ?>
                <span class="badge text-bg-warning ms-auto" style="font-weight:500;" title="Contacta a tu proveedor para renovar la suscripción">
                    <i class="bi bi-exclamation-triangle"></i>
                    <?php echo $diasGraciaRestantes > 0
                        ? 'Quedan ' . $diasGraciaRestantes . ' día' . ($diasGraciaRestantes === 1 ? '' : 's') . ' para pagar la suscripción'
                        : 'Último día para pagar la suscripción'; ?>
                </span>
            <?php endif; ?>
        </header>
        <main class="pb-content">
