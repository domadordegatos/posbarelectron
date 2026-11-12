-- Migración 039: el logo de la FACTURA es distinto del logo de la APLICACIÓN.
-- El de la aplicación se ve en la barra lateral (sobre el color del negocio);
-- el de la factura se imprime en papel — y una impresora térmica (solo negro o
-- blanco) necesita otra imagen: sin fondo de color, o en blanco y negro.
-- Hasta hoy había un solo logo para las dos cosas.
--
-- logo_factura_path NULL = no se subió uno propio: la factura usa el logo de la
-- aplicación (como hasta ahora). imprimir_logo_factura = 0 = la factura no lleva logo.

ALTER TABLE configuracion_negocio ADD COLUMN logo_factura_path TEXT NULL;
ALTER TABLE configuracion_negocio ADD COLUMN imprimir_logo_factura INTEGER NOT NULL DEFAULT 1;
