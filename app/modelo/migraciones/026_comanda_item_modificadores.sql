-- Migración 026: las mesas/comandas ahora también recuerdan los
-- modificadores elegidos por cada ítem (ej. un helado con sus toppings) —
-- antes se perdían al guardar la mesa y retomarla después.

CREATE TABLE IF NOT EXISTS comanda_item_modificadores (
    id_detalle INTEGER PRIMARY KEY AUTOINCREMENT,
    comanda_item_id INTEGER NOT NULL REFERENCES comanda_items(id_item) ON DELETE CASCADE,
    modificador_id INTEGER NOT NULL REFERENCES modificadores(id_modificador),
    orden INTEGER NOT NULL DEFAULT 0
);

CREATE INDEX IF NOT EXISTS idx_comanda_item_modificadores_item ON comanda_item_modificadores(comanda_item_id);
