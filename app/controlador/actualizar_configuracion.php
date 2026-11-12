<?php
require_once "../modelo/bootstrap.php";
require_once "../modelo/Auditoria.php";

$usuarioActual = Auth::requerirRolApi('admin');
Csrf::validarApi();

header('Content-Type: application/json');

$nombreNegocio = trim($_POST['nombre_negocio'] ?? '');
$colorPrimario = trim($_POST['color_primario'] ?? '#4f46e5');
$colorSecundario = trim($_POST['color_secundario'] ?? '#f97316');
$moneda = trim($_POST['moneda'] ?? 'COP');
$zonaHoraria = trim($_POST['zona_horaria'] ?? 'America/Bogota');
$formatoTicket = trim($_POST['formato_ticket'] ?? 'a4');
$nit = trim($_POST['nit'] ?? '');
$telefono = trim($_POST['telefono'] ?? '');
$direccion = trim($_POST['direccion'] ?? '');
$lema = trim($_POST['lema'] ?? '');
// Cada formato de rollo guarda su propio ancho/margen (ver Configuracion::actualizar): así probar 58mm
// y volver a 80mm no borra el milimetraje ya ajustado para 80mm.
$numero = fn($clave) => is_numeric($_POST[$clave] ?? '') ? round((float) $_POST[$clave], 1) : null;
$anchoTicket58Mm = $numero('ancho_ticket_58_mm');
$margenTicket58Mm = $numero('margen_ticket_58_mm');
$anchoTicket80Mm = $numero('ancho_ticket_80_mm');
$margenTicket80Mm = $numero('margen_ticket_80_mm');
$margenTicketA4Mm = $numero('margen_ticket_a4_mm');
$impresoraRecibo = trim($_POST['impresora_recibo'] ?? '');
$impresoraCocina = trim($_POST['impresora_cocina'] ?? '');
$impresoraEtiquetas = trim($_POST['impresora_etiquetas'] ?? '');
$anchoEtiquetaMm = $numero('ancho_etiqueta_mm');
$altoEtiquetaMm = $numero('alto_etiqueta_mm');

if ($nombreNegocio === '') {
    echo json_encode(['error' => 'El nombre del negocio es obligatorio.']);
    exit;
}

$ok = Configuracion::actualizar(
    $nombreNegocio, $colorPrimario, $moneda, $zonaHoraria, $formatoTicket, $nit, $telefono, $direccion, $lema,
    $anchoTicket58Mm, $margenTicket58Mm, $colorSecundario, $impresoraRecibo, $impresoraCocina,
    $anchoTicket80Mm, $margenTicket80Mm, $margenTicketA4Mm,
    $impresoraEtiquetas, $anchoEtiquetaMm, $altoEtiquetaMm
);

// Si se eligió una imagen y no se pudo guardar (formato, tamaño), se avisa: antes fallaba en silencio.
$avisos = [];
if ($ok && !empty($_FILES['logo']['name']) && Configuracion::guardarLogo($_FILES['logo']) === false) {
    $avisos[] = 'No se pudo guardar el logo de la aplicación: usa una imagen PNG, JPG o WebP de hasta 2 MB.';
}
if ($ok && !empty($_FILES['logo_factura']['name']) && Configuracion::guardarLogoFactura($_FILES['logo_factura']) === false) {
    $avisos[] = 'No se pudo guardar el logo de la factura: usa una imagen PNG, JPG o WebP de hasta 2 MB.';
}
if ($ok && !empty($_POST['quitar_logo_factura'])) {
    Configuracion::quitarLogoFactura();
}
// Solo si vino en el formulario (un envío sin ese campo no debe apagar el logo).
if ($ok && isset($_POST['imprimir_logo_factura'])) {
    Configuracion::actualizarImprimirLogoFactura(!empty($_POST['imprimir_logo_factura']));
}

if ($ok) {
    Auditoria::registrar((int) $usuarioActual['id'], 'configuracion.actualizar', 'configuracion_negocio', 1, [
        'nombre_negocio' => $nombreNegocio,
        'moneda' => $moneda,
        'formato_ticket' => $formatoTicket,
    ]);
}

echo json_encode(['ok' => (bool) $ok, 'avisos' => $avisos]);
