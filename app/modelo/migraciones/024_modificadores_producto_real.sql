-- Migración 024: cada "modificador" pasa a apuntar a un producto real del
-- catálogo (en vez de un nombre/precio escritos a mano) — así el nombre y
-- el precio siempre se leen en vivo del producto (se actualizan solos si
-- se edita), y ese mismo producto se puede seguir vendiendo suelto si el
-- tendero lo agrega a la cuadrícula. Si el producto del topping controla
-- inventario, usarlo como modificador también descuenta su stock — igual
-- que ya pasa con los componentes de un combo; si no lo controla (ej. un
-- helado servido a ojo, sin cantidad exacta), no se descuenta nada.

ALTER TABLE modificadores ADD COLUMN producto_id INTEGER REFERENCES productos(id_producto);

ALTER TABLE movimiento_modificadores ADD COLUMN producto_id INTEGER REFERENCES productos(id_producto);
ALTER TABLE movimiento_modificadores ADD COLUMN afecto_stock INTEGER NOT NULL DEFAULT 0;
ALTER TABLE movimiento_modificadores ADD COLUMN unidades_descontadas INTEGER NOT NULL DEFAULT 0;

CREATE INDEX IF NOT EXISTS idx_modificadores_producto ON modificadores(producto_id);
