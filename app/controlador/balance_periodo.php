<?php
/**
 * El balance (ventas - gastos) del período de corte que el negocio tenga configurado (semanal,
 * quincenal o mensual — ver Configuracion::periodoBalance()/rangoPeriodo()). Lo usan el Panel y
 * Compras y gastos para no tener que elegir fechas a mano cada vez.
 */
require_once "../modelo/bootstrap.php";
require_once "../modelo/movimientos.php";

Auth::requerirPermisoApi('movimientos.ver');

header('Content-Type: application/json');

$periodo = Configuracion::periodoBalance();
$rango = Configuracion::rangoPeriodo($periodo['tipo'], $periodo['dia']);

$resumen = (new movimientos())->resumenGastos($rango['inicio'], $rango['fin']);

echo json_encode([
    'tipo' => $periodo['tipo'],
    'dia' => $periodo['dia'],
    'inicio' => $rango['inicio'],
    'fin' => $rango['fin'],
    'resumen' => $resumen,
]);
