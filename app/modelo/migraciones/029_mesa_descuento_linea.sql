-- Migración 029: las mesas también recuerdan el descuento manual por línea
-- (mismo motivo que 026 con los modificadores — si no, se perdería al
-- guardar la mesa y retomarla después).

ALTER TABLE comanda_items ADD COLUMN descuento_tipo TEXT;
ALTER TABLE comanda_items ADD COLUMN descuento_valor REAL NOT NULL DEFAULT 0;
