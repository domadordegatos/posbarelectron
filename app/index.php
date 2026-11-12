<?php
/**
 * Punto de entrada raíz — a esto apunta main.js con loadURL('http://127.0.0.1:<puerto>/').
 * Decide a dónde redirigir, en este orden:
 *   1. Activación, si el dispositivo no está activado todavía.
 *   2. Licencia revocada — el proveedor la desactivó explícitamente desde
 *      su servidor (estado suspendida/revocada). Aplica a CUALQUIER tipo
 *      de licencia, perpetua o suscripción; es el apagado de emergencia
 *      del proveedor, aparte del ciclo automático de pago.
 *   3. Suscripción vencida — solo licencias tipo suscripción que superaron
 *      los días de gracia sin que se confirme el pago. Las perpetuas nunca
 *      pasan por este check (pagada_hasta siempre nulo para ellas).
 *   4. Login normal, en cualquier otro caso.
 */
require_once __DIR__ . '/modelo/Licencia.php';

if (!Licencia::estaActivada()) {
    header('Location: /vista/activacion/index.php');
    exit;
}

if (Licencia::debeBloquearPorRevocacion()) {
    header('Location: /vista/licencia_revocada/index.php');
    exit;
}

if (Licencia::debeBloquearPorPago()) {
    header('Location: /vista/suscripcion_vencida/index.php');
    exit;
}

header('Location: /vista/login/index.php');
exit;
