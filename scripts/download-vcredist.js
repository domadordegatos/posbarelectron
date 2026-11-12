'use strict';

/**
 * Descarga el instalador oficial de Microsoft Visual C++ Redistributable
 * (x64) y lo deja en /vcredist, en la raíz del proyecto — de ahí lo toma
 * electron-builder como extraResource (ver electron-builder.yml) y
 * build/installer.nsh lo instala en silencio durante la instalación de
 * EneSoft POS.
 *
 * Por qué hace falta: el PHP portable que se descarga con
 * "npm run descargar-php" es un binario nativo compilado con el compilador
 * de Visual Studio de Microsoft (build "vs17" — ver scripts/download-php.js).
 * Ese php.exe depende en tiempo de ejecución de las DLL de ese runtime
 * (VCRUNTIME140.dll, MSVCP140.dll, etc.). Cualquier máquina de desarrollo ya
 * las tiene instaladas de antes (por Visual Studio, otros juegos/programas,
 * Windows Update, etc.), lo cual esconde el problema — pero un Windows 11
 * recién instalado en el negocio de un cliente puede NO tenerlas, y en ese
 * caso php.exe no arranca en absoluto: falla con el código de salida
 * 0xC0000135 (STATUS_DLL_NOT_FOUND), que en la app se ve como
 * "El servidor interno (PHP) se cerró inesperadamente. Código de salida:
 * 3221225781" — ese código decimal es exactamente 0xC0000135.
 *
 * Se corre automáticamente en "npm install" (script postinstall), o a mano
 * con "npm run descargar-vcredist".
 */

const https = require('https');
const fs = require('fs');
const path = require('path');
const os = require('os');

// Enlace oficial y permanente de Microsoft para la última versión del
// redistribuible x64 de VC++ 2015-2022 — Microsoft lo mantiene actualizado
// detrás de esta misma URL, así que no hay una "versión" que fijar aquí.
const URL_VCREDIST = 'https://aka.ms/vs/17/release/vc_redist.x64.exe';
const RAIZ_PROYECTO = path.join(__dirname, '..');
const CARPETA_VCREDIST = path.join(RAIZ_PROYECTO, 'vcredist');
const BINARIO_ESPERADO = path.join(CARPETA_VCREDIST, 'vc_redist.x64.exe');

function descargarConRedirecciones(url, destino, saltosRestantes = 5) {
  return new Promise((resolve, reject) => {
    if (saltosRestantes <= 0) {
      reject(new Error('Demasiadas redirecciones al descargar el VC++ Redistributable.'));
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

async function main() {
  if (process.platform !== 'win32') {
    console.log(
      'El VC++ Redistributable solo aplica al instalador de Windows — ' +
      `sáltatelo (plataforma actual: ${process.platform}).`
    );
    return;
  }

  if (fs.existsSync(BINARIO_ESPERADO)) {
    console.log(`VC++ Redistributable ya está en ${BINARIO_ESPERADO} — nada que hacer.`);
    return;
  }

  fs.mkdirSync(CARPETA_VCREDIST, { recursive: true });
  const destinoTemporal = path.join(os.tmpdir(), `vc_redist.x64-${Date.now()}.exe`);

  try {
    console.log(`Descargando VC++ Redistributable desde ${URL_VCREDIST} …`);
    await descargarConRedirecciones(URL_VCREDIST, destinoTemporal);
    fs.renameSync(destinoTemporal, BINARIO_ESPERADO);
    console.log(`Listo: VC++ Redistributable en ${BINARIO_ESPERADO}`);
  } catch (error) {
    fs.unlink(destinoTemporal, () => {});
    console.error('\nNo se pudo descargar el VC++ Redistributable automáticamente.');
    console.error(error.message);
    console.error(
      '\nPuedes descargarlo a mano: https://aka.ms/vs/17/release/vc_redist.x64.exe ' +
      `y guardarlo como ${BINARIO_ESPERADO}.`
    );
    process.exitCode = 1;
  }
}

main();
