-- Migración 020: interruptor local para imprimir automáticamente el QR de
-- fidelización en el recibo del cliente al cerrar cada venta — a diferencia
-- de fidelizacion_habilitada (que decide el proveedor por licencia), esto
-- lo decide el propio negocio una vez ya tiene la funcionalidad contratada.

ALTER TABLE configuracion_negocio ADD COLUMN fidelizacion_qr_auto_venta INTEGER NOT NULL DEFAULT 0;
