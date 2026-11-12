<?php
require_once __DIR__ . '/Licencia.php';

/**
 * Programa de fidelización por visitas — addon pago del servidor
 * (Licencia::datos()['fidelizacion_habilitada']), backend en
 * pos.nncoms.com/api/fidelizacion_*.php. Todo esto requiere internet: cada
 * método puede lanzar RuntimeException (sin conexión, servidor caído, addon
 * no contratado) — los controladores lo atrapan y responden
 * {"error": "..."} sin tumbar el resto del POS, que sigue funcionando
 * offline como siempre.
 */
class Fidelizacion
{
    public static function niveles(): array
    {
        $r = Licencia::llamarAutenticado('GET', 'fidelizacion_niveles.php');
        if (empty($r['ok'])) {
            throw new RuntimeException($r['error'] ?? 'No se pudieron cargar los niveles.');
        }

        return $r['niveles'] ?? [];
    }

    public static function guardarNivel(?int $id, int $visitasRequeridas, string $tipoRecompensa, float $valor, string $descripcion): void
    {
        $tiposValidos = ['descuento_pct', 'descuento_fijo', 'producto_gratis', 'texto_libre'];
        if (!in_array($tipoRecompensa, $tiposValidos, true)) {
            throw new InvalidArgumentException('Tipo de recompensa inválido.');
        }
        if ($visitasRequeridas <= 0) {
            throw new InvalidArgumentException('Las visitas requeridas deben ser mayor a cero.');
        }

        $campos = [
            'visitas_requeridas' => $visitasRequeridas,
            'tipo_recompensa' => $tipoRecompensa,
            'valor' => $valor,
            'descripcion' => $descripcion,
        ];
        if ($id !== null) {
            $campos['id'] = $id;
        }

        $r = Licencia::llamarAutenticado('POST', 'fidelizacion_niveles.php', $campos);
        if (empty($r['ok'])) {
            throw new RuntimeException($r['error'] ?? 'No se pudo guardar el nivel.');
        }
    }

    /**
     * A propósito no hay eliminarNivel(): un nivel nunca se borra desde
     * este panel, solo se desactiva (ver cambiarEstadoNivel) — así ninguna
     * recompensa que un cliente ya haya reclamado puede perder su rastro
     * histórico, sin depender de que el servidor lo bloquee.
     */
    public static function cambiarEstadoNivel(int $id, bool $activo): void
    {
        $r = Licencia::llamarAutenticado('POST', 'fidelizacion_niveles.php', [
            'accion' => $activo ? 'activar' : 'desactivar',
            'id' => $id,
        ]);
        if (empty($r['ok'])) {
            throw new RuntimeException($r['error'] ?? 'No se pudo cambiar el estado del nivel.');
        }
    }

    /** @return array la marca, con 'fuentes_disponibles' (catálogo de tipografías) agregado adentro. */
    public static function marca(): array
    {
        $r = Licencia::llamarAutenticado('GET', 'fidelizacion_marca.php');
        if (empty($r['ok'])) {
            throw new RuntimeException($r['error'] ?? 'No se pudo cargar la marca del programa.');
        }

        $marca = $r['marca'] ?? [];
        $marca['fuentes_disponibles'] = $r['fuentes_disponibles'] ?? [];

        return $marca;
    }

    public static function guardarMarca(string $nombrePrograma, string $colorPrincipal, string $eslogan = '', string $fuente = ''): void
    {
        if (!preg_match('/^#[0-9a-fA-F]{6}$/', $colorPrincipal)) {
            throw new InvalidArgumentException('Color inválido.');
        }

        $datos = [
            'nombre_programa' => $nombrePrograma,
            'color_principal' => $colorPrincipal,
            'eslogan' => $eslogan,
        ];
        if ($fuente !== '') {
            $datos['fuente'] = $fuente;
        }

        $r = Licencia::llamarAutenticado('POST', 'fidelizacion_marca.php', $datos);
        if (empty($r['ok'])) {
            throw new RuntimeException($r['error'] ?? 'No se pudo guardar la marca del programa.');
        }
    }

    /** $archivo: el array $_FILES['campo'] tal cual llega del formulario. Devuelve la URL pública de la imagen ya subida. */
    public static function subirImagen(string $tipo, array $archivo): string
    {
        if (!in_array($tipo, ['logo', 'fondo', 'sello', 'check'], true)) {
            throw new InvalidArgumentException('Tipo de imagen inválido.');
        }
        if (($archivo['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new RuntimeException('No se recibió ningún archivo.');
        }

        $r = Licencia::subirArchivoAutenticado(
            'fidelizacion_upload_imagen.php',
            ['tipo' => $tipo],
            'imagen',
            $archivo['tmp_name'],
            $archivo['name'],
            $archivo['type'] ?? null
        );
        if (empty($r['ok'])) {
            throw new RuntimeException($r['error'] ?? 'No se pudo subir la imagen.');
        }

        return $r['url'] ?? '';
    }

    /** {token, url, expira_en} — url es el texto que se pinta como QR (ver pb.pintarQrFidelizacion en app.js). */
    public static function generarQr(): array
    {
        $r = Licencia::llamarAutenticado('POST', 'fidelizacion_generar_qr.php');
        if (empty($r['ok'])) {
            throw new RuntimeException($r['error'] ?? 'No se pudo generar el código QR.');
        }

        return $r;
    }

    /** Alternativa sin QR: el cajero teclea el documento directamente. */
    public static function consultarCliente(string $numeroDocumento): array
    {
        $r = Licencia::llamarAutenticado('GET', 'fidelizacion_consultar_cliente.php', ['numero_documento' => $numeroDocumento]);
        if (empty($r['ok'])) {
            throw new RuntimeException($r['error'] ?? 'Cliente no encontrado.');
        }

        return $r;
    }

    public static function registrarVisita(string $numeroDocumento): array
    {
        $r = Licencia::llamarAutenticado('POST', 'fidelizacion_registrar_visita.php', ['numero_documento' => $numeroDocumento]);
        if (empty($r['ok'])) {
            throw new RuntimeException($r['error'] ?? 'No se pudo registrar la visita.');
        }

        return $r;
    }
}
