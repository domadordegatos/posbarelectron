-- Migración 022: grupos de modificadores por producto (ej. "3 toppings
-- incluidos" en un helado especial, con extras que sí se cobran) — módulo
-- activable desde el panel de superadministrador, igual que combos/IVA.

ALTER TABLE configuracion_negocio ADD COLUMN mod_modificadores INTEGER NOT NULL DEFAULT 0;

CREATE TABLE IF NOT EXISTS grupos_modificadores (
    id_grupo INTEGER PRIMARY KEY AUTOINCREMENT,
    producto_id INTEGER NOT NULL REFERENCES productos(id_producto),
    nombre TEXT NOT NULL,
    incluidos_gratis INTEGER NOT NULL DEFAULT 0,
    minimo INTEGER NOT NULL DEFAULT 0,
    maximo INTEGER,
    orden INTEGER NOT NULL DEFAULT 0
);

CREATE TABLE IF NOT EXISTS modificadores (
    id_modificador INTEGER PRIMARY KEY AUTOINCREMENT,
    grupo_id INTEGER NOT NULL REFERENCES grupos_modificadores(id_grupo),
    nombre TEXT NOT NULL,
    precio_adicional REAL NOT NULL DEFAULT 0,
    orden INTEGER NOT NULL DEFAULT 0
);

-- Una fila por cada modificador elegido en una línea de venta (gratis o
-- cobrado) — snapshot del nombre/precio para que la comanda y el historial
-- no cambien si luego se edita o borra el modificador original.
CREATE TABLE IF NOT EXISTS movimiento_modificadores (
    id_detalle INTEGER PRIMARY KEY AUTOINCREMENT,
    id_movimiento INTEGER NOT NULL REFERENCES movimiento(id_movimiento),
    modificador_id INTEGER REFERENCES modificadores(id_modificador),
    nombre_snapshot TEXT NOT NULL,
    precio_cobrado REAL NOT NULL DEFAULT 0,
    incluido INTEGER NOT NULL DEFAULT 0
);

CREATE INDEX IF NOT EXISTS idx_grupos_modificadores_producto ON grupos_modificadores(producto_id);
CREATE INDEX IF NOT EXISTS idx_modificadores_grupo ON modificadores(grupo_id);
CREATE INDEX IF NOT EXISTS idx_movimiento_modificadores_mov ON movimiento_modificadores(id_movimiento);
