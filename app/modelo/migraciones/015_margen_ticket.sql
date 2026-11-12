-- Migración 015: margen configurable del ticket (mm) — antes fijo en 2mm
-- para térmica y sin definir (por defecto del navegador) para A4.
-- NULL = usar el valor por defecto según el formato.

ALTER TABLE configuracion_negocio ADD COLUMN margen_ticket_mm INTEGER;
