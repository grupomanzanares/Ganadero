<?php
// ============================================================
// api/catalogos.php — Empresas, socios, proveedores, clientes,
//                     tipos animal, fletes, manutención
// Usa: ?recurso=empresas|socios|proveedores|clientes|tipos|fletes|manutencion
// ============================================================

require_once __DIR__ . '/../bootstrap.php';
header('Content-Type: application/json; charset=utf-8');

if (!Auth::check()) Response::unauthorized();

$recurso = input('recurso', '', 'GET');
$method  = $_SERVER['REQUEST_METHOD'];
$id      = (int)input('id', 0, 'GET');

match ($recurso) {
    'empresas'    => handleEmpresas($method, $id),
    'socios'      => handleSocios($method, $id),
    'proveedores' => handleProveedores($method, $id),
    'clientes'    => handleClientes($method, $id),
    'tipos'       => handleTipos(),
    'fletes'      => handleFletes($method, $id),
    'manutencion' => handleManutencion($method, $id),
    default       => Response::error("Recurso '{$recurso}' no válido.", 404),
};

// ── EMPRESAS ─────────────────────────────────────────────────
function handleEmpresas(string $method, int $id): void {
    $pdo = getDB();
    if ($method === 'GET') {
        if ($id > 0) {
            $stmt = $pdo->prepare('SELECT * FROM empresas WHERE id = ?');
            $stmt->execute([$id]);
            $r = $stmt->fetch();
            $r ? Response::success($r) : Response::notFound();
        }
        Response::success($pdo->query('SELECT id, nombre, nit, telefono, activa FROM empresas ORDER BY nombre')->fetchAll());
    }
    if ($method === 'POST') {
        Auth::requirePermission('empresas', 'crear');
        $d = jsonInput();
        if (empty($d['nombre'])) Response::error('El nombre es obligatorio.');
        $stmt = $pdo->prepare('INSERT INTO empresas (nombre, nit, telefono, direccion) VALUES (?,?,?,?)');
        $stmt->execute([$d['nombre'], $d['nit'] ?? null, $d['telefono'] ?? null, $d['direccion'] ?? null]);
        Response::success(['id' => $pdo->lastInsertId()], 'Empresa creada.');
    }
    if ($method === 'PUT') {
        Auth::requirePermission('empresas', 'editar');
        $d = jsonInput();
        $pdo->prepare('UPDATE empresas SET nombre=?, nit=?, telefono=?, direccion=?, activa=? WHERE id=?')
            ->execute([$d['nombre'], $d['nit'] ?? null, $d['telefono'] ?? null, $d['direccion'] ?? null, $d['activa'] ?? 1, $id]);
        Response::success(null, 'Empresa actualizada.');
    }
}

// ── SOCIOS ───────────────────────────────────────────────────
function handleSocios(string $method, int $id): void {
    $pdo = getDB();
    if ($method === 'GET') {
        $idEmpresa = (int)input('empresa', 0, 'GET');
        $todos     = (bool)input('todos',  0, 'GET'); // 1 = incluir inactivos

        if ($id > 0) {
            $stmt = $pdo->prepare(
                'SELECT s.*, e.nombre AS empresa, u.nombre AS usuario_nombre
                 FROM socios s
                 JOIN empresas e  ON e.id = s.id_empresa
                 LEFT JOIN usuarios u ON u.id = s.id_usuario
                 WHERE s.id = ?'
            );
            $stmt->execute([$id]);
            $r = $stmt->fetch();
            $r ? Response::success($r) : Response::notFound();
        }

        $where  = ['1=1'];
        $params = [];
        if ($idEmpresa > 0) { $where[] = 's.id_empresa = ?'; $params[] = $idEmpresa; }
        if (!$todos)         { $where[] = 's.activo = 1'; }
        $w = implode(' AND ', $where);

        $stmt = $pdo->prepare(
            "SELECT s.id, s.id_empresa, s.id_usuario,
                    s.nombre, s.cedula, s.telefono, s.email, s.activo,
                    e.nombre AS empresa,
                    u.nombre AS usuario_nombre
             FROM socios s
             JOIN empresas e  ON e.id = s.id_empresa
             LEFT JOIN usuarios u ON u.id = s.id_usuario
             WHERE {$w}
             ORDER BY s.nombre"
        );
        $stmt->execute($params);
        Response::success($stmt->fetchAll());
    }
    if ($method === 'POST') {
        Auth::requirePermission('socios', 'crear');
        $d = jsonInput();
        if (empty($d['nombre']) || empty($d['id_empresa'])) Response::error('Nombre y empresa son obligatorios.');
        $stmt = $pdo->prepare(
            'INSERT INTO socios (id_empresa, id_usuario, nombre, cedula, telefono, email) VALUES (?,?,?,?,?,?)'
        );
        $stmt->execute([
            (int)$d['id_empresa'],
            !empty($d['id_usuario']) ? (int)$d['id_usuario'] : null,
            $d['nombre'],
            $d['cedula']   ?? null,
            $d['telefono'] ?? null,
            $d['email']    ?? null,
        ]);
        Response::success(['id' => $pdo->lastInsertId()], 'Socio creado.');
    }
    if ($method === 'PUT') {
        Auth::requirePermission('socios', 'editar');
        $d = jsonInput();
        $pdo->prepare(
            'UPDATE socios SET id_empresa=?, id_usuario=?, nombre=?, cedula=?, telefono=?, email=?, activo=? WHERE id=?'
        )->execute([
            (int)$d['id_empresa'],
            !empty($d['id_usuario']) ? (int)$d['id_usuario'] : null,
            $d['nombre'],
            $d['cedula']   ?? null,
            $d['telefono'] ?? null,
            $d['email']    ?? null,
            (int)($d['activo'] ?? 1),
            $id,
        ]);
        Response::success(null, 'Socio actualizado.');
    }
}

// ── PROVEEDORES ──────────────────────────────────────────────
function handleProveedores(string $method, int $id): void {
    $pdo = getDB();
    if ($method === 'GET') {
        Response::success($pdo->query('SELECT id, nombre, nit, telefono FROM proveedores WHERE activo=1 ORDER BY nombre')->fetchAll());
    }
    if ($method === 'POST') {
        Auth::requirePermission('proveedores', 'crear');
        $d = jsonInput();
        if (empty($d['nombre'])) Response::error('El nombre es obligatorio.');
        $stmt = $pdo->prepare('INSERT INTO proveedores (nombre, nit, telefono) VALUES (?,?,?)');
        $stmt->execute([$d['nombre'], $d['nit'] ?? null, $d['telefono'] ?? null]);
        Response::success(['id' => $pdo->lastInsertId()], 'Proveedor creado.');
    }
}

// ── CLIENTES ─────────────────────────────────────────────────
function handleClientes(string $method, int $id): void {
    $pdo = getDB();
    if ($method === 'GET') {
        Response::success($pdo->query('SELECT id, nombre, nit, telefono FROM clientes WHERE activo=1 ORDER BY nombre')->fetchAll());
    }
    if ($method === 'POST') {
        Auth::requirePermission('clientes', 'crear');
        $d = jsonInput();
        if (empty($d['nombre'])) Response::error('El nombre es obligatorio.');
        $stmt = $pdo->prepare('INSERT INTO clientes (nombre, nit, telefono) VALUES (?,?,?)');
        $stmt->execute([$d['nombre'], $d['nit'] ?? null, $d['telefono'] ?? null]);
        Response::success(['id' => $pdo->lastInsertId()], 'Cliente creado.');
    }
}

// ── TIPOS ANIMAL ─────────────────────────────────────────────
function handleTipos(): void {
    Response::success(getDB()->query('SELECT id, nombre FROM tipos_animal ORDER BY nombre')->fetchAll());
}

// ── FLETES SALIDA ────────────────────────────────────────────
function handleFletes(string $method, int $id): void {
    $pdo = getDB();
    if ($method === 'GET') {
        if ($id > 0) {
            $stmt = $pdo->prepare('SELECT f.*, e.nombre AS empresa FROM fletes_salida f JOIN empresas e ON e.id=f.id_empresa WHERE f.id=?');
            $stmt->execute([$id]);
            $r = $stmt->fetch();
            $r ? Response::success($r) : Response::notFound();
        }
        $stmt = $pdo->prepare('SELECT f.id, f.fecha, f.origen, f.destino, f.vehiculo, f.cantidad_animales, f.valor_total, f.valor_por_animal, e.nombre AS empresa FROM fletes_salida f JOIN empresas e ON e.id=f.id_empresa ORDER BY f.fecha DESC');
        $stmt->execute();
        Response::success($stmt->fetchAll());
    }
    if ($method === 'POST') {
        Auth::requirePermission('fletes', 'crear');
        $d = jsonInput();
        if (empty($d['id_empresa']) || empty($d['fecha']) || empty($d['origen']) ||
            empty($d['destino'])   || empty($d['cantidad_animales']) || empty($d['valor_total'])) {
            Response::error('Todos los campos del flete son obligatorios.');
        }
        $stmt = $pdo->prepare('INSERT INTO fletes_salida (id_empresa,fecha,origen,destino,vehiculo,cantidad_animales,valor_total,observacion,creado_por) VALUES (?,?,?,?,?,?,?,?,?)');
        $stmt->execute([$d['id_empresa'],$d['fecha'],$d['origen'],$d['destino'],$d['vehiculo'] ?? null,
            $d['cantidad_animales'],$d['valor_total'],$d['observacion'] ?? null, Auth::user()['id']]);
        Response::success(['id' => $pdo->lastInsertId()], 'Flete registrado.');
    }
}

// ── MANUTENCIÓN ──────────────────────────────────────────────
function handleManutencion(string $method, int $id): void {
    Auth::requirePermission('manutencion', 'ver');
    $pdo = getDB();
    if ($method === 'GET') {
        Response::success($pdo->query('SELECT * FROM tarifas_manutencion ORDER BY fecha_vigencia DESC')->fetchAll());
    }
    if ($method === 'POST') {
        Auth::requirePermission('manutencion', 'crear');
        $d = jsonInput();
        if (empty($d['valor_dia']) || empty($d['fecha_vigencia'])) Response::error('Valor y fecha son obligatorios.');
        $stmt = $pdo->prepare('INSERT INTO tarifas_manutencion (valor_dia, fecha_vigencia, observacion, creado_por) VALUES (?,?,?,?)');
        $stmt->execute([$d['valor_dia'], $d['fecha_vigencia'], $d['observacion'] ?? null, Auth::user()['id']]);
        Response::success(['id' => $pdo->lastInsertId()], 'Tarifa registrada.');
    }
}
