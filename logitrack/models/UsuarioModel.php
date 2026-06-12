<?php
class UsuarioModel {

    public function __construct(private PDO $pdo) {}

    /**
     * Registra un nuevo usuario. Devuelve el id_usuario creado.
     * Lanza PDOException en error de BD (ej. duplicate entry 1062).
     */
    public function registrar(
        string $dni,
        string $nombre,
        string $apellido,
        string $email,
        string $password,
        string $rol,
        array  $extra = []
    ): int {
        $stmt_rol = $this->pdo->prepare("SELECT id_rol FROM rol WHERE nombre = :nombre");
        $stmt_rol->execute([':nombre' => $rol]);
        $fila_rol = $stmt_rol->fetch();

        if (!$fila_rol) {
            throw new InvalidArgumentException('Rol inválido.');
        }

        $hash = password_hash($password, PASSWORD_BCRYPT);

        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO usuario (dni, email, nombre, apellido, password_hash, id_rol)
                VALUES (:dni, :email, :nombre, :apellido, :hash, :id_rol)
            ");
            $stmt->execute([
                ':dni'      => $dni,
                ':email'    => $email,
                ':nombre'   => $nombre,
                ':apellido' => $apellido,
                ':hash'     => $hash,
                ':id_rol'   => $fila_rol['id_rol'],
            ]);

            $id_usuario = (int) $this->pdo->lastInsertId();

            if ($rol === 'chofer') {
                $chk = $this->pdo->prepare("SELECT id_chofer FROM chofer WHERE dni = :dni");
                $chk->execute([':dni' => $dni]);
                if (!$chk->fetch()) {
                    $stmt2 = $this->pdo->prepare("
                        INSERT INTO chofer (dni, nombre, apellido, email, legajo, tipo_licencia)
                        VALUES (:dni, :nombre, :apellido, :email, :legajo, :licencia)
                    ");
                    $stmt2->execute([
                        ':dni'      => $dni,
                        ':nombre'   => $nombre,
                        ':apellido' => $apellido,
                        ':email'    => $email,
                        ':legajo'   => $extra['legajo'],
                        ':licencia' => $extra['tipo_licencia'],
                    ]);
                }
            } elseif ($rol === 'cliente') {
                $chk = $this->pdo->prepare("SELECT id_cliente FROM cliente WHERE dni = :dni");
                $chk->execute([':dni' => $dni]);
                if (!$chk->fetch()) {
                    $stmt2 = $this->pdo->prepare("
                        INSERT INTO cliente (dni, nombre, apellido, email, direccion)
                        VALUES (:dni, :nombre, :apellido, :email, :direccion)
                    ");
                    $stmt2->execute([
                        ':dni'       => $dni,
                        ':nombre'    => $nombre,
                        ':apellido'  => $apellido,
                        ':email'     => $email,
                        ':direccion' => $extra['direccion'] ?? null,
                    ]);
                }
            } elseif ($rol === 'empleado') {
                $chk = $this->pdo->prepare("SELECT id_empleado FROM empleado WHERE dni = :dni");
                $chk->execute([':dni' => $dni]);
                if (!$chk->fetch()) {
                    $stmt2 = $this->pdo->prepare("
                        INSERT INTO empleado (dni, nombre, apellido, legajo, id_sucursal)
                        VALUES (:dni, :nombre, :apellido, '', :suc)
                    ");
                    $stmt2->execute([
                        ':dni'      => $dni,
                        ':nombre'   => $nombre,
                        ':apellido' => $apellido,
                        ':suc'      => $extra['id_sucursal'],
                    ]);

                    $id_empleado = (int) $this->pdo->lastInsertId();
                    $legajo = 'EMP' . str_pad((string) $id_empleado, 4, '0', STR_PAD_LEFT);
                    $this->pdo->prepare("UPDATE empleado SET legajo = :legajo WHERE id_empleado = :id")
                        ->execute([':legajo' => $legajo, ':id' => $id_empleado]);
                }
            }

            $this->pdo->commit();
        } catch (PDOException $e) {
            $this->pdo->rollBack();
            throw $e;
        }

        return $id_usuario;
    }

    /**
     * Busca un usuario por DNI + Email (recuperación de contraseña).
     */
    public function buscarPorDniEmail(string $dni, string $email): array|false {
        $stmt = $this->pdo->prepare("
            SELECT id_usuario, nombre
            FROM   usuario
            WHERE  dni = :dni AND email = :email
            LIMIT  1
        ");
        $stmt->execute([':dni' => $dni, ':email' => $email]);
        return $stmt->fetch();
    }

    /**
     * Actualiza la contraseña de un usuario identificado por DNI + Email.
     */
    public function actualizarPassword(string $dni, string $email, string $password): void {
        $hash = password_hash($password, PASSWORD_BCRYPT);
        $this->pdo->prepare("UPDATE usuario SET password_hash = :hash WHERE dni = :dni AND email = :email")
            ->execute([':hash' => $hash, ':dni' => $dni, ':email' => $email]);
    }
}
