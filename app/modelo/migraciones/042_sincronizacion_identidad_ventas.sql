-- Migración 042: identidad de origen para facturas/movimiento — primera pieza de la
-- sincronización entre cajas (varias registradoras del mismo negocio, mismo local o sedes
-- distintas, compartiendo licencia). Una venta hecha en una caja necesita poder copiarse a
-- otra sin chocar con su propio autoincrement, y "Factura #" necesita poder mostrarse con un
-- número único entre cajas (ver app/modelo/Sincronizacion.php). En una instalación de UN
-- SOLO equipo estas columnas quedan NULL para siempre: el comportamiento es idéntico al de
-- hoy, "Factura #" sigue siendo el id crudo (ver Sincronizacion::numeroFactura()).

ALTER TABLE facturas ADD COLUMN origen_caja_codigo TEXT;
ALTER TABLE facturas ADD COLUMN origen_id_factura INTEGER;
-- Nombre de quien vendió, tal cual lo reportó SU caja — su usuario_id no es resoluble aquí
-- (los usuarios no se sincronizan en esta fase). NULL en filas creadas localmente: ahí se
-- sigue mostrando usuarios.nombre como siempre.
ALTER TABLE facturas ADD COLUMN usuario_origen_nombre TEXT;

ALTER TABLE movimiento ADD COLUMN origen_caja_codigo TEXT;
ALTER TABLE movimiento ADD COLUMN origen_id_movimiento INTEGER;
-- Igual que arriba, pero para el profesional que atendió la línea (profesionales tampoco se
-- sincroniza en esta fase): se conserva el nombre para el ticket/reporte aunque no haya id local.
ALTER TABLE movimiento ADD COLUMN profesional_origen_nombre TEXT;

CREATE INDEX IF NOT EXISTS idx_facturas_origen ON facturas(origen_caja_codigo, origen_id_factura);
CREATE INDEX IF NOT EXISTS idx_movimiento_origen ON movimiento(origen_caja_codigo, origen_id_movimiento);

-- Usuario "sistema": dueño de las filas de facturas/movimiento que llegan de otra caja
-- (usuario_id es NOT NULL con FK a usuarios, y esas ventas no las hizo nadie de ESTE equipo).
-- Id negativo explícito: nunca choca con el autoincrement real (que arranca en 1 y nunca baja
-- de ahí). estado=0 lo oculta de logins y de los listados de usuarios activos.
INSERT OR IGNORE INTO usuarios (id_usuario, nombre, usuario, password_hash, rol, estado)
VALUES (-1, 'Sincronización entre cajas', '__sync__', '!', 'cajero', 0);
