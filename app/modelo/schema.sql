-- Esquema núcleo de EneSoft POS (SQLite) — usuarios, productos, ventas,
-- movimientos, configuración. Se aplica una sola vez, la primera vez que
-- se abre la app y el archivo .db todavía no existe (ver Database.php).
--
-- Alcance de este primer incremento (a propósito, no es un port 1:1 de la
-- versión MySQL): sin permisos por rol, sin paquetes/costeo/IVA/combos,
-- sin caja/turnos, sin clientes/proveedores, sin pago mixto ni
-- devoluciones parciales. Eso se agrega en incrementos siguientes.

PRAGMA foreign_keys = ON;

CREATE TABLE IF NOT EXISTS usuarios (
    id_usuario INTEGER PRIMARY KEY AUTOINCREMENT,
    nombre TEXT NOT NULL,
    usuario TEXT NOT NULL UNIQUE,
    password_hash TEXT NOT NULL,
    rol TEXT NOT NULL CHECK (rol IN ('admin', 'cajero')),
    estado INTEGER NOT NULL DEFAULT 1,
    creado_en TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS categorias (
    id_categoria INTEGER PRIMARY KEY AUTOINCREMENT,
    descripcion TEXT NOT NULL,
    estado INTEGER NOT NULL DEFAULT 1
);

CREATE TABLE IF NOT EXISTS productos (
    id_producto INTEGER PRIMARY KEY AUTOINCREMENT,
    nombre_producto TEXT NOT NULL,
    codigo TEXT UNIQUE,
    categoria INTEGER NOT NULL REFERENCES categorias(id_categoria),
    precio_unitario REAL NOT NULL DEFAULT 0,
    cantidad_existente INTEGER NOT NULL DEFAULT 0,
    minimo_stock INTEGER NOT NULL DEFAULT 0,
    controla_stock INTEGER NOT NULL DEFAULT 1,
    estado INTEGER NOT NULL DEFAULT 1
);

CREATE TABLE IF NOT EXISTS tipo_transaccion (
    id_transaccion INTEGER PRIMARY KEY,
    descripcion TEXT NOT NULL
);

INSERT OR IGNORE INTO tipo_transaccion (id_transaccion, descripcion) VALUES
    (1, 'venta'),
    (2, 'egreso'),
    (3, 'compra'),
    (4, 'perdida');

CREATE TABLE IF NOT EXISTS facturas (
    id_factura INTEGER PRIMARY KEY AUTOINCREMENT,
    fecha TEXT NOT NULL,
    hora TEXT NOT NULL,
    usuario_id INTEGER NOT NULL REFERENCES usuarios(id_usuario),
    tipo_transaccion INTEGER NOT NULL REFERENCES tipo_transaccion(id_transaccion),
    total REAL NOT NULL DEFAULT 0,
    ajuste REAL NOT NULL DEFAULT 0,
    metodo_pago TEXT NOT NULL DEFAULT 'efectivo' CHECK (metodo_pago IN ('efectivo', 'tarjeta', 'transferencia')),
    anulada INTEGER NOT NULL DEFAULT 0,
    anulada_motivo TEXT,
    anulada_por INTEGER REFERENCES usuarios(id_usuario),
    anulada_en TEXT
);

CREATE TABLE IF NOT EXISTS movimiento (
    id_movimiento INTEGER PRIMARY KEY AUTOINCREMENT,
    id_factura INTEGER NOT NULL REFERENCES facturas(id_factura),
    usuario_id INTEGER NOT NULL REFERENCES usuarios(id_usuario),
    tipo_transaccion INTEGER NOT NULL REFERENCES tipo_transaccion(id_transaccion),
    fecha TEXT NOT NULL,
    hora TEXT NOT NULL,
    producto_id INTEGER REFERENCES productos(id_producto),
    cantidad INTEGER NOT NULL DEFAULT 0,
    precio_unitario REAL NOT NULL DEFAULT 0,
    total REAL NOT NULL DEFAULT 0,
    descripcion TEXT DEFAULT '',
    afecto_stock INTEGER NOT NULL DEFAULT 1
);

CREATE INDEX IF NOT EXISTS idx_movimiento_factura ON movimiento(id_factura);
CREATE INDEX IF NOT EXISTS idx_movimiento_producto ON movimiento(producto_id);
CREATE INDEX IF NOT EXISTS idx_facturas_fecha ON facturas(fecha);

CREATE TABLE IF NOT EXISTS configuracion_negocio (
    id INTEGER PRIMARY KEY CHECK (id = 1),
    nombre_negocio TEXT NOT NULL DEFAULT 'Mi Negocio',
    color_primario TEXT NOT NULL DEFAULT '#4f46e5',
    moneda TEXT NOT NULL DEFAULT 'COP',
    zona_horaria TEXT NOT NULL DEFAULT 'America/Bogota',
    logo_path TEXT
);

INSERT OR IGNORE INTO configuracion_negocio (id) VALUES (1);

INSERT OR IGNORE INTO categorias (id_categoria, descripcion, estado) VALUES
    (1, 'General', 1);
