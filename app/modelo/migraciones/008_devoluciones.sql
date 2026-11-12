-- Migración 008: devoluciones parciales.

ALTER TABLE movimiento ADD COLUMN cantidad_devuelta INTEGER NOT NULL DEFAULT 0;
ALTER TABLE movimiento ADD COLUMN movimiento_origen_id INTEGER REFERENCES movimiento(id_movimiento);

INSERT OR IGNORE INTO tipo_transaccion (id_transaccion, descripcion) VALUES (6, 'devolucion');
