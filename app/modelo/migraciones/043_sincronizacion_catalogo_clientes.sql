-- Migración 043: identidad de origen + marca de tiempo para productos, categorías y clientes
-- — permite fusionar catálogo/clientes entre cajas con la regla "el cambio más reciente gana"
-- (last-write-wins, ver Sincronizacion::aplicarProducto()/aplicarCategoria()/aplicarCliente()),
-- y resolver "es la misma fila real" por (origen_caja_codigo, origen_id_x) sin inventar una
-- tabla de mapeo aparte. En una instalación de un solo equipo todo queda NULL para siempre.

ALTER TABLE productos ADD COLUMN origen_caja_codigo TEXT;
ALTER TABLE productos ADD COLUMN origen_id_producto INTEGER;
ALTER TABLE productos ADD COLUMN actualizado_en TEXT;

ALTER TABLE categorias ADD COLUMN origen_caja_codigo TEXT;
ALTER TABLE categorias ADD COLUMN origen_id_categoria INTEGER;
ALTER TABLE categorias ADD COLUMN actualizado_en TEXT;

ALTER TABLE clientes ADD COLUMN origen_caja_codigo TEXT;
ALTER TABLE clientes ADD COLUMN origen_id_cliente INTEGER;
ALTER TABLE clientes ADD COLUMN actualizado_en TEXT;

-- Índices únicos PARCIALES (solo sobre filas que ya tienen origen asignado): así ninguna fila
-- de una instalación de un solo equipo (origen NULL) puede chocar con otra bajo este índice.
CREATE UNIQUE INDEX IF NOT EXISTS ux_productos_origen ON productos(origen_caja_codigo, origen_id_producto)
    WHERE origen_caja_codigo IS NOT NULL;
CREATE UNIQUE INDEX IF NOT EXISTS ux_categorias_origen ON categorias(origen_caja_codigo, origen_id_categoria)
    WHERE origen_caja_codigo IS NOT NULL;
CREATE UNIQUE INDEX IF NOT EXISTS ux_clientes_origen ON clientes(origen_caja_codigo, origen_id_cliente)
    WHERE origen_caja_codigo IS NOT NULL;
