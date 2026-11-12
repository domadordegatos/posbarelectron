<?php
require_once __DIR__ . '/productos.php';
require_once __DIR__ . '/Configuracion.php';
require_once __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

/**
 * Lo opuesto de ImportacionProductos: en vez de traer productos hacia el sistema desde un
 * Excel, saca el catálogo completo que ya existe hacia un .xlsx real — para respaldo, revisar
 * en Excel, o mandarle la lista a alguien. Mismas columnas que "Ver datos completos" del
 * catálogo en pantalla.
 */
class ExportacionProductos
{
    public function generarXlsx(): Spreadsheet
    {
        $modulos = Configuracion::modulosActivos();
        $productos = (new productos())->listarTodos();

        $columnas = [
            ['titulo' => 'Producto', 'valor' => fn(array $p) => $p['nombre_producto']],
            ['titulo' => 'Código', 'valor' => fn(array $p) => (string) ($p['codigo'] ?? ''), 'texto' => true],
            ['titulo' => 'Categoría', 'valor' => fn(array $p) => $p['categoria']],
            ['titulo' => 'Precio', 'valor' => fn(array $p) => (float) $p['precio_unitario']],
        ];
        if ($modulos['costeo']) {
            $columnas[] = ['titulo' => 'Costo', 'valor' => fn(array $p) => (float) $p['costo_unitario']];
        }
        if ($modulos['iva']) {
            $columnas[] = ['titulo' => 'IVA (%)', 'valor' => fn(array $p) => (float) $p['tasa_iva']];
        }
        if ($modulos['existencias']) {
            $columnas[] = ['titulo' => 'Existencia', 'valor' => fn(array $p) => $p['controla_stock'] ? (int) $p['cantidad_existente'] : 'Sin control'];
            $columnas[] = ['titulo' => 'Mínimo', 'valor' => fn(array $p) => $p['controla_stock'] ? (int) $p['minimo_stock'] : ''];
        }
        $columnas[] = ['titulo' => 'Unidades por paquete', 'valor' => fn(array $p) => (int) $p['unidades_por_paquete']];
        $columnas[] = ['titulo' => 'Precio paquete', 'valor' => fn(array $p) => $p['precio_paquete'] !== null ? (float) $p['precio_paquete'] : ''];
        $columnas[] = ['titulo' => 'Tipo', 'valor' => fn(array $p) => $p['es_combo'] ? 'Combo' : ($p['se_vende_por_peso'] ? 'Por peso' : 'Unidad')];
        $columnas[] = ['titulo' => 'Estado', 'valor' => fn(array $p) => $p['estado'] ? 'Activo' : 'Inactivo'];

        $totalColumnas = count($columnas);

        $libro = new Spreadsheet();
        $hoja = $libro->getActiveSheet();
        $hoja->setTitle('Productos');

        foreach ($columnas as $i => $columna) {
            $letra = Coordinate::stringFromColumnIndex($i + 1);
            $hoja->setCellValue($letra . '1', $columna['titulo']);
            $hoja->getColumnDimension($letra)->setAutoSize(true);
            if (!empty($columna['texto'])) {
                $hoja->getStyle("{$letra}2:{$letra}" . (count($productos) + 1))->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_TEXT);
            }
        }

        $rangoEncabezado = 'A1:' . Coordinate::stringFromColumnIndex($totalColumnas) . '1';
        $hoja->getStyle($rangoEncabezado)->getFont()->setBold(true);
        $hoja->getStyle($rangoEncabezado)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('EEF2FF');
        $hoja->freezePane('A2');

        $filaActual = 2;
        foreach ($productos as $producto) {
            foreach ($columnas as $i => $columna) {
                $coordenada = Coordinate::stringFromColumnIndex($i + 1) . $filaActual;
                $valor = $columna['valor']($producto);
                if (!empty($columna['texto']) && $valor !== '') {
                    $hoja->setCellValueExplicit($coordenada, $valor, DataType::TYPE_STRING);
                } else {
                    $hoja->setCellValue($coordenada, $valor);
                }
            }
            $filaActual++;
        }

        return $libro;
    }
}
