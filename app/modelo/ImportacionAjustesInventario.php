<?php
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/productos.php';
require_once __DIR__ . '/motivos_ajuste.php';
require_once __DIR__ . '/inventario.php';
require_once __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx as LectorXlsx;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Carga masiva de "salidas/entradas sin pérdida ni ganancia" (ver inventario.php y migración 050)
 * desde un Excel — mismo patrón de previsualizar → confirmar que ImportacionProductos.php, pero
 * sin categorías nuevas: el motivo tiene que ser uno que ya exista en motivos_ajuste_inventario
 * (se administran aparte, en Inventario > Salida/entrada > Administrar motivos).
 */
class ImportacionAjustesInventario
{
    public const COLUMNAS_BASE = ['producto', 'direccion', 'cantidad', 'motivo', 'descripcion'];

    public function leer(string $rutaArchivo): array
    {
        try {
            $lector = new LectorXlsx();
            $lector->setReadDataOnly(true);
            $libro = $lector->load($rutaArchivo);
        } catch (\Throwable $e) {
            throw new RuntimeException('No se pudo leer el archivo de Excel — asegúrate de que sea un .xlsx válido.');
        }

        $filasCeldas = $libro->getActiveSheet()->toArray(null, true, true, false);
        if (empty($filasCeldas)) {
            throw new RuntimeException('El archivo está vacío.');
        }

        $encabezadoCrudo = array_shift($filasCeldas);
        $encabezado = array_map(fn($c) => strtolower(trim($this->celdaATexto($c))), $encabezadoCrudo);
        foreach (self::COLUMNAS_BASE as $columnaObligatoria) {
            if (!in_array($columnaObligatoria, $encabezado, true)) {
                throw new RuntimeException("Al archivo le falta la columna \"$columnaObligatoria\". Descarga la plantilla de nuevo y no cambies los encabezados.");
            }
        }

        $filas = [];
        $numeroFila = 1;
        foreach ($filasCeldas as $linea) {
            $numeroFila++;
            $vacia = true;
            foreach ($linea as $valor) {
                if (trim($this->celdaATexto($valor)) !== '') {
                    $vacia = false;
                    break;
                }
            }
            if ($vacia) {
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
        return [
            'encabezado' => self::COLUMNAS_BASE,
            'ejemplos' => [
                ['Jeringas x100', 'salida', '20', 'Canje / trueque', 'Intercambio con Droguería XYZ, lote L2024-08'],
                ['Guantes caja', 'entrada', '5', 'Otro', 'Devolución de un préstamo entre sucursales'],
            ],
        ];
    }

    /** Plantilla .xlsx real, con desplegables de dirección y de los motivos que ya existen. */
    public function generarPlantillaXlsx(array $motivosExistentes): Spreadsheet
    {
        $plantilla = $this->datosPlantilla();
        $encabezado = $plantilla['encabezado'];
        $totalColumnas = count($encabezado);
        $ultimaFilaValidacion = 500;

        $ayudaPorColumna = [
            'producto' => 'Obligatorio. El código de barras del producto, o si no tiene, su nombre EXACTO tal como está en el catálogo.',
            'direccion' => 'Obligatorio. "salida" (sale del inventario) o "entrada" (vuelve a entrar).',
            'cantidad' => 'Obligatorio. Un número entero mayor a 0.',
            'motivo' => 'Obligatorio. Tiene que ser uno de los que ya existen (Inventario > Salida/entrada > Administrar motivos) — este archivo no crea motivos nuevos.',
            'descripcion' => 'Opcional. El detalle libre (ej. con quién fue el canje, qué lote).',
        ];

        $libro = new Spreadsheet();
        $hoja = $libro->getActiveSheet();
        $hoja->setTitle('Ajustes');

        foreach ($encabezado as $i => $nombreColumna) {
            $letra = Coordinate::stringFromColumnIndex($i + 1);
            $hoja->setCellValue($letra . '1', $nombreColumna);
            if (isset($ayudaPorColumna[$nombreColumna])) {
                $hoja->getComment($letra . '1')->getText()->createTextRun($ayudaPorColumna[$nombreColumna]);
                $hoja->getComment($letra . '1')->setWidth('240pt')->setHeight('80pt');
            }
            $hoja->getColumnDimension($letra)->setAutoSize(true);
        }

        $rangoEncabezado = 'A1:' . Coordinate::stringFromColumnIndex($totalColumnas) . '1';
        $hoja->getStyle($rangoEncabezado)->getFont()->setBold(true);
        $hoja->getStyle($rangoEncabezado)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('EEF2FF');
        $hoja->freezePane('A2');

        $filaActual = 2;
        foreach ($plantilla['ejemplos'] as $filaEjemplo) {
            foreach ($filaEjemplo as $i => $valor) {
                $hoja->setCellValue(Coordinate::stringFromColumnIndex($i + 1) . $filaActual, $valor);
            }
            $filaActual++;
        }
        $rangoEjemplos = 'A2:' . Coordinate::stringFromColumnIndex($totalColumnas) . (count($plantilla['ejemplos']) + 1);
        $hoja->getStyle($rangoEjemplos)->getFont()->setItalic(true)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('FF92400E'));
        $hoja->getStyle($rangoEjemplos)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FEF3C7');

        $this->agregarListaDesplegable($hoja, $encabezado, 'direccion', ['salida', 'entrada'], $ultimaFilaValidacion);

        if (!empty($motivosExistentes)) {
            $hojaMotivos = $libro->createSheet();
            $hojaMotivos->setTitle('MotivosExistentes');
            foreach (array_values($motivosExistentes) as $i => $nombreMotivo) {
                $hojaMotivos->setCellValue('A' . ($i + 1), $nombreMotivo);
            }
            $hojaMotivos->setSheetState(Worksheet::SHEETSTATE_HIDDEN);

            $indiceMotivo = array_search('motivo', $encabezado, true);
            if ($indiceMotivo !== false) {
                $letra = Coordinate::stringFromColumnIndex($indiceMotivo + 1);
                $rango = 'MotivosExistentes!$A$1:$A$' . count($motivosExistentes);
                for ($fila = 2; $fila <= $ultimaFilaValidacion; $fila++) {
                    $validacion = $hoja->getCell($letra . $fila)->getDataValidation();
                    $validacion->setType(DataValidation::TYPE_LIST);
                    $validacion->setAllowBlank(true);
                    $validacion->setShowDropDown(true);
                    $validacion->setShowErrorMessage(true);
                    $validacion->setErrorStyle(DataValidation::STYLE_STOP);
                    $validacion->setErrorTitle('Motivo inexistente');
                    $validacion->setError('Ese motivo no existe — créalo antes en Inventario > Salida/entrada > Administrar motivos, o elige uno de la lista.');
                    $validacion->setFormula1($rango);
                }
            }
            $libro->setActiveSheetIndex(0);
        }

        return $libro;
    }

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
            $validacion->setError('Elige "salida" o "entrada".');
            $validacion->setFormula1('"' . implode(',', $opciones) . '"');
        }
    }

    /** Valida sin escribir nada — misma forma de respuesta que ImportacionProductos::validar(). */
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
        $motivosPorNombre = [];
        foreach ((new motivos_ajuste())->listarActivos() as $m) {
            $motivosPorNombre[mb_strtolower($m['nombre'])] = (int) $m['id_motivo'];
        }

        $filasValidadas = [];
        foreach ($filasCrudas as $filaCruda) {
            if (!is_array($filaCruda)) {
                continue;
            }
            [$errores, $datos] = $this->validarFila($filaCruda, $motivosPorNombre);
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
                'total' => count($filasValidadas),
                'validas' => count(array_filter($filasValidadas, fn($f) => $f['valida'])),
                'con_error' => count(array_filter($filasValidadas, fn($f) => !$f['valida'])),
            ];
        }

        $inv = new inventario();
        $log = [];
        $creados = 0;
        $omitidos = 0;
        foreach ($filasValidadas as $f) {
            $nombreFallback = $f['datos']['producto_nombre'] ?? ($f['crudo']['producto'] ?? '(sin nombre)');
            if (!$f['valida']) {
                $omitidos++;
                $log[] = ['fila' => $f['fila'], 'estado' => 'error', 'producto' => $nombreFallback, 'mensaje' => implode(' ', $f['errores'])];
                continue;
            }
            $datos = $f['datos'];
            try {
                $inv->registrarAjusteInventario(
                    $usuarioId,
                    $datos['producto_id'],
                    $datos['direccion'],
                    'unidad',
                    $datos['cantidad'],
                    $datos['motivo_id'],
                    $datos['descripcion']
                );
                $creados++;
                $log[] = ['fila' => $f['fila'], 'estado' => 'ok', 'producto' => $nombreFallback, 'mensaje' => 'Registrado correctamente.'];
            } catch (Throwable $e) {
                $omitidos++;
                $log[] = ['fila' => $f['fila'], 'estado' => 'error', 'producto' => $nombreFallback, 'mensaje' => $e->getMessage()];
            }
        }

        return ['creados' => $creados, 'omitidos' => $omitidos, 'log' => $log];
    }

    private function validarFila(array $filaCruda, array $motivosPorNombre): array
    {
        $errores = [];

        $terminoProducto = trim((string) ($filaCruda['producto'] ?? ''));
        $producto = null;
        if ($terminoProducto === '') {
            $errores[] = 'Falta el producto (código o nombre exacto).';
        } else {
            $producto = $this->buscarProducto($terminoProducto);
            if ($producto === null) {
                $errores[] = "No se encontró ningún producto con código o nombre exacto \"$terminoProducto\".";
            } elseif ((int) $producto['controla_stock'] !== 1) {
                $errores[] = "\"{$producto['nombre_producto']}\" no controla inventario — no se le pueden registrar salidas/entradas.";
            }
        }

        $direccionTexto = mb_strtolower(trim((string) ($filaCruda['direccion'] ?? '')));
        $direccion = null;
        if (!in_array($direccionTexto, ['salida', 'entrada'], true)) {
            $errores[] = 'La columna "direccion" debe decir "salida" o "entrada".';
        } else {
            $direccion = $direccionTexto;
        }

        $cantidadTexto = trim((string) ($filaCruda['cantidad'] ?? ''));
        $cantidad = ctype_digit($cantidadTexto) ? (int) $cantidadTexto : null;
        if ($cantidad === null || $cantidad <= 0) {
            $errores[] = 'La cantidad debe ser un número entero mayor a 0.';
        }

        if ($producto !== null && $direccion === 'salida' && $cantidad !== null && (int) $producto['cantidad_existente'] < $cantidad) {
            $errores[] = "No hay suficiente existencia de \"{$producto['nombre_producto']}\" (disponible: {$producto['cantidad_existente']}).";
        }

        $motivoTexto = trim((string) ($filaCruda['motivo'] ?? ''));
        $idMotivo = null;
        if ($motivoTexto === '') {
            $errores[] = 'Falta el motivo.';
        } elseif (!isset($motivosPorNombre[mb_strtolower($motivoTexto)])) {
            $errores[] = "El motivo \"$motivoTexto\" no existe — créalo antes en Administrar motivos, o usa uno de la lista.";
        } else {
            $idMotivo = $motivosPorNombre[mb_strtolower($motivoTexto)];
        }

        $datos = [
            'producto_id' => $producto['id_producto'] ?? null,
            'producto_nombre' => $producto['nombre_producto'] ?? $terminoProducto,
            'direccion' => $direccion,
            'cantidad' => $cantidad,
            'motivo' => $motivoTexto,
            'motivo_id' => $idMotivo,
            'descripcion' => trim((string) ($filaCruda['descripcion'] ?? '')),
        ];

        return [$errores, $datos];
    }

    /** Primero coincidencia exacta de código, si no, nombre exacto (sin importar mayúsculas). */
    private function buscarProducto(string $termino): ?array
    {
        $pdo = Database::conexion();
        $stmt = $pdo->prepare(
            'SELECT id_producto, nombre_producto, controla_stock, cantidad_existente
             FROM productos WHERE estado = 1 AND codigo = :codigo LIMIT 1'
        );
        $stmt->execute(['codigo' => $termino]);
        $fila = $stmt->fetch();
        if ($fila) {
            return $fila;
        }

        $stmt = $pdo->prepare(
            'SELECT id_producto, nombre_producto, controla_stock, cantidad_existente
             FROM productos WHERE estado = 1 AND nombre_producto = :nombre COLLATE NOCASE LIMIT 1'
        );
        $stmt->execute(['nombre' => $termino]);

        return $stmt->fetch() ?: null;
    }
}
