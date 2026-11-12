-- Migración 037: dos módulos nuevos, para que el software sirva a negocios de
-- servicios (peluquería, taller, consultorio…) además de comercio y bares.
--
--  * existencias: control de inventario (existencias, mínimos, compras y
--    pérdidas de productos, alertas de stock). Un negocio de puros servicios
--    no lo necesita y lo estorba.
--  * propina: la propina voluntaria al cobrar. Tiene sentido en bares,
--    restaurantes, peluquerías; no en una tienda.
--
-- Ambos arrancan ENCENDIDOS (DEFAULT 1) para que ninguna instalación existente
-- pierda nada: hasta hoy el inventario y la propina estaban siempre activos.
-- El proveedor los apaga desde Superadmin o a distancia (ver ConfiguracionRemota).

ALTER TABLE configuracion_negocio ADD COLUMN mod_existencias INTEGER NOT NULL DEFAULT 1;
ALTER TABLE configuracion_negocio ADD COLUMN mod_propina INTEGER NOT NULL DEFAULT 1;

-- Parámetros de la propina (decisión del negocio, se editan en Configuración):
-- el porcentaje sugerido y si se aplica sola al cobrar (la persona puede quitarla).
ALTER TABLE configuracion_negocio ADD COLUMN propina_porcentaje REAL NOT NULL DEFAULT 10;
ALTER TABLE configuracion_negocio ADD COLUMN propina_por_defecto INTEGER NOT NULL DEFAULT 0;
