'use strict';

/**
 * Cifrado en reposo de la base de datos SQLite (ver README, sección
 * "Cifrado de la base de datos"). PHP necesita el archivo en texto plano
 * mientras corre (no hay build de pdo_sqlite con SQLCipher para el PHP
 * portable de Windows) — lo que sí se controla es que, apenas la app no
 * está corriendo, en el disco no quede nada sin cifrar. La clave AES vive
 * protegida con safeStorage (usa el cifrado de datos del propio Windows,
 * ligado a la cuenta de usuario) en vez de en un archivo plano.
 *
 * Separado de main.js para poder probarlo con un script headless de
 * Electron sin tener que levantar ventana ni proceso de PHP.
 */

const { safeStorage } = require('electron');
const fs = require('fs');
const path = require('path');
const crypto = require('crypto');

const NOMBRE_DB = 'posbar.db';
const SUFIJOS_ARCHIVOS_SQLITE = ['', '-wal', '-shm']; // el principal + los que crea el modo WAL
const NOMBRE_DB_CIFRADA = 'posbar.db.enc';
const NOMBRE_CLAVE_PROTEGIDA = 'clave.protegida';

let claveCifrado = null;

function rutaArchivoDb(directorioDatos, sufijo = '') {
  return path.join(directorioDatos, NOMBRE_DB + sufijo);
}

function rutaDbCifrada(directorioDatos) {
  return path.join(directorioDatos, NOMBRE_DB_CIFRADA);
}

function rutaClaveProtegida(directorioDatos) {
  return path.join(directorioDatos, NOMBRE_CLAVE_PROTEGIDA);
}

function registrarEvento(directorioDatos, mensaje) {
  console.log(`[cifrado] ${mensaje}`);
  try {
    fs.appendFileSync(path.join(directorioDatos, 'cifrado.log'), `[${new Date().toISOString()}] ${mensaje}\n`);
  } catch (error) {
    // Un log que no se pudo escribir no debe bloquear nada más.
  }
}

function obtenerOCrearClave(directorioDatos) {
  if (!safeStorage.isEncryptionAvailable()) {
    registrarEvento(directorioDatos, 'safeStorage no está disponible en este equipo — la base de datos NO se cifrará en reposo.');
    return null;
  }

  const ruta = rutaClaveProtegida(directorioDatos);
  if (fs.existsSync(ruta)) {
    const protegida = fs.readFileSync(ruta);
    return Buffer.from(safeStorage.decryptString(protegida), 'hex');
  }

  const clave = crypto.randomBytes(32);
  fs.writeFileSync(ruta, safeStorage.encryptString(clave.toString('hex')));
  return clave;
}

function cifrarArchivo(rutaOrigen, rutaDestino, clave) {
  const iv = crypto.randomBytes(12);
  const cifrador = crypto.createCipheriv('aes-256-gcm', clave, iv);
  const datos = fs.readFileSync(rutaOrigen);
  const cifrado = Buffer.concat([cifrador.update(datos), cifrador.final()]);
  // Formato del archivo: [iv 12 bytes][auth tag 16 bytes][datos cifrados].
  fs.writeFileSync(rutaDestino, Buffer.concat([iv, cifrador.getAuthTag(), cifrado]));
}

function descifrarArchivo(rutaOrigen, rutaDestino, clave) {
  const contenido = fs.readFileSync(rutaOrigen);
  const iv = contenido.subarray(0, 12);
  const etiqueta = contenido.subarray(12, 28);
  const cifrado = contenido.subarray(28);
  const descifrador = crypto.createDecipheriv('aes-256-gcm', clave, iv);
  descifrador.setAuthTag(etiqueta);
  fs.writeFileSync(rutaDestino, Buffer.concat([descifrador.update(cifrado), descifrador.final()]));
}

/**
 * Se corre ANTES de levantar PHP. Si posbar.db en texto plano ya existe,
 * quiere decir que el cierre anterior no pasó por cifrarYLimpiarAlCerrar
 * (un apagón, un cuelgue, "Finalizar tarea") — en vez de dejarla así
 * indefinidamente hasta el próximo cierre "limpio", se cifra de una vez
 * (usando esa misma copia, que es más nueva que cualquier .enc que
 * hubiera quedado de antes) y se sigue trabajando con ella tal cual.
 */
function prepararBaseDeDatosCifrada(directorioDatos) {
  claveCifrado = obtenerOCrearClave(directorioDatos);
  if (!claveCifrado) return;

  const rutaPlano = rutaArchivoDb(directorioDatos);
  const rutaCifrada = rutaDbCifrada(directorioDatos);

  if (fs.existsSync(rutaPlano)) {
    registrarEvento(directorioDatos, 'Se encontró la base de datos sin cifrar al iniciar (el cierre anterior no fue limpio) — cifrando de nuevo ahora.');
    cifrarArchivo(rutaPlano, rutaCifrada, claveCifrado);
    return;
  }

  if (fs.existsSync(rutaCifrada)) {
    descifrarArchivo(rutaCifrada, rutaPlano, claveCifrado);
    registrarEvento(directorioDatos, 'Base de datos descifrada para esta sesión.');
  }
  // Si no existe ninguna de las dos es una instalación nueva — PHP crea
  // posbar.db en texto plano (ver Database::conexion()); se cifra por
  // primera vez en el próximo cierre limpio o snapshot periódico.
}

function tomarSnapshotCifrado(directorioDatos) {
  if (!claveCifrado) return;
  const rutaPlano = rutaArchivoDb(directorioDatos);
  if (!fs.existsSync(rutaPlano)) return;
  try {
    cifrarArchivo(rutaPlano, rutaDbCifrada(directorioDatos), claveCifrado);
  } catch (error) {
    console.error('[cifrado] No se pudo tomar el snapshot periódico:', error.message);
  }
}

/**
 * Cierre limpio: cifra la base de datos y borra los archivos en texto
 * plano (incluyendo -wal/-shm) — lo único que queda en disco al apagar
 * es el archivo cifrado.
 */
function cifrarYLimpiarAlCerrar(directorioDatos) {
  if (!claveCifrado) return;
  const rutaPlano = rutaArchivoDb(directorioDatos);
  if (!fs.existsSync(rutaPlano)) return;

  try {
    cifrarArchivo(rutaPlano, rutaDbCifrada(directorioDatos), claveCifrado);
    for (const sufijo of SUFIJOS_ARCHIVOS_SQLITE) {
      const ruta = rutaArchivoDb(directorioDatos, sufijo);
      if (fs.existsSync(ruta)) fs.unlinkSync(ruta);
    }
    registrarEvento(directorioDatos, 'Cierre limpio: base de datos cifrada, copia en texto plano eliminada.');
  } catch (error) {
    console.error('[cifrado] No se pudo cifrar/limpiar al cerrar:', error.message);
  }
}

module.exports = {
  prepararBaseDeDatosCifrada,
  tomarSnapshotCifrado,
  cifrarYLimpiarAlCerrar,
  _test: { rutaArchivoDb, rutaDbCifrada, rutaClaveProtegida, cifrarArchivo, descifrarArchivo, obtenerOCrearClave },
};
