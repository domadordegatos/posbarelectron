# Sincronización entre cajas — contrato con el servidor

Para el equipo SERVER HOUSE. Describe los tres endpoints nuevos que necesita
`https://pos.nncoms.com/api` para que varias cajas (equipos) que comparten una misma
`clave_licencia` vean las mismas ventas, inventario, clientes y catálogo.

## Idea en una frase

El servidor **no interpreta nada**: es un buzón. Cada caja empuja sus eventos, el servidor los
guarda en un log append-only, y cada caja los recoge (excepto los suyos) con un cursor.

```
Caja A ──sync_push──►  log por licencia  ──sync_pull──► Caja B
Caja B ──sync_push──►  (append-only)     ──sync_pull──► Caja A
```

Cada 20 s, cada equipo hace push (si tiene eventos) y pull. Sin internet no pasa nada: la caja
sigue vendiendo y se pone al día sola. Una licencia de un solo equipo nunca llama a estos
endpoints con eventos; si no están implementados, el equipo reintenta en silencio (mismo
criterio "mejor esfuerzo" del ping/heartbeat).

Todas las llamadas son `POST` con `token` (el mismo de `llamarAutenticado`, identifica
licencia + dispositivo). Respuestas siempre JSON con `ok`.

## 1. `sync_registrar_caja.php`

Asigna a este dispositivo su **código de caja** dentro de la licencia.

| Petición | |
|---|---|
| `token` | token del dispositivo |

Respuesta: `{"ok":true,"caja_codigo":"C2"}`

- **Idempotente por dispositivo (`install_id`)**: repetir la llamada devuelve el mismo código.
- Secuencial **por licencia**: `C1`, `C2`, `C3`… (no global). Solo `[A-Z0-9]`, máx. 6 caracteres;
  el equipo lo imprime en la factura como `C2-000042`.
- Permanente: nunca se reasigna a otro dispositivo, aunque el original se libere (evita facturas
  duplicadas con el mismo número).
- Debe ser libre de condiciones de carrera (dos equipos activando a la vez → códigos distintos;
  usar transacción/índice único `(licencia, caja_codigo)`).
- Error: `{"ok":false,"error":"..."}` (licencia suspendida, token inválido…). El equipo reintenta
  en el siguiente ciclo.

## 2. `sync_push.php`

| Petición | |
|---|---|
| `token` | token del dispositivo |
| `caja_codigo` | código de la caja que envía |
| `eventos` | **string JSON** con un array de eventos (hasta 200) |

Respuesta: `{"ok":true,"recibidos":N}`

- Cada evento es un objeto opaco con, como mínimo: `tipo`, `emitido_por` (la caja que lo generó,
  igual al `caja_codigo` del push), `caja_origen` y `id_evento_origen` (entero creciente por caja
  emisora). **El servidor lo guarda tal cual** (JSON sin modificar) y no debe reordenar ni
  validar campos internos.
- **Idempotencia**: la clave es `(licencia, caja_emisora, id_evento_origen)`. Un evento repetido
  (reintento tras un fallo de red) es un no-op. El equipo receptor además deduplica por su lado
  y aplica "gana el más reciente" al catálogo, así que un duplicado que se colara no corrompe
  nada; pero el servidor no debe crecer sin necesidad.
- Responder `ok:true` **solo si todos** quedaron guardados (el equipo marca como enviado el lote
  completo; si hay `ok:false` reenvía el lote entero, por eso la idempotencia es obligatoria).
- El servidor asigna a cada evento guardado un `id` global creciente **por licencia**.

## 3. `sync_pull.php`

| Petición | |
|---|---|
| `token` | token del dispositivo |
| `caja_codigo` | código de esta caja |
| `desde` | cursor entero (último `cursor_nuevo` recibido; 0 la primera vez) |

Respuesta:
```json
{"ok":true,"eventos":[ {...}, {...} ],"cursor_nuevo":1287}
```

- Devuelve los eventos de la licencia con `id > desde`, en orden de `id`, **excluyendo los que
  esa misma caja empujó** (`caja_emisora` = `caja_codigo` solicitante). Límite sugerido 200 por
  llamada; `cursor_nuevo` = mayor `id` **examinado** (aunque se hayan filtrado los propios), para
  que el cursor avance.
- **Importante**: filtrar por la caja *emisora* (la del push), **no** por `caja_origen` del
  evento. Una anulación hecha por C2 sobre una factura de C1 trae `caja_origen = C1` y
  `emitido_por = C2`: debe llegar a C1 y no a C2. (El equipo, por seguridad, también ignora los
  eventos con `emitido_por` igual a su propio código.)
- Sin eventos nuevos: `{"ok":true,"eventos":[],"cursor_nuevo":<igual a desde>}`.
- El equipo guarda el cursor solo tras aplicar; si falla a mitad, repite el pull (idempotente).
- **No hace falta garantizar orden** entre tipos (p.ej. una venta antes que su producto): el
  equipo difiere lo que aún no puede aplicar y lo reintenta solo.

## Qué debe guardar el servidor

```
licencia_cajas(licencia_id, install_id, caja_codigo, creado_en)   UNIQUE(licencia_id, caja_codigo), UNIQUE(licencia_id, install_id)
sync_eventos(id AUTO por licencia, licencia_id, caja_emisora, id_evento_origen, payload_json, recibido_en)
             UNIQUE(licencia_id, caja_emisora, id_evento_origen)
```
Aislamiento estricto por licencia: una caja nunca ve eventos de otra licencia.

## Semántica que el equipo ya resuelve (el servidor no debe hacerlo)

- Ventas/movimientos: solo se agregan; ids con espacio propio por caja `(caja, id)`.
- Stock: por **delta** (nunca valor absoluto); puede quedar negativo (queda auditado).
- Catálogo y clientes: gana el `actualizado_en` más reciente. Limitación conocida: sin fusión de
  campos; en empate exacto gana el evento entrante.
- Eventos con referencias a entidades que aún no llegaron se difieren y reintentan.

## Anulación de ventas: destino de cada producto

Al anular una venta, quien anula decide por producto si **volvió al inventario** (repone stock) o
**se descartó** (no repone; queda como pérdida). El servidor sigue sin interpretar nada, pero los
eventos lo llevan así:

- `venta_anulada` incluye `lineas_descartadas`: lista de `{caja, id}` con las líneas descartadas.
  Las demás líneas se reponen (solo la parte no devuelta antes).
- El descarte también viaja como un evento `perdida` con `afecto_stock: false` (es solo el
  registro contable de la pérdida: no mueve stock en ninguna caja).

## Fuera de la fase 1 (a propósito)

No se sincronizan: cambio de cliente de una venta, edición de facturas, insumos, gastos
(concepto/anulación), pérdidas por concepto, edición manual de stock desde la ficha del
producto (ajustes de inventario se reflejan solo por compra/pérdida/venta/devolución),
turnos, usuarios, mesas, configuración.

## Qué NO se puede verificar desde el equipo

Aislamiento entre licencias, ausencia de carrera en la asignación de códigos, límites de carga y
red real en producción. Pruebas sugeridas para SERVER HOUSE:

1. Dos dispositivos, misma licencia: push de A → pull de B lo recibe; pull de A **no** lo recibe.
2. Repetir el mismo push dos veces → un solo evento en el log.
3. Pedir `sync_registrar_caja` dos veces desde el mismo dispositivo → mismo código.
4. Activar dos dispositivos simultáneamente → códigos distintos.
5. Licencia B nunca ve eventos de licencia A.
6. Anulación hecha por C2 sobre una venta de C1: C1 la recibe y C2 no (ver filtro por emisora).
7. Servidor caído a mitad de push: el reintento no duplica.
