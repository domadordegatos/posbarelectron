-- Migración 019: promociones/descuentos programados por producto (ej.
-- happy hour) — módulo activable desde el panel de superadministrador,
-- igual que combos/IVA/mesas.

ALTER TABLE configuracion_negocio ADD COLUMN mod_promociones INTEGER NOT NULL DEFAULT 0;

CREATE TABLE IF NOT EXISTS promociones (
    id_promocion INTEGER PRIMARY KEY AUTOINCREMENT,
    nombre TEXT NOT NULL,
    producto_id INTEGER NOT NULL REFERENCES productos(id_producto),
    tipo_descuento TEXT NOT NULL CHECK (tipo_descuento IN ('porcentaje', 'fijo')),
    valor REAL NOT NULL,
    dias_semana TEXT,
    hora_inicio TEXT,
    hora_fin TEXT,
    estado INTEGER NOT NULL DEFAULT 1,
    creado_en TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_promociones_producto ON promociones(producto_id);
