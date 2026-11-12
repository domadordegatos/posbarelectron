<?php
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Configuracion.php';
require_once __DIR__ . '/Licencia.php';
require_once __DIR__ . '/Auditoria.php';

/**
 * Control a distancia de la configuración de un equipo, desde el servidor del
 * proveedor. Viaja en el mismo canal de siempre: cada respuesta del latido
 * (heartbeat, cada 6 h) puede traer un bloque "configuracion_remota", y el ping
 * (cada minuto) avisa cuando hay una revisión nueva para que llegue en minutos.
 * Contrato completo: docs/control_remoto.md
 *
 *   "configuracion_remota": {
 *       "gestionada": true,               // false / ausente = el proveedor NO la administra a distancia
 *       "revision": 7,                    // número que sube con cada cambio en el servidor
 *       "tipo_negocio": "servicios",      // opcional
 *       "aplicar_preset": true,           // opcional: parte de los módulos sugeridos de ese tipo
 *       "modulos": { "propina": true, "existencias": false },   // opcional: solo cambian los que aparecen
 *       "parametros": { "propina_porcentaje": 10, "propina_por_defecto": false }   // opcional (un valor null = "sin cambio")
 *   }
 *
 * Reglas de seguridad y robustez:
 *  - Solo se aceptan módulos, tipos y parámetros CONOCIDOS y con valores válidos;
 *    lo demás se ignora y queda anotado (un servidor más nuevo no rompe a un equipo viejo).
 *  - Se aplica solo cuando la revisión CAMBIA respecto de la última aplicada (idempotente:
 *    el mismo bloque llegando cada minuto no vuelve a tocar nada).
 *  - Lo aplicado queda guardado en el equipo: sin internet sigue funcionando igual.
 *  - Cada aplicación queda en la auditoría (revisión y cambios exactos).
 *  - Nunca borra datos: apagar un módulo solo oculta/bloquea su función.
 *  - Un error aquí jamás debe impedir el latido ni la venta (best-effort, como el resto de la licencia).
 */
class ConfiguracionRemota
{
    /** Lo que el servidor puede ajustar como "parámetros" (clave => [mínimo, máximo] o 'bool'). */
    private const PARAMETROS = [
        'propina_porcentaje' => [0, 30],
        'propina_por_defecto' => 'bool',
    ];

    /**
     * @return array{aplicada: bool, motivo: string, revision: int|null, cambios: array, advertencias: array}
     */
    public static function procesar(array $respuesta): array
    {
        $resultado = ['aplicada' => false, 'motivo' => '', 'revision' => null, 'cambios' => [], 'advertencias' => []];

        // Un servidor que todavía no conoce esta función no manda el bloque: no se toca nada.
        if (!array_key_exists('configuracion_remota', $respuesta)) {
            $resultado['motivo'] = 'El servidor no envió configuración remota.';
            return $resultado;
        }

        $bloque = $respuesta['configuracion_remota'];
        $datos = Licencia::datos();

        if (!is_array($bloque) || empty($bloque['gestionada'])) {
            // El proveedor dejó de administrarla a distancia: el control local (Superadmin) vuelve a mandar.
            if (!empty($datos['gestion_remota'])) {
                Licencia::fusionarDatos(['gestion_remota' => false]);
                $resultado['motivo'] = 'El proveedor dejó de administrar la configuración a distancia.';
            } else {
                $resultado['motivo'] = 'No se administra a distancia.';
            }
            return $resultado;
        }

        $revision = self::entero($bloque['revision'] ?? null);
        if ($revision === null || $revision < 1) {
            $resultado['motivo'] = 'Revisión inválida: se ignora el bloque.';
            return $resultado;
        }
        $resultado['revision'] = $revision;

        $revisionAplicada = (int) ($datos['config_revision_aplicada'] ?? 0);
        $yaGestionada = !empty($datos['gestion_remota']);
        if ($yaGestionada && $revision === $revisionAplicada) {
            $resultado['motivo'] = 'Ya estaba aplicada esta revisión.';
            return $resultado;
        }

        $advertencias = [];
        $tipoActual = Configuracion::tipoNegocio();
        $modulosActuales = Configuracion::modulosActivos();

        // --- tipo de negocio y preset
        $tipoNuevo = $tipoActual;
        if (array_key_exists('tipo_negocio', $bloque)) {
            if (is_string($bloque['tipo_negocio']) && in_array($bloque['tipo_negocio'], Configuracion::TIPOS_NEGOCIO, true)) {
                $tipoNuevo = $bloque['tipo_negocio'];
            } else {
                $advertencias[] = 'tipo_negocio desconocido: se ignora.';
            }
        }
        $modulosNuevos = $modulosActuales;
        if (!empty($bloque['aplicar_preset']) && isset(Configuracion::PRESETS_TIPO_NEGOCIO[$tipoNuevo])) {
            foreach (Configuracion::PRESETS_TIPO_NEGOCIO[$tipoNuevo] as $modulo => $activo) {
                $modulosNuevos[$modulo] = (bool) $activo;
            }
        }

        // --- módulos: solo los conocidos, con valor booleano claro
        if (isset($bloque['modulos']) && is_array($bloque['modulos'])) {
            foreach ($bloque['modulos'] as $modulo => $valor) {
                if ($valor === null) {
                    continue; // null = "sin cambio para este módulo" (un servidor puede mandar el mapa completo)
                }
                if (!in_array($modulo, Configuracion::MODULOS, true)) {
                    $advertencias[] = "módulo desconocido \"$modulo\": se ignora.";
                    continue;
                }
                $booleano = self::booleano($valor);
                if ($booleano === null) {
                    $advertencias[] = "valor inválido para el módulo \"$modulo\": se ignora.";
                    continue;
                }
                $modulosNuevos[$modulo] = $booleano;
            }
        } elseif (isset($bloque['modulos'])) {
            $advertencias[] = '"modulos" no es un objeto: se ignora.';
        }

        // --- parámetros
        $parametrosNuevos = [];
        if (isset($bloque['parametros']) && is_array($bloque['parametros'])) {
            foreach ($bloque['parametros'] as $clave => $valor) {
                if ($valor === null) {
                    continue; // null = "sin valor definido en el servidor": el equipo conserva el suyo, sin advertencia
                }
                if (!isset(self::PARAMETROS[$clave])) {
                    $advertencias[] = "parámetro desconocido \"$clave\": se ignora.";
                    continue;
                }
                $regla = self::PARAMETROS[$clave];
                if ($regla === 'bool') {
                    $booleano = self::booleano($valor);
                    if ($booleano === null) {
                        $advertencias[] = "valor inválido para \"$clave\": se ignora.";
                        continue;
                    }
                    $parametrosNuevos[$clave] = $booleano;
                } else {
                    if (!is_numeric($valor) || (float) $valor < $regla[0] || (float) $valor > $regla[1]) {
                        $advertencias[] = "valor fuera de rango para \"$clave\": se ignora.";
                        continue;
                    }
                    $parametrosNuevos[$clave] = (float) $valor;
                }
            }
        }

        // --- aplicar solo lo que de verdad cambia
        $cambios = [];
        if ($tipoNuevo !== $tipoActual) {
            $cambios['tipo_negocio'] = [$tipoActual, $tipoNuevo];
        }
        foreach (Configuracion::MODULOS as $modulo) {
            if (($modulosNuevos[$modulo] ?? false) !== ($modulosActuales[$modulo] ?? false)) {
                $cambios['modulos'][$modulo] = [(bool) ($modulosActuales[$modulo] ?? false), (bool) $modulosNuevos[$modulo]];
            }
        }
        if (isset($cambios['tipo_negocio']) || isset($cambios['modulos'])) {
            Configuracion::guardarModulos($tipoNuevo, $modulosNuevos);
        }
        if ($parametrosNuevos) {
            $porcentaje = $parametrosNuevos['propina_porcentaje'] ?? Configuracion::propinaPorcentaje();
            $porDefecto = $parametrosNuevos['propina_por_defecto'] ?? Configuracion::propinaPorDefecto();
            if ($porcentaje !== Configuracion::propinaPorcentaje() || $porDefecto !== Configuracion::propinaPorDefecto()) {
                $cambios['parametros'] = ['propina_porcentaje' => $porcentaje, 'propina_por_defecto' => $porDefecto];
                Configuracion::guardarParametrosPropina($porcentaje, $porDefecto);
            }
        }

        Licencia::fusionarDatos([
            'gestion_remota' => true,
            'config_revision_aplicada' => $revision,
            'config_aplicada_en' => date('c'),
        ]);

        try {
            Auditoria::registrar(null, 'configuracion_remota', 'configuracion', null, [
                'revision' => $revision,
                'cambios' => $cambios,
                'advertencias' => $advertencias,
            ]);
        } catch (Throwable $e) {
            // La auditoría nunca debe impedir aplicar la configuración.
        }

        return ['aplicada' => true, 'motivo' => 'Revisión aplicada.', 'revision' => $revision, 'cambios' => $cambios, 'advertencias' => $advertencias];
    }

    /** Lo que este equipo le informa al servidor en cada latido, para que el panel muestre "aplicada ✓ / pendiente". */
    public static function estadoParaServidor(): array
    {
        $datos = Licencia::datos();
        $activos = array_keys(array_filter(Configuracion::modulosActivos()));

        return [
            'config_revision_aplicada' => (int) ($datos['config_revision_aplicada'] ?? 0),
            'tipo_negocio' => Configuracion::tipoNegocio(),
            'modulos_activos' => json_encode(array_values($activos)),
            // Para que el panel del servidor pueda IMPORTAR lo que el equipo tiene hoy (y no arrancar con todo vacío).
            'parametros_actuales' => json_encode([
                'propina_porcentaje' => Configuracion::propinaPorcentaje(),
                'propina_por_defecto' => Configuracion::propinaPorDefecto(),
            ]),
        ];
    }

    private static function entero($valor): ?int
    {
        if (is_int($valor)) {
            return $valor;
        }
        if (is_string($valor) && preg_match('/^-?\d+$/', $valor)) {
            return (int) $valor;
        }
        if (is_float($valor) && floor($valor) === $valor) {
            return (int) $valor;
        }

        return null;
    }

    private static function booleano($valor): ?bool
    {
        if (is_bool($valor)) {
            return $valor;
        }
        if ($valor === 1 || $valor === '1' || $valor === 'true') {
            return true;
        }
        if ($valor === 0 || $valor === '0' || $valor === 'false') {
            return false;
        }

        return null;
    }
}
