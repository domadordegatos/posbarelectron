<?php
require_once "../modelo/bootstrap.php";

Auth::iniciarSesion();
unset($_SESSION['superadmin'], $_SESSION['superadmin_desde']);

header('Location: /vista/superadmin/login.php');
