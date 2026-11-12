-- Migración 032: cuadrícula de Ventas agrupada por categoría, como
-- alternativa a la cuadrícula de productos elegidos a mano que ya existía
-- (destacado_orden). El negocio elige el modo desde Configuración.

-- 'productos' (como hoy) o 'categorias' (nuevo).
ALTER TABLE configuracion_negocio ADD COLUMN ventas_cuadricula_modo TEXT NOT NULL DEFAULT 'productos';

-- NULL = la categoría no aparece en la cuadrícula; un número = sí aparece,
-- en ese orden — mismo patrón que productos.destacado_orden.
ALTER TABLE categorias ADD COLUMN orden_cuadricula INTEGER NULL DEFAULT NULL;

-- Orden manual de un producto DENTRO de la cuadrícula de su categoría
-- (independiente de destacado_orden, que es para el otro modo).
ALTER TABLE productos ADD COLUMN orden_categoria INTEGER NOT NULL DEFAULT 0;
