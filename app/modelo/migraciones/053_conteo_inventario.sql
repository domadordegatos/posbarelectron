-- Migración 053: conteo físico de inventario ("stocktake") — el día que una empresa cuenta todo
-- (o una categoría) y compara contra lo que dice el sistema, para registrar las diferencias.
-- No inventa un mecanismo financiero nuevo: al cerrar, cada diferencia se registra reusando
-- inventario::registrarPerdida() (faltante como pérdida real) o
-- inventario::registrarAjusteInventario() (faltante/sobrante sin pérdida ni ganancia, con motivo) —
-- el usuario elige cuál, por eso ninguna de las dos tablas de aquí guarda plata ni motivo: el
-- rastro de CÓMO se registró cada diferencia queda en facturas/movimiento como cualquier otro
-- movimiento, con id_conteo para poder relacionarlo.

CREATE TABLE conteos_inventario (
    id_conteo        INTEGER PRIMARY KEY AUTOINCREMENT,
    fecha_inicio     TEXT NOT NULL DEFAULT (datetime('now', 'localtime')),
    fecha_cierre     TEXT,
    usuario_id       INTEGER REFERENCES usuarios (id_usuario),
    usuario_cierre_id INTEGER REFERENCES usuarios (id_usuario),
    categoria_id     INTEGER REFERENCES categorias (id_categoria), -- NULL = todo el catálogo
    estado           TEXT NOT NULL DEFAULT 'abierto' CHECK (estado IN ('abierto', 'cerrado')),
    nota             TEXT
);

-- Solo un conteo abierto a la vez (se exige en el modelo, no aquí) — evita contar dos veces el
-- mismo producto en conteos simultáneos que se pisarían entre sí.
CREATE TABLE conteo_detalle (
    id_detalle        INTEGER PRIMARY KEY AUTOINCREMENT,
    conteo_id         INTEGER NOT NULL REFERENCES conteos_inventario (id_conteo),
    producto_id       INTEGER NOT NULL REFERENCES productos (id_producto),
    cantidad_sistema  INTEGER NOT NULL, -- "foto" de cantidad_existente al ABRIR el conteo
    cantidad_contada  INTEGER,          -- NULL = todavía no se ha contado esta línea
    contado_en        TEXT,
    registro_id_factura INTEGER REFERENCES facturas (id_factura), -- a qué movimiento quedó el ajuste, una vez cerrado
    UNIQUE (conteo_id, producto_id)
);

INSERT OR IGNORE INTO permisos (clave, descripcion) VALUES
    ('conteo_inventario.registrar', 'Hacer conteos físicos de inventario y registrar las diferencias encontradas');
