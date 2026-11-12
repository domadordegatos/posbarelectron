-- Migración 041: se puede corregir el monto de apertura de un turno YA CERRADO (días anteriores),
-- no solo del abierto. Corregirlo cambia el efectivo esperado y la diferencia de ese día, así que
-- cada ajuste guarda también cómo estaban el esperado y la diferencia antes y después (y si el
-- turno estaba abierto o cerrado al momento del ajuste), para poder reconstruir siempre qué cambió.
-- En un turno abierto esos cuatro valores quedan vacíos (todavía no hay cuadre).

ALTER TABLE turno_ajustes ADD COLUMN turno_estado TEXT NOT NULL DEFAULT 'abierto';
ALTER TABLE turno_ajustes ADD COLUMN esperado_anterior REAL;
ALTER TABLE turno_ajustes ADD COLUMN esperado_nuevo REAL;
ALTER TABLE turno_ajustes ADD COLUMN diferencia_anterior REAL;
ALTER TABLE turno_ajustes ADD COLUMN diferencia_nueva REAL;
