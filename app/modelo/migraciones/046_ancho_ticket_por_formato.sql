-- Migración 046: ancho_ticket_mm y margen_ticket_mm eran UN SOLO valor compartido entre 58mm y 80mm —
-- al probar un formato se perdía el ajuste fino que ya se tenía guardado para el otro (reporte de un
-- cliente real: configuró 80mm a 72.5mm, probó 58mm y al volver a 80mm tocó medirlo de nuevo). Ahora cada
-- formato de rollo tiene su propio ancho y margen, y A4 su propio margen. Las columnas viejas se dejan
-- (SQLite no soporta DROP COLUMN en todas las versiones empaquetadas) con el valor que ya tenían: se
-- migran al formato que estuviera activo en ese momento, para no perder el ajuste de nadie al actualizar.
ALTER TABLE configuracion_negocio ADD COLUMN ancho_ticket_58_mm REAL;
ALTER TABLE configuracion_negocio ADD COLUMN margen_ticket_58_mm REAL;
ALTER TABLE configuracion_negocio ADD COLUMN ancho_ticket_80_mm REAL;
ALTER TABLE configuracion_negocio ADD COLUMN margen_ticket_80_mm REAL;
ALTER TABLE configuracion_negocio ADD COLUMN margen_ticket_a4_mm REAL;

UPDATE configuracion_negocio
   SET ancho_ticket_58_mm = ancho_ticket_mm, margen_ticket_58_mm = margen_ticket_mm
 WHERE formato_ticket = 'termica_58' AND ancho_ticket_mm IS NOT NULL;

UPDATE configuracion_negocio
   SET margen_ticket_58_mm = margen_ticket_mm
 WHERE formato_ticket = 'termica_58' AND ancho_ticket_mm IS NULL AND margen_ticket_mm IS NOT NULL;

UPDATE configuracion_negocio
   SET ancho_ticket_80_mm = ancho_ticket_mm, margen_ticket_80_mm = margen_ticket_mm
 WHERE formato_ticket = 'termica_80' AND ancho_ticket_mm IS NOT NULL;

UPDATE configuracion_negocio
   SET margen_ticket_80_mm = margen_ticket_mm
 WHERE formato_ticket = 'termica_80' AND ancho_ticket_mm IS NULL AND margen_ticket_mm IS NOT NULL;

UPDATE configuracion_negocio
   SET margen_ticket_a4_mm = margen_ticket_mm
 WHERE formato_ticket = 'a4' AND margen_ticket_mm IS NOT NULL;
