<?php
/**
 * Plantilla de superadmin_secreto.php (ver ese archivo, que NO se sube al repo — está en
 * .gitignore). Copia este archivo como "superadmin_secreto.php" en esta misma carpeta y
 * genera tu propio secreto_totp (ej. con `openssl rand -hex 32`) antes de usar el panel
 * de superadministrador. Sin este archivo, SuperAdmin::estaConfigurado() devuelve false y
 * el panel simplemente se reporta como no configurado — no rompe el resto de la app.
 */
return [
    'root_usuario' => 'root',
    'secreto_totp' => 'CAMBIA_ESTO_POR_UN_SECRETO_PROPIO',
];
