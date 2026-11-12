-- Migración 017: permitir editar el ajuste/total de una factura de venta ya
-- generada (permiso configurable, igual que ventas.anular), con trazabilidad
-- de quién hizo cada edición y cuándo.

CREATE TABLE IF NOT EXISTS factura_ediciones (
    id_edicion INTEGER PRIMARY KEY AUTOINCREMENT,
    id_factura INTEGER NOT NULL REFERENCES facturas(id_factura),
    usuario_id INTEGER REFERENCES usuarios(id_usuario),
    fecha_hora TEXT NOT NULL DEFAULT (datetime('now', 'localtime')),
    ajuste_anterior REAL NOT NULL,
    ajuste_nuevo REAL NOT NULL,
    total_anterior REAL NOT NULL,
    total_nuevo REAL NOT NULL,
    motivo TEXT NOT NULL
);

INSERT OR IGNORE INTO permisos (clave, descripcion) VALUES
    ('facturas.editar', 'Editar el ajuste/total de facturas de venta ya generadas');
