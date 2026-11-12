-- Migración 044: buzones de sincronización entre cajas. sync_eventos_salientes es lo que ESTA
-- caja tiene para mandar (outbox); sync_eventos_entrantes es lo que ESTA caja recibió de otras
-- (inbox) — su UNIQUE(caja_origen, tipo_entidad, entidad_local_id) sirve dos propósitos: evita
-- aplicar el mismo evento dos veces si el servidor lo repite en un pull (idempotencia), y guarda
-- los que no se pudieron aplicar todavía (ej. una venta que llegó antes que el producto que
-- necesita) para reintentarlos en el próximo ciclo sin perderlos. En una instalación de un solo
-- equipo estas tablas quedan vacías para siempre (Sincronizacion::registrarEventoSaliente() no
-- inserta nada si esta caja nunca recibió un código de sincronización).

CREATE TABLE IF NOT EXISTS sync_eventos_salientes (
    id_evento INTEGER PRIMARY KEY AUTOINCREMENT,
    tipo_entidad TEXT NOT NULL,
    entidad_local_id INTEGER NOT NULL,
    payload TEXT NOT NULL,
    creado_en TEXT NOT NULL DEFAULT (datetime('now', 'localtime')),
    enviado INTEGER NOT NULL DEFAULT 0,
    enviado_en TEXT,
    intentos INTEGER NOT NULL DEFAULT 0,
    ultimo_error TEXT
);
CREATE INDEX IF NOT EXISTS idx_sync_salientes_pendientes ON sync_eventos_salientes(enviado, id_evento);

CREATE TABLE IF NOT EXISTS sync_eventos_entrantes (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    caja_origen TEXT NOT NULL,
    tipo_entidad TEXT NOT NULL,
    entidad_local_id INTEGER NOT NULL,
    payload TEXT NOT NULL,
    recibido_en TEXT NOT NULL DEFAULT (datetime('now', 'localtime')),
    aplicado INTEGER NOT NULL DEFAULT 0,
    aplicado_en TEXT,
    intentos INTEGER NOT NULL DEFAULT 0,
    ultimo_error TEXT,
    UNIQUE (caja_origen, tipo_entidad, entidad_local_id)
);
CREATE INDEX IF NOT EXISTS idx_sync_entrantes_pendientes ON sync_eventos_entrantes(aplicado, id);
