# Control remoto de la configuración de cada equipo

Cómo el proveedor cambia, **desde el servidor**, qué funciones tiene un negocio (tipo de
negocio, módulos, parámetros) y cómo llega ese cambio al PC del cliente. El lado del equipo
ya está implementado; este documento es el contrato para el lado del servidor
(`https://pos.nncoms.com/api`).

## Idea en una frase

El equipo ya le habla al servidor todo el tiempo (latido cada 6 h y ping cada ~60 s).
Se le agrega al servidor la **configuración deseada** de esa licencia con un **número de
revisión**; el equipo la aplica cuando ese número cambia y le **reporta** qué aplicó.

```
Panel del proveedor ──guarda config + revision++──► base del servidor
                                                         │
   PC del cliente ──ping (60 s)────────────────────────►│  responde: config_revision = 7
   PC del cliente ◄── (7 ≠ 6 aplicada) pide latido YA ──┘
   PC del cliente ──heartbeat + estado aplicado────────►│  responde: configuracion_remota {…revision 7…}
   PC del cliente aplica, guarda en licencia.json, audita
   PC del cliente ──próximo heartbeat: config_revision_aplicada = 7 ► el panel muestra "aplicada ✓"
```

Un cambio llega en ~1–2 minutos con el equipo prendido y en internet. Sin internet, el equipo
sigue con lo último que aplicó y se pone al día en cuanto vuelva la conexión.

## 1. Lo que el servidor debe agregar

### 1.1 `heartbeat.php` — respuesta (nuevo bloque opcional)

Además de lo que ya devuelve (`ok`, `pagada_hasta`, `fidelizacion_habilitada`, …):

```json
{
  "ok": true,
  "...": "campos de siempre",
  "configuracion_remota": {
    "gestionada": true,
    "revision": 7,
    "tipo_negocio": "servicios",
    "aplicar_preset": true,
    "modulos": { "propina": true, "existencias": false },
    "parametros": { "propina_porcentaje": 10, "propina_por_defecto": false }
  }
}
```

| Campo | Obligatorio | Significado |
|---|---|---|
| `gestionada` | sí | `true` = el proveedor administra este equipo a distancia. `false` (o bloque ausente) = no; el panel local (Superadmin) manda. |
| `revision` | sí si `gestionada` | Entero **≥ 1**. Súbelo en **cada** cambio guardado en el panel. El equipo aplica cuando este número **es distinto** del último aplicado (también si baja: si restauras una copia, manda el servidor). Igual = no hace nada. |
| `tipo_negocio` | no | Uno de: `tienda`, `supermercado`, `bar_restaurante`, `servicios`, `taller`, `personalizado`. Cambia el tipo y siembra los conceptos de gastos de ese tipo. |
| `aplicar_preset` | no | `true` = parte de los módulos sugeridos del `tipo_negocio` (ver tabla 4) y encima aplica `modulos`. `false`/ausente = solo cambia el tipo, los módulos quedan como estaban. |
| `modulos` | no | Objeto `nombre → booleano`. **Solo cambian los que aparecen**; los demás conservan su valor. |
| `parametros` | no | Ver 1.4. Igual que `modulos`: solo cambian los que aparecen. |

Reglas del equipo (para que el servidor sepa qué esperar):

* **Solo acepta lo conocido**: un módulo, tipo o parámetro desconocido, o con valor inválido
  (fuera de rango, no booleano), **se ignora y queda anotado** en la auditoría del equipo; lo válido
  del mismo bloque sí se aplica. Puedes mandar módulos nuevos a un servidor adelantado sin romper equipos viejos.
* Booleanos aceptados: `true/false`, `1/0`, `"1"/"0"`, `"true"/"false"`.
* Un valor `null` (en `modulos` o en `parametros`) significa **"sin cambio / sin valor definido"**: se conserva lo que el equipo tenga, sin advertencia. Así el servidor puede mandar siempre el mapa completo.
* El servidor puede mandar el mapa **completo** de módulos (estado final absoluto) y omitir `aplicar_preset`: es totalmente válido y es lo que hace el servidor actual. Un módulo que el servidor aún no conozca (por ejemplo `profesionales`) simplemente no se toca.
* `revision` inválida (0, negativa, texto, decimal, vacía) → **se ignora todo el bloque**.
* **Idempotente**: el mismo bloque llegando en cada latido no vuelve a tocar nada.
* Apagar un módulo **nunca borra datos**: solo oculta/bloquea la función. Volver a encenderlo la recupera.
* Cada aplicación queda en la auditoría del equipo (`accion = configuracion_remota`, con revisión y cambios exactos).

### 1.2 `ping.php` — respuesta (campo nuevo opcional)

Cuando `gestionada = true`, agrega **`config_revision`** con la revisión vigente:

```json
{ "ok": true, "config_revision": 7 }
```

* Si es distinta de la que el equipo tiene aplicada, el equipo pide el latido completo de inmediato
  (con un tope de **una vez cada 5 minutos** para no generar un latido por minuto si algo no se pudiera aplicar).
* Si `gestionada = false`, **omite el campo** (o manda `0`): el equipo lo ignora.

### 1.3 `heartbeat.php` — petición (campos nuevos que manda el equipo)

El equipo agrega estos campos (los servidores viejos los ignoran sin problema):

| Campo | Ejemplo | Para qué |
|---|---|---|
| `config_revision_aplicada` | `7` | Última revisión que el equipo aplicó (0 = ninguna). Cuando coincide con la del panel: **"aplicada ✓"**; si no: **"pendiente"** (con la hora del último latido). |
| `tipo_negocio` | `"servicios"` | Tipo que tiene el equipo ahora. |
| `modulos_activos` | `["propina","caja_turnos",…]` (texto JSON) | Módulos que tiene encendidos **ahora** — permite ver si alguien los cambió localmente (Superadmin) y comparar con lo publicado. |
| `parametros_actuales` | `{"propina_porcentaje":10,"propina_por_defecto":false}` (texto JSON) | Parámetros que tiene hoy. **Junto con `tipo_negocio` y `modulos_activos` sirve para IMPORTAR**: al abrir el formulario de una licencia que aún no tiene configuración publicada (o con un botón "Importar desde el equipo"), los interruptores y parámetros se rellenan con lo último que el equipo reportó, en vez de arrancar vacíos. |

### 1.4 Parámetros disponibles (`parametros`)

| Clave | Valores | Efecto |
|---|---|---|
| `propina_porcentaje` | número 0–30 | Porcentaje de propina sugerido al cobrar (0 = no se sugiere valor). |
| `propina_por_defecto` | booleano | La propina sugerida se aplica sola al cobrar (la persona puede quitarla). |

## 2. Lo que debe tener el panel del proveedor

1. **Por licencia** (y opcionalmente por dispositivo si una licencia cubre varios equipos): un formulario con
   tipo de negocio, la lista de módulos (interruptores) y los parámetros. Al **guardar**, `revision = revision + 1`.
   Un botón "Aplicar sugerido del tipo" equivale a `aplicar_preset: true`.
2. **Interruptor "Administrar a distancia"** (`gestionada`). Apagado = el equipo vuelve al control local.
3. **Indicador de estado por equipo**: `revision publicada` vs `config_revision_aplicada` reportada
   (aplicada ✓ / pendiente desde hace X) y, si `modulos_activos` reportado ≠ publicado, "modificado localmente".
4. **Historial de cambios** (quién, cuándo, qué revisión, qué cambió).
5. **Valores por defecto al crear una licencia nueva**: sugerir el preset según el tipo elegido.

## 3. Comportamientos que conviene conocer

* **Cambio local vs remoto.** El panel local del equipo (Superadmin) sigue funcionando; su cambio dura hasta la
  **siguiente revisión** publicada (con la misma revisión el equipo no vuelve a aplicar). El panel local muestra un
  aviso cuando el equipo está administrado a distancia.
* **Equipo liberado/revocado.** Si el servidor responde `ok:false` al latido/ping, el equipo se libera como siempre y
  suelta la administración remota; su configuración queda como estaba hasta que se active una licencia nueva. Al activar
  una licencia nueva, la revisión guardada se reinicia (la revisión 1 de la licencia nueva sí se aplica).
* **Sin internet / servidor caído.** El latido y el ping son "best effort": nada se rompe ni se cambia; se reintenta
  solo. Nunca una falla de red toca la configuración.
* **Lo que NO se controla a distancia.** El porcentaje/automático de propina **sí** (1.4). Los datos del negocio
  (nombre, NIT, impresoras, formato de ticket) siguen siendo decisión del negocio en Configuración.
* **Seguridad.** Todo va por HTTPS con verificación de certificado y el token del dispositivo, igual que el resto de
  la licencia. El bloque no lleva secretos. El equipo no ejecuta nada que venga del servidor: solo aplica valores de una
  lista blanca.

## 4. Módulos y tipos de negocio

Módulos disponibles (claves para `modulos`):

`paquetes`, `costeo`, `permisos_rol`, `caja_turnos`, `clientes_credito`, `proveedores`, `iva`, `combos`,
`mesas_comandas`, `promociones`, `modificadores`, **`existencias`**, **`propina`**, **`profesionales`** (14 en total).

* `existencias` = control de inventario (existencias, mínimos, alertas, compras y pérdidas de productos).
  **Apagado** = negocio de servicios: ningún producto controla inventario, no se descuenta ni valida nada, desaparecen
  las alertas y las pantallas de stock. Los gastos (luz, arriendo…) siguen funcionando.
* `profesionales` = asignar quién realizó cada servicio (estilista, mecánico…) y calcular su comisión sobre lo vendido (pantallas Profesionales y Comisiones). **Arranca APAGADO** en las instalaciones existentes (es una función nueva).
* `propina` = propina voluntaria al cobrar. **Apagado** = no hay campo de propina y el servidor de la app ignora una que llegue.

Sugeridos por tipo (`aplicar_preset: true`):

| Módulo | tienda | supermercado | bar_restaurante | servicios | taller |
|---|:-:|:-:|:-:|:-:|:-:|
| paquetes | ✓ | ✓ | ✓ | – | – |
| costeo | ✓ | ✓ | ✓ | – | ✓ |
| permisos_rol | ✓ | ✓ | ✓ | ✓ | ✓ |
| caja_turnos | ✓ | ✓ | ✓ | ✓ | ✓ |
| clientes_credito | – | ✓ | ✓ | ✓ | ✓ |
| proveedores | ✓ | ✓ | ✓ | – | ✓ |
| iva | – | ✓ | ✓ | – | ✓ |
| combos | – | – | ✓ | ✓ | ✓ |
| mesas_comandas | – | – | ✓ | – | – |
| promociones | – | – | ✓ | ✓ | – |
| modificadores | – | – | ✓ | – | – |
| **existencias** | ✓ | ✓ | ✓ | **–** | ✓ |
| **propina** | – | – | ✓ | **✓** | – |
| **profesionales** | – | – | – | **✓** | **✓** |

`personalizado` no tiene preset (queda tal cual se configure).

Fuente de verdad de estos valores: `app/modelo/Configuracion.php` (`MODULOS`, `TIPOS_NEGOCIO`, `PRESETS_TIPO_NEGOCIO`).

## 5. Compatibilidad hacia atrás

* Servidor viejo + equipo nuevo: no manda el bloque → el equipo no toca nada (probado).
* Servidor nuevo + equipo viejo: los campos extra se ignoran; el equipo viejo no conoce `configuracion_remota` y sigue como hasta ahora.
* Equipos existentes: los módulos nuevos (`existencias`, `propina`) **arrancan encendidos** al actualizar, así nadie pierde nada.

## 6. Pruebas sugeridas para el servidor

1. `gestionada:true, revision:1, modulos:{existencias:false}` → en ≤ 2 min el equipo lo aplica y en el siguiente latido reporta `config_revision_aplicada: 1`.
2. Repetir la misma `revision` con otro contenido → el equipo **no** cambia (regla de idempotencia); subir a 2 → sí.
3. `tipo_negocio:"servicios", aplicar_preset:true` → el equipo queda sin inventario y con propina.
4. Valores inválidos (`"modulos":{"x":true,"propina":"quizás"}`) → se aplica lo válido, sin errores.
5. `gestionada:false` → el panel local recupera el control.
6. Apagar el servidor en medio → el equipo sigue operando con la última configuración.

El lado del equipo se probó simulando el servidor (60 comprobaciones): reglas de aplicación, valores inválidos,
ping que adelanta el latido, fallas de red, licencia liberada y activación de una licencia nueva.
