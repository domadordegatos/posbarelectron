'use strict';

/**
 * Descarga PHP portable (Windows, NTS x64) desde windows.php.net y lo
 * extrae en /php, en la raíz del proyecto — de ahí lo toma main.js tanto
 * en desarrollo como en producción (ver rutaBinarioPhp en main.js).
 *
 * Se corre automáticamente en "npm install" (script postinstall), o a
 * mano con "npm run descargar-php".
 *
 * Nota: las URLs de windows.php.net cambian con cada versión. Si esta
 * versión ya no existe cuando lo corras, revisa https://windows.php.net/download/
 * y actualiza VERSION_PHP más abajo.
 */

const https = require('https');
const fs = require('fs');
const path = require('path');
const { execFileSync } = require('child_process');
const os = require('os');

const VERSION_PHP = '8.5.10';
const RAIZ_PROYECTO = path.join(__dirname, '..');
const CARPETA_PHP = path.join(RAIZ_PROYECTO, 'php');
const BINARIO_ESPERADO = path.join(CARPETA_PHP, 'php.exe');

// windows.php.net publica cada release primero en /releases/ y lo mueve a
// /releases/archives/ cuando sale un parche más nuevo — se intentan ambas.
function candidatosUrl(version) {
  const nombreArchivo = `php-${version}-nts-Win32-vs17-x64.zip`;
  return [
    `https://windows.php.net/downloads/releases/${nombreArchivo}`,
    `https://windows.php.net/downloads/releases/archives/${nombreArchivo}`,
  ];
}

function descargarConRedirecciones(url, destino, saltosRestantes = 5) {
  return new Promise((resolve, reject) => {
    if (saltosRestantes <= 0) {
      reject(new Error('Demasiadas redirecciones al descargar PHP.'));
      return;
    }

    const archivo = fs.createWriteStream(destino);
    https
      .get(url, (respuesta) => {
        if (respuesta.statusCode >= 300 && respuesta.statusCode < 400 && respuesta.headers.location) {
          archivo.close();
          fs.unlink(destino, () => {});
          resolve(descargarConRedirecciones(respuesta.headers.location, destino, saltosRestantes - 1));
          return;
        }

        if (respuesta.statusCode !== 200) {
          archivo.close();
          fs.unlink(destino, () => {});
          reject(new Error(`HTTP ${respuesta.statusCode} al descargar ${url}`));
          return;
        }

        respuesta.pipe(archivo);
        archivo.on('finish', () => archivo.close(resolve));
      })
      .on('error', (error) => {
        fs.unlink(destino, () => {});
        reject(error);
      });
  });
}

async function descargarPhpPortable(version) {
  const urls = candidatosUrl(version);
  const zipTemporal = path.join(os.tmpdir(), `php-portable-${version}.zip`);

  let ultimoError = null;
  for (const url of urls) {
    try {
      console.log(`Descargando PHP portable desde ${url} …`);
      await descargarConRedirecciones(url, zipTemporal);
      return zipTemporal;
    } catch (error) {
      console.warn(`  Falló (${error.message}), probando siguiente URL…`);
      ultimoError = error;
    }
  }

  throw new Error(
    `No se pudo descargar PHP ${version} desde ninguna URL conocida.\n` +
    `Último error: ${ultimoError ? ultimoError.message : 'desconocido'}\n` +
    'Revisa https://windows.php.net/download/ y actualiza VERSION_PHP en scripts/download-php.js.'
  );
}

function rutaTarNativo() {
  // Si esto corre dentro de Git Bash / MSYS, "tar" en el PATH puede resolver
  // al tar de MSYS en vez del nativo de Windows, y ese interpreta mal las
  // rutas con letra de unidad (las confunde con "host:archivo" de tar
  // remoto). Se apunta directo al tar.exe de System32 para evitar eso.
  const raizWindows = process.env.SystemRoot || 'C:\\Windows';
  const candidato = path.join(raizWindows, 'System32', 'tar.exe');
  return fs.existsSync(candidato) ? candidato : 'tar';
}

function extraerZip(rutaZip, carpetaDestino) {
  fs.mkdirSync(carpetaDestino, { recursive: true });
  console.log(`Extrayendo en ${carpetaDestino} …`);
  // tar viene incluido en Windows 10 (1803+) y Windows 11 — evita depender
  // de un paquete npm extra solo para descomprimir un zip.
  execFileSync(rutaTarNativo(), ['-xf', rutaZip, '-C', carpetaDestino], { stdio: 'inherit' });
}

/**
 * El PHP portable de Windows no trae paquete de certificados raíz del
 * sistema operativo — sin esto, cURL rechaza cualquier HTTPS (activación,
 * heartbeat, respaldo) con "unable to get local issuer certificate". Se usa
 * el bundle oficial de Mozilla que el propio proyecto curl publica y
 * mantiene actualizado para este uso exacto.
 */
function descargarCacert(carpetaPhp) {
  const destino = path.join(carpetaPhp, 'cacert.pem');
  if (fs.existsSync(destino)) {
    console.log('cacert.pem ya existe, no se toca.');
    return Promise.resolve();
  }

  console.log('Descargando paquete de certificados raíz (cacert.pem) …');
  return descargarConRedirecciones('https://curl.se/ca/cacert.pem', destino).then(() => {
    console.log(`cacert.pem listo en ${destino}`);
  });
}

/**
 * El zip de windows.php.net trae php.ini-production/-development como
 * plantillas, no un php.ini activo — sin esto PHP arranca sin las extensiones
 * que el sistema necesita (ver extensionesNecesarias abajo: incluye "zip" y
 * "fileinfo" porque PhpSpreadsheet los requiere para leer/escribir .xlsx).
 * También hay que fijar extension_dir a mano: el valor compilado por defecto
 * en este build apunta a la ruta donde PHP se compiló (algo como C:\php\ext),
 * no a donde de verdad quedó extraído en este proyecto.
 */
function configurarPhpIni(carpetaPhp) {
  const rutaIni = path.join(carpetaPhp, 'php.ini');
  if (fs.existsSync(rutaIni)) {
    console.log('php.ini ya existe, no se toca.');
    return;
  }

  const rutaPlantilla = path.join(carpetaPhp, 'php.ini-production');
  if (!fs.existsSync(rutaPlantilla)) {
    throw new Error(`No se encontró ${rutaPlantilla} para usar como base de php.ini.`);
  }

  let contenido = fs.readFileSync(rutaPlantilla, 'utf8');

  const extensionesNecesarias = ['curl', 'mbstring', 'openssl', 'pdo_sqlite', 'sqlite3', 'zip', 'fileinfo'];
  for (const extension of extensionesNecesarias) {
    contenido = contenido.replace(`;extension=${extension}`, `extension=${extension}`);
  }

  // Relativo al propio php.ini (no al directorio de trabajo del proceso) —
  // así funciona sin importar desde dónde lo lance Electron.
  contenido = contenido.replace(';extension_dir = "ext"', 'extension_dir = "ext"');
  contenido = contenido.replace(';date.timezone =', 'date.timezone = America/Bogota');

  fs.writeFileSync(rutaIni, contenido, 'utf8');
  console.log(`php.ini creado en ${rutaIni} (${extensionesNecesarias.join(', ')} habilitados).`);
}

async function main() {
  if (process.platform !== 'win32') {
    console.log(
      'Este script descarga el PHP portable para Windows. ' +
      `Plataforma actual: ${process.platform} — sáltatelo y coloca tu propio binario de PHP en /php.`
    );
    return;
  }

  if (fs.existsSync(BINARIO_ESPERADO)) {
    console.log(`PHP portable ya está en ${BINARIO_ESPERADO} — nada que hacer.`);
    await descargarCacert(CARPETA_PHP);
    return;
  }

  try {
    const zipDescargado = await descargarPhpPortable(VERSION_PHP);
    extraerZip(zipDescargado, CARPETA_PHP);
    fs.unlinkSync(zipDescargado);

    if (!fs.existsSync(BINARIO_ESPERADO)) {
      throw new Error(`Se extrajo el zip pero no aparece php.exe en ${CARPETA_PHP}.`);
    }

    configurarPhpIni(CARPETA_PHP);
    await descargarCacert(CARPETA_PHP);

    console.log(`Listo: PHP ${VERSION_PHP} portable en ${CARPETA_PHP}`);
  } catch (error) {
    console.error('\nNo se pudo preparar PHP portable automáticamente.');
    console.error(error.message);
    console.error(
      '\nPuedes descargarlo a mano: ve a https://windows.php.net/download/, ' +
      'baja el zip "Non Thread Safe" x64, y descomprímelo en la carpeta /php ' +
      `de este proyecto (debe quedar en ${BINARIO_ESPERADO}).`
    );
    process.exitCode = 1;
  }
}

main();
