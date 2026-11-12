<?php
require_once "../../modelo/bootstrap.php";
require_once "../../modelo/Licencia.php";
require_once "../../modelo/Respaldo.php";

$usuarioActual = Auth::requerirRol('admin');
$tituloPagina = 'Configuración';
$paginaActiva = 'configuracion';
$config = Configuracion::obtener();
$licencia = Licencia::datos();
$periodoBalance = Configuracion::periodoBalance();

require_once __DIR__ . "/../parciales/head.php";
require_once __DIR__ . "/../parciales/sidebar_inicio.php";
?>

<div class="row g-3">
    <div class="col-lg-7">
        <form id="formConfiguracion">
            <div class="pb-card">
                <div class="pb-card-body">
                    <h6 class="mb-1"><i class="bi bi-shop"></i> Identidad del negocio</h6>
                    <p class="text-muted small mb-3">Nombre, colores y configuración regional — se usan en toda la aplicación: la barra lateral, los reportes y las fechas de tickets y cierres.</p>
                    <div class="mb-3">
                        <label for="nombre_negocio" class="form-label">Nombre del negocio</label>
                        <input type="text" class="form-control" id="nombre_negocio" value="<?php echo htmlspecialchars($config['nombre_negocio'], ENT_QUOTES, 'UTF-8'); ?>" required>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-4">
                            <label for="color_primario" class="form-label">Color principal <i class="bi bi-question-circle text-muted" style="cursor:pointer;" data-pb-ayuda="Se usa en la barra lateral y en los botones principales de toda la aplicación."></i></label>
                            <input type="color" class="form-control form-control-color w-100" id="color_primario" value="<?php echo htmlspecialchars($config['color_primario'], ENT_QUOTES, 'UTF-8'); ?>">
                        </div>
                        <div class="col-4">
                            <label for="color_secundario" class="form-label">Color secundario <i class="bi bi-question-circle text-muted" style="cursor:pointer;" data-pb-ayuda="Color de acento para detalles secundarios de la interfaz, como botones de aviso."></i></label>
                            <input type="color" class="form-control form-control-color w-100" id="color_secundario" value="<?php echo htmlspecialchars($config['color_secundario'] ?? '#f97316', ENT_QUOTES, 'UTF-8'); ?>">
                        </div>
                        <div class="col-4">
                            <label for="moneda" class="form-label">Moneda <i class="bi bi-question-circle text-muted" style="cursor:pointer;" data-pb-ayuda="Código de 3 letras (ej. COP, USD, MXN) — cambia el símbolo y el formato con el que se muestran todos los precios de la aplicación."></i></label>
                            <input type="text" class="form-control" id="moneda" value="<?php echo htmlspecialchars($config['moneda'], ENT_QUOTES, 'UTF-8'); ?>" placeholder="COP">
                        </div>
                    </div>
                    <div class="mb-0">
                        <label for="zona_horaria" class="form-label">Zona horaria <i class="bi bi-question-circle text-muted" style="cursor:pointer;" data-pb-ayuda="Formato de zona de IANA, ej. America/Bogota. Afecta la hora que se imprime en los tickets y qué día cuenta como 'hoy' en reportes, turnos de caja y cierres."></i></label>
                        <input type="text" class="form-control" id="zona_horaria" value="<?php echo htmlspecialchars($config['zona_horaria'], ENT_QUOTES, 'UTF-8'); ?>" placeholder="America/Bogota">
                    </div>
                </div>
            </div>

            <div class="pb-card mt-3">
                <div class="pb-card-body">
                    <h6 class="mb-1"><i class="bi bi-receipt"></i> Datos de facturación</h6>
                    <p class="text-muted small mb-3">Estos datos aparecen impresos en el ticket de cada venta. Todos son opcionales.</p>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label for="nit" class="form-label">NIT</label>
                            <input type="text" class="form-control" id="nit" value="<?php echo htmlspecialchars($config['nit'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" placeholder="Opcional">
                        </div>
                        <div class="col-6">
                            <label for="telefono" class="form-label">Teléfono</label>
                            <input type="text" class="form-control" id="telefono" value="<?php echo htmlspecialchars($config['telefono'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" placeholder="Opcional">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="direccion" class="form-label">Dirección</label>
                        <input type="text" class="form-control" id="direccion" value="<?php echo htmlspecialchars($config['direccion'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" placeholder="Opcional">
                    </div>
                    <div class="mb-0">
                        <label for="lema" class="form-label">Lema / frase del negocio</label>
                        <input type="text" class="form-control" id="lema" value="<?php echo htmlspecialchars($config['lema'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" placeholder="Opcional — ej. &quot;Gracias por su compra&quot;">
                    </div>
                </div>
            </div>

            <div class="pb-card mt-3">
                <div class="pb-card-body">
                    <h6 class="mb-1"><i class="bi bi-printer"></i> Impresión y papel</h6>
                    <p class="text-muted small mb-3">Qué tamaño de papel usa tu impresora y a cuál impresora se manda cada tipo de ticket.</p>
                    <div class="mb-3">
                        <label for="formato_ticket" class="form-label">Formato del ticket</label>
                        <select class="form-select" id="formato_ticket" onchange="cambiarFormatoTicket()">
                            <option value="a4" <?php echo $config['formato_ticket'] === 'a4' ? 'selected' : ''; ?>>Hoja A4</option>
                            <option value="termica_58" <?php echo $config['formato_ticket'] === 'termica_58' ? 'selected' : ''; ?>>Impresora térmica 58mm</option>
                            <option value="termica_80" <?php echo $config['formato_ticket'] === 'termica_80' ? 'selected' : ''; ?>>Impresora térmica 80mm</option>
                        </select>
                    </div>
                    <div class="alert alert-warning py-2 small d-none" id="aviso_formato_termica">
                        <i class="bi bi-exclamation-triangle"></i>
                        La impresora de recibos elegida parece de <strong>rollo (térmica)</strong>, pero el formato del ticket está en <strong>Hoja A4</strong>:
                        la factura saldría con el encabezado y los precios cortados. Cambia el formato a <strong>Impresora térmica 80mm</strong> (o 58mm).
                    </div>
                    <div class="mb-3" id="campo_ancho_ticket">
                        <label for="ancho_ticket_mm" class="form-label">Ancho exacto de la tirilla (mm)</label>
                        <input type="number" class="form-control" id="ancho_ticket_mm" min="25" max="120" step="0.5"
                               placeholder="Déjalo vacío para usar el ancho del formato de arriba (58 o 80mm)">
                        <div class="form-text">Es el ancho que realmente imprime tu impresora (suele ser menor que el papel: un rollo de 80 mm imprime ~72–76 mm). Acepta decimales, ej. 72.5. Imprime la hoja de prueba y anota el último número de la regla que se vea completo. Cada formato (58mm / 80mm) guarda el suyo por separado — probar uno no borra el del otro.</div>
                    </div>
                    <div class="mb-3">
                        <label for="margen_ticket_mm" class="form-label">Margen del ticket (mm)</label>
                        <input type="number" class="form-control" id="margen_ticket_mm" min="0" max="50" step="0.5"
                               placeholder="Déjalo vacío para usar 2mm (térmica) o el margen normal de A4">
                        <div class="form-text">Aplica al formato elegido arriba — 0 si tu impresora térmica no necesita nada de margen. También se guarda por separado para cada formato.</div>
                    </div>
                    <!-- Valores por formato (58mm/80mm/A4) — #ancho_ticket_mm y #margen_ticket_mm de arriba son solo
                         la "ventana" que muestra y edita el par del formato elegido en #formato_ticket; cambiarValoresPapel()
                         guarda lo que se estaba viendo antes de mostrar el del formato nuevo (ver script al pie). -->
                    <input type="hidden" id="ancho_ticket_58_mm" value="<?php echo htmlspecialchars((string) ($config['ancho_ticket_58_mm'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                    <input type="hidden" id="margen_ticket_58_mm" value="<?php echo htmlspecialchars((string) ($config['margen_ticket_58_mm'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                    <input type="hidden" id="ancho_ticket_80_mm" value="<?php echo htmlspecialchars((string) ($config['ancho_ticket_80_mm'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                    <input type="hidden" id="margen_ticket_80_mm" value="<?php echo htmlspecialchars((string) ($config['margen_ticket_80_mm'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                    <input type="hidden" id="margen_ticket_a4_mm" value="<?php echo htmlspecialchars((string) ($config['margen_ticket_a4_mm'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label for="impresora_recibo" class="form-label">Impresora de recibos</label>
                            <select class="form-select" id="impresora_recibo">
                                <option value="">Ninguna (usar diálogo normal)</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label for="impresora_cocina" class="form-label">Impresora de cocina</label>
                            <select class="form-select" id="impresora_cocina">
                                <option value="">Ninguna (no imprime comanda)</option>
                            </select>
                        </div>
                        <div class="form-text">
                            Si eliges una impresora, cada venta se imprime sola ahí (sin diálogo del sistema) al confirmarla.
                            La de cocina imprime solo productos y cantidades, sin precios — pensada para alistar el pedido.
                            Puedes elegir la misma impresora en ambas, o dejar alguna en "Ninguna" para seguir imprimiendo esa a mano.
                        </div>
                    </div>
                    <div class="mb-0">
                        <button type="button" class="btn btn-outline-secondary btn-sm" onclick="abrirModalPrueba()"><i class="bi bi-file-earmark-text"></i> Ver e imprimir hoja de prueba</button>
                        <div class="form-text">
                            Abre una vista previa de cómo debe salir el ticket con el papel, la impresora y los datos del formulario (no hace falta guardar antes), con productos reales de tu catálogo, marcas de INICIO y FIN y una regla en centímetros. Imprímela y compárala: si falta el borde derecho o el FIN, el papel elegido es más ancho que el rollo.
                            En la ventana de cada factura también hay un selector de papel que recuerda la elección de ese equipo.
                        </div>
                    </div>
                </div>
            </div>

            <?php if (Configuracion::moduloActivo('existencias')): ?>
            <div class="pb-card mt-3">
                <div class="pb-card-body">
                    <h6 class="mb-1"><i class="bi bi-upc-scan"></i> Etiquetas de código de barras</h6>
                    <p class="text-muted small mb-3">
                        Para imprimir la etiqueta de un producto (ícono <i class="bi bi-upc-scan"></i> en Inventario) —
                        distinta de la factura: una impresora propia y un tamaño de etiqueta fijo (ancho y alto), no una
                        tirilla continua.
                    </p>
                    <div class="mb-3">
                        <label for="impresora_etiquetas" class="form-label">Impresora de etiquetas</label>
                        <select class="form-select" id="impresora_etiquetas">
                            <option value="">Ninguna (usar diálogo normal)</option>
                        </select>
                    </div>
                    <div class="row g-2 mb-0">
                        <div class="col-6">
                            <label for="ancho_etiqueta_mm" class="form-label">Ancho de la etiqueta (mm)</label>
                            <input type="number" class="form-control" id="ancho_etiqueta_mm" min="15" max="150" step="0.5"
                                   placeholder="Ej. 50"
                                   value="<?php echo htmlspecialchars((string) ($config['ancho_etiqueta_mm'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                        </div>
                        <div class="col-6">
                            <label for="alto_etiqueta_mm" class="form-label">Alto de la etiqueta (mm)</label>
                            <input type="number" class="form-control" id="alto_etiqueta_mm" min="15" max="150" step="0.5"
                                   placeholder="Ej. 30"
                                   value="<?php echo htmlspecialchars((string) ($config['alto_etiqueta_mm'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                        </div>
                        <div class="form-text">Mira la etiqueta de tu rollo con una regla — el tamaño exacto que compraste (ej. 50×30mm). Si lo dejas vacío, se usa 50×30mm.</div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <div class="pb-card mt-3">
                <div class="pb-card-body">
                    <h6 class="mb-1"><i class="bi bi-image"></i> Logos</h6>
                    <p class="text-muted small mb-3">Imágenes de tu marca: una para la aplicación, otra opcional para la factura impresa.</p>
                    <div class="mb-3">
                        <label for="logo" class="form-label">Logo de la aplicación (opcional)</label>
                        <input type="file" class="form-control" id="logo" accept="image/png,image/jpeg,image/webp">
                        <div class="form-text">Es el que se ve en la barra lateral, sobre el color de tu negocio.</div>
                        <?php if (!empty($config['logo_path'])): ?>
                            <img src="<?php echo htmlspecialchars($config['logo_path'], ENT_QUOTES, 'UTF-8'); ?>" alt="" class="mt-2 rounded" style="max-height:60px;background:var(--pb-primary,#4f46e5);padding:4px;">
                        <?php endif; ?>
                    </div>
                    <div class="mb-0" id="bloque_logo_factura">
                        <label for="logo_factura" class="form-label">Logo de la factura (opcional)</label>
                        <input type="file" class="form-control" id="logo_factura" accept="image/png,image/jpeg,image/webp">
                        <div class="form-text">
                            Es el que se imprime arriba de la factura y puede ser distinto del de la aplicación. Una impresora
                            térmica solo imprime negro o blanco: usa una imagen <strong>sin fondo de color</strong> (PNG con fondo
                            blanco o transparente, letras en negro). Si no subes una, la factura usa el logo de la aplicación.
                        </div>
                        <?php $logoFacturaActual = Configuracion::logoParaFactura(); ?>
                        <?php if (!empty($config['logo_factura_path'])): ?>
                            <div class="mt-2 d-flex align-items-center gap-3 flex-wrap">
                                <img src="<?php echo htmlspecialchars($config['logo_factura_path'], ENT_QUOTES, 'UTF-8'); ?>" alt="" class="border rounded" style="max-height:60px;background:#fff;padding:4px;">
                                <div class="form-check mb-0">
                                    <input class="form-check-input" type="checkbox" id="quitar_logo_factura">
                                    <label class="form-check-label small" for="quitar_logo_factura">Quitar este logo (volver a usar el de la aplicación)</label>
                                </div>
                            </div>
                        <?php elseif (!empty($logoFacturaActual)): ?>
                            <div class="form-text mt-1"><i class="bi bi-info-circle"></i> Ahora la factura está usando el logo de la aplicación.</div>
                        <?php endif; ?>
                        <div class="form-check mt-2">
                            <input class="form-check-input" type="checkbox" id="imprimir_logo_factura" <?php echo (int) ($config['imprimir_logo_factura'] ?? 1) ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="imprimir_logo_factura">Imprimir el logo en la factura</label>
                        </div>
                    </div>
                </div>
            </div>

            <div class="pb-card mt-3">
                <div class="pb-card-body d-flex flex-wrap align-items-center gap-3">
                    <button type="button" class="btn btn-primary" onclick="guardarConfiguracion()">
                        <i class="bi bi-save"></i> Guardar cambios
                    </button>
                    <span class="text-muted small">Guarda todo lo de arriba: identidad, facturación, impresión y logos.</span>
                </div>
            </div>
        </form>
    </div>
    <div class="col-lg-5">
        <?php if (Configuracion::moduloActivo('propina')): ?>
        <div class="pb-card mt-3" id="card_propina">
            <div class="pb-card-body">
                <h6 class="mb-2"><i class="bi bi-cash-coin"></i> Propina</h6>
                <p class="text-muted small">
                    Al cobrar aparece la propina voluntaria con dos botones: <strong>Sin propina</strong> y el
                    porcentaje sugerido. Aquí decides ese porcentaje y si se ofrece ya puesta.
                </p>
                <div class="row g-2 align-items-end">
                    <div class="col-sm-4">
                        <label for="propina_porcentaje" class="form-label">Porcentaje sugerido (%)</label>
                        <input type="number" class="form-control" id="propina_porcentaje" min="0" max="30" step="0.5"
                               value="<?php echo htmlspecialchars((string) Configuracion::propinaPorcentaje(), ENT_QUOTES, 'UTF-8'); ?>">
                    </div>
                    <div class="col-sm-5">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="propina_por_defecto" <?php echo Configuracion::propinaPorDefecto() ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="propina_por_defecto">Aplicarla sola al cobrar (la persona puede quitarla)</label>
                        </div>
                    </div>
                    <div class="col-sm-3">
                        <button type="button" class="btn btn-primary w-100" onclick="guardarPropina()"><i class="bi bi-save"></i> Guardar</button>
                    </div>
                </div>
                <div class="form-text">Con 0% no se sugiere ningún valor y el botón del porcentaje no aparece.</div>
            </div>
        </div>
        <?php endif; ?>
        <div class="pb-card mt-3" id="card_periodo_balance">
            <div class="pb-card-body">
                <h6 class="mb-2"><i class="bi bi-calendar-range"></i> Período de balance</h6>
                <p class="text-muted small">
                    El Panel y Compras y gastos muestran solos el balance (ventas menos gastos) de "este período" —
                    sin tener que elegir las fechas a mano. Elige el ritmo con el que trabajas.
                </p>
                <div class="row g-2 align-items-end">
                    <div class="col-sm-4">
                        <label for="periodo_balance_tipo" class="form-label">Se repite</label>
                        <select class="form-select" id="periodo_balance_tipo" onchange="mostrarCampoDiaPeriodo()">
                            <option value="semanal" <?php echo $periodoBalance['tipo'] === 'semanal' ? 'selected' : ''; ?>>Cada semana</option>
                            <option value="quincenal" <?php echo $periodoBalance['tipo'] === 'quincenal' ? 'selected' : ''; ?>>Cada quincena (1–15 y 16–fin de mes)</option>
                            <option value="mensual" <?php echo $periodoBalance['tipo'] === 'mensual' ? 'selected' : ''; ?>>Cada mes</option>
                        </select>
                    </div>
                    <div class="col-sm-4" id="campo_dia_periodo_semanal" style="display:none;">
                        <label for="periodo_dia_semana" class="form-label">Empieza el</label>
                        <select class="form-select" id="periodo_dia_semana">
                            <?php foreach (['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado', 'Domingo'] as $i => $nombreDia): ?>
                                <option value="<?php echo $i + 1; ?>" <?php echo $periodoBalance['tipo'] === 'semanal' && $periodoBalance['dia'] === $i + 1 ? 'selected' : ''; ?>><?php echo $nombreDia; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-sm-4" id="campo_dia_periodo_mensual" style="display:none;">
                        <label for="periodo_dia_mes" class="form-label">Empieza el día (1–28)</label>
                        <input type="number" class="form-control" id="periodo_dia_mes" min="1" max="28"
                               value="<?php echo htmlspecialchars((string) ($periodoBalance['tipo'] === 'mensual' ? $periodoBalance['dia'] : 1), ENT_QUOTES, 'UTF-8'); ?>">
                    </div>
                    <div class="col-sm-3">
                        <button type="button" class="btn btn-primary w-100" onclick="guardarPeriodoBalance()"><i class="bi bi-save"></i> Guardar</button>
                    </div>
                </div>
                <div class="form-text">Máximo 28 para el día del mes, así el período siempre cae en un día que existe en todos los meses (incluido febrero).</div>
            </div>
        </div>
        <div class="pb-card mt-3">
            <div class="pb-card-body">
                <h6 class="mb-2"><i class="bi bi-toggles"></i> Nivel de funciones</h6>
                <p class="text-muted small mb-0">
                    Qué módulos están activos (paquetes, IVA, combos, caja, clientes, proveedores, mesas…) lo
                    administra el proveedor del sistema, no este panel. Si necesitas más funciones, contáctanos.
                </p>
            </div>
        </div>

        <div class="pb-card mt-3">
            <div class="pb-card-body">
                <h6 class="mb-2"><i class="bi bi-cloud-arrow-up"></i> Respaldo remoto</h6>
                <?php if (empty($licencia['respaldo_habilitado'])): ?>
                    <div class="pb-upsell">
                        <p class="mb-1 fw-semibold pb-upsell-titulo"><i class="bi bi-stars"></i> Actualmente no cuentas con esta funcionalidad</p>
                        <p class="text-muted small mb-2">
                            Con el respaldo automático, tu base de datos se guarda sola en la nube todos los
                            días — así nunca pierdes tus ventas, productos ni clientes, pase lo que pase con
                            este equipo.
                        </p>
                        <p class="small mb-0">
                            ¿Quieres activarlo? Llama a soporte:
                            <a href="https://nncoms.com" target="_blank" rel="noopener">nncoms.com</a>
                            · <a href="mailto:soporte@nncoms.com">soporte@nncoms.com</a>
                        </p>
                    </div>
                <?php else: ?>
                    <p class="text-muted small mb-2">
                        Se envía automáticamente cada 24 horas mientras la app está abierta (necesita internet
                        en ese momento; si no hay, simplemente lo intenta en el próximo ciclo).
                    </p>
                    <p class="small mb-3">
                        Último respaldo enviado:
                        <strong id="ultimoRespaldoTexto"><?php echo htmlspecialchars($licencia['ultimo_respaldo_en'] ?? 'ninguno todavía', ENT_QUOTES, 'UTF-8'); ?></strong>
                    </p>
                    <?php $proximoRespaldoDisponibleEn = Respaldo::proximoRespaldoManualDisponibleEn(); ?>
                    <button type="button" class="btn btn-outline-primary btn-sm" id="btnRespaldarAhora"
                            onclick="hacerRespaldoAhora()" <?php echo $proximoRespaldoDisponibleEn ? 'disabled' : ''; ?>>
                        <i class="bi bi-cloud-upload"></i> Hacer respaldo ahora
                    </button>
                    <?php if ($proximoRespaldoDisponibleEn): ?>
                        <span class="text-muted small ms-2">
                            Ya se hizo uno hace poco — disponible de nuevo a las
                            <?php echo htmlspecialchars($proximoRespaldoDisponibleEn->format('H:i'), ENT_QUOTES, 'UTF-8'); ?>.
                        </span>
                    <?php endif; ?>
                    <div id="avisoRespaldo" class="alert py-2 px-3 mt-3 d-none"></div>
                <?php endif; ?>
            </div>
        </div>

        <div class="pb-card mt-3">
            <div class="pb-card-body">
                <h6 class="mb-2"><i class="bi bi-download"></i> Actualizaciones</h6>
                <p class="small mb-2">
                    Versión instalada: <strong><?php echo htmlspecialchars(trim((string) @file_get_contents(__DIR__ . '/../../version.txt')) ?: '0.0.0', ENT_QUOTES, 'UTF-8'); ?></strong>
                </p>
                <?php if (empty($licencia['actualizaciones_habilitadas'])): ?>
                    <div class="pb-upsell mb-3">
                        <p class="mb-1 fw-semibold pb-upsell-titulo"><i class="bi bi-stars"></i> Actualmente no cuentas con esta funcionalidad</p>
                        <p class="text-muted small mb-2">
                            Con las actualizaciones automáticas, tu sistema se mantiene siempre al día con
                            las últimas mejoras y correcciones, sin que tengas que hacer nada — herramientas
                            a la vanguardia, siempre.
                        </p>
                        <p class="small mb-0">
                            ¿Quieres activarlo? Llama a soporte:
                            <a href="https://nncoms.com" target="_blank" rel="noopener">nncoms.com</a>
                            · <a href="mailto:soporte@nncoms.com">soporte@nncoms.com</a>
                        </p>
                    </div>
                <?php else: ?>
                    <div id="avisoActualizacionDisponible" class="alert alert-info py-2 px-3 mb-3 d-none">
                        <i class="bi bi-arrow-up-circle"></i>
                        Hay una versión nueva disponible (<strong id="versionDisponibleTexto"></strong>).
                        <?php if (!empty($licencia['notas_version'])): ?>
                            <div class="mt-2 small border-top pt-2">
                                <strong>Qué trae esta versión:</strong>
                                <div class="text-body" style="white-space:pre-line;"><?php echo htmlspecialchars($licencia['notas_version'], ENT_QUOTES, 'UTF-8'); ?></div>
                            </div>
                        <?php endif; ?>
                        <div class="mt-2">
                            <button type="button" class="btn btn-primary btn-sm" onclick="pb.iniciarActualizacion()">
                                <i class="bi bi-cloud-download"></i> Iniciar actualización
                            </button>
                            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="pb.rechazarActualizacion()">
                                Rechazar por ahora
                            </button>
                        </div>
                    </div>
                    <div id="cajaProgresoActualizacion" class="pb-log-actualizacion d-none">
                        <div class="pb-log-actualizacion-lineas" id="lineasProgresoActualizacion"></div>
                    </div>
                <?php endif; ?>
                <p class="small text-muted mb-3">
                    ¿Necesitas ayuda? <a href="https://nncoms.com" target="_blank" rel="noopener">nncoms.com</a>
                    · <a href="mailto:soporte@nncoms.com">soporte@nncoms.com</a>
                </p>
                <p class="text-muted small mb-3">
                    Esto es solo para casos en los que tu proveedor te entregó un instalador y las actualizaciones
                    remotas no están funcionando en este equipo — no es un indicador de que algo esté fallando
                    ahora mismo, es simplemente la opción de respaldo.
                </p>
                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="pb.elegirActualizacionManual()">
                    <i class="bi bi-file-earmark-arrow-up"></i> Actualizar manualmente…
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Hoja de prueba de impresión: vista previa de cómo debe salir según lo que hay en el formulario. -->
<div class="modal fade" id="modalPruebaImpresion" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable" style="max-width:820px;">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title">Hoja de prueba de impresión</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="d-flex flex-nowrap gap-3 align-items-start">
                    <!-- Izquierda: la hoja tal cual saldrá (con el papel y los datos del formulario). -->
                    <div class="flex-grow-1 d-flex justify-content-center" style="min-width:0;">
                        <div id="prueba_vista" class="pb-ticket pb-ticket-vista pb-print-area"></div>
                    </div>
                    <!-- Derecha: controles (nada de esto se imprime). -->
                    <div class="d-grid gap-2 pb-print-hide" style="flex:0 0 230px; width:230px;">
                        <div>
                            <div class="small fw-semibold mb-1">Qué imprimir</div>
                            <div class="btn-group w-100" id="prueba_tipo">
                                <button type="button" class="btn btn-sm btn-primary" data-tipo="factura" onclick="cambiarTipoPrueba('factura')">Factura</button>
                                <button type="button" class="btn btn-sm btn-outline-secondary" data-tipo="comanda" onclick="cambiarTipoPrueba('comanda')">Comanda</button>
                            </div>
                        </div>
                        <div>
                            <div class="small fw-semibold mb-1">Papel</div>
                            <div class="btn-group w-100" id="prueba_papel">
                                <button type="button" class="btn btn-sm btn-outline-secondary" data-formato="termica_58" onclick="cambiarPapelPrueba('termica_58')">58 mm</button>
                                <button type="button" class="btn btn-sm btn-outline-secondary" data-formato="termica_80" onclick="cambiarPapelPrueba('termica_80')">80 mm</button>
                                <button type="button" class="btn btn-sm btn-outline-secondary" data-formato="a4" onclick="cambiarPapelPrueba('a4')">Hoja A4</button>
                            </div>
                            <div class="form-text">Cambia el formato del formulario: recuerda pulsar Guardar si te sirve.</div>
                        </div>
                        <div id="prueba_bloque_ancho">
                            <div class="small fw-semibold mb-1">Ancho de impresión (mm)</div>
                            <div class="input-group input-group-sm">
                                <button type="button" class="btn btn-outline-secondary" onclick="ajustarAnchoPrueba(-0.5)" title="Restar 0,5 mm"><i class="bi bi-dash-lg"></i></button>
                                <input type="number" class="form-control text-center" id="prueba_ancho" min="25" max="120" step="0.5" oninput="fijarAnchoPrueba(this.value)">
                                <button type="button" class="btn btn-outline-secondary" onclick="ajustarAnchoPrueba(0.5)" title="Sumar 0,5 mm"><i class="bi bi-plus-lg"></i></button>
                            </div>
                            <div class="btn-group btn-group-sm w-100 mt-1">
                                <button type="button" class="btn btn-outline-secondary" onclick="ajustarAnchoPrueba(-5)">−5</button>
                                <button type="button" class="btn btn-outline-secondary" onclick="ajustarAnchoPrueba(-1)">−1</button>
                                <button type="button" class="btn btn-outline-secondary" onclick="ajustarAnchoPrueba(1)">+1</button>
                                <button type="button" class="btn btn-outline-secondary" onclick="ajustarAnchoPrueba(5)">+5</button>
                            </div>
                            <div class="form-text">Ve moviéndolo e imprimiendo hasta que se vea el borde derecho. Cuando acomode, pulsa Guardar ancho.</div>
                            <button type="button" class="btn btn-success btn-sm w-100 mt-1" onclick="guardarConfiguracion()"><i class="bi bi-check2-circle"></i> Guardar ancho</button>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="prueba_tira_larga" onchange="dibujarPrueba()">
                            <label class="form-check-label small" for="prueba_tira_larga">Añadir tira larga (30 líneas) para ver que no se corta</label>
                        </div>
                        <button type="button" class="btn btn-outline-secondary btn-sm" onclick="cargarProductosPrueba()"><i class="bi bi-shuffle"></i> Otros productos</button>
                        <button type="button" class="btn btn-primary" onclick="imprimirPrueba()"><i class="bi bi-printer"></i> Imprimir esta hoja</button>
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cerrar</button>
                        <div class="small text-muted">Impresora: <strong id="prueba_impresora"></strong></div>
                        <div class="small text-muted" id="prueba_estado"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . "/../parciales/pie.php"; ?>
<script src="/assets/vendor/qrcodejs/qrcode.min.js"></script>
<script>
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

// Sin esto, Enter en cualquier campo (ej. Nombre del negocio) recargaba la
// página en vez de guardar los cambios.
$('#formConfiguracion').on('submit', function (e) {
    e.preventDefault();
    guardarConfiguracion();
});

function mostrarCampoAnchoTicket() {
    const esTermica = $('#formato_ticket').val() !== 'a4';
    $('#campo_ancho_ticket').toggleClass('d-none', !esTermica);
}

// Ancho/margen por formato (58mm/80mm/A4): #ancho_ticket_mm y #margen_ticket_mm de arriba son una sola
// "ventana" que muestra y edita el par del formato que esté elegido en #formato_ticket en ese momento —
// cambiar el formato guarda primero lo que se veía (en el campo oculto de ESE formato) y recién después
// muestra lo guardado del formato nuevo, para que probar uno no borre el ajuste fino del otro.
function clavesPapel(formato) {
    if (formato === 'termica_58') return { ancho: '#ancho_ticket_58_mm', margen: '#margen_ticket_58_mm' };
    if (formato === 'termica_80') return { ancho: '#ancho_ticket_80_mm', margen: '#margen_ticket_80_mm' };
    return { ancho: null, margen: '#margen_ticket_a4_mm' };
}
let formatoVisible = <?php echo json_encode($config['formato_ticket']); ?>;

function guardarValoresPapelVisibles() {
    const claves = clavesPapel(formatoVisible);
    if (claves.ancho) $(claves.ancho).val($('#ancho_ticket_mm').val());
    $(claves.margen).val($('#margen_ticket_mm').val());
}

function mostrarValoresPapel(formato) {
    const claves = clavesPapel(formato);
    $('#ancho_ticket_mm').val(claves.ancho ? $(claves.ancho).val() : '');
    $('#margen_ticket_mm').val($(claves.margen).val());
    formatoVisible = formato;
}

function cambiarFormatoTicket() {
    guardarValoresPapelVisibles();
    mostrarValoresPapel($('#formato_ticket').val());
    mostrarCampoAnchoTicket();
}

function revisarFormatoContraImpresora() {
    const termica = pb.esImpresoraTermica($('#impresora_recibo').val()) || pb.esImpresoraTermica($('#impresora_cocina').val());
    $('#aviso_formato_termica').toggleClass('d-none', !(termica && $('#formato_ticket').val() === 'a4'));
}
$('#formato_ticket, #impresora_recibo, #impresora_cocina').on('change', revisarFormatoContraImpresora);
mostrarValoresPapel(formatoVisible);
mostrarCampoAnchoTicket();

const IMPRESORA_RECIBO_GUARDADA = <?php echo json_encode($config['impresora_recibo'] ?? ''); ?>;
const IMPRESORA_COCINA_GUARDADA = <?php echo json_encode($config['impresora_cocina'] ?? ''); ?>;
const IMPRESORA_ETIQUETAS_GUARDADA = <?php echo json_encode($config['impresora_etiquetas'] ?? ''); ?>;

pb.listarImpresoras().then(function (impresoras) {
    if (!impresoras || impresoras.length === 0) return;
    [
        { selector: '#impresora_recibo', guardada: IMPRESORA_RECIBO_GUARDADA },
        { selector: '#impresora_cocina', guardada: IMPRESORA_COCINA_GUARDADA },
        { selector: '#impresora_etiquetas', guardada: IMPRESORA_ETIQUETAS_GUARDADA },
    ].forEach(function (campo) {
        if ($(campo.selector).length === 0) return;
        const $select = $(campo.selector);
        impresoras.forEach(function (imp) {
            $select.append(`<option value="${imp.nombre}">${imp.nombre}${imp.predeterminada ? ' (predeterminada)' : ''}</option>`);
        });
        if (campo.guardada) $select.val(campo.guardada);
    });
    revisarFormatoContraImpresora();
});

// ---- Hoja de prueba de impresión (modal) ----
// Muestra cómo debe salir un ticket con el papel, la impresora y los datos que hay
// AHORA en el formulario (aunque no se haya guardado), con productos reales al azar del
// catálogo, y permite imprimirla para compararla con lo que sale en papel.
const PRUEBA_FIDELIZACION = <?php echo !empty($licencia['fidelizacion_habilitada']) ? 'true' : 'false'; ?>;
const PRUEBA_ATENDIO = <?php echo json_encode($usuarioActual['nombre']); ?>;
let pruebaProductos = [];
let pruebaTipo = 'factura';
let pruebaHtml = '';
let pruebaForzado = null;
let pruebaImpresora = null;

function formatoDelFormulario() {
    const formato = $('#formato_ticket').val();
    const margenTexto = $('#margen_ticket_mm').val();
    return {
        formato: formato,
        anchoMm: formato === 'a4' ? null : (parseFloat($('#ancho_ticket_mm').val()) || null),
        margenMm: margenTexto !== '' ? parseFloat(margenTexto) : (formato === 'a4' ? null : 2),
    };
}

function dibujarPrueba() {
    pruebaForzado = formatoDelFormulario();
    pruebaImpresora = (pruebaTipo === 'comanda'
        ? ($('#impresora_cocina').val() || $('#impresora_recibo').val())
        : ($('#impresora_recibo').val() || $('#impresora_cocina').val())) || null;

    const opciones = {
        productos: pruebaProductos,
        negocio: {
            nombre: $('#nombre_negocio').val(),
            nit: $('#nit').val(),
            telefono: $('#telefono').val(),
            direccion: $('#direccion').val(),
            lema: $('#lema').val(),
            logoFactura: (window.PB_NEGOCIO || {}).logoFactura || null,
        },
        atendio: PRUEBA_ATENDIO,
        conQr: PRUEBA_FIDELIZACION,
        tiraLarga: $('#prueba_tira_larga').is(':checked'),
    };
    pruebaHtml = pruebaTipo === 'comanda'
        ? pb.construirComandaDePrueba(pruebaImpresora, pruebaForzado, opciones)
        : pb.construirPaginaDePrueba(pruebaImpresora, pruebaForzado, opciones);

    const vista = document.getElementById('prueba_vista');
    vista.innerHTML = pruebaHtml;
    pb.prepararTicketParaImprimir(vista, pruebaTipo, pruebaForzado);

    $('#prueba_papel button').each(function () {
        const activo = $(this).data('formato') === pruebaForzado.formato;
        $(this).toggleClass('btn-primary', activo).toggleClass('btn-outline-secondary', !activo);
    });
    $('#prueba_tipo button').each(function () {
        const activo = $(this).data('tipo') === pruebaTipo;
        $(this).toggleClass('btn-primary', activo).toggleClass('btn-outline-secondary', !activo);
    });
    $('#prueba_impresora').text(pruebaImpresora || 'ninguna — abrirá el diálogo del sistema');
    $('#prueba_bloque_ancho').toggleClass('d-none', pruebaForzado.formato === 'a4');
    if (!$('#prueba_ancho').is(':focus')) $('#prueba_ancho').val(pruebaForzado.anchoMm || (pruebaForzado.formato === 'termica_58' ? 58 : 80));
}

// El ancho manda sobre 58/80: se escribe en el mismo campo del formulario y se redibuja la hoja.
function fijarAnchoPrueba(valor) {
    const mm = parseFloat(valor);
    if (isNaN(mm)) return;
    $('#ancho_ticket_mm').val(Math.min(120, Math.max(25, Math.round(mm * 2) / 2)));
    dibujarPrueba();
}

function ajustarAnchoPrueba(delta) {
    const actual = parseFloat($('#prueba_ancho').val()) || (pruebaForzado && pruebaForzado.anchoMm) || 80;
    $('#prueba_ancho').val(Math.min(120, Math.max(25, Math.round((actual + delta) * 2) / 2)));
    fijarAnchoPrueba($('#prueba_ancho').val());
}

function cargarProductosPrueba() {
    $.getJSON('/controlador/productos_muestra_impresion.php', { cantidad: 4 })
        .done(function (r) { pruebaProductos = (r && r.productos) || []; })
        .fail(function () { pruebaProductos = []; })
        .always(dibujarPrueba);
}

function abrirModalPrueba() {
    $('#prueba_estado').empty();
    bootstrap.Modal.getOrCreateInstance('#modalPruebaImpresion').show();
    cargarProductosPrueba();
}

function cambiarTipoPrueba(tipo) {
    pruebaTipo = tipo;
    dibujarPrueba();
}

function cambiarPapelPrueba(formato) {
    guardarValoresPapelVisibles();
    mostrarValoresPapel(formato);
    $('#formato_ticket').val(formato);
    mostrarCampoAnchoTicket();
    revisarFormatoContraImpresora();
    dibujarPrueba();
}

async function imprimirPrueba() {
    const $estado = $('#prueba_estado').empty();
    const linea = (texto, error) => $estado.append($('<div></div>').toggleClass('text-danger', !!error).text(new Date().toLocaleTimeString('es-CO') + ' — ' + texto));

    if (!pruebaImpresora) {
        pb.imprimirConDialogo(pruebaHtml, pruebaForzado);
        linea('abierto el diálogo de impresión del sistema.');
        return;
    }
    const r = await pb.imprimirTicketSilencioso(pruebaHtml, pruebaImpresora, 'prueba', pruebaForzado);
    if (r && r.duplicado) linea('ya se había enviado hace un instante.');
    else if (r && r.ok) linea('enviada a "' + pruebaImpresora + '". Compárala con la vista de la izquierda.');
    else linea('no se pudo imprimir (' + ((r && r.error) || 'la impresora no respondió') + ').', true);
}

function guardarPropina() {
    const porcentaje = parseFloat($('#propina_porcentaje').val());
    if (isNaN(porcentaje) || porcentaje < 0 || porcentaje > 30) {
        pb.error('El porcentaje debe estar entre 0 y 30.');
        return;
    }
    $.ajax({
        type: 'POST',
        url: '/controlador/actualizar_propina.php',
        data: { porcentaje: porcentaje, por_defecto: $('#propina_por_defecto').is(':checked') ? 1 : 0, csrf_token: pb.csrfToken() },
        dataType: 'json',
        success: function (r) {
            if (r.ok) pb.success('Propina guardada.');
            else pb.error(r.error || 'No se pudo guardar.');
        },
        error: function () { pb.error('Error en el proceso.'); },
    });
}

function mostrarCampoDiaPeriodo() {
    const tipo = $('#periodo_balance_tipo').val();
    $('#campo_dia_periodo_semanal').toggle(tipo === 'semanal');
    $('#campo_dia_periodo_mensual').toggle(tipo === 'mensual');
}
mostrarCampoDiaPeriodo();

function guardarPeriodoBalance() {
    const tipo = $('#periodo_balance_tipo').val();
    const dia = tipo === 'semanal' ? $('#periodo_dia_semana').val() : (tipo === 'mensual' ? $('#periodo_dia_mes').val() : 1);
    $.ajax({
        type: 'POST',
        url: '/controlador/actualizar_periodo_balance.php',
        data: { tipo: tipo, dia: dia, csrf_token: pb.csrfToken() },
        dataType: 'json',
        success: function (r) {
            if (r.ok) pb.success('Período de balance guardado.');
            else pb.error(r.error || 'No se pudo guardar.');
        },
        error: function () { pb.error('Error en el proceso.'); },
    });
}

function guardarConfiguracion() {
    guardarValoresPapelVisibles(); // deja lo que se está viendo en el campo oculto de SU formato antes de leerlos
    const datos = new FormData();
    datos.append('nombre_negocio', $('#nombre_negocio').val());
    datos.append('color_primario', $('#color_primario').val());
    datos.append('color_secundario', $('#color_secundario').val());
    datos.append('moneda', $('#moneda').val());
    datos.append('zona_horaria', $('#zona_horaria').val());
    datos.append('formato_ticket', $('#formato_ticket').val());
    datos.append('ancho_ticket_58_mm', $('#ancho_ticket_58_mm').val());
    datos.append('margen_ticket_58_mm', $('#margen_ticket_58_mm').val());
    datos.append('ancho_ticket_80_mm', $('#ancho_ticket_80_mm').val());
    datos.append('margen_ticket_80_mm', $('#margen_ticket_80_mm').val());
    datos.append('margen_ticket_a4_mm', $('#margen_ticket_a4_mm').val());
    datos.append('nit', $('#nit').val());
    datos.append('telefono', $('#telefono').val());
    datos.append('direccion', $('#direccion').val());
    datos.append('lema', $('#lema').val());
    datos.append('impresora_recibo', $('#impresora_recibo').val());
    datos.append('impresora_cocina', $('#impresora_cocina').val());
    datos.append('impresora_etiquetas', $('#impresora_etiquetas').val() || '');
    datos.append('ancho_etiqueta_mm', $('#ancho_etiqueta_mm').val() || '');
    datos.append('alto_etiqueta_mm', $('#alto_etiqueta_mm').val() || '');
    datos.append('csrf_token', pb.csrfToken());
    const archivoLogo = $('#logo')[0].files[0];
    if (archivoLogo) datos.append('logo', archivoLogo);
    const archivoLogoFactura = $('#logo_factura')[0].files[0];
    if (archivoLogoFactura) datos.append('logo_factura', archivoLogoFactura);
    if ($('#quitar_logo_factura').is(':checked')) datos.append('quitar_logo_factura', 1);
    datos.append('imprimir_logo_factura', $('#imprimir_logo_factura').is(':checked') ? 1 : 0);

    $.ajax({
        type: 'POST',
        url: '/controlador/actualizar_configuracion.php',
        data: datos,
        processData: false,
        contentType: false,
        dataType: 'json',
        success: function (r) {
            if (r.ok) {
                // Si alguna imagen no se pudo guardar (formato/tamaño) se dice cuál, en vez de callar.
                (r.avisos || []).forEach((a) => pb.error(a));
                pb.success('Configuración guardada. Recargando…');
                setTimeout(() => location.reload(), (r.avisos || []).length ? 2800 : 800);
            } else {
                pb.error(r.error || 'Error al guardar la configuración.');
            }
        },
        error: function () { pb.error('Error en el proceso.'); }
    });
}

function hacerRespaldoAhora() {
    const $boton = $('#btnRespaldarAhora').prop('disabled', true);
    const $aviso = $('#avisoRespaldo').removeClass('d-none alert-success alert-danger').addClass('alert-secondary').text('Enviando respaldo…');
    $.ajax({
        type: 'POST',
        url: '/controlador/backup.php',
        data: { csrf_token: pb.csrfToken() },
        dataType: 'json',
        success: function (r) {
            $boton.prop('disabled', false);
            if (r.ok) {
                $aviso.removeClass('alert-secondary').addClass('alert-success').text('Respaldo enviado correctamente (' + Math.round(r.tamano_bytes / 1024) + ' KB).');
                $('#ultimoRespaldoTexto').text(r.fecha);
            } else {
                $aviso.removeClass('alert-secondary').addClass('alert-danger').text(r.error || 'No se pudo enviar el respaldo.');
            }
        },
        error: function (xhr) {
            $boton.prop('disabled', false);
            // backup.php responde con http_response_code(500) para CUALQUIER
            // fallo (sin internet, servidor de respaldos caído, un bug del
            // lado del servidor, etc.) — jQuery lo manda aquí en vez de a
            // success() por el código de estado, pero el JSON con el motivo
            // real sigue viniendo en el cuerpo. Mostrar ese motivo en vez de
            // un genérico ayuda a distinguir "no hay internet" de "el
            // servidor respondió pero algo le falló".
            const msg = xhr.responseJSON && xhr.responseJSON.error
                ? xhr.responseJSON.error
                : 'Error de conexión al intentar enviar el respaldo.';
            $aviso.removeClass('alert-secondary').addClass('alert-danger').text(msg);
        }
    });
}
</script>
