-- Migración 012: datos del negocio para imprimir en la factura/ticket
-- (NIT, teléfono, dirección, lema) — antes solo se guardaba el nombre.
-- Todos opcionales: no todo negocio tiene NIT, por ejemplo.

ALTER TABLE configuracion_negocio ADD COLUMN nit TEXT;
ALTER TABLE configuracion_negocio ADD COLUMN telefono TEXT;
ALTER TABLE configuracion_negocio ADD COLUMN direccion TEXT;
ALTER TABLE configuracion_negocio ADD COLUMN lema TEXT;
