-- Migración 002: unidad/paquete + costo unitario/utilidad.
-- Se aplica sola (ver Database.php::aplicarMigracionesPendientes), no hace
-- falta correrla a mano.

ALTER TABLE productos ADD COLUMN unidades_por_paquete INTEGER NOT NULL DEFAULT 1;
ALTER TABLE productos ADD COLUMN precio_paquete REAL;
ALTER TABLE productos ADD COLUMN costo_unitario REAL NOT NULL DEFAULT 0;

ALTER TABLE movimiento ADD COLUMN presentacion TEXT NOT NULL DEFAULT 'unidad';
ALTER TABLE movimiento ADD COLUMN unidades_por_paquete INTEGER NOT NULL DEFAULT 1;
ALTER TABLE movimiento ADD COLUMN unidades_totales INTEGER NOT NULL DEFAULT 0;
ALTER TABLE movimiento ADD COLUMN costo_unitario REAL NOT NULL DEFAULT 0;

-- Backfill: todo lo que ya existía se vendió/compró en presentación
-- "unidad" (1 a 1), así que unidades_totales = cantidad.
UPDATE movimiento SET unidades_totales = cantidad WHERE unidades_totales = 0;
