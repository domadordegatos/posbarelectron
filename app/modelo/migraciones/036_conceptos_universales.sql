-- Migración 036: el catálogo de conceptos de gastos (035) debe servir a CUALQUIER
-- tipo de negocio. La 035 sembró para todos conceptos propios de comida y
-- comercio (Gas, Bolsas plásticas, Vasos y platos desechables, Servilletas,
-- Aseo): a un taller o una peluquería no les sirven.
--
-- 1) Se retiran esos conceptos SOLO si nunca se usaron (si alguien ya registró
--    un gasto con ellos, se respetan tal cual).
-- 2) Quedan sembrados los universales (Nómina se suma a Luz, Agua, Internet,
--    Arriendo, que ya estaban).
-- 3) Los propios de cada tipo de negocio se siembran según el tipo que tenga
--    configurado esta instalación (el resto de tipos los recibe cuando el
--    proveedor cambie el tipo en Superadmin — ver conceptos_gasto::sembrarParaTipo).
--    Es el mismo mapa que conceptos_gasto::SEMILLAS_POR_TIPO.

DELETE FROM conceptos_gasto
WHERE nombre IN ('Gas', 'Bolsas plásticas', 'Vasos y platos desechables', 'Servilletas', 'Aseo')
  AND id_concepto NOT IN (SELECT concepto_id FROM movimiento WHERE concepto_id IS NOT NULL);

INSERT OR IGNORE INTO conceptos_gasto (nombre, categoria) VALUES ('Nómina', 'otro');

-- tienda y supermercado
INSERT OR IGNORE INTO conceptos_gasto (nombre, categoria)
SELECT 'Bolsas plásticas', 'insumo'
WHERE (SELECT tipo_negocio FROM configuracion_negocio WHERE id = 1) IN ('tienda', 'supermercado', 'bar_restaurante');
INSERT OR IGNORE INTO conceptos_gasto (nombre, categoria)
SELECT 'Aseo', 'insumo'
WHERE (SELECT tipo_negocio FROM configuracion_negocio WHERE id = 1) IN ('tienda', 'supermercado', 'bar_restaurante');
INSERT OR IGNORE INTO conceptos_gasto (nombre, categoria)
SELECT 'Transporte y fletes', 'servicio'
WHERE (SELECT tipo_negocio FROM configuracion_negocio WHERE id = 1) IN ('tienda', 'supermercado');

-- bar y restaurante
INSERT OR IGNORE INTO conceptos_gasto (nombre, categoria)
SELECT 'Gas', 'servicio'
WHERE (SELECT tipo_negocio FROM configuracion_negocio WHERE id = 1) = 'bar_restaurante';
INSERT OR IGNORE INTO conceptos_gasto (nombre, categoria)
SELECT 'Vasos y platos desechables', 'insumo'
WHERE (SELECT tipo_negocio FROM configuracion_negocio WHERE id = 1) = 'bar_restaurante';
INSERT OR IGNORE INTO conceptos_gasto (nombre, categoria)
SELECT 'Servilletas', 'insumo'
WHERE (SELECT tipo_negocio FROM configuracion_negocio WHERE id = 1) = 'bar_restaurante';
