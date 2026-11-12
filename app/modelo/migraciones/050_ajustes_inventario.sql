-- Migración 050: salidas/entradas de inventario que NO representan pérdida ni ganancia real
-- (ej. canje/trueque con otra empresa, muestras, donaciones, uso interno, vencimiento que no
-- se quiere reportar como pérdida). Mueven existencia real, igual que una compra o una pérdida,
-- pero quedan fuera de resumenGastos()/balance — ver inventario::registrarAjusteInventario().

INSERT OR IGNORE INTO tipo_transaccion (id_transaccion, descripcion) VALUES (7, 'salida');
INSERT OR IGNORE INTO tipo_transaccion (id_transaccion, descripcion) VALUES (8, 'entrada');

ALTER TABLE movimiento ADD COLUMN motivo_ajuste TEXT;

INSERT OR IGNORE INTO permisos (clave, descripcion) VALUES
    ('ajustes_inventario.registrar', 'Registrar salidas/entradas de inventario que no son compra, venta ni pérdida (canje, donación, muestra...)');
