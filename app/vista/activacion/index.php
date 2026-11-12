<?php
require_once "../../modelo/bootstrap.php";
require_once "../../modelo/Licencia.php";

if (Licencia::estaActivada()) {
    header('Location: /vista/login/index.php');
    exit;
}

$csrfToken = Csrf::token();
$installId = Licencia::installId();
$motivoLiberacion = Licencia::motivoLiberacion();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Activar · <?php echo htmlspecialchars(POS_PRODUCT_NAME, ENT_QUOTES, 'UTF-8'); ?></title>
    <link rel="stylesheet" href="/assets/vendor/bootstrap/css/bootstrap.min.css">
    <link rel="stylesheet" href="/assets/vendor/icons/bootstrap-icons.css">
    <link rel="stylesheet" href="/assets/css/app.css<?php echo '?v=' . @filemtime(__DIR__ . '/../../assets/css/app.css'); ?>">
    <style>
        :root { --pb-primary: #111827; --pb-primary-dark: #000000; }
        body { background: #0b0f19; }
    </style>
</head>
<body>
    <div class="pb-login-wrap">
        <div class="pb-login-card">
            <div class="pb-login-logo"><i class="bi bi-key fs-1"></i></div>
            <h4 class="text-center mb-1">Activar esta instalación</h4>
            <p class="text-center text-muted mb-4" style="font-size:.85rem;">
                Ingresa la clave de licencia que te entregó tu proveedor.
                Esto solo hace falta una vez — necesita internet ahora, pero
                nunca más para el uso diario.
            </p>

            <?php if ($motivoLiberacion): ?>
                <div class="alert alert-warning py-2 px-3 mb-3">
                    <i class="bi bi-exclamation-triangle"></i>
                    <?php echo htmlspecialchars($motivoLiberacion, ENT_QUOTES, 'UTF-8'); ?>
                </div>
            <?php endif; ?>

            <div id="alertaActivacion" class="alert alert-danger d-none" role="alert"></div>

            <form id="formActivacion">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                <div class="mb-3">
                    <label for="claveLicencia" class="form-label">Clave de licencia</label>
                    <input type="text" class="form-control text-center" style="letter-spacing:.1em;" id="claveLicencia" name="clave_licencia"
                           placeholder="XXXX-XXXX-XXXX-XXXX" autofocus required autocomplete="off">
                </div>
                <div class="mb-3">
                    <label for="nombreDispositivo" class="form-label">Nombre de este equipo (opcional)</label>
                    <input type="text" class="form-control" id="nombreDispositivo" name="nombre_dispositivo"
                           placeholder="Caja principal" autocomplete="off">
                </div>
                <button type="submit" class="btn btn-dark w-100 mt-2" id="btnActivar">
                    <i class="bi bi-shield-check"></i> Activar
                </button>
            </form>

            <div class="pb-login-brand"><?php echo htmlspecialchars(POS_PRODUCT_NAME, ENT_QUOTES, 'UTF-8'); ?></div>
            <div class="text-center text-muted mt-2" style="font-size:.7rem;" title="Identificador único de este dispositivo">
                <?php echo htmlspecialchars($installId, ENT_QUOTES, 'UTF-8'); ?>
            </div>
        </div>
    </div>

    <script src="/assets/vendor/jquery/jquery.min.js"></script>
    <script src="/assets/js/app.js<?php echo '?v=' . @filemtime(__DIR__ . '/../../assets/js/app.js'); ?>"></script>
    <script>
        pb.autoformatearClaveLicencia('#claveLicencia');

        $('#formActivacion').on('submit', function (e) {
            e.preventDefault();
            const $alerta = $('#alertaActivacion').addClass('d-none');
            const $boton = $('#btnActivar').prop('disabled', true);
            $.ajax({
                type: 'POST',
                url: '/controlador/activar_licencia.php',
                data: $(this).serialize(),
                dataType: 'json',
                success: function (respuesta) {
                    if (respuesta.ok) {
                        window.location.href = '/vista/login/index.php';
                    } else {
                        $alerta.text(respuesta.error || 'No se pudo activar. Verifica la clave e intenta de nuevo.').removeClass('d-none');
                        $boton.prop('disabled', false);
                    }
                },
                error: function (xhr) {
                    const msg = xhr.responseJSON && xhr.responseJSON.error ? xhr.responseJSON.error : 'No se pudo contactar el servidor de activación. Revisa tu conexión a internet.';
                    $alerta.text(msg).removeClass('d-none');
                    $boton.prop('disabled', false);
                }
            });
        });
    </script>
</body>
</html>
