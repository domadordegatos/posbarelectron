-- Destino de cada línea al anular una venta: 'inventario' (el producto volvió a existencias) o
-- 'descartado' (se perdió: no repone stock y queda como pérdida). NULL = línea no anulada.
ALTER TABLE movimiento ADD COLUMN anulacion_destino TEXT;
