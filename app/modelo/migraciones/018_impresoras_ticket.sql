-- Migración 018: impresora dedicada para el recibo del cliente y para la
-- comanda de cocina — permite imprimir ambas automáticamente y en equipos
-- distintos al confirmar una venta, sin diálogo del sistema operativo.

ALTER TABLE configuracion_negocio ADD COLUMN impresora_recibo TEXT;
ALTER TABLE configuracion_negocio ADD COLUMN impresora_cocina TEXT;
