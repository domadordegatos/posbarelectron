-- Migración 051: los motivos de "salida/entrada sin pérdida ni ganancia" (migración 050) eran
-- una lista fija escrita en PHP — el negocio no podía agregar los suyos. Pasa a ser un catálogo
-- editable, igual que conceptos_gasto (ver migración 035): se puede renombrar, agregar o
-- desactivar desde la interfaz. Queda sembrado con una lista básica que sirve para cualquier
-- negocio, para que no arranque vacío.
--
-- La columna "motivo_ajuste" (texto) que agregó la migración 050 se deja tal cual (nunca se
-- edita una migración ya aplicada) pero deja de usarse: de aquí en adelante el motivo se
-- referencia por id en "motivo_ajuste_id", igual que concepto_id en conceptos_gasto.

CREATE TABLE motivos_ajuste_inventario (
    id_motivo INTEGER PRIMARY KEY AUTOINCREMENT,
    nombre    TEXT NOT NULL,
    estado    INTEGER NOT NULL DEFAULT 1,
    creado_en TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- "canje" y "Canje" son el mismo motivo.
CREATE UNIQUE INDEX idx_motivos_ajuste_nombre ON motivos_ajuste_inventario (nombre COLLATE NOCASE);

ALTER TABLE movimiento ADD COLUMN motivo_ajuste_id INTEGER REFERENCES motivos_ajuste_inventario (id_motivo);

INSERT INTO motivos_ajuste_inventario (nombre) VALUES
    ('Canje / trueque'),
    ('Donación'),
    ('Muestra gratis'),
    ('Vencimiento (sin reportarlo como pérdida)'),
    ('Uso interno del negocio'),
    ('Otro');
