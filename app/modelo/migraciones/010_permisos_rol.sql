-- Migración 010: permisos configurables por rol (admin siempre tiene todo,
-- hardcoded en Auth.php — esta tabla solo aplica al rol cajero).

CREATE TABLE IF NOT EXISTS permisos (
    id_permiso INTEGER PRIMARY KEY AUTOINCREMENT,
    clave TEXT NOT NULL UNIQUE,
    descripcion TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS rol_permisos (
    id_rol_permiso INTEGER PRIMARY KEY AUTOINCREMENT,
    rol TEXT NOT NULL,
    id_permiso INTEGER NOT NULL REFERENCES permisos(id_permiso) ON DELETE CASCADE,
    UNIQUE (rol, id_permiso)
);

INSERT OR IGNORE INTO permisos (clave, descripcion) VALUES
    ('productos.ver', 'Ver el catálogo de productos'),
    ('productos.editar', 'Crear y editar productos'),
    ('compras.registrar', 'Registrar compras de inventario'),
    ('perdidas.registrar', 'Registrar pérdidas de inventario'),
    ('movimientos.ver', 'Ver el historial de ventas y movimientos'),
    ('caja.gestionar', 'Abrir y cerrar turnos de caja'),
    ('ventas.anular', 'Anular ventas y hacer devoluciones'),
    ('clientes.gestionar', 'Gestionar clientes y créditos'),
    ('proveedores.gestionar', 'Gestionar proveedores');
