-- Migración 035: catálogo de "conceptos" para compras/gastos y pérdidas que NO
-- son un producto del inventario: servicios (gas, luz, agua, internet,
-- arriendo), insumos (bolsas, vasos, servilletas, aseo) u otros. Antes,
-- Compras/Pérdidas solo aceptaba productos ya creados en el catálogo de
-- ventas, y para un recibo de la luz solo servía el "Egreso" suelto de Ventas.
--
-- Un concepto no tiene stock ni precio de venta: solo un nombre para no
-- reescribirlo cada vez y poder agrupar los gastos después.

CREATE TABLE conceptos_gasto (
    id_concepto INTEGER PRIMARY KEY AUTOINCREMENT,
    nombre      TEXT NOT NULL,
    categoria   TEXT NOT NULL DEFAULT 'otro' CHECK (categoria IN ('servicio', 'insumo', 'otro')),
    estado      INTEGER NOT NULL DEFAULT 1,
    creado_en   TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- "gas" y "Gas" son el mismo concepto.
CREATE UNIQUE INDEX idx_conceptos_gasto_nombre ON conceptos_gasto (nombre COLLATE NOCASE);

-- El movimiento (compra/gasto o pérdida) de un concepto lo referencia; los de
-- productos y los egresos sueltos de Ventas siguen con NULL.
ALTER TABLE movimiento ADD COLUMN concepto_id INTEGER REFERENCES conceptos_gasto (id_concepto);

INSERT INTO conceptos_gasto (nombre, categoria) VALUES
    ('Gas', 'servicio'),
    ('Luz', 'servicio'),
    ('Agua', 'servicio'),
    ('Internet', 'servicio'),
    ('Arriendo', 'servicio'),
    ('Bolsas plásticas', 'insumo'),
    ('Vasos y platos desechables', 'insumo'),
    ('Servilletas', 'insumo'),
    ('Aseo', 'insumo');
