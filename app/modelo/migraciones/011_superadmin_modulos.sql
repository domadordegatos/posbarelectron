-- Migración 011: tipo de negocio + módulos activables, y bitácora de
-- acceso al panel de superadministrador (proveedor del software).

ALTER TABLE configuracion_negocio ADD COLUMN tipo_negocio TEXT NOT NULL DEFAULT 'personalizado';
ALTER TABLE configuracion_negocio ADD COLUMN mod_paquetes INTEGER NOT NULL DEFAULT 1;
ALTER TABLE configuracion_negocio ADD COLUMN mod_costeo INTEGER NOT NULL DEFAULT 1;
ALTER TABLE configuracion_negocio ADD COLUMN mod_permisos_rol INTEGER NOT NULL DEFAULT 1;
ALTER TABLE configuracion_negocio ADD COLUMN mod_caja_turnos INTEGER NOT NULL DEFAULT 1;
ALTER TABLE configuracion_negocio ADD COLUMN mod_clientes_credito INTEGER NOT NULL DEFAULT 1;
ALTER TABLE configuracion_negocio ADD COLUMN mod_proveedores INTEGER NOT NULL DEFAULT 1;
ALTER TABLE configuracion_negocio ADD COLUMN mod_iva INTEGER NOT NULL DEFAULT 1;
ALTER TABLE configuracion_negocio ADD COLUMN mod_combos INTEGER NOT NULL DEFAULT 1;
ALTER TABLE configuracion_negocio ADD COLUMN mod_mesas_comandas INTEGER NOT NULL DEFAULT 1;

CREATE TABLE IF NOT EXISTS superadmin_intentos (
    id_intento INTEGER PRIMARY KEY AUTOINCREMENT,
    ip TEXT NOT NULL,
    usuario TEXT NOT NULL,
    exitoso INTEGER NOT NULL DEFAULT 0,
    creado_en TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_superadmin_intentos_ip_fecha ON superadmin_intentos(ip, creado_en);
