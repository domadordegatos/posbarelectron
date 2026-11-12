-- Migración 014: catálogo fijo de mesas (rediseño) — antes cada mesa era
-- un nombre libre que se escribía cada vez ("+ Nueva mesa"); ahora el
-- admin las configura una sola vez y en Ventas se ven como una cuadrícula
-- fija coloreada por estado (libre/ocupada), igual que un POS de
-- restaurante de verdad.

CREATE TABLE IF NOT EXISTS mesas (
    id_mesa INTEGER PRIMARY KEY AUTOINCREMENT,
    nombre TEXT NOT NULL,
    activa INTEGER NOT NULL DEFAULT 1
);

-- mesa_nombre se conserva (snapshot histórico, igual que costo_unitario en
-- movimiento) — mesa_id es la referencia real al catálogo fijo.
ALTER TABLE comandas ADD COLUMN mesa_id INTEGER REFERENCES mesas(id_mesa);
