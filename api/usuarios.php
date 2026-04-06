<?php
// ============================================================
// api/usuarios.php — CRUD usuarios del sistema
// ============================================================
require_once __DIR__ . '/../bootstrap.php';
header('Content-Type: application/json; charset=utf-8');

if (!Auth::check()) Response::unauthorized();
Auth::requirePermission('usuarios', 'ver');

$method = $_SERVER['REQUEST_METHOD'];
$id     = (int)input('id', 0, 'GET');

match ($method) {
    'GET'    => handleGet($id),
    'POST'   => handlePost(),
    'PUT'    => handlePut($id),
    default  => Response::error('Método no permitido.', 405),
};

function handleGet(int $id): void {
    $pdo = getDB();
    if ($id > 0) {
        $stmt = $pdo->prepare(
            'SELECT u.id, u.nombre, u.email, u.activo, u.id_rol,
                    r.nombre AS rol
             FROM usuarios u JOIN roles r ON r.id = u.id_rol
             WHERE u.id = ?'
        );
        $stmt->execute([$id]);
        $u = $stmt->fetch();
        $u ? Response::success($u) : Response::notFound();
    }
    $stmt = $pdo->query(
        'SELECT u.id, u.nombre, u.email, u.activo, u.id_rol,
                r.nombre AS rol, u.creado_en
         FROM usuarios u JOIN roles r ON r.id = u.id_rol
         ORDER BY u.nombre'
    );
    Response::success($stmt->fetchAll());
}

function handlePost(): void {
    Auth::requirePermission('usuarios', 'crear');
    $d = jsonInput();
    if (empty($d['nombre']) || empty($d['email']) || empty($d['password']) || empty($d['rol'])) {
        Response::error('Nombre, email, contraseña y rol son obligatorios.');
    }
    $pdo  = getDB();
    $chk  = $pdo->prepare('SELECT id FROM usuarios WHERE email = ?');
    $chk->execute([$d['email']]);
    if ($chk->fetch()) Response::error('El email ya está registrado.');

    $stmt = $pdo->prepare(
        'INSERT INTO usuarios (nombre, email, password, id_rol, activo)
         VALUES (?,?,?,?,?)'
    );
    $stmt->execute([
        $d['nombre'],
        $d['email'],
        password_hash($d['password'], PASSWORD_BCRYPT, ['cost' => 12]),
        (int)$d['rol'],
        (int)($d['activo'] ?? 1),
    ]);
    Logger::log('crear', 'usuarios', (int)$pdo->lastInsertId());
    Response::success(['id' => $pdo->lastInsertId()], 'Usuario creado correctamente.');
}

function handlePut(int $id): void {
    Auth::requirePermission('usuarios', 'editar');
    if ($id <= 0) Response::error('ID inválido.');
    $d   = jsonInput();
    $pdo = getDB();

    $stmt = $pdo->prepare('SELECT id FROM usuarios WHERE id = ?');
    $stmt->execute([$id]);
    if (!$stmt->fetch()) Response::notFound('Usuario no encontrado.');

    $fields = ['nombre=?', 'email=?', 'id_rol=?', 'activo=?'];
    $params = [$d['nombre'], $d['email'], (int)$d['rol'], (int)($d['activo'] ?? 1)];

    if (!empty($d['password'])) {
        $fields[] = 'password=?';
        $params[] = password_hash($d['password'], PASSWORD_BCRYPT, ['cost' => 12]);
    }
    $params[] = $id;
    $pdo->prepare('UPDATE usuarios SET ' . implode(', ', $fields) . ' WHERE id = ?')
        ->execute($params);

    Logger::log('editar', 'usuarios', $id);
    Response::success(null, 'Usuario actualizado correctamente.');
}
