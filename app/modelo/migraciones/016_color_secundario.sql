-- Migración 016: color secundario del negocio (además del color_primario
-- que ya existe) — se usa como acento/contraste en la interfaz.

ALTER TABLE configuracion_negocio ADD COLUMN color_secundario TEXT DEFAULT '#f97316';
