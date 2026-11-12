window.PB_ACTUALIZACION_FORZADA = false;

const pb = (() => {
    function toastContainer() {
        let el = document.getElementById('pb-toast-container');
        if (!el) {
            el = document.createElement('div');
            el.id = 'pb-toast-container';
            el.className = 'pb-toast-container';
            document.body.appendChild(el);
        }
        return el;
    }

    function toast(mensaje, tipo) {
        const contenedor = toastContainer();
        const el = document.createElement('div');
        el.className = `pb-toast pb-toast-${tipo}`;
        el.textContent = mensaje;
        contenedor.appendChild(el);
        setTimeout(() => el.remove(), 3200);
    }

    function debounce(fn, espera) {
        let temporizador;
        // function normal (no flecha): así, cuando jQuery invoca el
        // resultado con .call(elemento, evento) al disparar el evento, "this"
        // adentro sí queda como el elemento — se captura aquí y se le pasa a
        // fn vía apply() dentro del setTimeout. Con una flecha aquí, "this"
        // nunca es el elemento (jQuery no puede forzarlo en una flecha), y
        // dentro de fn queda como "window" — quien llame a $(this) adentro
        // de una función debounced termina hitting bugs muy raros de rastrear
        // (ej. $(window).val() revienta porque window no es un input).
        return function (...args) {
            const contexto = this;
            clearTimeout(temporizador);
            temporizador = setTimeout(() => fn.apply(contexto, args), espera);
        };
    }

    /** XXXXXXXXXXXXXXXX -> XXXX-XXXX-XXXX-XXXX, sin importar cómo se haya pegado/escrito. */
    function formatearClaveLicencia(valor) {
        const limpio = (valor || '').toUpperCase().replace(/[^A-Z0-9]/g, '').slice(0, 16);
        return limpio.match(/.{1,4}/g)?.join('-') || limpio;
    }

    /** Conecta el auto-formateo (guion cada 4 caracteres) a un campo de clave de licencia. */
    function autoformatearClaveLicencia(selector) {
        $(selector).on('input', function () {
            const posicionOriginal = this.selectionStart;
            const largoOriginal = this.value.length;
            this.value = formatearClaveLicencia(this.value);
            // Compensa los guiones que se van agregando para que el cursor
            // no salte siempre al final mientras se escribe en medio del texto.
            const diferencia = this.value.length - largoOriginal;
            this.setSelectionRange(posicionOriginal + diferencia, posicionOriginal + diferencia);
        });
    }

    /**
     * Ayuda al clic para campos confusos (ej. "tasa de IVA", "costo
     * unitario") — cualquier elemento con data-pb-ayuda="texto" muestra ese
     * texto en un popover al hacer clic. A propósito NO es solo un tooltip
     * de mouseover: en pantalla táctil el hover no existe, así que esa
     * ayuda nunca se vería.
     */
    function inicializarAyudas() {
        document.querySelectorAll('[data-pb-ayuda]').forEach((el) => {
            if (bootstrap.Popover.getInstance(el)) return;
            new bootstrap.Popover(el, {
                trigger: 'click',
                placement: 'top',
                container: 'body',
                content: el.getAttribute('data-pb-ayuda'),
            });
        });
    }
    document.addEventListener('click', (e) => {
        document.querySelectorAll('[data-pb-ayuda]').forEach((el) => {
            if (el === e.target || el.contains(e.target) || e.target.closest('.popover')) return;
            bootstrap.Popover.getInstance(el)?.hide();
        });
    });
    inicializarAyudas();

    function moneda(valor) {
        return new Intl.NumberFormat('es-CO', {
            style: 'currency',
            currency: window.PB_MONEDA || 'COP',
            minimumFractionDigits: 0,
            maximumFractionDigits: 2,
        }).format(valor || 0);
    }

    function csrfToken() {
        const meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.content : '';
    }

    /** Píldora de estado (ver .pb-pill en app.css) — reemplaza el texto plano "Activo"/"Inactivo" en las tablas. */
    function pill(esActivo, textoActivo, textoInactivo) {
        return esActivo
            ? `<span class="pb-pill pb-pill-activo">${textoActivo || 'Activo'}</span>`
            : `<span class="pb-pill pb-pill-inactivo">${textoInactivo || 'Inactivo'}</span>`;
    }

    function toggleSidebar() {
        document.querySelector('.pb-sidebar')?.classList.toggle('pb-open');
    }

    /**
     * Panel lateral (solo escritorio): se contrae a una franja de íconos tras
     * PB_SIDEBAR_ESPERA_MS sin USARLO — moverse o hacer clic en el resto de la
     * pantalla no cuenta, es inactividad DEL PANEL, no de la app. Un clic
     * dentro (que no sea un enlace) lo vuelve a abrir sin navegar; el
     * botoncito de flecha lo fija abierto o contraído a mano. head.php ya
     * puso la clase inicial en <html> antes de pintar (ver ahí el porqué),
     * así que aquí solo hay que mantenerla al día y reaccionar a los clics.
     */
    const PB_SIDEBAR_ESPERA_MS = 15000;
    const PB_SIDEBAR_CLAVE = 'pb_sidebar_contraido';

    function inicializarSidebarAutocontraible() {
        const sidebar = document.querySelector('.pb-sidebar');
        if (!sidebar) return;
        const html = document.documentElement;
        const escritorio = () => window.matchMedia('(min-width: 901px)').matches;

        function contraido() {
            return html.classList.contains('pb-sidebar-rail');
        }

        function fijarEstado(nuevoContraido) {
            html.classList.toggle('pb-sidebar-rail', nuevoContraido);
            try { localStorage.setItem(PB_SIDEBAR_CLAVE, nuevoContraido ? '1' : '0'); } catch (e) { /* sin almacenamiento local: no se recuerda, sin problema */ }
        }

        let temporizador = null;
        function reiniciarTemporizador() {
            if (temporizador) clearTimeout(temporizador);
            temporizador = setTimeout(function () {
                if (escritorio()) fijarEstado(true);
            }, PB_SIDEBAR_ESPERA_MS);
        }

        function alUsarlo() {
            if (contraido()) fijarEstado(false);
            reiniciarTemporizador();
        }

        // Clic dentro del panel: en un enlace, navega normal (se deja que el
        // navegador siga su curso) pero se avisa que la PRÓXIMA pantalla debe
        // abrir expandida — clic en cualquier otra parte (el logo, el fondo)
        // lo expande aquí mismo sin salir de la página.
        sidebar.addEventListener('click', function (e) {
            if (e.target.closest('a')) {
                try { localStorage.setItem(PB_SIDEBAR_CLAVE, '0'); } catch (err) { /* no pasa nada, solo no se recuerda */ }
                return;
            }
            alUsarlo();
        });
        // Llegar con Tab (teclado) a un enlace de adentro también cuenta como "usarlo".
        sidebar.addEventListener('focusin', alUsarlo);

        const boton = document.getElementById('pbSidebarPin');
        if (boton) {
            boton.addEventListener('click', function (e) {
                e.stopPropagation();
                fijarEstado(!contraido());
                reiniciarTemporizador();
            });
        }

        reiniciarTemporizador();
    }
    // Se llama aquí mismo (no antes, como inicializarAyudas): PB_SIDEBAR_ESPERA_MS es un "const" y
    // llamarla más arriba en el archivo, antes de que esa línea se ejecute, revienta el módulo entero.
    inicializarSidebarAutocontraible();

    /**
     * Cuántos enlaces trae el menú varía según qué módulos tenga encendidos el negocio (de unos
     * pocos a los 17 a la vez). El espaciado de .pb-nav-link está pensado para el dedo en pantalla
     * táctil, así que por defecto es holgado — pero un negocio con casi todo encendido, en una
     * pantalla baja, podría no caber sin scroll con ese espaciado. En vez de elegir un espaciado
     * único que a unos les quede holgado y a otros les obligue a hacer scroll, se prueba EN ESE
     * EQUIPO, de más espacioso a más ceñido (ver los niveles pb-nav-cozy/pb-nav-compact en
     * app.css), y se usa el primero que quepa entero sin scroll. Se repite si la ventana cambia de
     * alto (pantalla más chica, ventana redimensionada).
     */
    const PB_DENSIDAD_CLAVE = 'pb_sidebar_densidad';

    function medirYAjustarDensidadNav() {
        const sidebar = document.querySelector('.pb-sidebar');
        if (!sidebar) return;
        const html = document.documentElement;
        const niveles = ['', 'pb-nav-cozy', 'pb-nav-compact'];
        let elegido = niveles[niveles.length - 1];
        for (const nivel of niveles) {
            html.classList.remove('pb-nav-cozy', 'pb-nav-compact');
            if (nivel) html.classList.add(nivel);
            // +1px de margen: con decimales de fuente, scrollHeight puede superar clientHeight por
            // una fracción de píxel aunque visualmente no haga falta ningún scroll.
            if (sidebar.scrollHeight <= sidebar.clientHeight + 1) { elegido = nivel; break; }
            // Ni el nivel más ceñido alcanza (demasiados módulos para una pantalla muy baja): se
            // deja en ese último nivel — sigue siendo tocable, y el scroll que quede es delgado.
        }
        // Se recuerda en este equipo (lo mismo que ya hace pb_sidebar_contraido): head.php lo aplica
        // ANTES de pintar la próxima pantalla, para que el espaciado no se vea "saltar" justo
        // después de cargar cada una — solo se nota, si acaso, la primera vez o si cambia el alto.
        try { localStorage.setItem(PB_DENSIDAD_CLAVE, elegido); } catch (e) { /* sin almacenamiento local: se recalcula cada vez, sin problema */ }
    }
    // La fuente de los íconos (bootstrap-icons) carga aparte y llega un instante después del primer
    // pintado — medir antes de que esté lista puede dar una altura de fila distinta a la real (con
    // el glifo de reserva) y elegir un nivel más apretado de lo necesario. document.fonts.ready
    // ayuda, pero en la práctica la métrica final de la fuente a veces se asienta un pelín DESPUÉS
    // de que esa promesa avisa — por eso se remide una vez más, poco después, para autocorregirse
    // si hiciera falta (medirYAjustarDensidadNav siempre vuelve a probar desde el nivel más cómodo,
    // nunca se queda pegada en un nivel más apretado de lo que en verdad hace falta).
    function ajustarDensidadNav() {
        if (document.fonts && document.fonts.ready) {
            document.fonts.ready.then(medirYAjustarDensidadNav).catch(medirYAjustarDensidadNav);
        } else {
            medirYAjustarDensidadNav();
        }
        setTimeout(medirYAjustarDensidadNav, 400);
    }
    ajustarDensidadNav();
    window.addEventListener('resize', debounce(ajustarDensidadNav, 200));

    /**
     * Papeles que sabe manejar la app. "ancho" es el ancho del ticket en mm
     * (null = hoja completa, sin ancho propio).
     */
    const FORMATOS_TICKET = {
        termica_58: { etiqueta: '58 mm', ancho: 58 },
        termica_80: { etiqueta: '80 mm', ancho: 80 },
        a4: { etiqueta: 'Hoja A4', ancho: null },
    };

    /**
     * Formato con el que de verdad se arma un ticket. Orden de prioridad:
     *  1. El de Configuración, si es térmico (con su ancho exacto en mm si se puso).
     *  2. Si Configuración sigue en "Hoja A4" (el valor de fábrica) pero la
     *     impresora elegida es claramente una de rollo (Xprinter, POS-80,
     *     "thermal", etc.), el de rollo: en A4 la factura se maqueta al ancho de
     *     una hoja y en el rollo se pierde lo centrado (logo, NIT, número) y lo
     *     alineado a la derecha (precios, TOTAL).
     *  3. El de Configuración.
     */
    const PATRON_IMPRESORA_TERMICA = /xprinter|x-printer|thermal|t[eé]rmic|receipt|recibo|rongta|bixolon|zjiang|gprinter|munbyn|epson\s*tm|star\s*tsp|citizen\s*ct|pos[\s-]?(58|80)|xp[\s-]?(58|80|q|n|t|365|420|d)|tm[\s-]?t\d|(^|[^0-9])(58|80)\s?(mm|ii|iii)([^a-z]|$)/i;
    // Antes cada ventana de impresión tenía un selector de papel que se recordaba en el equipo. Ya no
    // existe (el papel y el ancho se ajustan solo en Configuración): se borra la elección vieja para que
    // no siga mandando sin que nadie pueda verla ni quitarla.
    try { localStorage.removeItem('pb_formato_ticket_equipo'); } catch (e) { /* sin almacenamiento local */ }

    function esImpresoraTermica(nombre) {
        return !!nombre && PATRON_IMPRESORA_TERMICA.test(nombre);
    }

    function formatoEfectivo(deviceName) {
        const configurado = window.PB_TICKET_FORMATO || 'a4';
        if (configurado !== 'a4' || !esImpresoraTermica(deviceName)) return configurado;
        return /(^|[^0-9])58([^0-9]|$)/.test(deviceName) ? 'termica_58' : 'termica_80';
    }

    /**
     * Medidas finales del ticket. "forzado" ({formato, anchoMm, margenMm}) se usa solo
     * para la página de prueba de Configuración, que prueba lo que está en el
     * formulario aunque todavía no se haya guardado.
     */
    /**
     * El ancho/margen guardado es POR FORMATO (58mm, 80mm, A4) — nunca un solo valor compartido, para
     * que detectar/elegir un formato distinto no pise el ajuste fino que ya se tenía en otro. El formato
     * EFECTIVO puede no ser el configurado (ver formatoEfectivo: una Configuración en A4 con una
     * impresora que trae "58" en el nombre pasa sola a térmica 58), así que siempre se lee el par que
     * corresponde al formato ya resuelto, no uno ambiental fijo.
     */
    function anchoMargenGuardados(formato) {
        if (formato === 'termica_58') return { ancho: window.PB_TICKET_ANCHO_58_MM, margen: window.PB_TICKET_MARGEN_58_MM };
        if (formato === 'termica_80') return { ancho: window.PB_TICKET_ANCHO_80_MM, margen: window.PB_TICKET_MARGEN_80_MM };
        return { ancho: null, margen: window.PB_TICKET_MARGEN_A4_MM };
    }

    function medidasTicket(deviceName, forzado) {
        const formato = forzado ? forzado.formato : formatoEfectivo(deviceName);
        const termica = formato === 'termica_58' || formato === 'termica_80';
        const guardados = anchoMargenGuardados(formato);
        const anchoPersonalizado = forzado ? forzado.anchoMm : guardados.ancho;
        const margenConfigurado = forzado && 'margenMm' in forzado ? forzado.margenMm : guardados.margen;
        return {
            formato,
            termica,
            anchoMm: termica ? (anchoPersonalizado || FORMATOS_TICKET[formato].ancho) : null,
            margenMm: margenConfigurado !== null && margenConfigurado !== undefined ? margenConfigurado : (termica ? 2 : null),
        };
    }

    /**
     * Aplica al elemento del ticket la clase y el tamaño de página @page
     * que correspondan al formato (A4 o térmica), para que
     * window.print() salga con el ancho correcto. "auto" en la altura es
     * lo que evita que un ticket largo (muchos productos) se corte en
     * varias páginas/tirillas — imprime como una sola tira continua sin
     * importar cuánto mida el contenido.
     */
    function prepararTicketParaImprimir(elemento, tipo, forzado) {
        const m = medidasTicket(impresoraPara(tipo || 'factura'), forzado);
        elemento.classList.remove('pb-ticket-termica-58', 'pb-ticket-termica-80');

        let idEstilo = document.getElementById('pb-estilo-pagina-impresion');
        if (!idEstilo) {
            idEstilo = document.createElement('style');
            idEstilo.id = 'pb-estilo-pagina-impresion';
            document.head.appendChild(idEstilo);
        }

        if (m.termica) {
            elemento.classList.add(m.formato === 'termica_58' ? 'pb-ticket-termica-58' : 'pb-ticket-termica-80');
            elemento.style.width = m.anchoMm + 'mm';
            idEstilo.textContent = `@page { size: ${m.anchoMm}mm auto; margin: ${m.margenMm}mm; }`;
        } else {
            elemento.style.width = '';
            idEstilo.textContent = m.margenMm !== null ? `@page { margin: ${m.margenMm}mm; }` : '';
        }
    }

    /**
     * Envuelve el HTML de un ticket con su propio <style>@page{...}</style>
     * (mismas medidas que prepararTicketParaImprimir, pero
     * autocontenido en el string en vez de tocar el <head> de la página
     * real) y la clase pb-print-area — así la ventana oculta de impresión
     * silenciosa (ver main.js) solo necesita cargar bootstrap.min.css +
     * app.css para que el ticket se vea igual que impreso desde pantalla.
     */
    function envolverTicketParaImpresionSilenciosa(htmlInterno, deviceName, forzado) {
        const m = medidasTicket(deviceName, forzado);
        let claseFormato = '';
        let anchoEstilo = '';
        let paginaCss;
        if (m.termica) {
            claseFormato = m.formato === 'termica_58' ? 'pb-ticket-termica-58' : 'pb-ticket-termica-80';
            anchoEstilo = `width:${m.anchoMm}mm;`;
            paginaCss = `@page { size: ${m.anchoMm}mm auto; margin: ${m.margenMm}mm; }`;
        } else {
            paginaCss = `@page { margin: ${m.margenMm === null ? 0 : m.margenMm}mm; }`;
        }

        return `<style>${paginaCss}</style><div class="pb-ticket pb-print-area ${claseFormato}" style="${anchoEstilo}">${htmlInterno}</div>`;
    }

    function escaparHtml(texto) {
        return String(texto === null || texto === undefined ? '' : texto)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    /**
     * Marco de una hoja de prueba: un banner al INICIO y otro al FIN con flechas
     * en los dos bordes, y el borde izquierdo/derecho del papel dibujado con
     * línea punteada a todo lo largo. Si en el papel falta el borde derecho (o el
     * "FIN"), el papel elegido es más ancho que el rollo o el rollo se cortó.
     */
    function marcoDePrueba(htmlInterno, m) {
        const etiqueta = FORMATOS_TICKET[m.formato].etiqueta;
        const banner = (texto) => `
            <div style="display:flex;justify-content:space-between;font-weight:bold;">
                <span>◄</span><span>${texto}</span><span>►</span>
            </div>`;
        // Líneas verticales cada 10 mm a TODO el largo del ticket: donde dejen de verse por la
        // derecha es hasta donde imprime realmente la impresora.
        // Solo en rollo térmico: en A4 el ancho de la hoja lo da el papel y una regla de 190 mm se saldría de la vista.
        const ancho = m.anchoMm || 190;
        let guias = '';
        for (let mm = 10; m.termica && mm < ancho; mm += 10) {
            guias += `<div style="position:absolute;left:${mm}mm;top:0;bottom:0;border-left:1px dotted #000;pointer-events:none;"></div>`;
        }
        return `
            <div style="position:relative;color:#000;border-left:2px dashed #000;border-right:2px dashed #000;box-sizing:border-box;">
                ${guias}
                ${banner('▼ INICIO · ' + etiqueta + ' ▼')}
                ${m.termica ? reglaMilimetros(m) : ''}
                ${htmlInterno}
                ${m.termica ? reglaMilimetros(m) : ''}
                ${banner('▲ FIN DEL TICKET ▲')}
            </div>`;
    }

    /**
     * Regla en milímetros: una marca por mm (corta), cada 5 mm (media, con su número) y cada
     * 10 mm (larga, en negrita). La última marca lleva el ancho total configurado. El último
     * número que se alcance a ver completo en el papel es el ancho REAL que imprime la
     * impresora: ese valor va en Configuración > "Ancho exacto de la tirilla".
     */
    function reglaMilimetros(m) {
        const ancho = m.anchoMm || 190;
        const marcas = [];
        for (let i = 0; i <= Math.floor(ancho); i++) {
            const alto = i % 10 === 0 ? 14 : (i % 5 === 0 ? 10 : 5);
            marcas.push(`<div style="position:absolute;left:${i}mm;top:0;height:${alto}px;border-left:1px solid #000;"></div>`);
            if (i % 5 === 0) {
                const estilo = i === 0 ? '' : 'transform:translateX(-50%);';
                marcas.push(`<div style="position:absolute;left:${i}mm;top:15px;${estilo}font-size:${i % 10 === 0 ? 9 : 8}px;line-height:10px;${i % 10 === 0 ? 'font-weight:bold;' : ''}">${i}</div>`);
            }
        }
        marcas.push(`<div style="position:absolute;left:${ancho}mm;top:0;height:14px;border-left:2px solid #000;"></div>`);
        marcas.push(`<div style="position:absolute;right:0;top:26px;font-size:9px;line-height:10px;font-weight:bold;white-space:nowrap;">▲ ${ancho} mm</div>`);
        return `<div style="position:relative;height:38px;width:${ancho}mm;border-bottom:1px solid #000;box-sizing:content-box;">${marcas.join('')}</div>`;
    }

    /**
     * Factura de prueba para comprobar cómo sale el ticket en la impresora:
     * - un marco con INICIO / FIN y el borde del papel dibujado a los lados;
     * - datos de la impresora y una línea a la izquierda, una centrada y una a
     *   la derecha (justo lo que se perdía con el papel mal elegido);
     * - una regla en centímetros;
     * - una factura de ejemplo con el encabezado real del negocio, productos
     *   de su catálogo (opciones.productos), un renglón con adición y el QR;
     * - opcionalmente una tira larga numerada (opciones.tiraLarga) para ver que
     *   un ticket largo sale de corrido, sin cortarse ni partirse.
     * opciones: { productos:[{nombre_producto,precio_unitario}], negocio, atendio, conQr, tiraLarga }
     */
    function construirPaginaDePrueba(nombreImpresora, forzado, opciones) {
        const o = opciones || {};
        const m = medidasTicket(nombreImpresora, forzado);
        const etiqueta = FORMATOS_TICKET[m.formato].etiqueta;
        const negocio = o.negocio || {};

        const productos = (o.productos && o.productos.length) ? o.productos : [{ nombre_producto: 'Producto de ejemplo', precio_unitario: 10000 }];
        let total = 0;
        const filas = productos.map((p, i) => {
            const cantidad = (i % 2) + 1;
            const valor = (parseFloat(p.precio_unitario) || 0) * cantidad;
            total += valor;
            let fila = `<div style="display:flex;justify-content:space-between;gap:6px;"><span>${cantidad} u. ${escaparHtml(p.nombre_producto)}</span><span style="white-space:nowrap;">${moneda(valor)}</span></div>`;
            if (i === 0) {
                total += 1500;
                fila += `<div style="margin-left:10px;">+ Adición de ejemplo (+${moneda(1500)})</div>`;
            }
            return fila;
        }).join('');

        const lineaDatos = [negocio.direccion, negocio.telefono, negocio.nit ? `NIT ${negocio.nit}` : null].filter(Boolean).map(escaparHtml).join(' · ');
        const encabezado = `
            <div style="text-align:center;margin-bottom:6px;">
                ${negocio.logoFactura ? `<img src="${escaparHtml(negocio.logoFactura)}" alt="" style="max-height:48px;max-width:80%;object-fit:contain;"><br>` : ''}
                <strong>${escaparHtml(negocio.nombre || 'Nombre del negocio')}</strong>
                ${lineaDatos ? `<br><small>${lineaDatos}</small>` : ''}
                ${negocio.lema ? `<br><small><em>${escaparHtml(negocio.lema)}</em></small>` : ''}
            </div>`;

        const qr = o.conQr ? generarImagenQrDataUrl('https://ejemplo.enesoft.local/qr', 140) : null;
        const tira = o.tiraLarga
            ? '<hr><div style="text-align:center;font-weight:bold;">TIRA LARGA (¿sale completa?)</div>'
                + Array.from({ length: 30 }, (_, i) => `<div>Línea ${String(i + 1).padStart(2, '0')} ${'█'.repeat(2 + (i % 10))}</div>`).join('')
            : '';

        return marcoDePrueba(`
            <div style="text-align:center;font-weight:bold;">PÁGINA DE PRUEBA</div>
            <div>Impresora: ${nombreImpresora ? escaparHtml(nombreImpresora) : '(diálogo del sistema)'}</div>
            <div>Papel: <strong>${etiqueta}</strong>${m.anchoMm ? ` (ancho ${m.anchoMm} mm)` : ''}</div>
            <div style="display:flex;justify-content:space-between;"><span>◄ izquierda</span><span>derecha ►</span></div>
            <div style="text-align:center;">— centro —</div>
            ${m.termica ? `<div class="small"><strong>Regla en milímetros</strong> arriba y abajo, con una línea punteada cada 10 mm a todo el largo. Mira hasta qué número llegan por la derecha: ese es el ancho real que imprime tu impresora. Cambia el ancho en la ventana (botones − / +) y vuelve a imprimir hasta que se vea el borde derecho y el FIN. Si falta el 0 de la izquierda, se recorta ese lado.</div>` : ''}
            <hr>
            ${encabezado}
            <div style="text-align:center;margin-bottom:6px;"><strong>Factura #PRUEBA</strong><br><small>${escaparHtml(new Date().toLocaleString('es-CO'))}</small></div>
            <div>Atendió: <strong>${escaparHtml(o.atendio || 'Cajero')}</strong></div>
            <div>Cliente: <strong>Cliente de ejemplo</strong></div>
            <hr>
            ${filas}
            <hr>
            <div style="display:flex;justify-content:space-between;font-weight:bold;"><span>TOTAL</span><span>${moneda(total)}</span></div>
            ${qr ? `<hr><div style="text-align:center;"><img src="${qr}" style="width:140px;height:140px;"><div class="small">Escanea para sumar visita (QR de ejemplo)</div></div>` : ''}
            ${tira}
            <div class="small" style="margin-top:6px;">Deben verse los dos bordes punteados, ambas flechas y el FIN. Si falta el borde derecho o "derecha ►", el papel elegido es más ancho que el rollo.</div>
        `, m);
    }

    /** Comanda de ejemplo (sin precios) con los mismos productos, dentro del marco de prueba. */
    function construirComandaDePrueba(nombreImpresora, forzado, opciones) {
        const o = opciones || {};
        const m = medidasTicket(nombreImpresora, forzado);
        const items = ((o.productos && o.productos.length) ? o.productos : [{ nombre_producto: 'Producto de ejemplo' }]).map((p, i) => ({
            cantidad: (i % 2) + 1,
            presentacion: 'unidad',
            nombre_producto: p.nombre_producto,
            modificadores: i === 0 ? [{ nombre: 'Adición de ejemplo' }] : [],
        }));
        return marcoDePrueba(construirComandaHTML({
            mesa: 'Mesa de prueba',
            vendedor: o.atendio || 'Cajero',
            cliente: 'Cliente de ejemplo',
            fecha: new Date().toLocaleString('es-CO'),
            items,
        }), m);
    }

    /**
     * Imprime un ticket sin diálogo del sistema operativo, directo a la
     * impresora configurada en Configuración. Se resuelve a
     * {ok:false, disponible:false} sin lanzar error cuando no hay impresora
     * configurada o la app no corre dentro de Electron (ej. probando en un
     * navegador normal) — en ese caso el llamador simplemente no hace nada
     * más, dejando el flujo manual (botón "Imprimir" con window.print())
     * como única forma de imprimir, igual que hoy.
     */
    /**
     * Qué impresora usa cada tipo de documento. Un negocio con UNA sola
     * impresora puede haberla elegido solo en "recibo" o solo en "cocina"
     * en Configuración — en ese caso el otro documento sale por la misma en
     * vez de quedarse sin impresora y caer al diálogo del sistema.
     * tipo: 'factura' | 'comanda'.
     */
    function impresoraPara(tipo) {
        const recibo = window.PB_IMPRESORA_RECIBO || null;
        const cocina = window.PB_IMPRESORA_COCINA || null;
        return tipo === 'comanda' ? (cocina || recibo) : (recibo || cocina);
    }

    // Huella barata del contenido para detectar el MISMO trabajo mandado dos
    // veces seguidas (doble clic, clic mientras el anterior aún se procesa).
    const ultimosTrabajosImpresion = {};
    function huellaTexto(texto) {
        let h = 0;
        for (let i = 0; i < texto.length; i++) h = (h * 31 + texto.charCodeAt(i)) | 0;
        return h;
    }

    /**
     * etiqueta ('factura' | 'comanda' | 'qr'…) solo sirve para el registro de
     * impresión (impresion.log en la carpeta de datos) y para descartar un
     * doble clic: el mismo documento a la misma impresora dentro de 3
     * segundos se ignora (devuelve ok con duplicado:true) en vez de salir
     * dos veces en papel.
     */
    function imprimirTicketSilencioso(htmlInterno, deviceName, etiqueta, forzado) {
        if (!deviceName || !window.electronAPI || !window.electronAPI.imprimirTicket) {
            return Promise.resolve({ ok: false, disponible: false });
        }
        const clave = `${etiqueta || 'ticket'}|${deviceName}|${medidasTicket(deviceName, forzado).formato}|${htmlInterno.length}|${huellaTexto(htmlInterno)}`;
        const ahora = Date.now();
        if (ultimosTrabajosImpresion[clave] && ahora - ultimosTrabajosImpresion[clave] < 3000) {
            return Promise.resolve({ ok: true, duplicado: true });
        }
        ultimosTrabajosImpresion[clave] = ahora;

        const html = envolverTicketParaImpresionSilenciosa(htmlInterno, deviceName, forzado);
        // Solo cuenta como "ya enviado" lo que la impresora aceptó: si falló, el reintento
        // inmediato debe poder salir (y no decir "ya se había enviado" sin que haya salido nada).
        return window.electronAPI.imprimirTicket({ html, deviceName, etiqueta: etiqueta || 'ticket' })
            .then((r) => {
                const resultado = r || { ok: false, error: 'Sin respuesta del proceso de impresión.' };
                if (!resultado.ok) delete ultimosTrabajosImpresion[clave];
                return resultado;
            })
            .catch((error) => {
                delete ultimosTrabajosImpresion[clave];
                return { ok: false, error: (error && error.message) || 'No se pudo comunicar con la impresora.' };
            });
    }

    /**
     * Impresión por el diálogo normal del sistema (cuando NO hay ninguna
     * impresora configurada) de UN documento concreto. Antes se imprimía lo
     * que estuviera visible: si había otra vista previa abierta detrás
     * (ej. la de la comanda), las dos salían encimadas en la misma hoja. Aquí
     * se arma un contenedor temporal y una clase en <body> que, en el CSS de
     * impresión, oculta cualquier otro .pb-print-area.
     */
    function imprimirConDialogo(htmlInterno, forzado) {
        document.querySelectorAll('#pb-impresion-temporal').forEach((el) => el.remove());
        const temporal = document.createElement('div');
        temporal.id = 'pb-impresion-temporal';
        temporal.className = 'pb-print-area';
        temporal.innerHTML = envolverTicketParaImpresionSilenciosa(htmlInterno, null, forzado);
        document.body.appendChild(temporal);
        document.body.classList.add('pb-imprimiendo-una');

        const limpiar = () => {
            document.body.classList.remove('pb-imprimiendo-una');
            temporal.remove();
            window.removeEventListener('afterprint', limpiar);
        };
        window.addEventListener('afterprint', limpiar);
        window.print();
        // Por si el navegador nunca dispara "afterprint".
        setTimeout(limpiar, 60000);
    }

    /** Lista de impresoras instaladas en este equipo — [] fuera de Electron. */
    function listarImpresoras() {
        if (!window.electronAPI || !window.electronAPI.listarImpresoras) {
            return Promise.resolve([]);
        }
        return window.electronAPI.listarImpresoras();
    }

    /**
     * Detalle imprimible de una factura ya cobrada — compartido por Facturas (su pantalla propia)
     * y por "Facturas recientes" de Ventas (para corregir/reimprimir sin salir de la venta en
     * curso), así un cambio acá no hay que repetirlo en los dos lugares. opciones.puedeAnular
     * controla el botón "Devolver" de cada línea (en Ventas no se ofrece: devolver es una acción
     * de más peso que se deja solo en la pantalla dedicada de Facturas).
     */
    function construirDetalleFacturaHtml(f, opciones) {
        const o = opciones || {};
        const puedeAnular = !!o.puedeAnular;
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

        let filas = '';
        f.productos.forEach(function (p) {
            const etiqueta = p.presentacion === 'peso' ? 'kg' : (p.presentacion === 'paquete' ? `paquete x${p.unidades_por_paquete}` : 'unidad');
            const ivaTexto = p.tasa_iva > 0 ? `, IVA ${p.tasa_iva}%: ${moneda(p.valor_iva)}` : '';
            const restante = p.cantidad - p.cantidad_devuelta;
            const devueltoTexto = p.cantidad_devuelta > 0 ? ` <span class="badge bg-secondary pb-print-hide">Devuelto: ${p.cantidad_devuelta}</span>` : '';
            const destinoTexto = p.anulacion_destino === 'descartado' ? ' <span class="badge bg-danger pb-print-hide">Descartado</span>' : (p.anulacion_destino === 'inventario' ? ' <span class="badge bg-success pb-print-hide">Repuesto al inventario</span>' : '');
            const accion = (puedeAnular && !f.anulada && restante > 0)
                ? `<button class="pb-btn-icon pb-btn-icon-warn pb-print-hide" onclick="devolverLinea(${p.id_movimiento}, ${restante})" title="Devolver"><i class="bi bi-arrow-return-left"></i></button>`
                : '';
            // Cada adición en su propio renglón: cantidad, valor y total. El renglón del producto muestra
            // su precio base, así lo que se suma hacia abajo es exactamente el total de la línea.
            const filasModificadores = (p.modificadores || []).map(m => `<tr>
                <td class="ps-3">+ ${escaparHtml(m.nombre_snapshot)}</td>
                <td class="text-center text-nowrap">${p.cantidad}</td>
                ${m.incluido
                    ? '<td class="text-end text-nowrap" colspan="2">Incluido</td>'
                    : `<td class="text-end text-nowrap">${moneda(m.precio_cobrado)}</td><td class="text-end text-nowrap">${moneda(m.precio_cobrado * p.cantidad)}</td>`}
                <td></td></tr>`).join('');
            const totalMostrado = (p.modificadores || []).length > 0 ? p.precio_unitario * p.cantidad : p.total;
            const descuentoHtml = p.descuento_linea > 0
                ? `<br><small class="text-muted">Descuento: -${moneda(p.descuento_linea)}</small>`
                : '';
            filas += `<tr><td>${p.nombre_producto} <small class="text-muted">(${etiqueta}${ivaTexto})</small>${p.profesional ? `<br><small class="text-muted">Profesional: ${escaparHtml(p.profesional)}</small>` : ''}${devueltoTexto}${destinoTexto}${descuentoHtml}</td><td class="text-center text-nowrap">${p.cantidad}</td><td class="text-end text-nowrap">${moneda(p.precio_unitario)}</td><td class="text-end text-nowrap">${moneda(totalMostrado)}</td><td class="text-center">${accion}</td></tr>${filasModificadores}`;
        });
        const ajusteFila = f.ajuste
            ? `<tr><td colspan="3" class="text-end">${f.ajuste < 0 ? 'Descuento' : 'Recargo'}</td><td class="text-end">${moneda(f.ajuste)}</td><td></td></tr>`
            : '';
        const propinaFila = f.propina > 0
            ? `<tr><td colspan="3" class="text-end">Propina</td><td class="text-end">${moneda(f.propina)}</td><td></td></tr>`
            : '';
        const etiquetasPago = { efectivo: 'Efectivo', tarjeta: 'Tarjeta', transferencia: 'Transferencia', credito: 'Crédito' };
        const pagosHtml = (f.pagos || []).length > 1
            ? '<p class="mb-1"><strong>Pagos:</strong> ' + f.pagos.map(p => `${etiquetasPago[p.metodo_pago] || p.metodo_pago}: ${moneda(p.valor)}`).join(' · ') + '</p>'
            : '';
        const clienteHtml = `<p class="mb-1">Cliente: <strong>${f.cliente ? escaparHtml(f.cliente) : 'No aplica'}</strong></p>`;
        const edicionesHtml = (f.ediciones || []).length > 0
            ? `<div class="pb-print-hide small text-muted mb-2">
                <strong>Historial de ediciones:</strong>
                <ul class="mb-0 ps-3">
                    ${f.ediciones.map(e => `<li>${e.fecha_hora} — ${e.usuario || '—'}: ajuste ${moneda(e.ajuste_anterior)} → ${moneda(e.ajuste_nuevo)} (${e.motivo})</li>`).join('')}
                </ul>
            </div>`
            : '';
        let qrFooterHtml = '';
        if (f.fidelizacion_qr_url && window.QRCode) {
            const imagenQr = generarImagenQrDataUrl(f.fidelizacion_qr_url, 140);
            if (imagenQr) {
                qrFooterHtml = `
                    <hr>
                    <div class="text-center">
                        <img src="${imagenQr}" style="width:140px;height:140px;">
                        <div class="small">Escanea para sumar visita</div>
                    </div>
                `;
            }
        }
        const numeroFactura = f.numero_factura || f.id_factura;
        const html = `
            ${encabezadoHtml}
            <div class="text-center mb-2"><strong>Factura #${numeroFactura}</strong><br><small>${f.fecha} ${f.hora}</small></div>
            <p class="mb-1">Atendió: <strong>${f.vendedor || '—'}</strong></p>
            ${clienteHtml}
            ${f.anulada ? `<div class="alert alert-secondary py-2 pb-print-hide">Venta anulada. Motivo: ${f.anulada_motivo || '—'}</div>` : ''}
            ${pagosHtml}
            <table class="table table-sm table-bordered">
                <thead><tr><th>Producto</th><th class="text-center text-nowrap">Cant.</th><th class="text-end text-nowrap">Precio</th><th class="text-end text-nowrap">Total</th><th class="pb-print-hide"></th></tr></thead>
                <tbody>${filas}</tbody>
                <tfoot>
                    ${(f.ajuste || f.propina > 0) ? `<tr><td colspan="3" class="text-end">Subtotal</td><td class="text-end">${moneda(f.subtotal)}</td><td></td></tr>` : ''}
                    ${ajusteFila}
                    ${propinaFila}
                    <tr><td colspan="3" class="text-end"><strong>Total</strong></td><td class="text-end"><strong>${moneda(f.total_venta)}</strong></td><td></td></tr>
                </tfoot>
            </table>
            ${edicionesHtml}
            ${qrFooterHtml}
        `;
        return { titulo: `Factura #${numeroFactura}`, html };
    }

    /**
     * Ticket de cocina: solo cantidades y productos, sin precios ni totales
     * (quien prepara el pedido no necesita ver dinero). Usa estilos en
     * línea en vez de utilidades de Bootstrap porque va destinado a
     * imprimirse tal cual, con letra grande para leerse rápido en cocina.
     */
    function construirComandaHTML({ mesa, vendedor, fecha, items, cliente }) {
        let filas = '';
        (items || []).forEach(function (item) {
            const etiqueta = item.presentacion === 'peso' ? 'kg' : (item.presentacion === 'paquete' ? `paquete x${item.unidades_por_paquete || 1}` : 'u.');
            filas += `<div style="font-size:1.15em;font-weight:bold;margin:8px 0 2px;">${item.cantidad} ${etiqueta} — ${item.nombre_producto}</div>`;
            // Todos los modificadores (gratis o cobrados) — cocina necesita
            // saber qué preparar sin importar si ese topping se cobró o no.
            // En negrita y negro puro: en una térmica el trazo fino (peso
            // normal) sale clarito y cocina no alcanza a leerlo.
            (item.modificadores || []).forEach(function (mod) {
                filas += `<div style="font-size:1.05em;font-weight:bold;margin-left:14px;color:#000;">+ ${mod.nombre}</div>`;
            });
        });
        return `
            <div style="color:#000;">
                <div style="text-align:center;font-weight:bold;font-size:1.3em;">*** COMANDA ***</div>
                <hr>
                ${mesa ? `<div><strong>Mesa: ${mesa}</strong></div>` : ''}
                <div>Hora: ${fecha}</div>
                <div>Atiende: ${escaparHtml(vendedor)}</div>
                <div>Cliente: <strong>${cliente ? escaparHtml(cliente) : 'No aplica'}</strong></div>
                <hr>
                ${filas || '<div>(sin productos)</div>'}
            </div>
        `;
    }

    /**
     * QR de fidelización de un solo uso — requiere haber incluido
     * parciales/modal_qr_fidelizacion.php en la página (trae el modal y
     * qrcode.min.js). Se usa igual desde Ventas (al cobrar) y desde la
     * pantalla de Fidelización (para imprimir uno suelto, ej. para dejarlo
     * pegado en el mostrador).
     */
    let ultimoQrFidelizacionUrl = null;

    /**
     * Convierte una URL en una imagen PNG (data: URL) usando qrcodejs, sin
     * dejar nada visible en pantalla — requiere que qrcode.min.js ya esté
     * cargado (ver parciales/modal_qr_fidelizacion.php). A diferencia de
     * pintar el QR directo en un <canvas> del documento, esto sirve para
     * insertarlo como <img> dentro de un ticket que se va a imprimir: un
     * <canvas> nunca serializa sus píxeles vía innerHTML, una <img> sí.
     */
    function generarImagenQrDataUrl(texto, tamano) {
        // Si la página no cargó la librería del QR, se devuelve null (quien llama lo trata como "sin QR") en vez de romper.
        if (typeof QRCode === 'undefined') return null;
        const contenedor = document.createElement('div');
        contenedor.style.cssText = 'position:absolute;left:-9999px;top:-9999px;';
        document.body.appendChild(contenedor);
        new QRCode(contenedor, { text: texto, width: tamano || 160, height: tamano || 160 });
        const canvas = contenedor.querySelector('canvas');
        const dataUrl = canvas ? canvas.toDataURL('image/png') : null;
        document.body.removeChild(contenedor);
        return dataUrl;
    }

    // ---------- Etiquetas de código de barras (ver vista/registro) ----------
    // Mismo truco que generarImagenQrDataUrl: un <canvas> dibujado fuera de pantalla, convertido a
    // data URL — un <canvas> no serializa sus píxeles al pasar por innerHTML (ventana oculta de
    // impresión silenciosa), así que hay que convertirlo a <img> ANTES de armar el HTML a imprimir.
    // CODE128 (no EAN-13) porque el campo "código" del producto es texto libre sin validar — EAN-13
    // exige exactamente 13 dígitos con dígito de verificación, y hoy nada garantiza eso.
    function generarImagenCodigoBarrasDataUrl(valor, opciones) {
        if (typeof JsBarcode === 'undefined' || !valor) return null;
        const canvas = document.createElement('canvas');
        try {
            JsBarcode(canvas, valor, Object.assign({
                format: 'CODE128',
                displayValue: true,
                margin: 4,
                height: 45,
                fontSize: 13,
            }, opciones || {}));
        } catch (e) {
            return null; // valor no representable (ej. caracteres que CODE128 no soporta)
        }
        return canvas.toDataURL('image/png');
    }

    /** Ancho/alto configurados en Configuración (ver migración 052) — 50×30mm si no se ha puesto nada. */
    function medidasEtiqueta() {
        return {
            anchoMm: window.PB_ETIQUETA_ANCHO_MM || 50,
            altoMm: window.PB_ETIQUETA_ALTO_MM || 30,
        };
    }

    /**
     * A diferencia del ticket (ancho fijo, alto "auto" — una tira continua que crece con el
     * contenido), una etiqueta térmica tiene ancho Y alto fijos (es una pieza prediseñada, no un
     * rollo que se corta donde termine el contenido) — por eso NO reutiliza
     * envolverTicketParaImpresionSilenciosa/medidasTicket, que asumen lo primero.
     */
    function envolverEtiquetaParaImpresionSilenciosa(htmlInterno) {
        const m = medidasEtiqueta();
        return `<style>@page { size: ${m.anchoMm}mm ${m.altoMm}mm; margin: 0; }</style>` +
            `<div class="pb-etiqueta pb-print-area" style="width:${m.anchoMm}mm;height:${m.altoMm}mm;">${htmlInterno}</div>`;
    }

    /** Igual que imprimirTicketSilencioso pero para una etiqueta (ver envolverEtiquetaParaImpresionSilenciosa). */
    function imprimirEtiquetaSilenciosa(htmlInterno, deviceName) {
        if (!deviceName || !window.electronAPI || !window.electronAPI.imprimirTicket) {
            return Promise.resolve({ ok: false, disponible: false });
        }
        const html = envolverEtiquetaParaImpresionSilenciosa(htmlInterno);
        return window.electronAPI.imprimirTicket({ html, deviceName, etiqueta: 'etiqueta_producto' })
            .catch((error) => ({ ok: false, error: (error && error.message) || 'No se pudo comunicar con la impresora.' }));
    }

    /** Igual que imprimirConDialogo pero para una etiqueta. */
    function imprimirEtiquetaConDialogo(htmlInterno) {
        document.querySelectorAll('#pb-impresion-temporal').forEach((el) => el.remove());
        const temporal = document.createElement('div');
        temporal.id = 'pb-impresion-temporal';
        temporal.className = 'pb-print-area';
        temporal.innerHTML = envolverEtiquetaParaImpresionSilenciosa(htmlInterno);
        document.body.appendChild(temporal);
        document.body.classList.add('pb-imprimiendo-una');

        const limpiar = () => {
            document.body.classList.remove('pb-imprimiendo-una');
            temporal.remove();
            window.removeEventListener('afterprint', limpiar);
        };
        window.addEventListener('afterprint', limpiar);
        window.print();
        setTimeout(limpiar, 60000);
    }

    /** HTML de una etiqueta: nombre, precio y el código de barras (con el número debajo, dibujado por JsBarcode). */
    function construirEtiquetaHtml(producto) {
        const imagen = generarImagenCodigoBarrasDataUrl(producto.codigo);
        if (!imagen) return null;
        return `
            <div style="width:100%;height:100%;box-sizing:border-box;padding:2mm;display:flex;flex-direction:column;align-items:center;justify-content:center;text-align:center;overflow:hidden;">
                <div style="font-size:9px;font-weight:bold;line-height:1.1;max-height:2.2em;overflow:hidden;">${escaparHtml(producto.nombre_producto)}</div>
                <div style="font-size:11px;font-weight:bold;margin:1px 0;">${moneda(producto.precio_unitario)}</div>
                <img src="${imagen}" style="max-width:100%;max-height:60%;">
            </div>`;
    }

    /**
     * getOrCreateInstance (no "new Modal(...)" suelto) — generarQrFidelizacion()
     * abre el modal de una vez para mostrar "Generando…" y esta función lo
     * vuelve a "mostrar" cuando ya hay QR para pintar; con "new Modal().show()"
     * cada vez, esa segunda llamada creaba una SEGUNDA instancia con su
     * propio fondo oscuro — al cerrar solo se quitaba el de la instancia más
     * reciente, y el primer fondo quedaba huérfano bloqueando toda la app.
     * getOrCreateInstance() reutiliza la misma instancia y show() sobre un
     * modal ya abierto no hace nada, así nunca se duplica el fondo.
     */
    function modalQrFidelizacion() {
        return bootstrap.Modal.getOrCreateInstance('#modalQrFidelizacion');
    }

    /** Pinta cualquier URL como QR en el modal compartido — usado tanto para el QR de un solo uso como para el enlace fijo del negocio (que no expira). */
    function mostrarQrUrl(url, mensajeEstado) {
        ultimoQrFidelizacionUrl = url;
        $('#qr_fidelizacion_contenedor').empty();
        modalQrFidelizacion().show();
        new QRCode(document.getElementById('qr_fidelizacion_contenedor'), { text: url, width: 220, height: 220 });
        $('#qr_fidelizacion_estado').text(mensajeEstado || '');
        $('#btnImprimirQrFidelizacion').prop('disabled', false);
    }

    function generarQrFidelizacion() {
        ultimoQrFidelizacionUrl = null;
        $('#qr_fidelizacion_contenedor').empty();
        $('#btnImprimirQrFidelizacion').prop('disabled', true);
        modalQrFidelizacion().show();

        // navigator.onLine solo mira el adaptador de red (puede dar falso
        // positivo con wifi conectado pero sin salida a internet real), así
        // que no reemplaza el intento real — pero cuando SÍ dice que no hay
        // red, es una señal confiable: evita el viaje redondo inútil y el
        // texto crudo del error de cURL, mostrando de una el aviso claro.
        if (!navigator.onLine) {
            $('#qr_fidelizacion_estado').text('Sin conexión a internet — este código no se puede generar sin conexión.');
            return;
        }

        $('#qr_fidelizacion_estado').text('Generando…');

        $.ajax({
            type: 'POST',
            url: '/controlador/fidelizacion_generar_qr.php',
            data: { csrf_token: csrfToken() },
            dataType: 'json',
            success: function (r) {
                if (r.ok && r.url) {
                    mostrarQrUrl(r.url, r.expira_en ? `Válido hasta ${r.expira_en} — se usa una sola vez.` : 'El código se usa una sola vez.');
                } else {
                    $('#qr_fidelizacion_estado').text(r.error || 'No se pudo generar el código.');
                }
            },
            error: function () {
                $('#qr_fidelizacion_estado').text('Sin conexión con el servidor de fidelización.');
            }
        });
    }

    /**
     * El <canvas> del QR no se puede mandar tal cual a la impresora
     * silenciosa (ver imprimirTicketSilencioso) ni reinsertar en otro sitio
     * del DOM: .innerHTML de un canvas nunca serializa los píxeles
     * dibujados, solo la etiqueta vacía. Por eso se convierte primero a
     * imagen (toDataURL) y se imprime como <img>, en ambos caminos.
     */
    function imprimirQrFidelizacion() {
        if (!ultimoQrFidelizacionUrl) return;
        const canvas = document.querySelector('#qr_fidelizacion_contenedor canvas');
        const imagenQr = canvas ? canvas.toDataURL('image/png') : null;
        if (!imagenQr) {
            toast('No se pudo preparar el código para imprimir.', 'error');
            return;
        }

        const negocio = window.PB_NEGOCIO || {};
        const htmlQr = `
            <div style="text-align:center;">
                ${negocio.nombre ? `<strong>${negocio.nombre}</strong><br>` : ''}
                <strong style="font-size:1.1em;">Escanea para sumar visita</strong>
                <div style="margin:10px 0;"><img src="${imagenQr}" style="width:180px;height:180px;"></div>
                <div style="font-size:0.85em;">Código de un solo uso.</div>
            </div>
        `;

        const impresora = window.PB_IMPRESORA_RECIBO || window.PB_IMPRESORA_COCINA;
        if (impresora) {
            imprimirTicketSilencioso(htmlQr, impresora, 'qr').then(function (r) {
                if (r && r.disponible !== false && !r.ok) {
                    toast('No se pudo imprimir: ' + (r.error || 'error desconocido'), 'error');
                }
            });
            return;
        }

        // Sin impresora configurada: diálogo normal del sistema, imprimiendo
        // solo este documento (sin tocar ni mezclarse con el ticket visible).
        imprimirConDialogo(htmlQr);
    }

    /**
     * Gráfico de barras simple en SVG puro — sin librería externa (nada de
     * CDN ni un paso de build, mismo criterio que el resto del proyecto).
     * datos: [{etiqueta, valor}, ...]. opciones.formatearValor(valor) da el
     * texto del tooltip nativo (<title>) de cada barra.
     */
    function graficoBarras(idContenedor, datos, opciones) {
        opciones = opciones || {};
        const $contenedor = $('#' + idContenedor).empty();
        if (!datos || datos.length === 0 || datos.every(d => !d.valor)) {
            $contenedor.append('<p class="text-muted small mb-0">Sin datos para mostrar en este período.</p>');
            return;
        }

        const alto = opciones.alto || 160;
        const anchoBarra = opciones.anchoBarra || 28;
        const espacio = 10;
        const anchoTotal = datos.length * (anchoBarra + espacio) + espacio;
        const maxValor = Math.max(...datos.map(d => d.valor), 1);
        const color = opciones.color || 'var(--pb-primary)';
        const formatear = opciones.formatearValor || (v => v);

        let barras = '';
        datos.forEach(function (d, i) {
            const alturaBarra = Math.max(2, (d.valor / maxValor) * (alto - 30));
            const x = espacio + i * (anchoBarra + espacio);
            const y = (alto - 20) - alturaBarra;
            const etiquetaCorta = d.etiquetaCorta || d.etiqueta;
            barras += `
                <rect x="${x}" y="${y}" width="${anchoBarra}" height="${alturaBarra}" style="fill:${color};" rx="3">
                    <title>${d.etiqueta}: ${formatear(d.valor)}</title>
                </rect>
                <text x="${x + anchoBarra / 2}" y="${alto - 6}" text-anchor="middle" font-size="10" fill="#6b7280">${etiquetaCorta}</text>
            `;
        });

        $contenedor.html(
            `<svg viewBox="0 0 ${anchoTotal} ${alto}" style="width:100%;height:${alto}px;" preserveAspectRatio="xMinYMid meet">${barras}</svg>`
        );
    }

    /**
     * Reemplaza confirm()/prompt() nativos — en Electron, un diálogo nativo
     * abierto a mitad de un evento de clic puede dejar el "gesto" de clic
     * a medio procesar, y el siguiente clic en la página se pierde hasta
     * que algo lo destraba. Con un modal propio (Bootstrap, no nativo)
     * esto no pasa. Ambas funciones devuelven una Promise, así que hay
     * que usarlas con await en vez de llamarlas directo.
     */
    function modalPregunta() {
        let $modal = $('#pb-modal-pregunta');
        if ($modal.length) return $modal;

        $('body').append(`
            <div class="modal fade" id="pb-modal-pregunta" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered modal-sm">
                    <div class="modal-content">
                        <div class="modal-body">
                            <p class="pb-modal-pregunta-mensaje mb-2"></p>
                            <input type="text" class="form-control pb-modal-pregunta-input" style="display:none;">
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary btn-sm pb-modal-pregunta-cancelar" data-bs-dismiss="modal">Cancelar</button>
                            <button type="button" class="btn btn-primary btn-sm pb-modal-pregunta-aceptar">Aceptar</button>
                        </div>
                    </div>
                </div>
            </div>
        `);
        return $('#pb-modal-pregunta');
    }

    function preguntar(mensaje, valorInicial, tipoInput) {
        return new Promise((resolve) => {
            const $modal = modalPregunta();
            $modal.find('.pb-modal-pregunta-mensaje').text(mensaje);
            const $input = $modal.find('.pb-modal-pregunta-input')
                .attr('type', tipoInput || 'text')
                .val(valorInicial !== undefined && valorInicial !== null ? valorInicial : '')
                .show();

            let resuelto = false;
            const bsModal = new bootstrap.Modal($modal[0]);

            const aceptar = () => {
                resuelto = true;
                resolve($input.val());
                bsModal.hide();
            };

            $modal.find('.pb-modal-pregunta-aceptar').off('click').on('click', aceptar);
            // El input no vive dentro de un <form>, así que Enter no dispara
            // nada por su cuenta — sin esto, la única forma de aceptar era
            // hacer clic con el mouse, algo que en pantalla táctil o con
            // teclado no es lo esperable.
            $input.off('keydown').on('keydown', (e) => {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    aceptar();
                }
            });
            $modal.off('hidden.bs.modal').one('hidden.bs.modal', () => {
                if (!resuelto) resolve(null);
            });
            $modal.off('shown.bs.modal').one('shown.bs.modal', () => $input.trigger('focus').trigger('select'));

            bsModal.show();
        });
    }

    function confirmar(mensaje) {
        return new Promise((resolve) => {
            const $modal = modalPregunta();
            $modal.find('.pb-modal-pregunta-mensaje').text(mensaje);
            $modal.find('.pb-modal-pregunta-input').hide();

            let resuelto = false;
            const bsModal = new bootstrap.Modal($modal[0]);

            $modal.find('.pb-modal-pregunta-aceptar').off('click').on('click', () => {
                resuelto = true;
                resolve(true);
                bsModal.hide();
            });
            $modal.off('hidden.bs.modal').one('hidden.bs.modal', () => {
                if (!resuelto) resolve(false);
            });

            bsModal.show();
        });
    }

    /**
     * Buscador con lista de resultados navegable por teclado (flechas +
     * Enter/espacio), además de clic/toque — mismo patrón ya probado en el
     * buscador de productos de Ventas. Ojo: el resaltado al pasar el mouse
     * cambia una CLASE, nunca vuelve a dibujar el <li> — volver a dibujarlo
     * justo debajo del cursor a mitad de un clic (o un toque, en pantalla
     * táctil) hace que el clic se pierda porque el elemento original ya no
     * existe cuando el navegador intenta completarlo.
     *
     * opciones:
     *   inputSelector, resultadosSelector — el <input> y el <ul>/<ol> destino
     *   renderItem(item, indice, resaltado) — debe devolver el HTML del <li>,
     *     con data-indice="${indice}" en la raíz
     *   onSeleccionar(item) — qué hacer al elegir uno
     *   textoSinResultados — opcional, se muestra si la búsqueda no encontró nada
     *
     * Devuelve { establecerResultados(lista), limpiar() }.
     */
    function buscadorConTeclado({ inputSelector, resultadosSelector, renderItem, onSeleccionar, textoSinResultados }) {
        let resultados = [];
        let indiceResaltado = -1;

        function render() {
            const $resultados = $(resultadosSelector).empty();
            if (resultados.length === 0) {
                if (textoSinResultados && $(inputSelector).val().trim().length > 0) {
                    $resultados.append(`<li class="list-group-item text-muted">${textoSinResultados}</li>`);
                }
                return;
            }
            resultados.forEach((item, indice) => {
                $resultados.append(renderItem(item, indice, indice === indiceResaltado));
            });
            $resultados.find('li[data-indice]').on('mouseenter', function () {
                indiceResaltado = parseInt($(this).data('indice'), 10);
                $resultados.find('li[data-indice]').removeClass('pb-resultado-activo');
                $(this).addClass('pb-resultado-activo');
            }).on('click', function () {
                seleccionar(parseInt($(this).data('indice'), 10));
            });
        }

        function seleccionar(indice) {
            const item = resultados[indice];
            if (!item) return;
            resultados = [];
            indiceResaltado = -1;
            onSeleccionar(item);
            render();
        }

        function establecerResultados(nuevos) {
            resultados = nuevos;
            indiceResaltado = nuevos.length > 0 ? 0 : -1;
            render();
        }

        $(inputSelector).on('keydown', function (e) {
            if (e.key === 'ArrowDown') {
                if (resultados.length === 0) return;
                e.preventDefault();
                indiceResaltado = Math.min(indiceResaltado + 1, resultados.length - 1);
                render();
            } else if (e.key === 'ArrowUp') {
                if (resultados.length === 0) return;
                e.preventDefault();
                indiceResaltado = Math.max(indiceResaltado - 1, 0);
                render();
            } else if (e.key === 'Enter' || e.key === ' ') {
                if (resultados.length === 0 || indiceResaltado < 0) return;
                e.preventDefault();
                seleccionar(indiceResaltado);
            }
        });

        return { establecerResultados, limpiar: () => establecerResultados([]) };
    }

    /**
     * Aviso fijo en pantalla cuando main.js manda una actualización crítica
     * forzada por el proveedor (ver preload.js/electronAPI). Mientras esté
     * activo, el resto de las pantallas (Ventas) revisan
     * window.PB_ACTUALIZACION_FORZADA antes de dejar iniciar una venta o
     * mesa NUEVA — lo que ya estaba en curso sigue funcionando normal.
     *
     * `estado` puede ser: false/null (nada pendiente), true (pendiente, sin
     * más detalle todavía), o { instalando: true, segundos } en la cuenta
     * regresiva final antes de reiniciar.
     */
    let ultimoEstadoForzado = false;
    let ultimoDetalleForzado = '';

    function mostrarBannerActualizacionForzada(estado) {
        ultimoEstadoForzado = estado;
        let el = document.getElementById('pb-banner-actualizacion-forzada');
        if (!estado) {
            if (el) el.remove();
            return;
        }
        if (!el) {
            el = document.createElement('div');
            el.id = 'pb-banner-actualizacion-forzada';
            el.className = 'pb-banner-actualizacion-forzada';
            document.body.prepend(el);
        }
        if (estado && typeof estado === 'object' && estado.instalando) {
            el.innerHTML = `<i class="bi bi-arrow-repeat"></i> Instalando la actualización crítica — EneSoft POS se reiniciará en ${estado.segundos}…`;
            return;
        }
        const detalle = ultimoDetalleForzado ? ` (${ultimoDetalleForzado})` : '';
        el.innerHTML = '<i class="bi bi-exclamation-triangle-fill"></i> Hay una actualización crítica pendiente. '
            + 'Puedes terminar con normalidad lo que tienes abierto — no se podrá iniciar una venta o mesa nueva hasta que se instale.'
            + detalle;
    }

    function registrarEscuchaActualizacionForzada() {
        if (!window.electronAPI) return;
        if (window.electronAPI.onActualizacionForzadaPendiente) {
            window.electronAPI.onActualizacionForzadaPendiente((estado) => {
                window.PB_ACTUALIZACION_FORZADA = !!estado;
                mostrarBannerActualizacionForzada(estado);
            });
        }
        // Estado inicial al cargar esta pantalla — sin esto, navegar a otra
        // página mientras la actualización forzada sigue "esperando" hacía
        // que la franja roja desapareciera hasta el próximo evento.
        if (window.electronAPI.consultarActualizacionForzada) {
            window.electronAPI.consultarActualizacionForzada().then((pendiente) => {
                if (pendiente) {
                    window.PB_ACTUALIZACION_FORZADA = true;
                    mostrarBannerActualizacionForzada(true);
                }
            }).catch(() => {});
        }
        // Mientras la franja roja esté activa, cada evento de progreso
        // (buscando/descargando %/etc — el mismo que alimenta el recuadro
        // de Configuración) también actualiza el texto de la franja, para
        // que el paso a paso se vea sin importar en qué pantalla estés.
        if (window.electronAPI.onEventoActualizacion) {
            window.electronAPI.onEventoActualizacion((evento) => {
                ultimoDetalleForzado = evento.mensaje;
                if (ultimoEstadoForzado === true) {
                    mostrarBannerActualizacionForzada(true);
                }
            });
        }
    }
    registrarEscuchaActualizacionForzada();

    async function elegirActualizacionManual() {
        if (!window.electronAPI || !window.electronAPI.elegirActualizacionManual) {
            toast('La actualización manual solo está disponible en la app de escritorio.', 'error');
            return;
        }
        const resultado = await window.electronAPI.elegirActualizacionManual();
        if (!resultado.ok && !resultado.cancelado) {
            toast(resultado.error || 'No se pudo iniciar el instalador.', 'error');
        }
    }

    async function restaurarBackupDesdeArchivo() {
        if (!window.electronAPI || !window.electronAPI.restaurarBackupDesdeArchivo) {
            toast('Restaurar un respaldo solo está disponible en la app de escritorio.', 'error');
            return;
        }
        const resultado = await window.electronAPI.restaurarBackupDesdeArchivo();
        if (resultado.ok) {
            toast('Base de datos restaurada correctamente.', 'success');
        } else if (!resultado.cancelado) {
            toast(resultado.error || 'No se pudo restaurar el respaldo.', 'error');
        }
    }

    /**
     * Badge en "Configuración" del sidebar (visible en cualquier pantalla,
     * no solo dentro de Configuración) cuando electron-updater detectó una
     * versión nueva. A diferencia del banner de actualización forzada, esta
     * NO se descarga sola — el admin decide desde Configuración.
     */
    let estadoActualizacionActual = { disponible: false };

    function actualizarBadgeSidebar(estado) {
        estadoActualizacionActual = estado || { disponible: false };
        const badge = document.getElementById('pb-badge-actualizacion');
        if (!badge) return;
        badge.classList.toggle('d-none', !estadoActualizacionActual.disponible);
        if (estadoActualizacionActual.disponible) {
            badge.title = 'Hay una actualización disponible (' + (estadoActualizacionActual.version || '') + ')';
        }
        document.dispatchEvent(new CustomEvent('pb:estado-actualizacion', { detail: estadoActualizacionActual }));
    }

    function registrarEscuchaEstadoActualizacion() {
        if (!window.electronAPI) return;
        if (window.electronAPI.consultarEstadoActualizacion) {
            window.electronAPI.consultarEstadoActualizacion().then(actualizarBadgeSidebar).catch(() => {});
        }
        if (window.electronAPI.onEstadoActualizacion) {
            window.electronAPI.onEstadoActualizacion(actualizarBadgeSidebar);
        }
    }
    registrarEscuchaEstadoActualizacion();

    /**
     * Número rojo junto a "Vencimientos" en el menú — visible desde cualquier pantalla, para que
     * un lote por vencer no dependa de que alguien se acuerde de entrar al Panel a revisar. El
     * elemento solo existe en el DOM si ese enlace del menú ya es visible (módulo activo + permiso),
     * así que no hace falta repetir esa misma comprobación aquí.
     */
    function actualizarBadgeVencimientos() {
        const badge = document.getElementById('pb-badge-vencimientos');
        if (!badge) return;
        $.getJSON('/controlador/contar_vencimientos_alerta.php', function (r) {
            const cantidad = r.cantidad || 0;
            badge.textContent = cantidad;
            badge.classList.toggle('d-none', cantidad === 0);
        }).fail(() => {});
    }
    actualizarBadgeVencimientos();

    async function iniciarActualizacion() {
        if (!window.electronAPI || !window.electronAPI.iniciarActualizacion) {
            toast('Esto solo está disponible en la app de escritorio.', 'error');
            return;
        }
        await window.electronAPI.iniciarActualizacion();
        toast('Buscando la actualización — puede tardar un momento en descargarse.', 'info');
    }

    async function rechazarActualizacion() {
        if (!window.electronAPI || !window.electronAPI.rechazarActualizacion) return;
        await window.electronAPI.rechazarActualizacion();
        toast('Actualización rechazada por ahora — se te avisará de nuevo si sale una versión más nueva.', 'info');
    }

    return {
        success: (m) => toast(m, 'success'),
        error: (m) => toast(m, 'error'),
        info: (m) => toast(m, 'info'),
        debounce,
        moneda,
        csrfToken,
        toggleSidebar,
        formatearClaveLicencia,
        autoformatearClaveLicencia,
        inicializarAyudas,
        buscadorConTeclado,
        prepararTicketParaImprimir,
        formatoEfectivo,
        esImpresoraTermica,
        construirPaginaDePrueba,
        construirComandaDePrueba,
        FORMATOS_TICKET,
        imprimirTicketSilencioso,
        imprimirConDialogo,
        impresoraPara,
        listarImpresoras,
        construirComandaHTML,
        construirDetalleFacturaHtml,
        escaparHtml,
        generarQrFidelizacion,
        mostrarQrUrl,
        imprimirQrFidelizacion,
        generarImagenQrDataUrl,
        generarImagenCodigoBarrasDataUrl,
        construirEtiquetaHtml,
        imprimirEtiquetaSilenciosa,
        imprimirEtiquetaConDialogo,
        graficoBarras,
        pill,
        confirmar,
        preguntar,
        elegirActualizacionManual,
        restaurarBackupDesdeArchivo,
        iniciarActualizacion,
        rechazarActualizacion,
        estadoActualizacion: () => estadoActualizacionActual,
    };
})();
