<?php
// Variables esperadas antes de incluir este archivo: $tituloPagina (string)
require_once __DIR__ . '/../../modelo/Configuracion.php';
$basePath = '';
$configNegocio = Configuracion::obtener();
$nombreNegocio = $configNegocio['nombre_negocio'];
$colorPrimario = $configNegocio['color_primario'];
$colorPrimarioClaro = Configuracion::ajustarColor($colorPrimario, 0.9);
$colorPrimarioOscuro = Configuracion::ajustarColor($colorPrimario, -0.15);
$colorSecundario = $configNegocio['color_secundario'] ?? '#f97316';
$colorSecundarioOscuro = Configuracion::ajustarColor($colorSecundario, -0.15);
$csrfToken = Csrf::token();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
    <title><?php echo htmlspecialchars($tituloPagina ?? $nombreNegocio, ENT_QUOTES, 'UTF-8'); ?> · <?php echo htmlspecialchars($nombreNegocio, ENT_QUOTES, 'UTF-8'); ?> · <?php echo htmlspecialchars(POS_PRODUCT_NAME, ENT_QUOTES, 'UTF-8'); ?></title>
    <link rel="stylesheet" href="<?php echo $basePath; ?>/assets/vendor/bootstrap/css/bootstrap.min.css">
    <link rel="stylesheet" href="<?php echo $basePath; ?>/assets/vendor/icons/bootstrap-icons.css">
    <link rel="stylesheet" href="<?php echo $basePath; ?>/assets/css/app.css<?php echo '?v=' . @filemtime(__DIR__ . '/../../assets/css/app.css'); ?>">
    <style>
        :root {
            --pb-primary: <?php echo htmlspecialchars($colorPrimario, ENT_QUOTES, 'UTF-8'); ?>;
            --pb-primary-dark: <?php echo htmlspecialchars($colorPrimarioOscuro, ENT_QUOTES, 'UTF-8'); ?>;
            --pb-primary-light: <?php echo htmlspecialchars($colorPrimarioClaro, ENT_QUOTES, 'UTF-8'); ?>;
            --pb-sidebar-active: <?php echo htmlspecialchars($colorPrimario, ENT_QUOTES, 'UTF-8'); ?>;
            --pb-secondary: <?php echo htmlspecialchars($colorSecundario, ENT_QUOTES, 'UTF-8'); ?>;
            --pb-secondary-dark: <?php echo htmlspecialchars($colorSecundarioOscuro, ENT_QUOTES, 'UTF-8'); ?>;
        }
    </style>
    <script>
        // Si el panel lateral quedó contraído (solo íconos) en este equipo, se marca ANTES de pintar
        // el cuerpo — si no, se vería un instante expandido y luego "saltaría" a contraído.
        try {
            if (localStorage.getItem('pb_sidebar_contraido') === '1' && window.matchMedia('(min-width: 901px)').matches) {
                document.documentElement.classList.add('pb-sidebar-rail');
            }
        } catch (e) { /* sin almacenamiento local: arranca expandido, sin problema */ }
        // Lo mismo con el espaciado de los enlaces (ver ajustarDensidadNav() en app.js): se recuerda
        // el último nivel que hizo falta en ESTE equipo y se aplica ya, antes de pintar — si no, cada
        // clic a otra pantalla se vería "saltar" de un espaciado a otro apenas termina de cargar.
        try {
            var pbDensidad = localStorage.getItem('pb_sidebar_densidad');
            if (pbDensidad === 'pb-nav-cozy' || pbDensidad === 'pb-nav-compact') {
                document.documentElement.classList.add(pbDensidad);
            }
        } catch (e) { /* sin almacenamiento local: arranca en el nivel cómodo, se ajusta solo si hace falta */ }
        window.PB_MONEDA = <?php echo json_encode($configNegocio['moneda']); ?>;
        window.PB_TICKET_FORMATO = <?php echo json_encode($configNegocio['formato_ticket'] ?? 'a4'); ?>;
        // Ancho y margen van por formato (58mm, 80mm, A4) — nunca un solo valor compartido entre los
        // tres, para que probar un formato no pise el ajuste fino que ya se tenía guardado en otro.
        window.PB_TICKET_ANCHO_58_MM = <?php echo json_encode($configNegocio['ancho_ticket_58_mm'] ?? null); ?>;
        window.PB_TICKET_MARGEN_58_MM = <?php echo json_encode($configNegocio['margen_ticket_58_mm'] ?? null); ?>;
        window.PB_TICKET_ANCHO_80_MM = <?php echo json_encode($configNegocio['ancho_ticket_80_mm'] ?? null); ?>;
        window.PB_TICKET_MARGEN_80_MM = <?php echo json_encode($configNegocio['margen_ticket_80_mm'] ?? null); ?>;
        window.PB_TICKET_MARGEN_A4_MM = <?php echo json_encode($configNegocio['margen_ticket_a4_mm'] ?? null); ?>;
        window.PB_IMPRESORA_RECIBO = <?php echo json_encode($configNegocio['impresora_recibo'] ?? null); ?>;
        window.PB_IMPRESORA_COCINA = <?php echo json_encode($configNegocio['impresora_cocina'] ?? null); ?>;
        window.PB_IMPRESORA_ETIQUETAS = <?php echo json_encode($configNegocio['impresora_etiquetas'] ?? null); ?>;
        window.PB_ETIQUETA_ANCHO_MM = <?php echo json_encode($configNegocio['ancho_etiqueta_mm'] ?? null); ?>;
        window.PB_ETIQUETA_ALTO_MM = <?php echo json_encode($configNegocio['alto_etiqueta_mm'] ?? null); ?>;
        // Datos del negocio para el encabezado del ticket impreso (ver mostrarTicket() en ventas/index.php).
        window.PB_NEGOCIO = {
            nombre: <?php echo json_encode($configNegocio['nombre_negocio']); ?>,
            nit: <?php echo json_encode($configNegocio['nit'] ?? null); ?>,
            telefono: <?php echo json_encode($configNegocio['telefono'] ?? null); ?>,
            direccion: <?php echo json_encode($configNegocio['direccion'] ?? null); ?>,
            lema: <?php echo json_encode($configNegocio['lema'] ?? null); ?>,
            logo: <?php echo json_encode($configNegocio['logo_path'] ?? null); ?>,
            // El que se imprime en la factura (propio de la factura, o el de la app si no hay uno, o null si se apagó).
            logoFactura: <?php echo json_encode(Configuracion::logoParaFactura()); ?>,
        };
    </script>
</head>
<body>
