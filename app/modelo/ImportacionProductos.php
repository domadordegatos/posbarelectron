<?php
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Configuracion.php';
require_once __DIR__ . '/productos.php';
require_once __DIR__ . '/Auditoria.php';
require_once __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx as LectorXlsx;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Carga masiva de productos simples desde un Excel .xlsx (ver vista/registro para el botón
 * de plantilla y el flujo de previsualizar → confirmar).
 *
 * No confía en nada que el navegador mande como "ya validado": confirmar()
 * vuelve a correr exactamente la misma validación de validar() sobre los
 * datos crudos antes de escribir, igual que el resto del sistema nunca
 * confía en un precio/total calculado del lado del cliente.
 */
class ImportacionProductos
{
    public const COLUMNAS_BASE = [
        'nombre_producto', 'categoria', 'precio_unitario', 'codigo_producto',
        'cantidad_existente', 'minimo_stock', 'controla_stock', 'se_vende_por_peso',
    ];

    public function leer(string $rutaArchivo): array
    {
        try {
            $lector = new LectorXlsx();
            $lector->setReadDataOnly(true);
            $libro = $lector->load($rutaArchivo);
        } catch (\Throwable $e) {
            throw new RuntimeException('No se pudo leer el archivo de Excel — asegúrate de que sea un .xlsx válido.');
        }

        // false en vez de null para las celdas vacías: así todo llega como string, nunca
        // null, sin que validarFila() tenga que distinguir los dos casos.
        $filasCeldas = $libro->getActiveSheet()->toArray(null, true, true, false);
        if (empty($filasCeldas)) {
            throw new RuntimeException('El archivo está vacío.');
        }

        $encabezadoCrudo = array_shift($filasCeldas);
        $encabezado = array_map(fn($c) => strtolower(trim($this->celdaATexto($c))), $encabezadoCrudo);
        $this->validarEncabezadoObligatorio($encabezado);

        $filas = [];
        $numeroFila = 1;
        foreach ($filasCeldas as $linea) {
            $numeroFila++;
            if ($this->lineaEstaVacia($linea)) {
                continue;
            }
            $fila = ['_fila' => $numeroFila];
            foreach ($encabezado as $i => $nombreColumna) {
                $fila[$nombreColumna] = trim($this->celdaATexto($linea[$i] ?? ''));
            }
            $filas[] = $fila;
        }

        if (empty($filas)) {
            throw new RuntimeException('El archivo no tiene filas de datos, solo el encabezado.');
        }

        return $filas;
    }

    private function validarEncabezadoObligatorio(array $encabezado): void
    {
        foreach (['nombre_producto', 'categoria', 'precio_unitario'] as $columnaObligatoria) {
            if (!in_array($columnaObligatoria, $encabezado, true)) {
                throw new RuntimeException("Al archivo le falta la columna \"$columnaObligatoria\". Descarga la plantilla de nuevo y no cambies los encabezados.");
            }
        }
    }

    private function lineaEstaVacia(array $linea): bool
    {
        foreach ($linea as $valor) {
            if (trim($this->celdaATexto($valor)) !== '') {
                return false;
            }
        }

        return true;
    }

    /** Convierte un valor leído de una celda (string, número, bool o null) a texto, sin notación científica para números grandes como un código de barras. */
    private function celdaATexto($valor): string
    {
        if ($valor === null || $valor === false) {
            return '';
        }
        if (is_float($valor)) {
            if ($valor == (int) $valor && abs($valor) < PHP_INT_MAX) {
                return (string) (int) $valor;
            }

            return rtrim(rtrim(number_format($valor, 10, '.', ''), '0'), '.');
        }

        return trim((string) $valor);
    }

    public function datosPlantilla(): array
    {
        $modulos = Configuracion::modulosActivos();
        $encabezado = self::COLUMNAS_BASE;
        if ($modulos['paquetes']) {
            $encabezado[] = 'unidades_por_paquete';
            $encabezado[] = 'precio_paquete';
        }
        if ($modulos['costeo']) {
            $encabezado[] = 'costo_unitario';
        }
        if ($modulos['iva']) {
            $encabezado[] = 'tasa_iva';
        }

        $filaEjemplo = function (array $valores) use ($encabezado) {
            return array_map(fn($col) => $valores[$col] ?? '', $encabezado);
        };

        // El nombre deja clarísimo que es un ejemplo (no un producto real que ya esté en el
        // sistema) — antes solo decía "Coca-Cola 400ml" y algunos usuarios pensaban que esas
        // filas eran parte de su catálogo real.
        $ejemplos = [
            $filaEjemplo([
                'nombre_producto' => '(EJEMPLO — bórrame) Coca-Cola 400ml', 'categoria' => 'Bebidas', 'precio_unitario' => '2500',
                'codigo_producto' => '7702090000000', 'cantidad_existente' => '24', 'minimo_stock' => '5',
                'controla_stock' => 'si', 'se_vende_por_peso' => 'no', 'unidades_por_paquete' => '1',
                'precio_paquete' => '', 'costo_unitario' => '1800', 'tasa_iva' => '0',
            ]),
            $filaEjemplo([
                'nombre_producto' => '(EJEMPLO — bórrame) Papas fritas', 'categoria' => 'Snacks', 'precio_unitario' => '3500',
                'codigo_producto' => '', 'cantidad_existente' => '10', 'minimo_stock' => '2',
                'controla_stock' => 'si', 'se_vende_por_peso' => 'no', 'unidades_por_paquete' => '1',
                'precio_paquete' => '', 'costo_unitario' => '2000', 'tasa_iva' => '19',
            ]),
        ];

        return ['encabezado' => $encabezado, 'ejemplos' => $ejemplos];
    }

    /**
     * La plantilla descargable como .xlsx real: encabezado en negrita, columnas con ancho
     * ajustado, comentarios explicando cada campo, y listas desplegables nativas de Excel
     * para "categoria" (las que ya existen en el negocio), "controla_stock" y
     * "se_vende_por_peso" ("si"/"no") — así se elige en vez de escribir a mano y equivocarse.
     * Las dos filas de ejemplo quedan resaltadas (relleno amarillo, cursiva) para que no se
     * confundan con productos reales ya cargados.
     */
    public function generarPlantillaXlsx(array $categoriasExistentes): Spreadsheet
    {
        $plantilla = $this->datosPlantilla();
        $encabezado = $plantilla['encabezado'];
        $totalColumnas = count($encabezado);
        $ultimaFilaValidacion = 500;

        $ayudaPorColumna = [
            'nombre_producto' => 'Obligatorio.',
            'categoria' => 'Obligatorio. Elige una de la lista o escribe una categoría nueva — se crea sola al confirmar la carga.',
            'precio_unitario' => 'Obligatorio. El precio al que se lo vendes al cliente, solo el número (sin $ ni puntos de miles).',
            'codigo_producto' => 'Opcional. Código de barras u otro identificador único (déjalo vacío si no tiene).',
            'cantidad_existente' => 'Cuántas unidades hay en existencia ahora mismo (déjalo en 0 si no sabes).',
            'minimo_stock' => 'Avisa cuando la existencia baje de este número.',
            'controla_stock' => '"si" o "no" — si este producto lleva control de inventario en el sistema.',
            'se_vende_por_peso' => '"si" o "no" — para productos que se venden por kilos/gramos en vez de por unidad.',
            'unidades_por_paquete' => 'Cuántas unidades trae un paquete/caja (deja 1 si no se vende por paquete).',
            'precio_paquete' => 'Precio de venta del paquete completo (déjalo vacío si no se vende por paquete).',
            'costo_unitario' => 'Lo que te cuesta a ti comprar una unidad (no el precio de venta).',
            'tasa_iva' => 'Porcentaje de IVA (ej. 19 para 19%), déjalo en 0 si no aplica.',
        ];

        $libro = new Spreadsheet();
        $hoja = $libro->getActiveSheet();
        $hoja->setTitle('Productos');

        foreach ($encabezado as $i => $nombreColumna) {
            $letra = Coordinate::stringFromColumnIndex($i + 1);
            $hoja->setCellValue($letra . '1', $nombreColumna);
            if (isset($ayudaPorColumna[$nombreColumna])) {
                $hoja->getComment($letra . '1')->getText()->createTextRun($ayudaPorColumna[$nombreColumna]);
                $hoja->getComment($letra . '1')->setWidth('220pt')->setHeight('70pt');
            }
            $hoja->getColumnDimension($letra)->setAutoSize(true);
        }

        $rangoEncabezado = 'A1:' . Coordinate::stringFromColumnIndex($totalColumnas) . '1';
        $hoja->getStyle($rangoEncabezado)->getFont()->setBold(true);
        $hoja->getStyle($rangoEncabezado)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('EEF2FF');
        $hoja->freezePane('A2');

        // codigo_producto como texto: un código de barras largo escrito en una celda numérica
        // normal, Excel lo puede convertir a notación científica y dañarlo. Se aplica ANTES de
        // escribir valores, pero eso solo cambia cómo se VE — a las celdas de ejemplo que ya
        // traen un código largo hay que meterles el valor como texto de verdad (más abajo,
        // con setCellValueExplicit), si no, PhpSpreadsheet igual las guarda como número.
        $indiceCodigo = array_search('codigo_producto', $encabezado, true);
        if ($indiceCodigo !== false) {
            $letra = Coordinate::stringFromColumnIndex($indiceCodigo + 1);
            $hoja->getStyle("{$letra}2:{$letra}{$ultimaFilaValidacion}")->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_TEXT);
        }

        $filaActual = 2;
        foreach ($plantilla['ejemplos'] as $filaEjemplo) {
            foreach ($filaEjemplo as $i => $valor) {
                $coordenada = Coordinate::stringFromColumnIndex($i + 1) . $filaActual;
                if ($i === $indiceCodigo && $valor !== '') {
                    $hoja->setCellValueExplicit($coordenada, $valor, DataType::TYPE_STRING);
                } else {
                    $hoja->setCellValue($coordenada, $valor);
                }
            }
            $filaActual++;
        }

        // Relleno amarillo + cursiva en las filas de ejemplo — para que no se confundan con
        // productos reales (eso fue justo lo que pasó la primera vez: se veían iguales a una
        // fila de verdad).
        $rangoEjemplos = 'A2:' . Coordinate::stringFromColumnIndex($totalColumnas) . (count($plantilla['ejemplos']) + 1);
        $hoja->getStyle($rangoEjemplos)->getFont()->setItalic(true)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('FF92400E'));
        $hoja->getStyle($rangoEjemplos)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FEF3C7');

        $this->agregarListaDesplegable($hoja, $encabezado, 'controla_stock', ['si', 'no'], $ultimaFilaValidacion);
        $this->agregarListaDesplegable($hoja, $encabezado, 'se_vende_por_peso', ['si', 'no'], $ultimaFilaValidacion);

        if (!empty($categoriasExistentes)) {
            // Se guardan en una segunda hoja (oculta) en vez de una lista "a,b,c" en la propia
            // fórmula de validación: así no hay límite de 255 caracteres ni problema si alguna
            // categoría trae una coma en el nombre.
            $hojaCategorias = $libro->createSheet();
            $hojaCategorias->setTitle('CategoriasExistentes');
            foreach (array_values($categoriasExistentes) as $i => $categoria) {
                $hojaCategorias->setCellValue('A' . ($i + 1), $categoria);
            }
            $hojaCategorias->setSheetState(Worksheet::SHEETSTATE_HIDDEN);

            $indiceCategoria = array_search('categoria', $encabezado, true);
            if ($indiceCategoria !== false) {
                $letra = Coordinate::stringFromColumnIndex($indiceCategoria + 1);
                $rangoCategorias = 'CategoriasExistentes!$A$1:$A$' . count($categoriasExistentes);
                for ($fila = 2; $fila <= $ultimaFilaValidacion; $fila++) {
                    $validacion = $hoja->getCell($letra . $fila)->getDataValidation();
                    $validacion->setType(DataValidation::TYPE_LIST);
                    $validacion->setAllowBlank(true);
                    $validacion->setShowDropDown(true);
                    $validacion->setShowErrorMessage(true);
                    $validacion->setErrorStyle(DataValidation::STYLE_INFORMATION);
                    $validacion->setErrorTitle('Categoría nueva');
                    $validacion->setError('Esa categoría no está en la lista — si la dejas así y confirmas la carga, se crea una categoría nueva con ese nombre.');
                    $validacion->setFormula1($rangoCategorias);
                }
            }

            $libro->setActiveSheetIndex(0);
        }

        return $libro;
    }

    /** "si"/"no" u otra lista corta y fija — no necesita una hoja aparte como las categorías. */
    private function agregarListaDesplegable(Worksheet $hoja, array $encabezado, string $columna, array $opciones, int $ultimaFila): void
    {
        $indice = array_search($columna, $encabezado, true);
        if ($indice === false) {
            return;
        }
        $letra = Coordinate::stringFromColumnIndex($indice + 1);
        for ($fila = 2; $fila <= $ultimaFila; $fila++) {
            $validacion = $hoja->getCell($letra . $fila)->getDataValidation();
            $validacion->setType(DataValidation::TYPE_LIST);
            $validacion->setAllowBlank(true);
            $validacion->setShowDropDown(true);
            $validacion->setShowErrorMessage(true);
            $validacion->setErrorStyle(DataValidation::STYLE_STOP);
            $validacion->setErrorTitle('Valor no válido');
            $validacion->setError('Escribe "si" o "no".');
            $validacion->setFormula1('"' . implode(',', $opciones) . '"');
        }
    }

    public function validar(array $filasCrudas): array
    {
        return $this->procesar($filasCrudas, false, 0);
    }

    public function confirmar(array $filasCrudas, int $usuarioId): array
    {
        return $this->procesar($filasCrudas, true, $usuarioId);
    }

    private function procesar(array $filasCrudas, bool $confirmar, int $usuarioId): array
    {
        $pdo = Database::conexion();
        $objProductos = new productos();
        $modulos = Configuracion::modulosActivos();

        $categoriasExistentes = [];
        foreach ($objProductos->listarCategorias() as $c) {
            $categoriasExistentes[$this->normalizarTexto($c['descripcion'])] = (int) $c['id_categoria'];
        }

        $codigosExistentes = [];
        foreach ($pdo->query("SELECT codigo FROM productos WHERE codigo IS NOT NULL AND codigo <> ''") as $r) {
            $codigosExistentes[$r['codigo']] = true;
        }

        $codigosEnArchivo = [];
        $categoriasNuevasClaves = [];
        $filasValidadas = [];

        foreach ($filasCrudas as $filaCruda) {
            if (!is_array($filaCruda)) {
                continue;
            }
            [$errores, $datos] = $this->validarFila($filaCruda, $categoriasExistentes, $codigosExistentes, $codigosEnArchivo, $categoriasNuevasClaves, $modulos);
            $filasValidadas[] = [
                'fila' => (int) ($filaCruda['_fila'] ?? 0),
                'valida' => empty($errores),
                'errores' => $errores,
                'datos' => $datos,
                'crudo' => $filaCruda,
            ];
        }

        if (!$confirmar) {
            return [
                'filas' => $filasValidadas,
                'categorias_nuevas' => array_values($categoriasNuevasClaves),
                'total' => count($filasValidadas),
                'validas' => count(array_filter($filasValidadas, fn($f) => $f['valida'])),
                'con_error' => count(array_filter($filasValidadas, fn($f) => !$f['valida'])),
            ];
        }

        $log = [];
        $creados = 0;
        $omitidos = 0;
        $idsCategoriasPorClave = $categoriasExistentes;

        $pdo->beginTransaction();
        try {
            foreach ($filasValidadas as $f) {
                $nombreFallback = $f['datos']['nombre_producto'] !== '' ? $f['datos']['nombre_producto'] : '(sin nombre)';

                if (!$f['valida']) {
                    $omitidos++;
                    $log[] = ['fila' => $f['fila'], 'estado' => 'error', 'producto' => $nombreFallback, 'mensaje' => implode(' ', $f['errores'])];
                    continue;
                }

                $datos = $f['datos'];

                if ($datos['codigo_producto'] !== '' && $objProductos->existeCodigo($datos['codigo_producto'])) {
                    $omitidos++;
                    $log[] = ['fila' => $f['fila'], 'estado' => 'error', 'producto' => $nombreFallback, 'mensaje' => "El código \"{$datos['codigo_producto']}\" ya existe (se creó entre que subiste el archivo y confirmaste)."];
                    continue;
                }

                $claveCategoria = $this->normalizarTexto($datos['categoria']);
                if (!isset($idsCategoriasPorClave[$claveCategoria])) {
                    $idsCategoriasPorClave[$claveCategoria] = $objProductos->crearCategoria($datos['categoria']);
                }

                $objProductos->crearProducto(
                    $datos['nombre_producto'],
                    $datos['codigo_producto'] !== '' ? $datos['codigo_producto'] : null,
                    $idsCategoriasPorClave[$claveCategoria],
                    $datos['precio_unitario'],
                    $datos['cantidad_existente'],
                    $datos['minimo_stock'],
                    1,
                    $datos['controla_stock'],
                    $datos['unidades_por_paquete'],
                    $datos['precio_paquete'],
                    $datos['costo_unitario'],
                    $datos['tasa_iva'],
                    false,
                    $datos['se_vende_por_peso']
                );
                $creados++;
                $log[] = ['fila' => $f['fila'], 'estado' => 'ok', 'producto' => $nombreFallback, 'mensaje' => 'Creado correctamente.'];
            }
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }

        if ($creados > 0) {
            Auditoria::registrar($usuarioId, 'productos.carga_masiva', 'producto', null, [
                'creados' => $creados,
                'omitidos' => $omitidos,
            ]);
        }

        return ['creados' => $creados, 'omitidos' => $omitidos, 'log' => $log];
    }

    private function validarFila(array $filaCruda, array $categoriasExistentes, array $codigosExistentes, array &$codigosEnArchivo, array &$categoriasNuevasClaves, array $modulos): array
    {
        $errores = [];
        $numero = (int) ($filaCruda['_fila'] ?? 0);

        $nombre = trim((string) ($filaCruda['nombre_producto'] ?? ''));
        if ($nombre === '') {
            $errores[] = 'Falta el nombre del producto.';
        }

        $categoriaTexto = trim((string) ($filaCruda['categoria'] ?? ''));
        if ($categoriaTexto === '') {
            $errores[] = 'Falta la categoría.';
        }

        $precio = $this->parsearNumero((string) ($filaCruda['precio_unitario'] ?? ''));
        if ($precio === null || $precio <= 0) {
            $errores[] = 'El precio unitario debe ser un número mayor a 0.';
        }

        $codigo = trim((string) ($filaCruda['codigo_producto'] ?? ''));
        if ($codigo !== '') {
            if (isset($codigosExistentes[$codigo])) {
                $errores[] = "El código \"$codigo\" ya existe en otro producto.";
            } elseif (isset($codigosEnArchivo[$codigo])) {
                $errores[] = "El código \"$codigo\" está repetido en el archivo (fila {$codigosEnArchivo[$codigo]}).";
            } else {
                $codigosEnArchivo[$codigo] = $numero;
            }
        }

        $cantidad = $this->parsearNumero((string) ($filaCruda['cantidad_existente'] ?? '')) ?? 0;
        if ($cantidad < 0) {
            $errores[] = 'La cantidad existente no puede ser negativa.';
        }

        $minimo = $this->parsearNumero((string) ($filaCruda['minimo_stock'] ?? '')) ?? 0;
        if ($minimo < 0) {
            $errores[] = 'El mínimo de stock no puede ser negativo.';
        }

        $controlaStock = $this->parsearSiNo((string) ($filaCruda['controla_stock'] ?? ''), true);
        if ($controlaStock === null) {
            $errores[] = 'La columna "controla_stock" debe decir "si" o "no".';
            $controlaStock = true;
        }

        // Sin el módulo "existencias" un producto importado nace sin inventario.
        if (!Configuracion::moduloActivo('existencias')) {
            $controlaStock = false;
        }

        $sePorPeso = $this->parsearSiNo((string) ($filaCruda['se_vende_por_peso'] ?? ''), false);
        if ($sePorPeso === null) {
            $errores[] = 'La columna "se_vende_por_peso" debe decir "si" o "no".';
            $sePorPeso = false;
        }

        $unidadesPorPaquete = 1;
        $precioPaquete = null;
        if ($modulos['paquetes']) {
            $unidadesTexto = trim((string) ($filaCruda['unidades_por_paquete'] ?? ''));
            $unidadesPorPaquete = $unidadesTexto === '' ? 1 : $this->parsearNumero($unidadesTexto);
            if ($unidadesPorPaquete === null || $unidadesPorPaquete < 1) {
                $errores[] = 'Unidades por paquete debe ser un número entero de al menos 1.';
                $unidadesPorPaquete = 1;
            }
            $precioPaqueteTexto = trim((string) ($filaCruda['precio_paquete'] ?? ''));
            if ($precioPaqueteTexto !== '') {
                $precioPaquete = $this->parsearNumero($precioPaqueteTexto);
                if ($precioPaquete === null || $precioPaquete <= 0) {
                    $errores[] = 'El precio de paquete debe ser un número mayor a 0 (o déjalo vacío).';
                    $precioPaquete = null;
                }
            }
        }

        $costo = 0.0;
        if ($modulos['costeo']) {
            $costo = $this->parsearNumero((string) ($filaCruda['costo_unitario'] ?? '')) ?? 0;
            if ($costo < 0) {
                $errores[] = 'El costo unitario no puede ser negativo.';
                $costo = 0;
            }
        }

        $tasaIva = 0.0;
        if ($modulos['iva']) {
            $tasaIva = $this->parsearNumero((string) ($filaCruda['tasa_iva'] ?? '')) ?? 0;
            if ($tasaIva < 0 || $tasaIva > 100) {
                $errores[] = 'La tasa de IVA debe estar entre 0 y 100.';
                $tasaIva = 0;
            }
        }

        if ($categoriaTexto !== '') {
            $clave = $this->normalizarTexto($categoriaTexto);
            if (!isset($categoriasExistentes[$clave]) && !isset($categoriasNuevasClaves[$clave])) {
                $categoriasNuevasClaves[$clave] = $categoriaTexto;
            }
        }

        $datos = [
            'nombre_producto' => $nombre,
            'categoria' => $categoriaTexto,
            'precio_unitario' => $precio ?? 0,
            'codigo_producto' => $codigo,
            'costo_unitario' => $costo,
            'cantidad_existente' => (int) $cantidad,
            'minimo_stock' => (int) $minimo,
            'controla_stock' => $controlaStock,
            'se_vende_por_peso' => $sePorPeso,
            'unidades_por_paquete' => (int) $unidadesPorPaquete,
            'precio_paquete' => $precioPaquete,
            'tasa_iva' => $tasaIva,
        ];

        return [$errores, $datos];
    }

    private function parsearNumero(string $valor): ?float
    {
        $valor = trim($valor);
        if ($valor === '') {
            return null;
        }
        $valor = str_replace(',', '.', $valor);
        if (!is_numeric($valor)) {
            return null;
        }

        return (float) $valor;
    }

    private function parsearSiNo(string $valor, bool $default): ?bool
    {
        $valor = mb_strtolower(trim($valor));
        if ($valor === '') {
            return $default;
        }
        if (in_array($valor, ['si', 'sí', 's', 'true', '1', 'yes'], true)) {
            return true;
        }
        if (in_array($valor, ['no', 'n', 'false', '0'], true)) {
            return false;
        }

        return null;
    }

    private function normalizarTexto(string $t): string
    {
        return mb_strtolower(trim($t));
    }
}
