-- Migración 013: ancho personalizado de la tirilla térmica (en mm) — para
-- impresoras que no encajan exactamente en los presets de 58/80mm.
-- NULL = usar el ancho por defecto del formato_ticket elegido.

ALTER TABLE configuracion_negocio ADD COLUMN ancho_ticket_mm INTEGER;
