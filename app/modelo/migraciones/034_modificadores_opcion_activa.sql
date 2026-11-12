-- Migración 034: una OPCIÓN de un grupo de modificadores (no solo el grupo
-- completo, ver 025) ya no se borra a ciegas al quitarla en el editor — si
-- ya quedó registrada en una venta o en una mesa abierta, borrarla la
-- rompería (la llave foránea lo bloquea). En ese caso se desactiva en vez
-- de borrarse: deja de ofrecerse, pero lo ya vendido/guardado no se toca.

ALTER TABLE modificadores ADD COLUMN activo INTEGER NOT NULL DEFAULT 1;
