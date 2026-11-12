-- Migración 052: impresión de etiquetas con código de barras por producto — una impresora y un
-- tamaño de papel propios (igual patrón que impresora_recibo/impresora_cocina, ver migración 046),
-- distintos de la factura: una etiqueta térmica es de ancho Y alto fijos (no una tira continua).

ALTER TABLE configuracion_negocio ADD COLUMN impresora_etiquetas TEXT;
ALTER TABLE configuracion_negocio ADD COLUMN ancho_etiqueta_mm REAL;
ALTER TABLE configuracion_negocio ADD COLUMN alto_etiqueta_mm REAL;
