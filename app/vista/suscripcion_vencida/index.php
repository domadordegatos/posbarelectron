<?php
require_once "../../modelo/bootstrap.php";
require_once "../../modelo/Licencia.php";

if (!Licencia::debeBloquearPorPago()) {
    header('Location: /vista/login/index.php');
    exit;
}

$csrfToken = Csrf::token();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
    <title>Suscripción vencida · <?php echo htmlspecialchars(POS_PRODUCT_NAME, ENT_QUOTES, 'UTF-8'); ?></title>
    <link rel="stylesheet" href="/assets/vendor/bootstrap/css/bootstrap.min.css">
    <link rel="stylesheet" href="/assets/vendor/icons/bootstrap-icons.css">
    <link rel="stylesheet" href="/assets/css/app.css<?php echo '?v=' . @filemtime(__DIR__ . '/../../assets/css/app.css'); ?>">
    <style>
        :root { --pb-primary: #b91c1c; --pb-primary-dark: #7f1d1d; }
        body { background: #0b0f19; }
    </style>
</head>
<body>
    <div class="pb-login-wrap">
        <div class="pb-login-card">
            <div class="pb-login-logo"><i class="bi bi-exclamation-octagon fs-1"></i></div>
            <h4 class="text-center mb-1"><?php echo htmlspecialchars(POS_PRODUCT_NAME, ENT_QUOTES, 'UTF-8'); ?></h4>
            <p class="text-center mb-2">
                La suscripción de esta instalación venció y ya pasó el período de gracia.
                El sistema queda bloqueado hasta confirmar el pago.
            </p>
            <p class="text-center text-muted mb-4" style="font-size:.9rem;">
                Contacta a tu proveedor y realiza el pago
                (<a href="https://nncoms.com" target="_blank" rel="noopener">nncoms.com</a>
                · <a href="mailto:soporte@nncoms.com">soporte@nncoms.com</a>).
                Una vez confirmado, asegúrate de tener conexión a internet en este equipo para
                que se active de nuevo automáticamente.
            </p>

            <div id="alertaVerificar" class="alert py-2 px-3 d-none" role="alert"></div>

            <button type="button" class="btn btn-dark w-100" id="btnVerificarPago" onclick="verificarPagoAhora()">
                <i class="bi bi-arrow-clockwise"></i> Ya pagué, verificar ahora
            </button>

            <div class="pb-login-brand mt-3">Panel del proveedor</div>
            <div class="text-center mt-2">
                <a href="/vista/superadmin/login.php" class="text-muted small">
                    <i class="bi bi-gear"></i> Configuración del sistema
                </a>
            </div>
        </div>
    </div>

    <script src="/assets/vendor/jquery/jquery.min.js"></script>
    <script>
        function verificarPagoAhora() {
            const $boton = $('#btnVerificarPago').prop('disabled', true);
            const $aviso = $('#alertaVerificar').removeClass('d-none alert-success alert-danger').addClass('alert-secondary').text('Consultando el servidor…');
            $.ajax({
                type: 'POST',
                url: '/controlador/heartbeat_licencia.php',
                dataType: 'json',
                success: function (r) {
                    if (r.ok) {
                        $aviso.removeClass('alert-secondary').addClass('alert-success').text('Listo, revisando el estado…');
                        setTimeout(() => window.location.href = '/', 700);
                    } else {
                        $boton.prop('disabled', false);
                        $aviso.removeClass('alert-secondary').addClass('alert-danger').text(r.error || 'No se pudo verificar. Revisa tu conexión a internet.');
                    }
                },
                error: function () {
                    $boton.prop('disabled', false);
                    $aviso.removeClass('alert-secondary').addClass('alert-danger').text('No se pudo contactar el servidor. Revisa tu conexión a internet.');
                }
            });
        }
    </script>
</body>
</html>
