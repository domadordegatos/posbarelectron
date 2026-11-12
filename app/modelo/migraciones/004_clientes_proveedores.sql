-- Migración 004: clientes + venta a crédito, proveedores.

CREATE TABLE IF NOT EXISTS clientes (
    id_cliente INTEGER PRIMARY KEY AUTOINCREMENT,
    nombre TEXT NOT NULL,
    identificacion TEXT,
    telefono TEXT,
    direccion TEXT,
    limite_credito REAL NOT NULL DEFAULT 0,
    estado INTEGER NOT NULL DEFAULT 1,
    creado_en TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS proveedores (
    id_proveedor INTEGER PRIMARY KEY AUTOINCREMENT,
    nombre TEXT NOT NULL,
    nit TEXT,
    telefono TEXT,
    direccion TEXT,
    estado INTEGER NOT NULL DEFAULT 1,
    creado_en TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

ALTER TABLE facturas ADD COLUMN cliente_id INTEGER REFERENCES clientes(id_cliente);
ALTER TABLE facturas ADD COLUMN proveedor_id INTEGER REFERENCES proveedores(id_proveedor);

INSERT OR IGNORE INTO tipo_transaccion (id_transaccion, descripcion) VALUES (5, 'abono');
