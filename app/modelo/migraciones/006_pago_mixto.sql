-- Migración 006: pago mixto. Necesita ensanchar el CHECK de
-- facturas.metodo_pago (SQLite no permite alterar un CHECK con ALTER
-- TABLE, así que se reconstruye la tabla siguiendo el procedimiento que
-- la propia documentación de SQLite recomienda para esto).

PRAGMA foreign_keys = OFF;

CREATE TABLE facturas_nueva (
    id_factura INTEGER PRIMARY KEY AUTOINCREMENT,
    fecha TEXT NOT NULL,
    hora TEXT NOT NULL,
    usuario_id INTEGER NOT NULL REFERENCES usuarios(id_usuario),
    tipo_transaccion INTEGER NOT NULL REFERENCES tipo_transaccion(id_transaccion),
    total REAL NOT NULL DEFAULT 0,
    ajuste REAL NOT NULL DEFAULT 0,
    metodo_pago TEXT NOT NULL DEFAULT 'efectivo'
        CHECK (metodo_pago IN ('efectivo', 'tarjeta', 'transferencia', 'credito', 'mixto')),
    anulada INTEGER NOT NULL DEFAULT 0,
    anulada_motivo TEXT,
    anulada_por INTEGER REFERENCES usuarios(id_usuario),
    anulada_en TEXT,
    cliente_id INTEGER REFERENCES clientes(id_cliente),
    proveedor_id INTEGER REFERENCES proveedores(id_proveedor),
    turno_id INTEGER REFERENCES turnos(id_turno)
);

INSERT INTO facturas_nueva
    (id_factura, fecha, hora, usuario_id, tipo_transaccion, total, ajuste, metodo_pago,
     anulada, anulada_motivo, anulada_por, anulada_en, cliente_id, proveedor_id, turno_id)
SELECT id_factura, fecha, hora, usuario_id, tipo_transaccion, total, ajuste, metodo_pago,
       anulada, anulada_motivo, anulada_por, anulada_en, cliente_id, proveedor_id, turno_id
FROM facturas;

DROP TABLE facturas;
ALTER TABLE facturas_nueva RENAME TO facturas;

PRAGMA foreign_keys = ON;

CREATE TABLE IF NOT EXISTS factura_pagos (
    id_pago INTEGER PRIMARY KEY AUTOINCREMENT,
    id_factura INTEGER NOT NULL REFERENCES facturas(id_factura) ON DELETE CASCADE,
    metodo_pago TEXT NOT NULL CHECK (metodo_pago IN ('efectivo', 'tarjeta', 'transferencia', 'credito')),
    valor REAL NOT NULL DEFAULT 0
);

CREATE INDEX IF NOT EXISTS idx_factura_pagos_factura ON factura_pagos(id_factura);

-- Backfill: cada factura de venta/egreso ya existente pasa a tener su
-- propia fila de pago único, igual que hicimos en la versión MySQL.
INSERT INTO factura_pagos (id_factura, metodo_pago, valor)
SELECT f.id_factura, f.metodo_pago, f.total
FROM facturas f
WHERE f.tipo_transaccion IN (1, 2)
  AND NOT EXISTS (SELECT 1 FROM factura_pagos fp WHERE fp.id_factura = f.id_factura);
