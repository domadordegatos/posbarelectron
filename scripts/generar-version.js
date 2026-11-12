#!/usr/bin/env node
'use strict';

/**
 * Genera una versión nueva del instalador: pide el número de versión,
 * actualiza package.json y app/version.txt, y corre el build de Windows.
 * Pensado para lanzarse desde dist/Generar nueva version.bat (doble clic),
 * pero también funciona corriéndolo directo: node scripts/generar-version.js
 */

const fs = require('fs');
const path = require('path');
const readline = require('readline');
const { execSync } = require('child_process');

const RAIZ = path.join(__dirname, '..');
const RUTA_PACKAGE_JSON = path.join(RAIZ, 'package.json');
const RUTA_VERSION_TXT = path.join(RAIZ, 'app', 'version.txt');

function compararVersiones(a, b) {
  const pa = a.split('.').map(Number);
  const pb = b.split('.').map(Number);
  for (let i = 0; i < 3; i++) {
    const diferencia = (pa[i] || 0) - (pb[i] || 0);
    if (diferencia !== 0) return diferencia;
  }
  return 0;
}

// No se usa rl.question() encadenado: en pruebas, la SEGUNDA pregunta se
// quedaba esperando para siempre incluso con una sola interfaz compartida
// (un problema conocido de readline con stdin no interactivo). Escuchar
// el evento 'line' directamente y encolar las respuestas es más confiable
// y funciona igual de bien escribiendo en vivo en la consola.
const rl = readline.createInterface({ input: process.stdin, output: process.stdout, terminal: false });
const lineasEnEspera = [];
let resolverProximaLinea = null;
rl.on('line', (linea) => {
  if (resolverProximaLinea) {
    const resolver = resolverProximaLinea;
    resolverProximaLinea = null;
    resolver(linea);
  } else {
    lineasEnEspera.push(linea);
  }
});

function preguntar(texto) {
  process.stdout.write(texto);
  const promesa = lineasEnEspera.length > 0
    ? Promise.resolve(lineasEnEspera.shift())
    : new Promise((resolve) => { resolverProximaLinea = resolve; });
  return promesa.then((linea) => linea.trim());
}

async function main() {
  const packageJson = JSON.parse(fs.readFileSync(RUTA_PACKAGE_JSON, 'utf8'));
  const versionActual = packageJson.version;

  console.log('============================================');
  console.log('  Generar nueva version de EneSoft POS');
  console.log('============================================');
  console.log(`Version actual instalada en el proyecto: ${versionActual}`);
  console.log('');

  const nuevaVersion = await preguntar('Numero de la version nueva (ej. 1.0.9): ');

  if (!/^\d+\.\d+\.\d+$/.test(nuevaVersion)) {
    console.error('\nEse numero no es valido — debe ser tres numeros separados por puntos, ej. 1.0.9.');
    process.exitCode = 1;
    return;
  }

  if (compararVersiones(nuevaVersion, versionActual) <= 0) {
    console.error(
      `\nLa version ${nuevaVersion} no es mas nueva que la actual (${versionActual}).\n` +
      'El sistema de actualizaciones (electron-updater) rechaza versiones repetidas o menores — usa un numero mayor.'
    );
    process.exitCode = 1;
    return;
  }

  const confirmar = await preguntar(`\nSe va a generar la version ${nuevaVersion}. Escribe S para confirmar: `);
  if (confirmar.toUpperCase() !== 'S') {
    console.log('Cancelado — no se modifico ni genero nada.');
    return;
  }

  packageJson.version = nuevaVersion;
  fs.writeFileSync(RUTA_PACKAGE_JSON, JSON.stringify(packageJson, null, 2) + '\n');
  fs.writeFileSync(RUTA_VERSION_TXT, nuevaVersion + '\n');
  console.log(`\nNumero de version actualizado: ${versionActual} -> ${nuevaVersion}`);

  console.log('\nGenerando el instalador (esto puede tardar varios minutos, no cierres esta ventana)...\n');
  try {
    execSync('npm run build:win', { cwd: RAIZ, stdio: 'inherit' });
  } catch (error) {
    console.error('\nERROR: fallo la generacion del instalador. Revisa el mensaje de arriba para saber que paso.');
    process.exitCode = 1;
    return;
  }

  console.log('\n============================================');
  console.log(`  Listo! Version ${nuevaVersion} generada en la carpeta dist`);
  console.log('============================================');
  console.log('\nArchivos para subir al panel de Versiones (los 3, juntos):');
  console.log(`  - EneSoft POS-Setup-${nuevaVersion}.exe`);
  console.log(`  - EneSoft POS-Setup-${nuevaVersion}.exe.blockmap`);
  console.log('  - latest.yml');
}

main().finally(() => rl.close());
