<?php
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Auth.php';
require_once __DIR__ . '/Csrf.php';
require_once __DIR__ . '/Configuracion.php';

define('POS_PRODUCT_NAME', 'EneSoft');

date_default_timezone_set(Configuracion::obtener()['zona_horaria'] ?? 'America/Bogota');
Auth::iniciarSesion();
