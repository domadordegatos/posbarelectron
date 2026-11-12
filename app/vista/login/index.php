<?php
require_once "../../modelo/bootstrap.php";

if (Auth::estaAutenticado()) {
    $rol = Auth::usuarioActual()['rol'];
    header('Location: ' . ($rol === 'admin' ? '/vista/home/index.php' : '/vista/ventas/index.php'));
    exit;
}

require_once "../../modelo/usuarios.php";
$configNegocio = Configuracion::obtener();
$nombreNegocio = $configNegocio['nombre_negocio'];
$colorPrimario = $configNegocio['color_primario'];
$colorPrimarioOscuro = Configuracion::ajustarColor($colorPrimario, -0.15);
$csrfToken = Csrf::token();
$hayUsuarios = count((new usuarios())->listar()) > 0;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar sesión · <?php echo htmlspecialchars($nombreNegocio, ENT_QUOTES, 'UTF-8'); ?> · <?php echo htmlspecialchars(POS_PRODUCT_NAME, ENT_QUOTES, 'UTF-8'); ?></title>
    <link rel="stylesheet" href="/assets/vendor/bootstrap/css/bootstrap.min.css">
    <link rel="stylesheet" href="/assets/vendor/icons/bootstrap-icons.css">
    <link rel="stylesheet" href="/assets/css/app.css<?php echo '?v=' . @filemtime(__DIR__ . '/../../assets/css/app.css'); ?>">
    <style>
        :root {
            --pb-primary: <?php echo htmlspecialchars($colorPrimario, ENT_QUOTES, 'UTF-8'); ?>;
            --pb-primary-dark: <?php echo htmlspecialchars($colorPrimarioOscuro, ENT_QUOTES, 'UTF-8'); ?>;
        }
    </style>
</head>
<body>
    <div class="pb-login-wrap">
        <div class="pb-login-card">
            <div class="pb-login-logo">
                <img src="/assets/img/nenesoft-logo.png" alt="">
            </div>
            <h4 class="text-center mb-1"><?php echo htmlspecialchars(POS_PRODUCT_NAME, ENT_QUOTES, 'UTF-8'); ?></h4>
            <div class="pb-login-brand mb-3"><?php echo htmlspecialchars($nombreNegocio, ENT_QUOTES, 'UTF-8'); ?></div>

            <?php if ($hayUsuarios): ?>
                <p class="text-center text-muted mb-4" style="font-size:.85rem;">Ingresa con tu usuario y contraseña</p>
                <div id="alertaLogin" class="alert alert-danger d-none" role="alert"></div>
                <form id="formLogin">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                    <div class="mb-3">
                        <label for="usuario" class="form-label">Usuario</label>
                        <input type="text" class="form-control" id="usuario" name="usuario" autofocus required autocomplete="username">
                    </div>
                    <div class="mb-3">
                        <label for="password" class="form-label">Contraseña</label>
                        <input type="password" class="form-control" id="password" name="password" required autocomplete="current-password">
                    </div>
                    <button type="submit" class="btn btn-primary w-100 mt-2">
                        <i class="bi bi-box-arrow-in-right"></i> Ingresar
                    </button>
                </form>
            <?php else: ?>
                <div id="alertaInicial" class="alert alert-danger d-none" role="alert"></div>
                <form id="formInicial">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                    <div class="mb-3">
                        <label for="usuarioInicial" class="form-label">Usuario</label>
                        <input type="text" class="form-control" id="usuarioInicial" name="usuario" autofocus required autocomplete="username">
                    </div>
                    <div class="mb-3">
                        <label for="passwordInicial" class="form-label">Contraseña</label>
                        <input type="password" class="form-control" id="passwordInicial" name="password" required minlength="6" autocomplete="new-password">
                        <div class="form-text">Mínimo 6 caracteres.</div>
                    </div>
                    <button type="submit" class="btn btn-primary w-100 mt-2">
                        <i class="bi bi-person-plus"></i> Crear cuenta y entrar
                    </button>
                </form>
            <?php endif; ?>

            <div class="text-center mt-3">
                <a href="/vista/superadmin/login.php" class="text-muted small">
                    <i class="bi bi-gear"></i> Configuración del sistema
                </a>
            </div>
        </div>
    </div>

    <script src="/assets/vendor/jquery/jquery.min.js"></script>
    <script>
        $('#formLogin').on('submit', function (e) {
            e.preventDefault();
            const $alerta = $('#alertaLogin').addClass('d-none');
            $.ajax({
                type: 'POST',
                url: '/controlador/login.php',
                data: $(this).serialize(),
                dataType: 'json',
                success: function (respuesta) {
                    if (respuesta.ok) {
                        window.location.href = respuesta.redirect;
                    } else {
                        $alerta.text(respuesta.error || 'No se pudo iniciar sesión.').removeClass('d-none');
                    }
                },
                error: function () {
                    $alerta.text('Error de conexión. Intenta de nuevo.').removeClass('d-none');
                }
            });
        });

        $('#formInicial').on('submit', function (e) {
            e.preventDefault();
            const $alerta = $('#alertaInicial').addClass('d-none');
            $.ajax({
                type: 'POST',
                url: '/controlador/crear_usuario_inicial.php',
                data: $(this).serialize(),
                dataType: 'json',
                success: function (respuesta) {
                    if (respuesta.ok) {
                        window.location.href = respuesta.redirect;
                    } else {
                        $alerta.text(respuesta.error || 'No se pudo crear la cuenta.').removeClass('d-none');
                    }
                },
                error: function () {
                    $alerta.text('Error de conexión. Intenta de nuevo.').removeClass('d-none');
                }
            });
        });
    </script>
</body>
</html>
