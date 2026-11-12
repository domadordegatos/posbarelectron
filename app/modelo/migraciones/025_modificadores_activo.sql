-- Migración 025: un grupo de modificadores ya no se puede borrar a ciegas
-- al editarlo — si alguna de sus opciones ya se usó en una venta real,
-- borrarla rompería esa venta histórica (la base de datos ya lo protege
-- con la llave foránea). En su lugar, guardarGrupos() ahora intenta borrar
-- y, si no puede por eso, desactiva el grupo viejo en vez de borrarlo —
-- mismo patrón que ya se usa con los niveles de fidelización.

ALTER TABLE grupos_modificadores ADD COLUMN activo INTEGER NOT NULL DEFAULT 1;
