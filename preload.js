'use strict';

/**
 * La interfaz vive por completo en PHP/HTML servido por localhost — este
 * preload casi no tiene trabajo que hacer más allá de exponer el puente
 * mínimo hacia el proceso principal: el aviso de actualización forzada
 * pendiente (para bloquear el inicio de ventas/mesas nuevas en la interfaz)
 * y el selector de instalador manual.
 */

const { contextBridge, ipcRenderer } = require('electron');

// OJO: la ventana corre con sandbox:true — ahí Electron solo polyfilla
// require() para un puñado de módulos (electron, events, timers, url).
// 'path' y 'fs' NO están en esa lista; pedirlos aquí lanza "Cannot find
// module" ANTES de llegar a exposeInMainWorld, y como esa excepción no
// queda dentro de ningún try/catch, deja TODO electronAPI sin definir en
// la ventana (sin avisar en ningún lado visible) — así se rompió en
// silencio el badge de actualización, el aviso forzado y el botón de
// actualización manual, los tres a la vez. Por eso este archivo evita
// cualquier require() que no sea 'electron'.

contextBridge.exposeInMainWorld('electronAPI', {
  onActualizacionForzadaPendiente(callback) {
    const manejador = (_evento, pendiente) => callback(pendiente);
    ipcRenderer.on('actualizacion:forzada-pendiente', manejador);
    return () => ipcRenderer.removeListener('actualizacion:forzada-pendiente', manejador);
  },

  consultarActualizacionForzada() {
    return ipcRenderer.invoke('actualizacion:forzada-estado');
  },

  elegirActualizacionManual() {
    return ipcRenderer.invoke('actualizacion:elegir-manual');
  },

  consultarEstadoActualizacion() {
    return ipcRenderer.invoke('actualizacion:estado');
  },

  onEstadoActualizacion(callback) {
    const manejador = (_evento, estado) => callback(estado);
    ipcRenderer.on('actualizacion:estado', manejador);
    return () => ipcRenderer.removeListener('actualizacion:estado', manejador);
  },

  onEventoActualizacion(callback) {
    const manejador = (_evento, dato) => callback(dato);
    ipcRenderer.on('actualizacion:evento', manejador);
    return () => ipcRenderer.removeListener('actualizacion:evento', manejador);
  },

  iniciarActualizacion() {
    return ipcRenderer.invoke('actualizacion:iniciar');
  },

  rechazarActualizacion() {
    return ipcRenderer.invoke('actualizacion:rechazar');
  },

  restaurarBackupDesdeArchivo() {
    return ipcRenderer.invoke('backup:restaurar-desde-archivo');
  },

  listarImpresoras() {
    return ipcRenderer.invoke('impresion:listar-impresoras');
  },

  imprimirTicket(opciones) {
    return ipcRenderer.invoke('impresion:imprimir-ticket', opciones);
  },
});
