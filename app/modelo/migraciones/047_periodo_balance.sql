-- Migración 047: período de corte para el "balance" (ventas - gastos) que el negocio sigue día a día.
-- Antes solo había un balance por rango de fechas elegido a mano en Compras y gastos; un cliente real
-- trabaja en ciclos de 15 días (cobra y paga el 15 de cada mes) y pidió que el Panel mostrara ese balance
-- solo, sin tener que escoger las fechas cada vez. Se generaliza a semanal/quincenal/mensual porque cada
-- negocio tiene su propio ritmo. 'mensual' con día 1 (el valor de fábrica) es el mes calendario de
-- siempre, así que un negocio que no configura nada ve exactamente lo que vería hoy.
ALTER TABLE configuracion_negocio ADD COLUMN periodo_balance_tipo TEXT NOT NULL DEFAULT 'mensual';
ALTER TABLE configuracion_negocio ADD COLUMN periodo_balance_dia INTEGER NOT NULL DEFAULT 1;
