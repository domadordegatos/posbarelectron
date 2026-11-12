'use strict';

/**
 * Proceso principal de Electron.
 *
 * Responsabilidad: lanzar el servidor embebido de PHP como proceso hijo,
 * esperar a que responda, abrir la ventana apuntando a ese servidor local,
 * y matar el proceso PHP quede lo que quede al cerrar la app.
 *
 * El código de la aplicación (PHP/HTML/CSS) vive en /app y nunca se toca
 * desde aquí — este archivo solo administra el ciclo de vida del proceso.
 */

const { app, BrowserWindow, dialog, Menu, ipcMain, shell, session } = require('electron');
const { spawn } = require('child_process');
const path = require('path');
const fs = require('fs');
const net = require('net');
const http = require('http');
const https = require('https');
const cifradoBd = require('./cifrado-bd');

const ESTA_EMPAQUETADO = app.isPackaged;
const ES_WINDOWS = process.platform === 'win32';
const PUERTO_PREFERIDO = 8080;
// latest.yml es un archivo estático chiquito servido por Cloudflare (caché
// del lado de ellos) — a diferencia del heartbeat/ping, esto NO le pega a
// la base de datos del servidor de licencias, así que no hay problema en
// revisarlo seguido: la detección de versión nueva queda casi al instante
// (similar al ping) aunque la INSTALACIÓN de la ruta normal (no forzada)
// sigue esperando a que el admin la acepte desde Configuración.
const INTERVALO_ACTUALIZACIONES_MS = 90 * 1000; // 90 segundos
const INTERVALO_HEARTBEAT_LICENCIA_MS = 6 * 60 * 60 * 1000; // 6 horas
const INTERVALO_PING_LICENCIA_MS = 60 * 1000; // 60 segundos — presencia + "forzar actualización" casi en tiempo real
const INTERVALO_SINCRONIZACION_CAJAS_MS = 20 * 1000; // 20 segundos — sincronización entre cajas de la misma licencia
const INTERVALO_RESPALDO_AUTOMATICO_MS = 24 * 60 * 60 * 1000; // 24 horas
const INTERVALO_SNAPSHOT_CIFRADO_MS = 15 * 60 * 1000; // 15 minutos
// Debe coincidir con publish.url en electron-builder.yml — el canal canary
// (ver canalActualizacion()) vive en un subdirectorio aparte del mismo
// servidor, con su propio latest.yml, para el rollout escalonado.
const URL_ACTUALIZACIONES_ESTABLE = 'https://pos.nncoms.com/actualizaciones/';
const URL_ACTUALIZACIONES_CANARY = 'https://pos.nncoms.com/actualizaciones/canary/';

let ventanaPrincipal = null;
let procesoPhp = null;
let apagandoApp = false;
// Distinto de apagandoApp: esto es un reinicio interno del proceso de PHP
// (ej. al restaurar un respaldo — ver ipcMain 'backup:restaurar-desde-archivo'),
// no un cierre real de la app. Evita que el handler 'exit' lo confunda con
// un cuelgue y muestre un error fatal, y evita tocar el flag de apagado
// real que usa el cierre limpio (cifrado, checkpoint) al salir de verdad.
let reiniciandoServidorPhp = false;
let puertoActual = null;
let temporizadorSnapshotCifrado = null;
// Últimas líneas que imprimió php.exe (stdout+stderr), para poder mostrar
// algo útil en el diálogo de error si nunca llega a responder — ver
// esperarServidorListo. También se guardan en php.log (rutaDatosUsuario)
// para poder revisar un arranque fallido después, sin tener que reproducirlo.
const MAX_LINEAS_LOG_PHP = 40;
let lineasLogPhp = [];

// ---------------------------------------------------------------------
// Rutas: deben resolver distinto en desarrollo (todo dentro del repo) y
// en producción (empaquetado, donde /app y /php van como extraResources
// junto al ejecutable, fuera del .asar).
// ---------------------------------------------------------------------

function rutaBinarioPhp() {
  const nombre = ES_WINDOWS ? 'php.exe' : 'php';
  const base = ESTA_EMPAQUETADO ? process.resourcesPath : __dirname;
  return path.join(base, 'php', nombre);
}

function rutaCarpetaApp() {
  const base = ESTA_EMPAQUETADO ? process.resourcesPath : __dirname;
  return path.join(base, 'app');
}

function rutaDatosUsuario() {
  // Persiste entre actualizaciones: nunca vive dentro de la carpeta de
  // instalación, que electron-builder puede borrar/reemplazar al actualizar.
  const dir = path.join(app.getPath('userData'), 'datos');
  if (!fs.existsSync(dir)) {
    fs.mkdirSync(dir, { recursive: true });
  }
  return dir;
}

/**
 * La app empaquetada no tiene consola visible en ningún lado — sin esto,
 * diagnosticar un problema de actualizaciones a distancia es puro
 * adivinar. Registra solo eventos relevantes (no cada ping exitoso, serían
 * miles de líneas) en un archivo de texto plano dentro de la carpeta de
 * datos, junto a posbar.db y cifrado.log.
 */
function logActualizacion(mensaje) {
  console.log(`[actualizaciones] ${mensaje}`);
  try {
    fs.appendFileSync(
      path.join(rutaDatosUsuario(), 'actualizaciones.log'),
      `[${new Date().toISOString()}] ${mensaje}\n`
    );
  } catch (error) {
    // un log que no se pudo escribir no debe bloquear nada más.
  }
}

// ---------------------------------------------------------------------
// Cifrado en reposo de la base de datos (ver cifrado-bd.js) y checkpoint
// del WAL antes de cerrar, para que no se pierda nada que estuviera
// confirmado solo en posbar.db-wal al matar el proceso de PHP.
// ---------------------------------------------------------------------

function programarSnapshotsCifrado() {
  temporizadorSnapshotCifrado = setInterval(
    () => cifradoBd.tomarSnapshotCifrado(rutaDatosUsuario()),
    INTERVALO_SNAPSHOT_CIFRADO_MS
  );
}

function solicitarCheckpointWal(puerto) {
  return new Promise((resolve) => {
    const solicitud = http.get(
      { host: '127.0.0.1', port: puerto, path: '/controlador/checkpoint_bd.php', timeout: 5000 },
      (res) => {
        res.resume();
        resolve();
      }
    );
    solicitud.on('error', () => resolve());
    solicitud.on('timeout', () => {
      solicitud.destroy();
      resolve();
    });
  });
}

// ---------------------------------------------------------------------
// Utilidades de red: encontrar un puerto libre y esperar a que PHP
// realmente esté respondiendo antes de cargar la ventana.
// ---------------------------------------------------------------------

function puertoEstaLibre(puerto) {
  return new Promise((resolve) => {
    const servidor = net.createServer();
    servidor.once('error', () => resolve(false));
    servidor.once('listening', () => {
      servidor.close(() => resolve(true));
    });
    servidor.listen(puerto, '127.0.0.1');
  });
}

async function obtenerPuertoDisponible() {
  if (await puertoEstaLibre(PUERTO_PREFERIDO)) {
    return PUERTO_PREFERIDO;
  }
  // El puerto preferido está ocupado (otra instancia, u otro programa) —
  // se pide uno libre al sistema operativo en vez de fallar.
  return new Promise((resolve, reject) => {
    const servidor = net.createServer();
    servidor.once('error', reject);
    servidor.listen(0, '127.0.0.1', () => {
      const { port } = servidor.address();
      servidor.close(() => resolve(port));
    });
  });
}

// intentosMax=150 / esperaMs=150 da mínimo ~22s de margen (más si algún
// intento individual se agota en su propio timeout de 1s) — nada corto: en
// la primera ejecución tras instalar en una máquina nueva, Windows Defender
// suele escanear en tiempo real un .exe que nunca había visto antes de
// dejarlo correr de verdad, lo que puede retrasar varios segundos que
// php.exe realmente empiece a escuchar aunque el proceso ya esté "corriendo".
// Con el límite corto anterior (40 intentos ≈ 6s) eso se veía, sin más
// explicación, como "PHP no respondió" — un fallo aparente que en realidad
// solo necesitaba más tiempo.
function esperarServidorListo(puerto, intentosMax = 150, esperaMs = 150) {
  return new Promise((resolve, reject) => {
    let intentos = 0;

    const intentar = () => {
      intentos += 1;
      const solicitud = http.get({ host: '127.0.0.1', port: puerto, path: '/', timeout: 1000 }, (res) => {
        res.resume(); // descarta el cuerpo, solo nos interesa que respondió
        resolve();
      });
      solicitud.on('error', reintentar);
      solicitud.on('timeout', () => {
        solicitud.destroy();
        reintentar();
      });
    };

    const reintentar = () => {
      if (intentos >= intentosMax) {
        const sigueVivo = procesoPhp !== null;
        const ultimasLineas = lineasLogPhp.length > 0
          ? `\n\nÚltimas líneas de PHP:\n${lineasLogPhp.join('\n')}`
          : '\n\n(PHP no imprimió nada — probablemente un antivirus/firewall está bloqueando el proceso o el puerto.)';
        reject(new Error(
          `PHP no respondió en 127.0.0.1:${puerto} después de ${intentosMax} intentos ` +
          `(proceso ${sigueVivo ? 'sigue corriendo' : 'ya no está corriendo'}).${ultimasLineas}`
        ));
        return;
      }
      setTimeout(intentar, esperaMs);
    };

    intentar();
  });
}

// ---------------------------------------------------------------------
// Ciclo de vida del proceso PHP
// ---------------------------------------------------------------------

function registrarLineaPhp(texto) {
  const linea = `[${new Date().toISOString()}] ${texto}`.trimEnd();
  console.log(linea);
  lineasLogPhp.push(linea);
  if (lineasLogPhp.length > MAX_LINEAS_LOG_PHP) {
    lineasLogPhp.shift();
  }
  try {
    fs.appendFileSync(path.join(rutaDatosUsuario(), 'php.log'), linea + '\n');
  } catch (error) {
    // un log que no se pudo escribir no debe bloquear el arranque.
  }
}

async function iniciarServidorPhp() {
  const binarioPhp = rutaBinarioPhp();
  const carpetaApp = rutaCarpetaApp();

  // Se reinicia en cada arranque/reinicio de PHP — solo interesa el
  // registro de la corrida actual, no acumular indefinidamente entre
  // sesiones (la app puede quedar abierta días).
  try {
    fs.writeFileSync(path.join(rutaDatosUsuario(), 'php.log'), '');
  } catch (error) {
    // idem: un log que no se pudo limpiar no debe bloquear el arranque.
  }

  if (!fs.existsSync(binarioPhp)) {
    throw new Error(
      `No se encontró el PHP portable en:\n${binarioPhp}\n\n` +
      'Corre "npm run descargar-php" antes de iniciar la aplicación.'
    );
  }
  if (!fs.existsSync(carpetaApp)) {
    throw new Error(`No se encontró la carpeta de la aplicación en:\n${carpetaApp}`);
  }

  cifradoBd.prepararBaseDeDatosCifrada(rutaDatosUsuario());

  const puerto = await obtenerPuertoDisponible();

  const rutaRouter = path.join(carpetaApp, 'router.php');
  const argumentosPhp = fs.existsSync(rutaRouter)
    ? ['-S', `127.0.0.1:${puerto}`, '-t', carpetaApp, rutaRouter]
    : ['-S', `127.0.0.1:${puerto}`, '-t', carpetaApp];

  procesoPhp = spawn(binarioPhp, argumentosPhp, {
    cwd: carpetaApp,
    env: {
      ...process.env,
      // La app PHP lee esta variable para saber dónde vive su SQLite —
      // nunca dentro de la carpeta de instalación (ver rutaDatosUsuario).
      POSBAR_DATA_DIR: rutaDatosUsuario(),
    },
    windowsHide: true,
  });

  lineasLogPhp = [];
  registrarLineaPhp(`[proceso] Arrancando: "${binarioPhp}" ${argumentosPhp.join(' ')}`);
  procesoPhp.stdout.on('data', (data) => registrarLineaPhp(`[php] ${data}`));
  procesoPhp.stderr.on('data', (data) => registrarLineaPhp(`[php] ${data}`));

  procesoPhp.on('error', (error) => {
    console.error('No se pudo iniciar el proceso de PHP:', error);
    if (!apagandoApp) {
      mostrarErrorFatal('No se pudo iniciar el servidor interno (PHP).', error.message);
    }
  });

  procesoPhp.on('exit', (codigo, señal) => {
    registrarLineaPhp(`[proceso] Terminó: código=${codigo}, señal=${señal || 'ninguna'}`);
    procesoPhp = null;
    if (!apagandoApp && !reiniciandoServidorPhp && codigo !== 0) {
      // 0xC0000135 = STATUS_DLL_NOT_FOUND: a php.exe (compilado con el
      // toolchain de Visual Studio) le falta el runtime de Microsoft
      // Visual C++ en esta máquina. El instalador ya lo instala solo (ver
      // build/installer.nsh) — si de todas formas aparece, es la pista más
      // rápida para el usuario o el soporte en vez de un código sin sentido.
      const esFaltaDeDll = codigo === 3221225781 || codigo === -1073741515;
      mostrarErrorFatal(
        'El servidor interno (PHP) se cerró inesperadamente.',
        esFaltaDeDll
          ? 'Falta un componente de Windows que PHP necesita para funcionar ' +
            '(Microsoft Visual C++ Redistributable). Vuelve a ejecutar el ' +
            'instalador de EneSoft POS — lo instala automáticamente — o ' +
            'descárgalo aparte desde https://aka.ms/vs/17/release/vc_redist.x64.exe ' +
            `\n\n(Código de salida: ${codigo}, señal: ${señal || 'ninguna'})`
          : `Código de salida: ${codigo}, señal: ${señal || 'ninguna'}`
      );
    }
  });

  await esperarServidorListo(puerto);

  return puerto;
}

function detenerServidorPhp() {
  if (!procesoPhp) return;
  apagandoApp = true;
  try {
    if (ES_WINDOWS) {
      // En Windows, child.kill() no siempre mata el árbol de procesos de
      // forma confiable — taskkill /T se asegura de que no quede huérfano.
      spawn('taskkill', ['/pid', String(procesoPhp.pid), '/f', '/t']);
    } else {
      procesoPhp.kill('SIGTERM');
    }
  } catch (error) {
    console.error('Error al detener el proceso de PHP:', error);
  }
  procesoPhp = null;
}

/**
 * Variante que SÍ espera a que el proceso realmente termine (con un tope
 * de tiempo) antes de devolver el control — necesario antes de tocar el
 * archivo de la base de datos: si se lee/cifra mientras PHP todavía tiene
 * el archivo abierto, se arriesga una copia a medias.
 */
function detenerServidorPhpYEsperar(esperaMaximaMs = 5000) {
  if (!procesoPhp) return Promise.resolve();

  const proceso = procesoPhp;
  return new Promise((resolve) => {
    const tope = setTimeout(resolve, esperaMaximaMs);
    proceso.once('exit', () => {
      clearTimeout(tope);
      resolve();
    });
    detenerServidorPhp();
  });
}

/**
 * Como detenerServidorPhpYEsperar(), pero para un reinicio INTERNO (ej.
 * restaurar un respaldo) — no marca apagandoApp (eso es solo para el cierre
 * real de la app), y no dispara el diálogo de "se cerró inesperadamente"
 * (ver reiniciandoServidorPhp arriba y el handler 'exit' en iniciarServidorPhp).
 */
function detenerServidorPhpTemporalmente(esperaMaximaMs = 5000) {
  if (!procesoPhp) return Promise.resolve();

  reiniciandoServidorPhp = true;
  const proceso = procesoPhp;
  return new Promise((resolve) => {
    const tope = setTimeout(resolve, esperaMaximaMs);
    proceso.once('exit', () => {
      clearTimeout(tope);
      resolve();
    });
    try {
      if (ES_WINDOWS) {
        spawn('taskkill', ['/pid', String(proceso.pid), '/f', '/t']);
      } else {
        proceso.kill('SIGTERM');
      }
    } catch (error) {
      console.error('Error al detener el proceso de PHP temporalmente:', error);
    }
    procesoPhp = null;
  });
}

// ---------------------------------------------------------------------
// Ventana principal
// ---------------------------------------------------------------------

function mostrarErrorFatal(titulo, detalle) {
  dialog.showErrorBox(titulo, detalle || '');
  app.quit();
}

async function crearVentanaPrincipal() {
  try {
    const puerto = await iniciarServidorPhp();

    ventanaPrincipal = new BrowserWindow({
      width: 1280,
      height: 800,
      minWidth: 1024,
      minHeight: 640,
      show: false,
      webPreferences: {
        preload: path.join(__dirname, 'preload.js'),
        contextIsolation: true,
        nodeIntegration: false,
        sandbox: true,
      },
    });

    ventanaPrincipal.once('ready-to-show', () => ventanaPrincipal.show());

    ventanaPrincipal.webContents.on('did-fail-load', (_evento, codigo, descripcion) => {
      console.error('Falló la carga de la ventana:', codigo, descripcion);
    });

    // Sin esto, un <a href="https://..."> a un sitio externo (ej. el link
    // de soporte en Configuración) navegaría la ventana ENTERA fuera de la
    // app, dejándola en blanco/rota — cualquier destino que no sea nuestro
    // propio servidor local se abre en el navegador del sistema en su lugar.
    const esUrlLocal = (url) => url.startsWith(`http://127.0.0.1:${puerto}/`);
    ventanaPrincipal.webContents.setWindowOpenHandler(({ url }) => {
      if (!esUrlLocal(url)) shell.openExternal(url);
      return { action: 'deny' };
    });
    ventanaPrincipal.webContents.on('will-navigate', (evento, url) => {
      if (!esUrlLocal(url)) {
        evento.preventDefault();
        shell.openExternal(url);
      }
    });

    ventanaPrincipal.on('closed', () => {
      ventanaPrincipal = null;
    });

    // El menú de la app está deshabilitado (Menu.setApplicationMenu(null)),
    // así que el atajo por defecto de DevTools no existe — se registra a
    // mano, y solo en --dev, para no dejarle esta puerta abierta al cliente
    // final en la versión empaquetada.
    if (process.argv.includes('--dev')) {
      ventanaPrincipal.webContents.on('before-input-event', (_evento, input) => {
        const esF12 = input.key === 'F12';
        const esCtrlShiftI = input.control && input.shift && input.key.toUpperCase() === 'I';
        if (esF12 || esCtrlShiftI) {
          ventanaPrincipal.webContents.toggleDevTools();
        }
      });
    }

    await ventanaPrincipal.loadURL(`http://127.0.0.1:${puerto}/`);

    return puerto;
  } catch (error) {
    console.error(error);
    mostrarErrorFatal('No se pudo iniciar EneSoft POS.', error.message);
    return null;
  }
}

// ---------------------------------------------------------------------
// Heartbeat de licencia: aviso periódico "sigo viva" al servidor de
// activaciones (ver app/modelo/Licencia.php). Es "best effort" — el propio
// endpoint PHP ya atrapa cualquier error de red y nunca afecta el uso del
// POS; aquí solo se ignora si la petición ni siquiera llega a responder.
// ---------------------------------------------------------------------

function programarHeartbeatLicencia(puerto) {
  const disparar = () => {
    // Se lee puertoActual (no el parámetro "puerto" capturado al arrancar)
    // porque si PHP se reinicia a mitad de sesión (ej. al restaurar un
    // respaldo — ver el handler 'backup:restaurar-desde-archivo') y le toca
    // un puerto distinto, este temporizador ya lleva horas corriendo y
    // seguiría apuntando para siempre al puerto viejo (muerto) si se
    // quedara con el valor original.
    const solicitud = http.get(
      { host: '127.0.0.1', port: puertoActual || puerto, path: '/controlador/heartbeat_licencia.php', timeout: 10000 },
      (res) => {
        let cuerpo = '';
        res.on('data', (trozo) => { cuerpo += trozo; });
        res.on('end', () => {
          try {
            const datos = JSON.parse(cuerpo);
            if (datos.liberada_localmente) {
              // Mismo caso que en el ping (ver programarPingLicencia) — el
              // heartbeat corre cada 6h, así que normalmente el ping ya lo
              // habrá detectado antes, pero esto cubre el caso de respaldo.
              logActualizacion('heartbeat: el servidor liberó/revocó este dispositivo — volviendo a la pantalla de activación.');
              if (ventanaPrincipal && !ventanaPrincipal.isDestroyed() && puertoActual) {
                ventanaPrincipal.loadURL(`http://127.0.0.1:${puertoActual}/`);
              }
            }
          } catch (error) {
            // respuesta no-JSON — no hay nada que hacer con esto aquí, heartbeat_licencia.php ya maneja sus propios errores.
          }
        });
      }
    );
    solicitud.on('error', (error) => console.error('[licencia] heartbeat no se pudo enviar:', error.message));
    solicitud.on('timeout', () => solicitud.destroy());
  };

  disparar();
  setInterval(disparar, INTERVALO_HEARTBEAT_LICENCIA_MS);
}

// ---------------------------------------------------------------------
// Ping de presencia: igual de "best effort" que el heartbeat, pero mucho
// más frecuente y liviano (ver app/controlador/ping_licencia.php) — para
// que el panel del proveedor pueda mostrar "en línea" y para que la señal
// de "forzar actualización" (ver programarRevisionDeSenalForzada) llegue
// en minutos en vez de esperar hasta el próximo heartbeat de 6h. No se
// loguean los errores individuales (a diferencia del heartbeat) para no
// llenar la consola si el negocio pasa horas sin internet.
// ---------------------------------------------------------------------

function programarPingLicencia(puerto) {
  const disparar = () => {
    // Mismo motivo que en programarHeartbeatLicencia: usar puertoActual en
    // vez del parámetro capturado, para sobrevivir un reinicio de PHP a
    // mitad de sesión sin quedarse pegado al puerto viejo.
    const solicitud = http.get(
      { host: '127.0.0.1', port: puertoActual || puerto, path: '/controlador/ping_licencia.php', timeout: 5000 },
      (res) => {
        let cuerpo = '';
        res.on('data', (trozo) => { cuerpo += trozo; });
        res.on('end', () => {
          try {
            const datos = JSON.parse(cuerpo);
            if (datos.liberada_localmente) {
              // El servidor dijo explícitamente que este token ya no sirve
              // (cupo liberado, dispositivo revocado, o licencia suspendida)
              // — a diferencia de un simple fallo de red (datos.fallo_red),
              // esto es autoritativo: hay que sacar al usuario YA, no
              // esperar a que alguien navegue o recargue por su cuenta.
              logActualizacion('ping: el servidor liberó/revocó este dispositivo — volviendo a la pantalla de activación.');
              if (ventanaPrincipal && !ventanaPrincipal.isDestroyed() && puertoActual) {
                ventanaPrincipal.loadURL(`http://127.0.0.1:${puertoActual}/`);
              }
            } else if (datos.ok === false && !datos.fallo_red) {
              logActualizacion(`ping: el servidor respondió ok:false: ${cuerpo}`);
            } else if (datos.forzar_actualizacion) {
              logActualizacion('ping: el servidor marcó forzar_actualizacion=true — señal recibida.');
            } else if (datos.forzar_respaldo) {
              logActualizacion('ping: el servidor pidió un respaldo de emergencia — señal recibida.');
            }
            // ok:true sin forzar_actualizacion, o un fallo_red, son el caso
            // normal/esperado — no se loguean, sería ruido cada 60s.
          } catch (error) {
            logActualizacion(`ping: respuesta no era JSON válido: ${cuerpo.slice(0, 200)}`);
          }
        });
      }
    );
    solicitud.on('error', (error) => logActualizacion(`ping: no se pudo enviar — ${error.message}`));
    solicitud.on('timeout', () => {
      solicitud.destroy();
      logActualizacion('ping: timeout esperando respuesta del proceso PHP local.');
    });
  };

  disparar();
  setInterval(disparar, INTERVALO_PING_LICENCIA_MS);
}

// ---------------------------------------------------------------------
// Sincronización entre cajas (ver docs/sincronizacion_cajas.md). Mejor
// esfuerzo y silenciosa: en un solo equipo el controlador responde de
// inmediato sin hacer nada. Nunca se solapan dos ciclos.
// ---------------------------------------------------------------------

function programarSincronizacionCajas(puerto) {
  let enCurso = false;
  const disparar = () => {
    if (enCurso) return;
    enCurso = true;
    const terminar = () => { enCurso = false; };
    const solicitud = http.get(
      { host: '127.0.0.1', port: puertoActual || puerto, path: '/controlador/sincronizacion.php', timeout: 60000 },
      (res) => {
        let cuerpo = '';
        res.on('data', (trozo) => { cuerpo += trozo; });
        res.on('end', () => {
          terminar();
          try {
            const datos = JSON.parse(cuerpo);
            if (datos.ok === false) {
              logActualizacion(`sincronización: ${datos.error || cuerpo.slice(0, 200)}`);
            }
          } catch (error) {
            logActualizacion(`sincronización: respuesta no era JSON válido: ${cuerpo.slice(0, 200)}`);
          }
        });
      }
    );
    solicitud.on('error', () => terminar());
    solicitud.on('timeout', () => { solicitud.destroy(); terminar(); });
  };

  disparar();
  setInterval(disparar, INTERVALO_SINCRONIZACION_CAJAS_MS);
}

// ---------------------------------------------------------------------
// Respaldo remoto automático (servicio pagado, ver respaldo_habilitado en
// Licencia.php) — best effort igual que el heartbeat: si esta licencia no
// lo tiene contratado, controlador/backup_automatico.php responde
// no_aplica=true y no hay nada más que hacer hasta el próximo ciclo largo.
// Si SÍ está contratado pero el intento falla (típicamente sin internet en
// ese momento), se reintenta pronto en vez de esperar 24h completas — así
// una ventana corta sin conexión no significa perder un día entero sin
// respaldo.
// ---------------------------------------------------------------------

const INTERVALO_REINTENTO_RESPALDO_MS = 30 * 60 * 1000; // 30 minutos

function programarRespaldoAutomatico(puerto) {
  const disparar = () => {
    // Mismo motivo que en programarHeartbeatLicencia: usar puertoActual en
    // vez del parámetro capturado, para sobrevivir un reinicio de PHP a
    // mitad de sesión sin quedarse pegado al puerto viejo.
    const solicitud = http.get(
      { host: '127.0.0.1', port: puertoActual || puerto, path: '/controlador/backup_automatico.php', timeout: 60000 },
      (res) => {
        let cuerpo = '';
        res.on('data', (trozo) => { cuerpo += trozo; });
        res.on('end', () => {
          let respuesta = null;
          try { respuesta = JSON.parse(cuerpo); } catch (error) { /* respuesta no-JSON, se trata como fallo */ }

          if (respuesta && (respuesta.ok || respuesta.no_aplica)) {
            programarSiguiente(INTERVALO_RESPALDO_AUTOMATICO_MS);
          } else {
            console.error('[respaldo] intento fallido, reintentando en 30 min:', respuesta ? respuesta.error : cuerpo);
            programarSiguiente(INTERVALO_REINTENTO_RESPALDO_MS);
          }
        });
      }
    );
    solicitud.on('error', (error) => {
      console.error('[respaldo] no se pudo enviar, reintentando en 30 min:', error.message);
      programarSiguiente(INTERVALO_REINTENTO_RESPALDO_MS);
    });
    solicitud.on('timeout', () => {
      solicitud.destroy();
      programarSiguiente(INTERVALO_REINTENTO_RESPALDO_MS);
    });
  };

  const programarSiguiente = (esperaMs) => {
    setTimeout(disparar, esperaMs);
  };

  disparar();
}

// ---------------------------------------------------------------------
// Auto-actualización (electron-updater apunta al "publish" de
// electron-builder.yml — cambia esa URL cuando tengas tu servidor).
//
// Gateado por actualizaciones_habilitadas — es un servicio pagado (ver
// Licencia.php): sin esto, CUALQUIER instalación empaquetada recibiría el
// auto-update en cuanto se configure el publish, haya pagado o no.
// licencia.json lo lee directo del disco (lo escribe PHP en cada
// heartbeat) — no hace falta pasar por HTTP para esta sola lectura.
// ---------------------------------------------------------------------

function tieneActualizacionesHabilitadas() {
  try {
    const ruta = path.join(rutaDatosUsuario(), 'licencia.json');
    const datos = JSON.parse(fs.readFileSync(ruta, 'utf8'));
    return datos.actualizaciones_habilitadas === true;
  } catch (error) {
    return false; // sin licencia.json todavía, o corrupto — por defecto NO se actualiza solo.
  }
}

// Rollout escalonado: el proveedor prueba una versión nueva en 1-2 equipos
// (canal 'canary', asignado por dispositivo desde su panel) antes de
// soltarla al resto — ver Licencia::heartbeat(). Por defecto (o si aún no
// hay licencia.json) siempre 'estable', el canal normal.
function canalActualizacion() {
  try {
    const ruta = path.join(rutaDatosUsuario(), 'licencia.json');
    const datos = JSON.parse(fs.readFileSync(ruta, 'utf8'));
    return datos.canal_actualizacion === 'canary' ? 'canary' : 'estable';
  } catch (error) {
    return 'estable';
  }
}

let autoUpdaterInstancia = null;
let actualizacionForzadaEnCurso = false;

// Estado de la revisión NORMAL (no forzada): a diferencia de la forzada,
// esta NO descarga sola apenas encuentra algo — solo avisa (badge en
// Configuración) y espera a que el admin decida "Iniciar actualización" o
// "Rechazar por ahora" desde la interfaz (ver ipcMain.handle abajo).
let ultimaVersionDetectada = null;
let versionRechazada = null;
let descargaSolicitadaPorUsuario = false;
// Con la revisión cada 90s, "no hay novedades" se repetiría miles de veces
// al día si se logueara siempre — esto solo deja pasar el log cuando el
// resultado realmente CAMBIA (nada -> versión X, o versión X -> nada).
let ultimoResultadoLogueado = undefined;

function avisarActualizacionForzadaARenderer(estado) {
  if (ventanaPrincipal && !ventanaPrincipal.isDestroyed()) {
    ventanaPrincipal.webContents.send('actualizacion:forzada-pendiente', estado);
  }
}

function estadoActualizacionActual() {
  // Durante una actualización forzada nunca se ofrece "Iniciar/Rechazar"
  // (ver avisarEstadoActualizacionARenderer) — esto cubre también el caso
  // de que alguien recargue/entre a Configuración justo en ese momento.
  const disponible = !actualizacionForzadaEnCurso
    && !!ultimaVersionDetectada
    && ultimaVersionDetectada.version !== versionRechazada;
  return {
    disponible,
    version: ultimaVersionDetectada ? ultimaVersionDetectada.version : null,
    notas: ultimaVersionDetectada && ultimaVersionDetectada.releaseNotes
      ? String(ultimaVersionDetectada.releaseNotes)
      : null,
  };
}

function avisarEstadoActualizacionARenderer() {
  if (ventanaPrincipal && !ventanaPrincipal.isDestroyed()) {
    ventanaPrincipal.webContents.send('actualizacion:estado', estadoActualizacionActual());
  }
}

/**
 * Empuja un evento puntual (no un estado a consultar, un renglón más al
 * "recuadro" de progreso en Configuración) — reutiliza logActualizacion
 * para que el archivo de log y lo que ve el admin en pantalla digan
 * siempre lo mismo, sin mantener dos mensajes por separado.
 */
function enviarEventoActualizacion(mensaje, tipo = 'info') {
  logActualizacion(mensaje);
  if (ventanaPrincipal && !ventanaPrincipal.isDestroyed()) {
    ventanaPrincipal.webContents.send('actualizacion:evento', { mensaje, tipo, ts: Date.now() });
  }
}

function obtenerAutoUpdater() {
  if (autoUpdaterInstancia) return autoUpdaterInstancia;

  const { autoUpdater } = require('electron-updater');
  // El logger interno de electron-updater (busca versión, descarga, % de
  // progreso, cualquier error de red/firma) es la fuente más completa de
  // qué está pasando — sin esto, solo veríamos nuestros propios mensajes
  // y nos perderíamos el detalle real de electron-updater.
  autoUpdater.logger = {
    info: (m) => logActualizacion(`electron-updater: ${m}`),
    warn: (m) => logActualizacion(`electron-updater [warn]: ${m}`),
    error: (m) => logActualizacion(`electron-updater [error]: ${m}`),
    debug: () => {},
  };
  // No instalar solo al cerrar la app — se pide confirmación explícita
  // (ver 'update-downloaded' abajo); si el cajero dice "Después", la
  // actualización descargada queda en espera hasta que la acepte. La
  // excepción es una actualización FORZADA (ver revisarActualizacionForzada):
  // ahí no se pregunta, se espera a que no haya nada en curso y se instala sola.
  autoUpdater.autoInstallOnAppQuit = false;
  // Tampoco se descarga sola apenas se detecta una versión nueva (flujo
  // NORMAL): solo se avisa (badge en Configuración) y se espera a que el
  // admin decida — ver ipcMain.handle('actualizacion:iniciar'/'rechazar').
  // La ruta FORZADA sigue siendo instantánea: dispara downloadUpdate() a
  // mano apenas detecta algo, sin pasar por autoDownload.
  autoUpdater.autoDownload = false;
  let ultimoPorcentajeReportado = -1;

  autoUpdater.on('checking-for-update', () => {
    enviarEventoActualizacion('Buscando actualización…', 'info');
  });
  autoUpdater.on('error', (error) => {
    enviarEventoActualizacion(
      `No se pudo completar: ${error.message}. Si el problema persiste, contacta a soporte.`,
      'error'
    );
    if (actualizacionForzadaEnCurso) {
      // Sin esto, un fallo de descarga (servidor caído, archivo faltante,
      // sin internet un momento) dejaba la actualización forzada atascada
      // para siempre — actualizacionForzadaEnCurso nunca volvía a false,
      // así que nunca se reintentaba ni se avisaba nada más.
      logActualizacion('la actualización crítica falló — reintentando en 5 min.');
      actualizacionForzadaEnCurso = false;
      setTimeout(revisarActualizacionForzada, 5 * 60 * 1000);
    }
  });
  autoUpdater.on('update-available', (info) => {
    if (ultimoResultadoLogueado !== info.version) {
      enviarEventoActualizacion(`Versión ${info.version} disponible.`, 'info');
      ultimoResultadoLogueado = info.version;
    }
    ultimaVersionDetectada = info;
    if (actualizacionForzadaEnCurso || descargaSolicitadaPorUsuario) {
      ultimoPorcentajeReportado = -1;
      enviarEventoActualizacion('Descargando…', 'info');
      descargaSolicitadaPorUsuario = false;
      // El 'error' de arriba ya maneja cualquier fallo (incluida la
      // promesa rechazada) — este catch vacío solo evita el aviso de
      // "unhandled promise rejection" en el proceso principal.
      autoUpdater.downloadUpdate().catch(() => {});
    }
    // El badge + "Iniciar actualización/Rechazar" son de la ruta NORMAL,
    // donde el admin sí elige — en la ruta forzada ya está la franja roja
    // (avisarActualizacionForzadaARenderer) contando la historia completa;
    // mostrar también los botones daría a entender que se puede rechazar
    // algo que en realidad ya va a instalarse sí o sí.
    if (!actualizacionForzadaEnCurso) {
      avisarEstadoActualizacionARenderer();
    }
  });
  autoUpdater.on('download-progress', (progreso) => {
    // Se avisa cada 10 puntos porcentuales (y en 100%) — reportar cada %
    // sería spam tanto en el log como en el recuadro de Configuración.
    const porcentaje = Math.floor(progreso.percent);
    if (porcentaje - ultimoPorcentajeReportado >= 10 || porcentaje >= 100) {
      ultimoPorcentajeReportado = porcentaje;
      enviarEventoActualizacion(`Descargando… ${porcentaje}%`, 'info');
    }
  });
  autoUpdater.on('update-not-available', () => {
    if (ultimoResultadoLogueado !== null) {
      logActualizacion('revisión completa — no hay novedades.');
      ultimoResultadoLogueado = null;
    }
    ultimaVersionDetectada = null;
    avisarEstadoActualizacionARenderer();
    if (actualizacionForzadaEnCurso) {
      // El proveedor forzó la revisión pero este equipo ya está al día —
      // no hay nada que instalar, se levanta el bloqueo de la interfaz.
      logActualizacion('la actualización forzada no encontró versión nueva — nada que instalar, se levanta el bloqueo.');
      actualizacionForzadaEnCurso = false;
      avisarActualizacionForzadaARenderer(false);
    }
  });
  autoUpdater.on('update-downloaded', async (info) => {
    enviarEventoActualizacion('Descarga completa.', 'exito');
    if (actualizacionForzadaEnCurso) {
      logActualizacion(`crítica descargada (${info.version}) — esperando que no haya nada en curso para instalar.`);
      iniciarEsperaSeguraParaInstalar();
      return;
    }

    logActualizacion(`descargada (${info.version}), pidiendo confirmación al usuario`);
    const { response } = await dialog.showMessageBox(ventanaPrincipal, {
      type: 'info',
      title: 'Actualización disponible',
      message: `Hay una nueva versión de EneSoft POS (${info.version}) lista para instalar.`,
      detail: 'Se recomienda instalarla cuando no haya una venta en curso. La aplicación se reiniciará.',
      buttons: ['Instalar ahora', 'Después'],
      defaultId: 0,
      cancelId: 1,
    });
    if (response === 0) {
      autoUpdater.quitAndInstall();
    }
  });

  autoUpdaterInstancia = autoUpdater;
  return autoUpdater;
}

/**
 * A diferencia de obtenerAutoUpdater() (que crea la instancia una sola vez
 * y la reutiliza), esto reaplica el feed URL en CADA llamada según el canal
 * vigente — así un cambio de canal hecho por el proveedor entre un
 * heartbeat y el siguiente se respeta en la próxima revisión, sin tener
 * que reiniciar la app.
 */
function obtenerAutoUpdaterConCanal() {
  const autoUpdater = obtenerAutoUpdater();
  const url = canalActualizacion() === 'canary' ? URL_ACTUALIZACIONES_CANARY : URL_ACTUALIZACIONES_ESTABLE;
  autoUpdater.setFeedURL({ provider: 'generic', url, channel: 'latest' });
  return autoUpdater;
}

function revisarActualizaciones() {
  if (!ESTA_EMPAQUETADO) return; // en desarrollo no hay instalador real que reemplazar
  // Se vuelve a leer licencia.json en CADA intento (no una sola vez al
  // arrancar) — si la licencia no tenía el servicio habilitado en el
  // momento exacto en que abrió la app pero se activa después (ej. el
  // proveedor corrige el flag y llega en el próximo heartbeat/ping), este
  // mismo temporizador ya en marcha lo detecta solo en el siguiente ciclo,
  // sin que el cliente tenga que cerrar y volver a abrir la app. Antes esto
  // se revisaba solo una vez dentro de configurarActualizaciones() y, si
  // daba false esa única vez, el setInterval de abajo ni siquiera se creaba
  // — quedaba apagado para siempre el resto de la sesión aunque el flag
  // cambiara a true diez segundos después.
  if (!tieneActualizacionesHabilitadas()) return;

  // Sin log de "consultando" aquí — a cada 90s sería puro ruido en el
  // archivo; 'update-available'/'update-not-available'/'error' (arriba)
  // ya registran lo único que importa: cuándo cambia algo.
  // checkForUpdates() (no ...AndNotify): con autoDownload=false, solo avisa
  // que hay algo nuevo (badge) — el admin decide si descargarla desde
  // Configuración, no se baja sola en este flujo.
  obtenerAutoUpdaterConCanal().checkForUpdates().catch((error) => {
    logActualizacion(`no se pudo revisar: ${error.message}`);
  });
}

function configurarActualizaciones() {
  if (!ESTA_EMPAQUETADO) {
    return;
  }
  if (!tieneActualizacionesHabilitadas()) {
    logActualizacion('esta licencia no tiene el servicio de actualizaciones activo ahora mismo — se reintentará solo en cada ciclo, sin bloquear el resto de la sesión.');
  }

  // El intervalo SIEMPRE se arma, sin importar el resultado de la revisión
  // de ahora mismo — revisarActualizaciones() vuelve a comprobar el flag
  // en cada tick (ver arriba).
  revisarActualizaciones();
  setInterval(revisarActualizaciones, INTERVALO_ACTUALIZACIONES_MS);
}

// ---------------------------------------------------------------------
// Actualización FORZADA por el proveedor ("medida brutal" para un fallo
// crítico que hay que corregir ya): el proveedor marca un dispositivo
// puntual en su panel, ping_licencia.php/heartbeat_licencia.php lo notan y
// dejan una señal en disco (ver Licencia::ping/heartbeat), y este poller la
// revisa cada 20 segundos mientras la app está abierta — como el ping ya
// corre cada 60s, la señal llega a este punto en menos de 2 minutos.
//
// A propósito IGNORA tieneActualizacionesHabilitadas() (no es el servicio
// estándar por suscripción, es una acción explícita y puntual del
// proveedor) y NO pide confirmación como el flujo normal: descarga sola,
// bloquea el inicio de ventas/mesas NUEVAS en la interfaz (ver
// preload.js/app.js), pero deja terminar con normalidad cualquier venta o
// mesa que ya estuviera en curso — recién cuando no queda nada abierto
// instala y reinicia la app sola.
// ---------------------------------------------------------------------

function rutaSenalForzarActualizacion() {
  return path.join(rutaDatosUsuario(), 'forzar_actualizacion.flag');
}

// Si el proveedor programó una hora para instalar (ver
// escribirSenalForzarActualizacion en Licencia.php), la descarga y el
// bloqueo de ventas nuevas empiezan igual de rápido que siempre — solo el
// último paso (instalar de verdad) espera también a que llegue esta hora,
// además de que no haya nada en curso. null = sin horario, instala apenas
// sea seguro (comportamiento de siempre).
let instalarForzadaDespuesDe = null;

function programarRevisionDeSenalForzada() {
  if (!ESTA_EMPAQUETADO) return;

  setInterval(() => {
    const ruta = rutaSenalForzarActualizacion();
    if (!fs.existsSync(ruta)) return;

    let contenido = null;
    try {
      contenido = fs.readFileSync(ruta, 'utf8');
    } catch (error) {
      // no se pudo leer (raro) — se sigue igual, tratando como "sin horario".
    }
    try {
      fs.unlinkSync(ruta); // se consume una sola vez
    } catch (error) {
      // si no se pudo borrar, se revisa de nuevo en el próximo ciclo — no pasa nada.
    }

    instalarForzadaDespuesDe = null;
    if (contenido) {
      try {
        const datos = JSON.parse(contenido);
        if (datos && datos.instalar_despues_de) {
          const fecha = new Date(datos.instalar_despues_de);
          if (!isNaN(fecha.getTime())) {
            instalarForzadaDespuesDe = fecha;
            logActualizacion(`señal de actualización forzada con horario programado: no se instala antes de ${fecha.toLocaleString('es-CO')}.`);
          }
        }
      } catch (error) {
        // el archivo estaba vacío (touch de siempre) o no era JSON — instala apenas sea seguro, como siempre.
      }
    }

    logActualizacion('señal de actualización forzada encontrada en disco — iniciando actualización crítica.');
    revisarActualizacionForzada();
  }, 20 * 1000);
}

// ---------------------------------------------------------------------
// Respaldo de EMERGENCIA pedido por el proveedor (ver Licencia::ping) —
// mismo patrón que la actualización forzada: una señal en disco que este
// poller revisa cada 20s. A diferencia del respaldo automático (24h) o el
// botón manual (máximo cada 12h), este SALTA ese límite de horas — sigue
// exigiendo que la licencia tenga el respaldo habilitado (eso no se salta).
// ---------------------------------------------------------------------

function rutaSenalForzarRespaldo() {
  return path.join(rutaDatosUsuario(), 'forzar_respaldo.flag');
}

function programarRevisionDeSenalForzarRespaldo() {
  if (!ESTA_EMPAQUETADO) return;

  setInterval(() => {
    const ruta = rutaSenalForzarRespaldo();
    if (!fs.existsSync(ruta) || !puertoActual) return;

    try {
      fs.unlinkSync(ruta); // se consume una sola vez
    } catch (error) {
      // si no se pudo borrar, se revisa de nuevo en el próximo ciclo — no pasa nada.
    }
    logActualizacion('señal de respaldo de emergencia encontrada en disco — enviando ahora.');
    const solicitud = http.get(
      { host: '127.0.0.1', port: puertoActual, path: '/controlador/backup_forzado.php', timeout: 60000 },
      (res) => {
        let cuerpo = '';
        res.on('data', (trozo) => { cuerpo += trozo; });
        res.on('end', () => {
          try {
            const datos = JSON.parse(cuerpo);
            logActualizacion(datos.ok ? 'respaldo de emergencia enviado correctamente.' : `respaldo de emergencia falló: ${datos.error}`);
          } catch (error) {
            logActualizacion(`respaldo de emergencia: respuesta no era JSON válido: ${cuerpo.slice(0, 200)}`);
          }
        });
      }
    );
    solicitud.on('error', (error) => logActualizacion(`respaldo de emergencia: no se pudo enviar — ${error.message}`));
    solicitud.on('timeout', () => solicitud.destroy());
  }, 20 * 1000);
}

function revisarActualizacionForzada() {
  if (!ESTA_EMPAQUETADO) {
    logActualizacion('actualización forzada ignorada: la app no está empaquetada (modo desarrollo).');
    return;
  }
  if (actualizacionForzadaEnCurso) {
    logActualizacion('actualización forzada ignorada: ya hay una en curso.');
    return;
  }
  actualizacionForzadaEnCurso = true;
  avisarActualizacionForzadaARenderer(true);

  const intentar = () => {
    logActualizacion('consultando al servidor de actualizaciones por la versión crítica…');
    obtenerAutoUpdaterConCanal().checkForUpdates().catch((error) => {
      logActualizacion(`fallo revisando la actualización crítica, reintentando en 5 min: ${error.message}`);
      setTimeout(intentar, 5 * 60 * 1000);
    });
  };
  intentar();
}

/** Le pregunta a la página cargada si el carrito actual tiene algo sin cobrar. */
function hayCarritoEnCurso() {
  if (!ventanaPrincipal || ventanaPrincipal.isDestroyed()) return Promise.resolve(false);
  return ventanaPrincipal.webContents
    .executeJavaScript('(function () { try { return typeof carrito !== "undefined" && Array.isArray(carrito) && carrito.length > 0; } catch (e) { return false; } })()')
    .catch(() => false);
}

/** Le pregunta a PHP si hay alguna mesa/comanda abierta ahora mismo. */
function hayMesasAbiertas(puerto) {
  return new Promise((resolve) => {
    const solicitud = http.get(
      { host: '127.0.0.1', port: puerto, path: '/controlador/estado_actividad.php', timeout: 5000 },
      (res) => {
        let cuerpo = '';
        res.on('data', (trozo) => { cuerpo += trozo; });
        res.on('end', () => {
          try {
            resolve(!!JSON.parse(cuerpo).hay_mesas_abiertas);
          } catch (error) {
            resolve(true); // ante la duda, asume que NO es seguro instalar
          }
        });
      }
    );
    solicitud.on('error', () => resolve(true));
    solicitud.on('timeout', () => { solicitud.destroy(); resolve(true); });
  });
}

async function esSeguroInstalarAhora(puerto) {
  const [carritoEnCurso, mesasAbiertas] = await Promise.all([hayCarritoEnCurso(), hayMesasAbiertas(puerto)]);
  return !carritoEnCurso && !mesasAbiertas;
}

let esperandoInstalacionSegura = false;

/**
 * accionInstalar: qué hacer una vez es seguro instalar — por defecto,
 * quitAndInstall() de electron-updater (ruta normal/forzada de subir de
 * versión). instalarVersionDirigida() (más abajo) pasa una función propia
 * para ejecutar un instalador ya descargado a mano (rollback a una versión
 * anterior, algo que electron-updater nunca haría solo porque considera
 * "sin novedad" cualquier versión igual o más vieja que la instalada).
 */
function iniciarEsperaSeguraParaInstalar(accionInstalar) {
  accionInstalar = accionInstalar || (() => obtenerAutoUpdater().quitAndInstall());
  if (esperandoInstalacionSegura) return;
  esperandoInstalacionSegura = true;
  logActualizacion('esperando a que no haya ventas/mesas en curso para instalar la actualización crítica…');

  let ultimoAvisoBloqueado = 0;
  const intentar = async () => {
    const seguro = puertoActual ? await esSeguroInstalarAhora(puertoActual) : true;
    const dentroDeHorario = !instalarForzadaDespuesDe || Date.now() >= instalarForzadaDespuesDe.getTime();
    if (!seguro || !dentroDeHorario) {
      // Se loguea máximo una vez por minuto mientras espera — evita llenar
      // el archivo si una mesa queda abierta varias horas, o si falta
      // mucho para la hora programada.
      if (Date.now() - ultimoAvisoBloqueado > 60 * 1000) {
        logActualizacion(
          !seguro
            ? 'todavía hay una venta o mesa en curso — sigue esperando.'
            : `esperando la hora programada para instalar (${instalarForzadaDespuesDe.toLocaleString('es-CO')}).`
        );
        ultimoAvisoBloqueado = Date.now();
      }
      setTimeout(intentar, 15 * 1000);
      return;
    }

    logActualizacion('nada en curso — instalando la actualización crítica ahora.');
    let segundosRestantes = 5;
    avisarActualizacionForzadaARenderer({ instalando: true, segundos: segundosRestantes });
    const cuentaRegresiva = setInterval(() => {
      segundosRestantes -= 1;
      if (segundosRestantes <= 0) {
        clearInterval(cuentaRegresiva);
        accionInstalar();
        return;
      }
      avisarActualizacionForzadaARenderer({ instalando: true, segundos: segundosRestantes });
    }, 1000);
  };

  intentar();
}

// ---------------------------------------------------------------------
// Instalación DIRIGIDA de una versión específica — usada para rollback
// (volver a una versión anterior) o para reinstalar una versión puntual a
// pedido del proveedor. electron-updater/autoUpdater nunca serviría esto
// (solo "actualiza", nunca "downgradea"), así que se descarga el .exe
// indicado a mano y se ejecuta directo, reusando el mismo bloqueo de
// ventas/mesas en curso y la cuenta regresiva que ya tiene la actualización
// forzada normal — para quien está frente al equipo, se ve exactamente
// igual (mismo aviso, misma espera), solo cambia qué versión termina
// instalada.
// ---------------------------------------------------------------------

function rutaSenalForzarVersion() {
  return path.join(rutaDatosUsuario(), 'forzar_version.flag');
}

function programarRevisionDeSenalForzarVersion() {
  if (!ESTA_EMPAQUETADO) return;

  setInterval(() => {
    const ruta = rutaSenalForzarVersion();
    if (!fs.existsSync(ruta)) return;

    let contenido;
    try {
      contenido = JSON.parse(fs.readFileSync(ruta, 'utf8'));
      fs.unlinkSync(ruta); // se consume una sola vez, igual que las otras señales
    } catch (error) {
      return; // archivo a medio escribir o corrupto — se reintenta en el próximo ciclo
    }

    if (!contenido || typeof contenido.version !== 'string' || typeof contenido.url !== 'string') {
      logActualizacion('señal de instalación dirigida encontrada pero con formato inválido — se ignora.');
      return;
    }

    logActualizacion(`el proveedor pidió instalar la versión ${contenido.version} en este equipo — iniciando descarga.`);
    instalarVersionDirigida(contenido.version, contenido.url);
  }, 20 * 1000);
}

function instalarVersionDirigida(version, url) {
  if (actualizacionForzadaEnCurso) {
    logActualizacion('ya hay una actualización crítica en curso — se ignora la instalación dirigida.');
    return;
  }
  actualizacionForzadaEnCurso = true;
  avisarActualizacionForzadaARenderer(true);
  enviarEventoActualizacion(`El proveedor solicitó instalar la versión ${version} — descargando…`, 'info');

  const rutaDestino = path.join(app.getPath('temp'), `posbar-instalador-dirigido-${version}.exe`);
  const archivo = fs.createWriteStream(rutaDestino);
  const cancelar = (mensaje) => {
    archivo.close();
    fs.unlink(rutaDestino, () => {});
    enviarEventoActualizacion(mensaje, 'error');
    actualizacionForzadaEnCurso = false;
    avisarActualizacionForzadaARenderer(false);
  };

  https.get(url, (respuesta) => {
    if (respuesta.statusCode !== 200) {
      respuesta.resume(); // drena la respuesta para no dejar el socket colgado
      cancelar(`No se pudo descargar la versión ${version}: HTTP ${respuesta.statusCode}.`);
      return;
    }

    const tamanoTotal = parseInt(respuesta.headers['content-length'] || '0', 10);
    let descargado = 0;
    let ultimoPorcentaje = -1;
    respuesta.on('data', (trozo) => {
      descargado += trozo.length;
      if (tamanoTotal > 0) {
        const porcentaje = Math.floor((descargado / tamanoTotal) * 100);
        if (porcentaje - ultimoPorcentaje >= 10 || porcentaje >= 100) {
          ultimoPorcentaje = porcentaje;
          enviarEventoActualizacion(`Descargando versión ${version}… ${porcentaje}%`, 'info');
        }
      }
    });
    respuesta.pipe(archivo);

    archivo.on('finish', () => {
      archivo.close(() => {
        // Un instalador real de Windows nunca pesa unos pocos KB — esto
        // detecta una página de error/redirección mal configurada en el
        // servidor antes de intentar "ejecutarla" como si fuera el .exe.
        const stats = fs.statSync(rutaDestino);
        if (stats.size < 1024 * 1024) {
          cancelar(`La descarga de la versión ${version} no parece un instalador válido (${stats.size} bytes) — se cancela por seguridad.`);
          return;
        }

        enviarEventoActualizacion(`Versión ${version} descargada — esperando que no haya nada en curso para instalar.`, 'exito');
        iniciarEsperaSeguraParaInstalar(() => {
          logActualizacion(`ejecutando el instalador de la versión ${version} (instalación dirigida por el proveedor)…`);
          const instalador = spawn(rutaDestino, [], { detached: true, stdio: 'ignore' });
          instalador.unref();
          setTimeout(() => app.quit(), 1500);
        });
      });
    });
  }).on('error', (error) => cancelar(`No se pudo descargar la versión ${version}: ${error.message}`));
}

// ---------------------------------------------------------------------
// Iniciar/rechazar la actualización NORMAL a pedido del admin (ver el
// badge en Configuración) — la revisión pasiva de cada 6h solo detecta y
// avisa, no descarga sola; esto es lo que realmente dispara la descarga.
// ---------------------------------------------------------------------

ipcMain.handle('actualizacion:estado', () => estadoActualizacionActual());

// Para que una pantalla recién cargada sepa si ya hay una actualización
// forzada en curso (esperando a que no haya nada abierto) — sin esto, si
// el admin navega a otra pantalla durante esa espera, la franja roja
// desaparecía hasta el siguiente evento (que puede tardar, ver
// iniciarEsperaSeguraParaInstalar).
ipcMain.handle('actualizacion:forzada-estado', () => actualizacionForzadaEnCurso);

ipcMain.handle('actualizacion:iniciar', () => {
  if (!ESTA_EMPAQUETADO) return { ok: false, error: 'No aplica en modo desarrollo.' };
  logActualizacion('el admin pidió iniciar la actualización desde Configuración.');
  if (ultimaVersionDetectada) {
    obtenerAutoUpdater().downloadUpdate();
  } else {
    descargaSolicitadaPorUsuario = true;
    revisarActualizaciones();
  }
  return { ok: true };
});

ipcMain.handle('actualizacion:rechazar', () => {
  if (ultimaVersionDetectada) {
    versionRechazada = ultimaVersionDetectada.version;
    logActualizacion(`el admin rechazó la versión ${versionRechazada} por ahora.`);
  }
  avisarEstadoActualizacionARenderer();
  return { ok: true };
});

// ---------------------------------------------------------------------
// Actualización manual: si la actualización remota falla (sin internet,
// servidor caído, la app quedó en un estado que ya no puede ni revisar
// actualizaciones), el propio negocio puede aplicar el instalador que le
// pase soporte a mano — sin depender de que el mecanismo automático esté
// sano. Se ofrece desde Configuración (ver vista/configuracion/index.php).
// ---------------------------------------------------------------------

ipcMain.handle('actualizacion:elegir-manual', async () => {
  const { canceled, filePaths } = await dialog.showOpenDialog(ventanaPrincipal, {
    title: 'Elegir instalador de EneSoft POS',
    filters: [{ name: 'Instalador de EneSoft POS', extensions: ['exe'] }],
    properties: ['openFile'],
  });
  if (canceled || filePaths.length === 0) {
    return { ok: false, cancelado: true };
  }

  const rutaInstalador = filePaths[0];
  const { response } = await dialog.showMessageBox(ventanaPrincipal, {
    type: 'warning',
    title: 'Actualizar manualmente',
    message: `EneSoft POS se va a cerrar para instalar:\n${path.basename(rutaInstalador)}`,
    detail: 'Asegúrate de que no haya una venta en curso antes de continuar. Cuando termine la instalación, vuelve a abrir EneSoft POS.',
    buttons: ['Cerrar e instalar', 'Cancelar'],
    defaultId: 1,
    cancelId: 1,
  });
  if (response !== 0) {
    return { ok: false, cancelado: true };
  }

  try {
    // Separado del proceso de Electron (detached) para que sobreviva al
    // cierre de la app — si no, Windows no puede reemplazar el .exe en uso.
    const proceso = spawn(rutaInstalador, [], { detached: true, stdio: 'ignore' });
    proceso.unref();
  } catch (error) {
    return { ok: false, error: error.message };
  }

  // app.quit() dispara el 'before-quit' normal (checkpoint, cifrado, matar
  // PHP) antes de cerrar de verdad — no hace falta replicar nada de eso aquí.
  setTimeout(() => app.quit(), 300);
  return { ok: true };
});

// ---------------------------------------------------------------------
// Restaurar la base de datos desde un archivo (respaldo descargado del
// servidor, o cualquier .db que entregue soporte) — panel del proveedor,
// para recuperar un equipo sin depender de que el dueño del negocio tenga
// copias a mano. Detiene PHP, guarda una copia de lo que había por si acaso,
// reemplaza el archivo, y reinicia todo sin necesidad de cerrar la app.
// ---------------------------------------------------------------------

ipcMain.handle('backup:restaurar-desde-archivo', async () => {
  const { canceled, filePaths } = await dialog.showOpenDialog(ventanaPrincipal, {
    title: 'Elegir archivo de respaldo (.db)',
    filters: [{ name: 'Base de datos SQLite', extensions: ['db'] }],
    properties: ['openFile'],
  });
  if (canceled || filePaths.length === 0) {
    return { ok: false, cancelado: true };
  }
  const rutaOrigen = filePaths[0];

  // Validación básica: el encabezado real de un archivo SQLite — evita
  // reemplazar la base de datos actual con un archivo cualquiera por error.
  try {
    const fd = fs.openSync(rutaOrigen, 'r');
    const cabecera = Buffer.alloc(16);
    fs.readSync(fd, cabecera, 0, 16, 0);
    fs.closeSync(fd);
    if (cabecera.toString('utf8', 0, 15) !== 'SQLite format 3') {
      return { ok: false, error: 'Ese archivo no parece ser una base de datos SQLite válida.' };
    }
  } catch (error) {
    return { ok: false, error: `No se pudo leer el archivo elegido: ${error.message}` };
  }

  const { response } = await dialog.showMessageBox(ventanaPrincipal, {
    type: 'warning',
    title: 'Restaurar base de datos',
    message: 'Esto va a REEMPLAZAR toda la información actual (ventas, productos, todo) con el contenido de ese archivo.',
    detail: 'Se guarda una copia de lo que hay ahora mismo antes de reemplazar, por si necesitas deshacerlo. La aplicación se va a reiniciar sola.',
    buttons: ['Restaurar', 'Cancelar'],
    defaultId: 1,
    cancelId: 1,
  });
  if (response !== 0) {
    return { ok: false, cancelado: true };
  }

  try {
    if (procesoPhp && puertoActual) {
      await solicitarCheckpointWal(puertoActual);
    }
    await detenerServidorPhpTemporalmente();

    const dir = rutaDatosUsuario();
    const rutaDb = path.join(dir, 'posbar.db');

    if (fs.existsSync(rutaDb)) {
      const marcaTiempo = new Date().toISOString().replace(/[:.]/g, '-');
      fs.copyFileSync(rutaDb, path.join(dir, `posbar-antes-de-restaurar-${marcaTiempo}.db`));
    }
    // Un -wal/-shm viejo referenciando datos que ya no existen podría
    // corromper la lectura de la base de datos restaurada.
    for (const sufijo of ['-wal', '-shm']) {
      const ruta = rutaDb + sufijo;
      if (fs.existsSync(ruta)) fs.unlinkSync(ruta);
    }

    fs.copyFileSync(rutaOrigen, rutaDb);

    const nuevoPuerto = await iniciarServidorPhp();
    puertoActual = nuevoPuerto;
    if (ventanaPrincipal && !ventanaPrincipal.isDestroyed()) {
      await ventanaPrincipal.loadURL(`http://127.0.0.1:${nuevoPuerto}/`);
    }

    return { ok: true };
  } catch (error) {
    return { ok: false, error: error.message };
  } finally {
    reiniciandoServidorPhp = false;
  }
});

// ---------------------------------------------------------------------
// Impresión silenciosa de tickets (recibo de cliente / comanda de cocina)
// a una impresora específica, sin diálogo del sistema operativo — así el
// cajero no tiene que elegir impresora ni confirmar nada en cada venta.
// Se usa una ventana oculta cargando el propio servidor local (para que
// las rutas relativas del ticket, como el logo del negocio, resuelvan
// igual que en la ventana real) en vez de una página data: (que no tiene
// origen propio y rompería esas rutas relativas).
// ---------------------------------------------------------------------

function imprimirHtmlSilencioso(html, deviceName) {
  return new Promise((resolve) => {
    if (!puertoActual) {
      resolve({ ok: false, error: 'El servidor local todavía no está listo.' });
      return;
    }

    const ventanaImpresion = new BrowserWindow({ show: false, webPreferences: { sandbox: true } });

    // Tope de seguridad: si el callback de print() nunca llega (impresora
    // apagada/atascada), la cola de impresión no debe quedarse esperando
    // para siempre y bloquear todo lo que se mande después.
    const topeEspera = setTimeout(() => {
      limpiar();
      resolve({ ok: false, error: 'La impresora no respondió a tiempo.' });
    }, 30000);

    const limpiar = () => {
      clearTimeout(topeEspera);
      if (!ventanaImpresion.isDestroyed()) ventanaImpresion.destroy();
    };

    // El HTML ya trae su propio <style>@page{...}</style> (ver
    // envolverTicketParaImpresionSilenciosa en app.js) — esta página solo
    // aporta bootstrap.min.css y app.css (mismas hojas de estilo que la
    // ventana real) para que el ticket se vea igual que en pantalla.
    ventanaImpresion.loadURL(`http://127.0.0.1:${puertoActual}/assets/imprimir.html`)
      .then(() => ventanaImpresion.webContents.executeJavaScript(
        // document.body.innerHTML solo INICIA la carga de cualquier <img>
        // (ej. el logo del negocio) — es asíncrona. Sin esperarla, print()
        // de la línea de abajo podía disparar antes de que la imagen
        // terminara de llegar, y el ticket salía sin logo (o con un hueco
        // en blanco) de forma intermitente. executeJavaScript() espera
        // sola la Promise si es lo último que devuelve el script — por eso
        // el Promise.all() queda como la última expresión aquí.
        `document.body.innerHTML = ${JSON.stringify(html)};
         Promise.all(Array.from(document.images).map((img) => img.complete ? Promise.resolve() : new Promise((resolve) => {
           img.addEventListener('load', resolve);
           img.addEventListener('error', resolve);
           setTimeout(resolve, 3000);
         })));`
      ))
      .then(() => {
        const opciones = { silent: true, printBackground: true, marginsType: 1 };
        if (deviceName) opciones.deviceName = deviceName;
        ventanaImpresion.webContents.print(opciones, (exito, motivoError) => {
          limpiar();
          resolve(exito ? { ok: true } : { ok: false, error: motivoError });
        });
      })
      .catch((error) => {
        limpiar();
        resolve({ ok: false, error: error.message });
      });
  });
}

ipcMain.handle('impresion:listar-impresoras', async () => {
  if (!ventanaPrincipal || ventanaPrincipal.isDestroyed()) return [];
  try {
    const impresoras = await ventanaPrincipal.webContents.getPrintersAsync();
    return impresoras.map((p) => ({ nombre: p.name, predeterminada: !!p.isDefault }));
  } catch (error) {
    return [];
  }
});

/**
 * Registro de cada trabajo de impresión silenciosa (impresion.log, junto a
 * posbar.db y php.log) — cuántos trabajos mandó de verdad la app, de qué
 * tipo y a qué impresora. Si en papel sale algo distinto (copias de más, un
 * documento por otro), este archivo dice si el origen fue la app o el
 * driver/impresora. Se corta a la mitad cuando pasa de ~300 KB.
 */
function logImpresion(mensaje) {
  console.log(`[impresion] ${mensaje}`);
  try {
    const ruta = path.join(rutaDatosUsuario(), 'impresion.log');
    if (fs.existsSync(ruta) && fs.statSync(ruta).size > 300 * 1024) {
      const contenido = fs.readFileSync(ruta, 'utf8');
      fs.writeFileSync(ruta, contenido.slice(Math.floor(contenido.length / 2)));
    }
    fs.appendFileSync(ruta, `[${new Date().toISOString()}] ${mensaje}\n`);
  } catch (error) {
    // un log que no se pudo escribir no debe bloquear la impresión.
  }
}

// Los trabajos se imprimen UNO A LA VEZ, en el orden en que llegan. Antes
// cada uno abría su propia ventana oculta y mandaba a imprimir de inmediato:
// "Imprimir con comanda" (factura + comanda) llegaba a la misma impresora
// casi al mismo tiempo, y en varias térmicas dos trabajos simultáneos se
// pisan o salen repetidos/cortados.
let colaImpresion = Promise.resolve();
let contadorTrabajosImpresion = 0;

ipcMain.handle('impresion:imprimir-ticket', (_evento, opciones) => {
  const html = opciones && typeof opciones.html === 'string' ? opciones.html : '';
  if (html.trim() === '') {
    return Promise.resolve({ ok: false, error: 'No hay contenido para imprimir.' });
  }
  const etiqueta = typeof opciones.etiqueta === 'string' ? opciones.etiqueta : 'ticket';
  const dispositivo = opciones.deviceName || null;
  const idTrabajo = ++contadorTrabajosImpresion;
  logImpresion(`trabajo #${idTrabajo} [${etiqueta}] en cola → ${dispositivo || '(predeterminada)'} (${html.length} bytes)`);

  const trabajo = colaImpresion.then(async () => {
    const resultado = await imprimirHtmlSilencioso(html, dispositivo);
    logImpresion(`trabajo #${idTrabajo} [${etiqueta}] ${resultado.ok ? 'enviado a la impresora' : 'FALLÓ: ' + resultado.error}`);
    // Un respiro para que el spooler cierre este trabajo antes del siguiente.
    await new Promise((res) => setTimeout(res, 400));
    return resultado;
  });
  colaImpresion = trabajo.catch(() => {});
  return trabajo;
});

// ---------------------------------------------------------------------
// Ciclo de vida de la app
// ---------------------------------------------------------------------

// Evita que se abran dos instancias (dos servidores PHP peleando por datos).
const bloqueoInstanciaUnica = app.requestSingleInstanceLock();
if (!bloqueoInstanciaUnica) {
  app.quit();
} else {
  app.on('second-instance', () => {
    if (ventanaPrincipal) {
      if (ventanaPrincipal.isMinimized()) ventanaPrincipal.restore();
      ventanaPrincipal.focus();
    }
  });

  app.whenReady().then(async () => {
    // Copias viejas de app.css/app.js (versiones anteriores las servían con caché de un año) no deben
    // quedarse mandando: se vacía la caché HTTP en cada arranque (cuesta milisegundos, todo es local).
    try { await session.defaultSession.clearCache(); } catch (e) { /* no crítico */ }
    // Sin esto, Electron muestra su menú por defecto (File/Edit/View/
    // Window con Recargar, DevTools, Zoom, etc.) — nada de eso tiene
    // sentido para el cajero de un POS, y algunas opciones (recargar a
    // mitad de una venta, abrir herramientas de desarrollador) son
    // directamente riesgosas. null = sin barra de menú en absoluto.
    Menu.setApplicationMenu(null);

    const puerto = await crearVentanaPrincipal();
    puertoActual = puerto;
    configurarActualizaciones();
    programarRevisionDeSenalForzada();
    programarRevisionDeSenalForzarRespaldo();
    programarRevisionDeSenalForzarVersion();
    if (puerto) {
      programarHeartbeatLicencia(puerto);
      programarPingLicencia(puerto);
      programarSincronizacionCajas(puerto);
      programarRespaldoAutomatico(puerto);
      programarSnapshotsCifrado();
    }

    app.on('activate', () => {
      if (BrowserWindow.getAllWindows().length === 0) {
        crearVentanaPrincipal();
      }
    });
  });

  app.on('window-all-closed', () => {
    if (!ES_WINDOWS) {
      // En macOS es normal dejar la app corriendo sin ventanas — pero el
      // servidor PHP no tiene sentido mantenerlo vivo sin ventana.
      detenerServidorPhp();
    }
    if (process.platform !== 'darwin') {
      app.quit();
    }
  });

  // Cierre "limpio": checkpoint del WAL, matar PHP, cifrar y borrar el
  // texto plano — en ese orden, porque cada paso depende del anterior.
  // preventDefault() + el guard de apagandoApp evitan que el app.quit()
  // final de acá abajo dispare este mismo handler en bucle.
  app.on('before-quit', (evento) => {
    if (apagandoApp) return;
    evento.preventDefault();
    apagandoApp = true;

    (async () => {
      if (temporizadorSnapshotCifrado) clearInterval(temporizadorSnapshotCifrado);
      if (procesoPhp && puertoActual) {
        await solicitarCheckpointWal(puertoActual);
      }
      await detenerServidorPhpYEsperar();
      cifradoBd.cifrarYLimpiarAlCerrar(rutaDatosUsuario());
      app.quit();
    })();
  });

  process.on('uncaughtException', (error) => {
    console.error('Excepción no capturada:', error);
    detenerServidorPhp();
  });
}
