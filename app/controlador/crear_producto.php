<?php
require_once "../modelo/bootstrap.php";
require_once "../modelo/productos.php";
require_once "../modelo/combos.php";
require_once "../modelo/Modificadores.php";
require_once "../modelo/Auditoria.php";

$usuarioActual = Auth::requerirPermisoApi('productos.editar');
Csrf::validarApi();

header('Content-Type: application/json');

$nombre_producto = trim($_POST['nombre_producto'] ?? '');
$codigo_producto = trim($_POST['codigo_producto'] ?? '');
$categoria = filter_input(INPUT_POST, 'categoria', FILTER_VALIDATE_INT);
$precio_unitario = filter_input(INPUT_POST, 'precio_unitario', FILTER_VALIDATE_FLOAT);
$cantidad_existente = filter_input(INPUT_POST, 'cantidad_existente', FILTER_VALIDATE_INT);
$minimo_stock = filter_input(INPUT_POST, 'minimo_stock', FILTER_VALIDATE_INT);
$estado = filter_input(INPUT_POST, 'estado', FILTER_VALIDATE_INT);
$controla_stock = filter_var($_POST['controla_stock'] ?? true, FILTER_VALIDATE_BOOLEAN);
$unidades_por_paquete = filter_input(INPUT_POST, 'unidades_por_paquete', FILTER_VALIDATE_INT) ?: 1;
$precio_paquete = filter_input(INPUT_POST, 'precio_paquete', FILTER_VALIDATE_FLOAT);
$precio_paquete = ($precio_paquete === false || $precio_paquete === null || $precio_paquete <= 0) ? null : $precio_paquete;
$costo_unitario = filter_input(INPUT_POST, 'costo_unitario', FILTER_VALIDATE_FLOAT);
$costo_unitario = ($costo_unitario === false || $costo_unitario === null) ? 0.0 : $costo_unitario;
$tasa_iva = filter_input(INPUT_POST, 'tasa_iva', FILTER_VALIDATE_FLOAT);
$tasa_iva = ($tasa_iva === false || $tasa_iva === null) ? 0.0 : $tasa_iva;
$es_combo = filter_var($_POST['es_combo'] ?? false, FILTER_VALIDATE_BOOLEAN);
$se_vende_por_peso = filter_var($_POST['se_vende_por_peso'] ?? false, FILTER_VALIDATE_BOOLEAN);
$componentesRaw = $_POST['componentes'] ?? '[]';
$componentes = json_decode($componentesRaw, true) ?: [];
$gruposModificadoresRaw = $_POST['grupos_modificadores'] ?? '[]';
$gruposModificadores = json_decode($gruposModificadoresRaw, true) ?: [];

if ($nombre_producto === '' || $categoria === false || $categoria === null
    || $precio_unitario === false || $precio_unitario === null
    || $cantidad_existente === false || $cantidad_existente === null
    || $minimo_stock === false || $minimo_stock === null
    || $estado === false || $estado === null
    || $unidades_por_paquete < 1
) {
    echo json_encode(['error' => 'Datos inválidos.']);
    exit;
}

$obj = new productos();

if ($codigo_producto !== '' && $obj->existeCodigo($codigo_producto)) {
    echo json_encode(['error' => 'Ya existe un producto con ese código de barras.']);
    exit;
}

$resultado = $obj->crearProducto(
    $nombre_producto,
    $codigo_producto,
    $categoria,
    $precio_unitario,
    $cantidad_existente,
    $minimo_stock,
    $estado,
    $controla_stock,
    $unidades_por_paquete,
    $precio_paquete,
    $costo_unitario,
    $tasa_iva,
    $es_combo,
    $se_vende_por_peso
);

if ($resultado === 1) {
    $idNuevoProducto = $obj->ultimoIdCreado();
    if ($es_combo && !empty($componentes)) {
        (new combos())->guardarComponentes($idNuevoProducto, $componentes);
    }
    if (!empty($gruposModificadores)) {
        (new Modificadores())->guardarGrupos($idNuevoProducto, $gruposModificadores);
    }
    Auditoria::registrar((int) $usuarioActual['id'], 'producto.crear', 'producto', $idNuevoProducto, [
        'nombre_producto' => $nombre_producto,
        'precio_unitario' => $precio_unitario,
        'estado' => $estado,
    ]);
}

echo json_encode($resultado);
