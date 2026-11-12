-- Migración 023: guarda el QR de fidelización que se generó para una venta
-- (si se generó) directamente en la factura, para poder reimprimirlo
-- después desde Facturas sin tener que generar uno nuevo.

ALTER TABLE facturas ADD COLUMN fidelizacion_qr_url TEXT;
