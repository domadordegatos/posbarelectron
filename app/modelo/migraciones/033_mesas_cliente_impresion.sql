-- Migración 033: asociar un cliente registrado a una mesa/comanda abierta
-- (para mostrar su nombre en la cuadrícula de mesas, si el negocio lo
-- activa), y llevar un registro de qué ya se mandó a imprimir a cocina en
-- cada mesa para no reimprimir lo que ya se despachó.

ALTER TABLE comandas ADD COLUMN cliente_id INTEGER NULL REFERENCES clientes(id_cliente);

-- Snapshot JSON {"firma": cantidad, ...} de lo último que se mandó a
-- imprimir para esta comanda — permite calcular solo lo NUEVO en el
-- siguiente "Guardar mesa" en vez de reimprimir el pedido completo.
ALTER TABLE comandas ADD COLUMN impreso_json TEXT NULL;

-- 'productos' (como hoy) o 'categorias' ya existían (migración 032); esta
-- es aparte: si se muestra o no el nombre del cliente en la cuadrícula de
-- mesas de Ventas cuando la mesa tiene uno asociado.
ALTER TABLE configuracion_negocio ADD COLUMN mesas_mostrar_cliente INTEGER NOT NULL DEFAULT 0;
