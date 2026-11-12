-- Migración 040: historial de ajustes del monto de apertura de un turno de caja.
--
-- Caso real: al abrir el día se digitó 330 en vez de 330.000. Ese monto es la base del
-- cuadre de caja (efectivo esperado = apertura + ventas en efectivo − egresos), así que
-- corregirlo es delicado: solo un administrador puede hacerlo (o un cajero con la
-- autorización de un administrador en ese momento) y cada cambio queda registrado aquí,
-- con lo que había, lo que quedó, el motivo, quién lo hizo y quién lo autorizó. Nunca se
-- borra ni se edita una fila: un segundo ajuste es otra fila.

CREATE TABLE turno_ajustes (
    id_ajuste      INTEGER PRIMARY KEY AUTOINCREMENT,
    turno_id       INTEGER NOT NULL REFERENCES turnos (id_turno),
    tipo           TEXT NOT NULL DEFAULT 'apertura',
    monto_anterior REAL NOT NULL,
    monto_nuevo    REAL NOT NULL,
    motivo         TEXT NOT NULL,
    usuario_id     INTEGER NOT NULL REFERENCES usuarios (id_usuario),
    autorizado_por INTEGER NOT NULL REFERENCES usuarios (id_usuario),
    creado_en      TEXT NOT NULL DEFAULT (datetime('now', 'localtime'))
);

CREATE INDEX idx_turno_ajustes_turno ON turno_ajustes (turno_id);
