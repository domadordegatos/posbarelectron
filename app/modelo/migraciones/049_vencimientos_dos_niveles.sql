-- Migración 049: la semaforización de vencimientos pasa a dos niveles — un "vencimiento" (lote +
-- fecha límite, UNA sola vez) puede tener VARIOS avisos (cuántos días antes avisar), sin repetir la
-- fecha cada vez que se agrega un aviso nuevo. Reemplaza la tabla plana de la migración 048 (recién
-- creada, sin datos de clientes reales todavía) por dos tablas relacionadas.

CREATE TABLE IF NOT EXISTS producto_vencimientos (
    id_vencimiento INTEGER PRIMARY KEY AUTOINCREMENT,
    producto_id INTEGER NOT NULL REFERENCES productos(id_producto),
    descripcion_lote TEXT,
    fecha_vencimiento TEXT NOT NULL,
    creado_en TEXT NOT NULL DEFAULT (datetime('now','localtime'))
);
CREATE INDEX IF NOT EXISTS idx_producto_vencimientos_producto ON producto_vencimientos(producto_id);
CREATE INDEX IF NOT EXISTS idx_producto_vencimientos_fecha ON producto_vencimientos(fecha_vencimiento);

CREATE TABLE IF NOT EXISTS producto_vencimiento_alertas (
    id_alerta INTEGER PRIMARY KEY AUTOINCREMENT,
    vencimiento_id INTEGER NOT NULL REFERENCES producto_vencimientos(id_vencimiento),
    dias_antes INTEGER NOT NULL DEFAULT 0,
    creado_en TEXT NOT NULL DEFAULT (datetime('now','localtime'))
);
CREATE INDEX IF NOT EXISTS idx_vencimiento_alertas_vencimiento ON producto_vencimiento_alertas(vencimiento_id);

-- Traslada lo que ya se haya guardado con la tabla plana anterior. Si dos filas viejas compartían
-- exactamente el mismo producto + lote + fecha, quedan agrupadas bajo el mismo vencimiento nuevo
-- (justo el comportamiento que se quería desde el principio).
INSERT INTO producto_vencimientos (producto_id, descripcion_lote, fecha_vencimiento, creado_en)
SELECT producto_id, descripcion_lote, fecha_vencimiento, MIN(creado_en)
FROM producto_alertas_vencimiento
GROUP BY producto_id, COALESCE(descripcion_lote, ''), fecha_vencimiento;

INSERT INTO producto_vencimiento_alertas (vencimiento_id, dias_antes, creado_en)
SELECT v.id_vencimiento, a.dias_antes, a.creado_en
FROM producto_alertas_vencimiento a
JOIN producto_vencimientos v
  ON v.producto_id = a.producto_id
 AND COALESCE(v.descripcion_lote, '') = COALESCE(a.descripcion_lote, '')
 AND v.fecha_vencimiento = a.fecha_vencimiento;

DROP TABLE producto_alertas_vencimiento;
