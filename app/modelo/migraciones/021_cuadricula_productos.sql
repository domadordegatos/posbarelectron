-- Migración 021: cuadrícula de productos destacados en Ventas — vista
-- alternativa (activable) al buscador, con íconos grandes para tocar
-- directo en vez de escribir el nombre. Coexiste con el buscador, nunca
-- lo reemplaza.

ALTER TABLE productos ADD COLUMN destacado_orden INTEGER;
CREATE INDEX IF NOT EXISTS idx_productos_destacado_orden ON productos(destacado_orden);

ALTER TABLE configuracion_negocio ADD COLUMN ventas_vista_cuadricula INTEGER NOT NULL DEFAULT 0;
