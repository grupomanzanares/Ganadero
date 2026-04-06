<?php
// ============================================================
// core/Auth.php — Autenticación y control de acceso
// ============================================================

class Auth {

    /** Inicia sesión del usuario */
    public static function login(string $email, string $password): bool {
        $pdo  = getDB();
        $stmt = $pdo->prepare(
            'SELECT u.id, u.nombre, u.email, u.password, u.activo,
                    r.id AS id_rol, r.nombre AS rol
             FROM usuarios u
             JOIN roles r ON r.id = u.id_rol
             WHERE u.email = ? LIMIT 1'
        );
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user || !$user['activo']) return false;
        if (!password_verify($password, $user['password'])) return false;

        $_SESSION['user'] = [
            'id'     => $user['id'],
            'nombre' => $user['nombre'],
            'email'  => $user['email'],
            'id_rol' => $user['id_rol'],
            'rol'    => $user['rol'],
        ];

        // Cargar permisos en sesión
        $_SESSION['permisos'] = self::loadPermisos($user['id_rol']);

        session_regenerate_id(true);
        return true;
    }

    /** Cierra la sesión */
    public static function logout(): void {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        session_destroy();
    }

    /** Retorna el usuario en sesión o null */
    public static function user(): ?array {
        return $_SESSION['user'] ?? null;
    }

    /** Verifica si hay sesión activa */
    public static function check(): bool {
        return isset($_SESSION['user']);
    }

    /** Verifica permiso sobre un módulo */
    public static function can(string $modulo, string $accion = 'ver'): bool {
        $permisos = $_SESSION['permisos'] ?? [];
        return ($permisos[$modulo][$accion] ?? false) == 1;
    }

    /** Redirige si no tiene sesión (para páginas PHP) */
    public static function requireLogin(): void {
        if (!self::check()) {
            header('Location: ' . APP_URL . '/login.php');
            exit;
        }
    }

    /** Aborta con 403 si no tiene permiso (para APIs) */
    public static function requirePermission(string $modulo, string $accion = 'ver'): void {
        if (!self::can($modulo, $accion)) {
            http_response_code(403);
            echo json_encode(['error' => 'Sin permiso para esta acción.']);
            exit;
        }
    }

    /** Carga array de permisos indexado por módulo */
    private static function loadPermisos(int $idRol): array {
        $stmt = getDB()->prepare(
            'SELECT modulo, ver, crear, editar, eliminar FROM permisos_rol WHERE id_rol = ?'
        );
        $stmt->execute([$idRol]);
        $permisos = [];
        foreach ($stmt->fetchAll() as $row) {
            $permisos[$row['modulo']] = [
                'ver'      => (bool)$row['ver'],
                'crear'    => (bool)$row['crear'],
                'editar'   => (bool)$row['editar'],
                'eliminar' => (bool)$row['eliminar'],
            ];
        }
        return $permisos;
    }
}
