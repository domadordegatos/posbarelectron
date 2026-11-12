-- Migración 005: caja / turnos.

CREATE TABLE IF NOT EXISTS turnos (
    id_turno INTEGER PRIMARY KEY AUTOINCREMENT,
    usuario_id INTEGER NOT NULL REFERENCES usuarios(id_usuario),
    fecha_apertura TEXT NOT NULL,
    hora_apertura TEXT NOT NULL,
    monto_apertura REAL NOT NULL DEFAULT 0,
    estado TEXT NOT NULL DEFAULT 'abierto',
    fecha_cierre TEXT,
    hora_cierre TEXT,
    monto_cierre_real REAL,
    monto_esperado REAL,
    diferencia REAL,
    observaciones TEXT,
    cerrado_por INTEGER REFERENCES usuarios(id_usuario)
);

ALTER TABLE facturas ADD COLUMN turno_id INTEGER REFERENCES turnos(id_turno);
