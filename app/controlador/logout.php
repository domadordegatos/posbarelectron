<?php
require_once "../modelo/bootstrap.php";

Auth::cerrarSesion();

header('Location: /vista/login/index.php');
exit;
