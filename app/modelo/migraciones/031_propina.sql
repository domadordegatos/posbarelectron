-- Migración 031: propina voluntaria en la venta — se registra aparte del
-- ajuste (no es un descuento/recargo sobre el precio) para poder
-- reportarla por separado.

ALTER TABLE facturas ADD COLUMN propina REAL NOT NULL DEFAULT 0;
