-- Migración 003: IVA por producto + combos/kits.

ALTER TABLE productos ADD COLUMN tasa_iva REAL NOT NULL DEFAULT 0;
ALTER TABLE productos ADD COLUMN es_combo INTEGER NOT NULL DEFAULT 0;

ALTER TABLE movimiento ADD COLUMN tasa_iva REAL NOT NULL DEFAULT 0;
ALTER TABLE movimiento ADD COLUMN valor_iva REAL NOT NULL DEFAULT 0;

CREATE TABLE IF NOT EXISTS producto_componentes (
    id_componente INTEGER PRIMARY KEY AUTOINCREMENT,
    producto_combo_id INTEGER NOT NULL REFERENCES productos(id_producto),
    producto_base_id INTEGER NOT NULL REFERENCES productos(id_producto),
    cantidad INTEGER NOT NULL DEFAULT 1
);

CREATE TABLE IF NOT EXISTS movimiento_combo_detalle (
    id_detalle INTEGER PRIMARY KEY AUTOINCREMENT,
    id_movimiento INTEGER NOT NULL REFERENCES movimiento(id_movimiento),
    producto_id INTEGER NOT NULL REFERENCES productos(id_producto),
    unidades_descontadas INTEGER NOT NULL DEFAULT 0
);

CREATE INDEX IF NOT EXISTS idx_producto_componentes_combo ON producto_componentes(producto_combo_id);
CREATE INDEX IF NOT EXISTS idx_movimiento_combo_detalle_mov ON movimiento_combo_detalle(id_movimiento);
