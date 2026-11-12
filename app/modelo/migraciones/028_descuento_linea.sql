-- Migración 028: descuento manual puntual por línea de venta (ej. "esta
-- empanada de ayer al 50%"), independiente del ajuste global de la factura.

ALTER TABLE movimiento ADD COLUMN descuento_linea REAL NOT NULL DEFAULT 0;
