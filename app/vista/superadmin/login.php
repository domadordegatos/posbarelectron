<?php
require_once "../../modelo/bootstrap.php";
require_once "../../modelo/SuperAdmin.php";

if (Auth::esSuperAdmin()) {
    header('Location: /vista/superadmin/index.php');
    exit;
}

$csrfToken = Csrf::token();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel del proveedor</title>
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
            <div class="pb-login-logo"><img src="/assets/img/nenesoft-logo.png" alt=""></div>
            <h4 class="text-center mb-1">Panel del proveedor</h4>
            <p class="text-center text-muted mb-4" style="font-size:.85rem;">Acceso restringido — código dinámico de 6 dígitos</p>

            <div id="alertaLogin" class="alert alert-danger d-none" role="alert"></div>

            <form id="formLoginSuperadmin">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                <div class="mb-3">
                    <label for="usuario" class="form-label">Usuario</label>
                    <input type="text" class="form-control" id="usuario" name="usuario" autofocus required autocomplete="off">
                </div>
                <div class="mb-3">
                    <label for="codigo" class="form-label">Código de 6 dígitos</label>
                    <input type="text" class="form-control text-center" style="letter-spacing:.3em;font-size:1.3rem;" id="codigo" name="codigo"
                           inputmode="numeric" pattern="\d{6}" maxlength="6" required autocomplete="off">
                </div>
                <button type="submit" class="btn btn-dark w-100 mt-2">
                    <i class="bi bi-shield-check"></i> Ingresar
                </button>
            </form>

            <div class="text-center mt-3">
                <a href="/vista/login/index.php" class="text-muted small">
                    <i class="bi bi-arrow-left"></i> Volver al inicio de sesión
                </a>
            </div>
        </div>
    </div>

    <script src="/assets/vendor/jquery/jquery.min.js"></script>
    <script>
        $('#codigo').on('input', function () {
            this.value = this.value.replace(/\D/g, '').slice(0, 6);
        });

        $('#formLoginSuperadmin').on('submit', function (e) {
            e.preventDefault();
            const $alerta = $('#alertaLogin').addClass('d-none');
            $.ajax({
                type: 'POST',
                url: '/controlador/superadmin_login.php',
                data: $(this).serialize(),
                dataType: 'json',
                success: function (respuesta) {
                    if (respuesta.ok) {
                        window.location.href = '/vista/superadmin/index.php';
                    } else {
                        $alerta.text(respuesta.error || 'No se pudo iniciar sesión.').removeClass('d-none');
                        $('#codigo').val('').trigger('focus');
                    }
                },
                error: function (xhr) {
                    const msg = xhr.responseJSON && xhr.responseJSON.error ? xhr.responseJSON.error : 'Error de conexión. Intenta de nuevo.';
                    $alerta.text(msg).removeClass('d-none');
                }
            });
        });
    </script>
</body>
</html>
