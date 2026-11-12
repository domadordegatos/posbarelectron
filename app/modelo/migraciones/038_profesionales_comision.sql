-- Migración 038: módulo "profesionales" — quién realizó cada servicio y cuánta
-- comisión le corresponde (peluquería, estética, taller…). Se asigna un
-- profesional a cada línea de la venta; el porcentaje de comisión del
-- profesional se GUARDA en la línea al vender (si después cambia el
-- porcentaje, lo ya vendido no cambia — igual que el costo y el IVA).
--
-- El módulo arranca APAGADO (DEFAULT 0): es una función nueva y ninguna
-- instalación existente la tenía; el proveedor la enciende donde aplica.

CREATE TABLE profesionales (
    id_profesional      INTEGER PRIMARY KEY AUTOINCREMENT,
    nombre              TEXT NOT NULL,
    comision_porcentaje REAL NOT NULL DEFAULT 0 CHECK (comision_porcentaje >= 0 AND comision_porcentaje <= 100),
    estado              INTEGER NOT NULL DEFAULT 1,
    creado_en           TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- "Ana" y "ana" son la misma persona.
CREATE UNIQUE INDEX idx_profesionales_nombre ON profesionales (nombre COLLATE NOCASE);

-- La línea de venta guarda quién y el porcentaje/valor de comisión vigentes al vender.
ALTER TABLE movimiento ADD COLUMN profesional_id INTEGER REFERENCES profesionales (id_profesional);
ALTER TABLE movimiento ADD COLUMN comision_porcentaje REAL NOT NULL DEFAULT 0;
ALTER TABLE movimiento ADD COLUMN comision_valor REAL NOT NULL DEFAULT 0;
CREATE INDEX idx_movimiento_profesional ON movimiento (profesional_id);

-- Una mesa abierta recuerda a quién se le asignó cada línea hasta que se cobre.
ALTER TABLE comanda_items ADD COLUMN profesional_id INTEGER REFERENCES profesionales (id_profesional);

ALTER TABLE configuracion_negocio ADD COLUMN mod_profesionales INTEGER NOT NULL DEFAULT 0;
