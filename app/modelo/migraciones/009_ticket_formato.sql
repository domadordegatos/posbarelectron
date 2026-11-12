-- Migración 009: formato de ticket (A4 / térmica 58 / térmica 80).

ALTER TABLE configuracion_negocio ADD COLUMN formato_ticket TEXT NOT NULL DEFAULT 'a4';
