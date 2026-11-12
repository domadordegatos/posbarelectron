-- Migración 048: semaforización de vencimientos — alertas configurables por producto para avisar
-- cuando se acerca una fecha límite (ej. lotes próximos a vencer, insumos regulados). Es informativo:
-- no reduce ni separa existencias por lote (el negocio ya revisa físicamente cuál caja es, el
-- sistema solo avisa que ALGO de ese producto está por vencer). Módulo activable como cualquier
-- otro (ej. promociones/combos), apagado por defecto.

ALTER TABLE configuracion_negocio ADD COLUMN mod_vencimientos INTEGER NOT NULL DEFAULT 0;

CREATE TABLE IF NOT EXISTS producto_alertas_vencimiento (
    id_alerta INTEGER PRIMARY KEY AUTOINCREMENT,
    producto_id INTEGER NOT NULL REFERENCES productos(id_producto),
    descripcion_lote TEXT,
    fecha_vencimiento TEXT NOT NULL,
    dias_antes INTEGER NOT NULL DEFAULT 0,
    creado_en TEXT NOT NULL DEFAULT (datetime('now','localtime'))
);

CREATE INDEX IF NOT EXISTS idx_alertas_vencimiento_producto ON producto_alertas_vencimiento(producto_id);
CREATE INDEX IF NOT EXISTS idx_alertas_vencimiento_fecha ON producto_alertas_vencimiento(fecha_vencimiento);
