# EneSoft POS — Escritorio

Empaqueta la aplicación PHP como programa de escritorio: Electron abre una
ventana (Chromium) que apunta a un servidor PHP embebido (`php -S`) corriendo
en `localhost`, con SQLite como base de datos — sin necesitar XAMPP, MySQL,
ni que el cliente instale nada por su cuenta.

```
posbar-desktop/
├── main.js              # arranca PHP, abre la ventana, revisa actualizaciones
├── preload.js            # puente mínimo entre Electron y la página
├── package.json
├── electron-builder.yml  # configuración del instalador
├── app/                  # el punto de venta real — SQLite, sin MySQL
│   ├── router.php        # sirve /uploads (logo) desde fuera de /app
│   ├── version.txt
│   ├── modelo/migraciones/  # cambios de esquema versionados (PRAGMA user_version)
│   ├── modelo/               # Database, Auth, SuperAdmin, Configuracion, productos, facturas,
│   │                          # inventario, movimientos, usuarios, clientes, proveedores, turnos,
│   │                          # comandas, combos, permisos
│   ├── controlador/          # ~60 endpoints JSON
│   ├── vista/                 # login, home, ventas, registro, movimientos, usuarios, configuración,
│   │                           # caja, clientes, proveedores, superadmin/
│   └── assets/                # Bootstrap, jQuery, CSS/JS propios (copiados de posbar)
├── scripts/
│   └── download-php.js   # descarga el PHP portable de Windows y prepara su php.ini
└── php/                  # (se genera solo) PHP portable, no va en git
```

## Alcance actual: paridad completa con la versión MySQL (`posbar/`)

Todo lo construido en `posbar/` a lo largo de este proyecto ya está portado
a SQLite y probado de punta a punta contra el servidor PHP real (no solo el
modelo — controladores y vistas, con `curl` contra `php -S` + `router.php`,
igual que se hizo con la versión web):

- **Núcleo**: login con roles fijos admin/cajero, arranque en frío (sin
  usuarios → el login se convierte en alta del primer administrador),
  productos, ventas con ajuste de descuento/recargo, compras/pérdidas,
  egresos, anular venta, movimientos.
- **Paquetes y costeo**: unidad/paquete con precios distintos, costo
  unitario como promedio ponderado (una compra a $0 diluye el costo),
  utilidad por venta y del día.
- **IVA por producto**, **combos/kits** (con reposición proporcional de
  componentes al anular o devolver).
- **Clientes + crédito** (cupo, abonos, saldo calculado desde los pagos
  reales — una venta mixta solo cuenta su porción a crédito) y
  **proveedores** (compras ligadas para trazabilidad).
- **Caja/turnos**: apertura, cuadre en vivo (solo cuenta la porción en
  efectivo de una venta mixta), cierre con diferencia, historial.
- **Pago mixto** (varios métodos en una misma venta) y **devoluciones
  parciales** (por línea, respeta lo ya devuelto, repone combos
  proporcionalmente).
- **Mesas/comandas**: guardar sin cobrar no toca el inventario; cobrar sí,
  en ese momento.
- **Permisos configurables por rol**: el admin decide qué puede hacer el
  cajero, checkbox por checkbox (`permisos`/`rol_permisos`).
- **Panel de superadministrador** (`/vista/superadmin/`): código dinámico
  de 6 dígitos tipo TOTP (el mismo secreto compartido de la versión web,
  embebido en `modelo/superadmin_secreto.php` — genera uno propio si vas a
  distribuir esto, ver advertencia abajo), 9 módulos activables por
  instalación con presets por tipo de negocio (tienda/supermercado/
  bar-restaurante), y gestión de usuarios de emergencia (crear el primer
  admin o restablecer una contraseña sin tocar la base de datos a mano).
  Verificado que apagar un módulo bloquea la función **incluso para el
  admin del negocio**, no solo para el cajero.

⚠️ **Antes de distribuir un instalador real**: `modelo/superadmin_secreto.php`
viaja dentro del propio paquete instalado — a diferencia de la versión web
(protegida por `.htaccess` en el servidor), cualquiera con acceso al disco
de una instalación de escritorio puede llegar a leerlo. Es el mismo trato
compartido-vs-simple que ya elegiste para la versión web; solo ten en cuenta
que aquí el archivo literalmente viaja a la máquina de cada cliente.

## Esquema versionado

El esquema de la base de datos se versiona con `PRAGMA user_version` +
`modelo/migraciones/NNN_*.sql` — cada incremento nuevo agrega su propio
archivo de migración ahí y sube `SCHEMA_VERSION` en `Database.php`; se
aplica solo, no hace falta correr nada a mano ni en instalaciones nuevas ni
en las que ya existían (probado: una base ya usada recibe solo las
migraciones que le faltan, sin perder datos).

## Cómo funciona

1. Electron arranca y lanza `php.exe -S 127.0.0.1:<puerto> -t app` como
   proceso hijo (ver `main.js`).
2. Espera a que ese servidor responda de verdad antes de abrir la ventana
   (no hay una carrera de "abrir la ventana y que cargue en blanco").
3. La ventana carga `http://127.0.0.1:<puerto>/` — desde ahí en adelante,
   todo tu código PHP normal en `/app` maneja la aplicación como si fuera
   un sitio web cualquiera.
4. La base de datos SQLite se guarda en `app.getPath('userData')` (una
   carpeta del sistema operativo pensada para esto, ej.
   `%APPDATA%\EneSoft POS\datos` en Windows) — **no** dentro de la carpeta
   de instalación, para que sobreviva a cada actualización. Tu código PHP la
   ubica leyendo la variable de entorno `POSBAR_DATA_DIR`, que `main.js` le
   pasa al proceso de PHP.
5. Al cerrar la ventana: `main.js` le pide a PHP un `PRAGMA wal_checkpoint`
   (`controlador/checkpoint_bd.php`), mata el proceso (`taskkill /T` en
   Windows), y cifra la base de datos (ver siguiente sección) antes de
   dejar cerrar la app de verdad.

## Cifrado de la base de datos (`cifrado-bd.js`)

No hay build de `pdo_sqlite` con SQLCipher para el PHP portable de
Windows, así que mientras el POS está abierto `posbar.db` vive
necesariamente en texto plano (PHP lo necesita así). Lo que sí se
controla es que, en cuanto la app no está corriendo, en el disco no quede
nada sin cifrar:

- **Al cerrar bien la app**: se cifra `posbar.db` → `posbar.db.enc` (AES-256-GCM,
  clave de 32 bytes protegida con `safeStorage`, que usa el cifrado de
  datos del propio Windows ligado a la cuenta de usuario) y se borran los
  archivos en texto plano (`posbar.db`, `-wal`, `-shm`).
- **Si hubo un cierre abrupto** (apagón, cuelgue, "Finalizar tarea"): al
  próximo arranque, si `posbar.db` en texto plano todavía existe, se cifra
  de inmediato (sin perder ese contenido — es más nuevo que cualquier
  `.enc` que hubiera quedado) y se registra en `datos/cifrado.log`.
- **Cada 15 minutos mientras la app corre**: se toma un snapshot cifrado
  de refresco, para que un `.enc` no quede demasiado desactualizado si la
  próxima vez que se abre la app es mucho después del apagón.

Esto no reemplaza el cifrado de disco completo de Windows (BitLocker) —
recomiéndaselo a tus clientes como capa adicional; con BitLocker activado,
este escenario deja de ser un problema del todo. Probado de punta a punta
con `safeStorage` real (no simulado) vía un script headless de Electron.

## Desarrollo

Requisitos: Node.js 18+, Windows (el descargador de PHP de este scaffold
solo cubre Windows por ahora — ver más abajo).

```bash
npm install          # instala Electron + electron-builder, y baja PHP portable
npm run dev           # abre la app en modo desarrollo
```

`npm install` corre automáticamente `scripts/download-php.js` (hook
`postinstall`), que descarga PHP portable NTS x64 desde windows.php.net y lo
deja en `/php`, junto con `/php/cacert.pem` (el PHP portable no trae paquete
de certificados raíz del sistema operativo — sin ese archivo, cualquier
llamada HTTPS al servidor de licencias falla con "unable to get local
issuer certificate"; `Licencia.php` ya lo apunta explícitamente vía
`CURLOPT_CAINFO`). Si algún día esa versión de PHP ya no existe en el
sitio, el script lo dice claramente y te indica la URL para bajarlo a
mano — solo hay que ajustar `VERSION_PHP` en `scripts/download-php.js` y
reintentar:

```bash
npm run descargar-php
```

`npm install` también corre `scripts/download-vcredist.js`, que baja el
instalador oficial de Microsoft del **Visual C++ Redistributable (x64)** y lo
deja en `/vcredist`. Es necesario porque el PHP portable de arriba es un
binario nativo compilado con el toolchain de Visual Studio (build "vs17") y
depende en tiempo de ejecución de ese runtime (`VCRUNTIME140.dll`,
`MSVCP140.dll`, etc.) — cualquier máquina de desarrollo ya lo tiene instalado
de antes por otros programas, lo cual esconde el problema, pero un Windows 11
recién instalado en el negocio de un cliente puede no tenerlo, y ahí `php.exe`
no arranca en absoluto (falla con el código de salida `0xC0000135` /
`3221225781`, que la app muestra como *"El servidor interno (PHP) se cerró
inesperadamente"*). `build/installer.nsh` lo instala en silencio como parte
del `.exe` de instalación (ver más abajo), así que un cliente nunca tiene que
instalarlo a mano.

```bash
npm run descargar-vcredist
```

## Generar el instalador

```bash
npm run build:win
```

Esto usa `electron-builder` con la configuración de `electron-builder.yml`:
genera un instalador NSIS (`.exe`) en `dist/`, dejando que el usuario elija
la carpeta de instalación y creando accesos directos de escritorio y menú
inicio. La carpeta `/app` (tu PHP), `/php` (el binario portable) y
`/vcredist` (el instalador de Microsoft) se copian tal cual junto al
ejecutable — **no** van dentro del `.asar`, porque un binario `.exe` no puede
correr desde dentro de un archivo empaquetado. `build/installer.nsh`
(`customInstall`) corre `vc_redist.x64.exe /install /quiet /norestart` justo
después de copiar esos archivos — si el runtime ya está instalado en la
máquina, el propio instalador de Microsoft lo detecta y no hace nada, así que
es seguro correrlo siempre sin verificar antes.

## Activación / licenciamiento (obligatorio antes de vender una instalación)

`app/index.php` (la ruta raíz que carga `main.js`) redirige a
`/vista/activacion/index.php` mientras el dispositivo no esté activado, y a
`/vista/login/index.php` una vez lo está — así que **una instalación nueva
no deja entrar a nadie hasta activarse con una clave de licencia**.
Internet solo hace falta en ese momento único; después de activada, la app
funciona 100% offline: cualquier llamada posterior (`heartbeat_licencia.php`,
que `main.js` dispara al abrir y cada 6 horas, o `backup.php`) es "best
effort" y nunca bloquea el uso normal si no hay conexión.

`URL_BASE` en `app/modelo/Licencia.php` ya apunta al servidor real
(`pos.nncoms.com`) — el contrato exacto de los endpoints (`activar.php`,
`heartbeat.php`, `respaldo.php`) está documentado aparte, ya implementado y
probado de punta a punta contra producción. `heartbeat.php` es también de
dónde salen `respaldo_habilitado` / `actualizaciones_habilitadas` (features
pagas por licencia), `pagada_hasta` (ver "Licencias tipo suscripción" abajo)
y el aviso de versión nueva disponible — `backup.php` ya revisa
`respaldo_habilitado` antes de intentar subir nada, y `main.js` ya revisa
`actualizaciones_habilitadas` antes de activar `electron-updater`.

## Revocación (`estado` — aplica a CUALQUIER tipo de licencia)

Aparte del ciclo de pago (abajo), `Licencia::debeBloquearPorRevocacion()`
es tu apagado de emergencia: si suspendes/revocas una licencia desde tu
panel (`estado != 'activa'`), el próximo heartbeat exitoso de ese
dispositivo lo detecta y bloquea de inmediato — redirige a
`vista/licencia_revocada/index.php` en vez del login. Aplica igual a
licencias perpetuas y de suscripción; no depende de `pagada_hasta` para
nada. Solo un rechazo **explícito** del servidor (el heartbeat sí
respondió, y dijo que no) dispara esto — un fallo de conexión real (sin
internet, servidor caído) nunca bloquea nada, sigue siendo "best effort"
como todo lo demás en `Licencia.php`.

Si una licencia queda revocada sin forma de reactivarla del lado del
servidor (o simplemente quieres pasar el equipo a otra licencia), el
panel de superadmin tiene una tarjeta **"Cambiar clave de licencia"**
(`controlador/superadmin_cambiar_licencia.php`) — reutiliza el mismo
`install_id` (no cuenta como una activación nueva para efectos de
`max_activations` en el servidor si ya se conocía, pero si el `install_id`
nunca se había visto en la licencia nueva, sí cuenta como una) y refresca
de inmediato todas las banderas con un heartbeat forzado, para no dejar
datos de la licencia anterior mostrándose.

## Licencias tipo suscripción (`pagada_hasta`)

Una licencia puede ser **perpetua** (`pagada_hasta` nulo — nunca vence, no
requiere nada más de tu parte) o **suscripción** (`pagada_hasta` con fecha —
tú la extiendes un mes desde tu panel cada vez que te pagan). Solo las de
suscripción tienen este comportamiento:

- **Aviso, sin bloquear** (`vista/parciales/sidebar_inicio.php`): en cuanto
  se pasa `pagada_hasta`, aparece un badge pequeño arriba a la derecha
  ("Quedan N días para pagar la suscripción") en cualquier pantalla del POS
  — nunca se muestra para licencias perpetuas.
- **Bloqueo** (`Licencia::debeBloquearPorPago()`, revisado en `app/index.php`
  en cada arranque): pasados 5 días de gracia sin que el heartbeat traiga
  una `pagada_hasta` nueva, la app redirige a
  `vista/suscripcion_vencida/index.php` en vez de al login — con un botón
  para forzar una verificación si ya hay internet.
- **Funciona sin internet**: el chequeo es local (usa `pagada_hasta` y la
  fecha del sistema, ambas cacheadas en `licencia.json`), así que el
  bloqueo aplica aunque el equipo lleve días sin conectarse.
- **Resistente a atrasar el reloj**: la app nunca usa el reloj del sistema
  directamente — usa `Licencia::fechaEfectivaHoy()`, que solo puede
  avanzar, nunca retroceder (se ancla a `fecha_servidor` en cada heartbeat
  exitoso). Atrasar el reloj de la PC no revive una licencia ya bloqueada.
  No es infalible (nada local lo es), pero sí le sube el costo a cualquiera
  que lo intente.

## Configurar el servidor de actualizaciones

Hay dos mecanismos de actualización separados, y ambos apuntan hoy a una URL
de ejemplo que tienes que reemplazar:

1. **electron-updater** (el instalador completo, silencioso): revisa
   `electron-builder.yml` → `publish.url`. Con `provider: generic`, solo
   necesitas un servidor que sirva estáticamente los archivos que genera
   `electron-builder` en `dist/` (el instalador `.exe` y el `latest.yml`
   que describe la versión más nueva) — puede ser cualquier hosting con
   HTTPS, no hace falta nada especial del lado del servidor. `main.js` ya
   revisa actualizaciones al abrir la app y cada 6 horas
   (`INTERVALO_ACTUALIZACIONES_MS`) — **pero solo si `actualizaciones_habilitadas`
   es `true`** en `licencia.json` (lo llena el heartbeat contra tu servidor de
   licencias, ver `Licencia.php`). Es un servicio pagado: una instalación sin
   ese flag nunca revisa ni descarga nada por su cuenta, sin importar que el
   `publish` esté configurado — evita que el auto-update le llegue gratis a
   quien no lo pagó. Solo toma efecto en el próximo arranque después de que
   el heartbeat confirme el flag (no a mitad de una sesión ya abierta).
2. **`app/update-checker.php`**: un chequeo aparte, hecho desde tu propio
   código PHP (útil si quieres mostrar un aviso dentro de la interfaz sin
   pasar por Electron). Reemplaza `URL_SERVIDOR_VERSION` en ese archivo por
   un endpoint tuyo que responda:
   ```json
   { "version": "1.1.0", "download_url": "https://tu-servidor.com/..." }
   ```

`app/modelo/Respaldo.php` sigue el mismo patrón para respaldos — ya apunta
a `Licencia::URL_BASE . '/respaldo.php'`, campo `archivo` en el multipart
(ver `app/controlador/backup.php` para el botón manual, y
`backup_automatico.php` para el disparo automático cada 24h desde `main.js`).

### Forzar una actualización puntual (soporte remoto)

Aparte del ciclo normal cada 6h, puedes forzar que un dispositivo
específico revise actualizaciones de inmediato — pensado para cuando estás
remoto (AnyDesk) con un cliente, o corrigiendo una falla en caliente. No
depende de `actualizaciones_habilitadas` (es una acción puntual tuya, no
el servicio estándar por suscripción). Necesita un campo nuevo en el
heartbeat de tu servidor (`forzar_actualizacion`, se consume una sola
vez) — contrato completo en `prompt_forzar_actualizacion.md`. El
mecanismo: `Licencia::heartbeat()` deja una señal en disco
(`forzar_actualizacion.flag`) cuando el servidor la manda, y un poller en
`main.js` (`programarRevisionDeSenalForzada`, cada 20s) la detecta, la
borra, y dispara `electron-updater` de inmediato.

## Mesas (catálogo fijo, solo si el módulo `mesas_comandas` está activo)

Las mesas ya no se escriben a mano cada vez ("+ Nueva mesa") — el admin las
configura **una sola vez** en `/vista/mesas/index.php` (nombre + activa),
y en Ventas aparecen como una cuadrícula fija coloreada por estado (verde
= libre, azul = ocupada con total en vivo). Un solo endpoint
(`abrir_mesa.php`, con `mesa_id`) sirve para los dos casos: si la mesa ya
tiene una comanda abierta la retoma tal cual, si está libre crea una
nueva — nunca duplica. `comandas.mesa_id` es la referencia real al
catálogo; `mesa_nombre` se sigue guardando como snapshot histórico, igual
patrón que `costo_unitario` en `movimiento`.

**Instalaciones que ya usaban mesas con nombres libres**: la migración 014
solo agrega la columna `mesa_id` (nullable) — las comandas viejas no se
tocan ni se pierden, pero no aparecerán en la cuadrícula nueva hasta que
el admin configure el catálogo de mesas por primera vez.

**Importante para instalaciones ya corriendo**: `Database::conexion()` es
un singleton cacheado por proceso — las migraciones nuevas solo se
revisan la primera vez que ese proceso de PHP abre la conexión. Si el
proceso de PHP ya estaba corriendo (Electron abierto) antes de agregar
esta migración, hace falta **cerrar y volver a abrir la app** para que se
aplique — no basta con recargar la ventana.

## Plataformas

Este scaffold está armado pensando en Windows como mínimo (es lo que pediste
para el punto de venta). Para macOS/Linux, `main.js` ya distingue el nombre
del binario (`php` vs `php.exe`) y el `taskkill` solo se usa en Windows, pero
`scripts/download-php.js` solo descarga la build de Windows — habría que
agregarle las URLs de macOS/Linux si algún día hace falta empaquetar para
esos sistemas.
