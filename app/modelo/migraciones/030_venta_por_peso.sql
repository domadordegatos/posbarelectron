-- Migración 030: productos que se venden por peso (báscula) — ej. carnes,
-- frutas y verduras, donde el precio por kilo puede variar incluso a
-- diario. Si el producto deja precio_unitario en 0, el cajero lo escribe
-- en el momento de la venta; si tiene un precio fijo (>0), ese manda
-- siempre. No se soporta inventario por peso en esta versión — igual que
-- los productos preparados al momento, se sirven "a ojo".

ALTER TABLE productos ADD COLUMN se_vende_por_peso INTEGER NOT NULL DEFAULT 0;
ALTER TABLE movimiento ADD COLUMN peso_kg REAL;
ALTER TABLE comanda_items ADD COLUMN peso_kg REAL;
