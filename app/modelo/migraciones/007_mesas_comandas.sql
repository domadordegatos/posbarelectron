-- Migración 007: mesas / comandas.

CREATE TABLE IF NOT EXISTS comandas (
    id_comanda INTEGER PRIMARY KEY AUTOINCREMENT,
    mesa_nombre TEXT NOT NULL,
    usuario_id INTEGER NOT NULL REFERENCES usuarios(id_usuario),
    fecha_apertura TEXT NOT NULL,
    hora_apertura TEXT NOT NULL,
    estado TEXT NOT NULL DEFAULT 'abierta',
    id_factura INTEGER REFERENCES facturas(id_factura)
);

CREATE TABLE IF NOT EXISTS comanda_items (
    id_item INTEGER PRIMARY KEY AUTOINCREMENT,
    comanda_id INTEGER NOT NULL REFERENCES comandas(id_comanda) ON DELETE CASCADE,
    producto_id INTEGER NOT NULL REFERENCES productos(id_producto),
    presentacion TEXT NOT NULL DEFAULT 'unidad',
    cantidad INTEGER NOT NULL DEFAULT 1
);

CREATE INDEX IF NOT EXISTS idx_comanda_items_comanda ON comanda_items(comanda_id);
