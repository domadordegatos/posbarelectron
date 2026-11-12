<?php
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Sincronizacion.php';

class clientes
{
    public function buscar(string $termino, int $limite = 10): array
    {
        $pdo = Database::conexion();
        $like = '%' . trim($termino) . '%';
        $stmt = $pdo->prepare(
            'SELECT id_cliente, nombre, identificacion, telefono, limite_credito
             FROM clientes
             WHERE estado = 1 AND (nombre LIKE :like1 OR identificacion LIKE :like2)
             ORDER BY nombre ASC
             LIMIT :limite'
        );
        $stmt->bindValue(':like1', $like, PDO::PARAM_STR);
        $stmt->bindValue(':like2', $like, PDO::PARAM_STR);
        $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function listar(): array
    {
        $pdo = Database::conexion();
        $stmt = $pdo->query(
            'SELECT id_cliente, nombre, identificacion, telefono, direccion, limite_credito, estado
             FROM clientes ORDER BY nombre ASC'
        );

        return $stmt->fetchAll();
    }

    /**
     * Crea el cliente y devuelve 1 si salió bien (0 si no). ATENCIÓN: eso es un "sí/no", NO el
     * identificador — quien necesite el id del cliente recién creado debe usar crearConId().
     * (El "Crear cliente nuevo" de Ventas usaba este valor como id: todo cliente creado ahí
     * quedaba asociado al cliente número 1, el primero de la base.)
     */
    public function crear(string $nombre, ?string $identificacion, ?string $telefono, ?string $direccion, float $limiteCredito): int
    {
        return $this->crearConId($nombre, $identificacion, $telefono, $direccion, $limiteCredito) > 0 ? 1 : 0;
    }

    /** Crea el cliente y devuelve su id_cliente REAL (0 si no se pudo crear). */
    public function crearConId(string $nombre, ?string $identificacion, ?string $telefono, ?string $direccion, float $limiteCredito): int
    {
        $pdo = Database::conexion();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare(
                "INSERT INTO clientes (nombre, identificacion, telefono, direccion, limite_credito, actualizado_en)
                 VALUES (:nombre, :identificacion, :telefono, :direccion, :limite, datetime('now','localtime'))"
            );
            $ok = $stmt->execute([
                'nombre' => $nombre,
                'identificacion' => $identificacion ?: null,
                'telefono' => $telefono ?: null,
                'direccion' => $direccion ?: null,
                'limite' => max(0, $limiteCredito),
            ]);
            $id = $ok ? (int) $pdo->lastInsertId() : 0;
            if ($id > 0) {
                Sincronizacion::marcarOrigenPropio($pdo, 'clientes', 'id_cliente', 'origen_id_cliente', $id);
                $this->encolarEventoCliente($pdo, $id);
            }
            $pdo->commit();

            return $id;
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public function editar(int $id, string $nombre, ?string $identificacion, ?string $telefono, ?string $direccion, float $limiteCredito, int $estado): int
    {
        $pdo = Database::conexion();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare(
                "UPDATE clientes
                 SET nombre = :nombre, identificacion = :identificacion, telefono = :telefono,
                     direccion = :direccion, limite_credito = :limite, estado = :estado, actualizado_en = datetime('now','localtime')
                 WHERE id_cliente = :id"
            );
            $ok = $stmt->execute([
                'nombre' => $nombre,
                'identificacion' => $identificacion ?: null,
                'telefono' => $telefono ?: null,
                'direccion' => $direccion ?: null,
                'limite' => max(0, $limiteCredito),
                'estado' => $estado,
                'id' => $id,
            ]);
            Sincronizacion::marcarOrigenPropio($pdo, 'clientes', 'id_cliente', 'origen_id_cliente', $id);
            $this->encolarEventoCliente($pdo, $id);
            $pdo->commit();

            return $ok ? 1 : 0;
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /** Arma y encola el evento "cliente" para las demás cajas — usado al crear y al editar. */
    private function encolarEventoCliente(PDO $pdo, int $id): void
    {
        if (!Sincronizacion::sincronizacionActiva()) {
            return;
        }
        $stmt = $pdo->prepare('SELECT nombre, identificacion, telefono, direccion, limite_credito, estado, actualizado_en FROM clientes WHERE id_cliente = :id');
        $stmt->execute(['id' => $id]);
        $c = $stmt->fetch();
        if (!$c) {
            return;
        }
        $origen = Sincronizacion::origenDe($pdo, 'clientes', 'id_cliente', 'origen_id_cliente', $id);
        if ($origen === null) {
            return;
        }
        Sincronizacion::registrarEventoSaliente($pdo, 'cliente', $id, [
            'caja_origen' => $origen['caja'],
            'id_cliente_origen' => $origen['id'],
            'nombre' => $c['nombre'],
            'identificacion' => $c['identificacion'],
            'telefono' => $c['telefono'],
            'direccion' => $c['direccion'],
            'limite_credito' => (float) $c['limite_credito'],
            'estado' => (int) $c['estado'],
            'actualizado_en' => $c['actualizado_en'],
        ]);
    }

    /** Lo que el cliente debe hoy: ventas a crédito no anuladas − abonos. */
    public function saldo(int $idCliente): float
    {
        $pdo = Database::conexion();
        $stmt = $pdo->prepare(
            "SELECT
                COALESCE((SELECT SUM(fp.valor) FROM factura_pagos fp
                          JOIN facturas f ON f.id_factura = fp.id_factura
                          WHERE f.cliente_id = :id1 AND fp.metodo_pago = 'credito' AND f.anulada = 0), 0)
                - COALESCE((SELECT SUM(total) FROM facturas WHERE cliente_id = :id2 AND tipo_transaccion = 5), 0) AS saldo"
        );
        $stmt->execute(['id1' => $idCliente, 'id2' => $idCliente]);

        return (float) $stmt->fetch()['saldo'];
    }

    public function listarConSaldo(): array
    {
        $clientes = $this->listar();
        foreach ($clientes as &$c) {
            $c['saldo'] = $this->saldo((int) $c['id_cliente']);
        }

        return $clientes;
    }
}
