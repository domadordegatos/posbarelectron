-- Migración 027: auditoría general de cambios administrativos (quién hizo
-- qué y cuándo) — productos, usuarios, permisos por rol y configuración
-- del negocio. Solo accesible al rol admin.

CREATE TABLE IF NOT EXISTS auditoria (
    id_evento INTEGER PRIMARY KEY AUTOINCREMENT,
    usuario_id INTEGER REFERENCES usuarios(id_usuario),
    accion TEXT NOT NULL,
    entidad_tipo TEXT NOT NULL,
    entidad_id INTEGER,
    detalle TEXT,
    creado_en TEXT NOT NULL DEFAULT (datetime('now', 'localtime'))
);

CREATE INDEX IF NOT EXISTS idx_auditoria_fecha ON auditoria(creado_en);
CREATE INDEX IF NOT EXISTS idx_auditoria_usuario ON auditoria(usuario_id);
