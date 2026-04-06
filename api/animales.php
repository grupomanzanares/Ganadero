<?php
// ============================================================
// api/animales.php — Gestión de animales (pesaje y código)
// ============================================================

require_once __DIR__ . '/../bootstrap.php';
header('Content-Type: application/json; charset=utf-8');

if (!Auth::check()) Response::unauthorized();

$method = $_SERVER['REQUEST_METHOD'];
$id     = (int)input('id', 0, 'GET');

match ($method) {
    'GET'  => handleGet($id),
    'PUT'  => handlePut($id),
    default => Response::error('Método no permitido.', 405),
};

// ── GET: animales de un contrato ─────────────────────────────
function handleGet(int $id): void {
    Auth::requirePermission('animales', 'ver');

    $idContrato = (int)input('contrato', 0, 'GET');
    if ($idContrato <= 0) Response::error('Se requiere id de contrato.');

    $stmt = getDB()->prepare(
        'SELECT a.id, a.codigo, a.peso_inicial_kg, a.peso_finca_kg,
                a.costo_compra_animal, a.costo_flete_animal,
                a.valor_promedio_kg, a.estado,
                c.fecha_compra
         FROM animales a
         JOIN contratos_compra c ON c.id = a.id_contrato
         WHERE a.id_contrato = ?
         ORDER BY a.id'
    );
    $stmt->execute([$idContrato]);
    Response::success($stmt->fetchAll());
}

// ── PUT: actualizar código y/o pesaje finca ──────────────────
function handlePut(int $id): void {
    Auth::requirePermission('animales', 'editar');
    if ($id <= 0) Response::error('ID inválido.');

    $data = jsonInput();
    $pdo  = getDB();

    // Verificar que el animal existe y el contrato está abierto
    $stmt = $pdo->prepare(
        'SELECT a.estado, c.estado AS estado_contrato
         FROM animales a
         JOIN contratos_compra c ON c.id = a.id_contrato
         WHERE a.id = ?'
    );
    $stmt->execute([$id]);
    $animal = $stmt->fetch();
    if (!$animal) Response::notFound('Animal no encontrado.');
    if ($animal['estado_contrato'] !== 'abierto') Response::error('El contrato está cerrado.');
    if ($animal['estado'] !== 'activo') Response::error('Este animal ya fue liquidado o anulado.');

    $fields  = [];
    $params  = [];

    if (isset($data['codigo'])) {
        // Verificar unicidad del código
        $stmtChk = $pdo->prepare('SELECT id FROM animales WHERE codigo = ? AND id != ?');
        $stmtChk->execute([$data['codigo'], $id]);
        if ($stmtChk->fetch()) Response::error('El código de animal ya existe.');
        $fields[] = 'codigo = ?';
        $params[] = sanitize($data['codigo']);
    }

    if (isset($data['peso_finca_kg'])) {
        $peso = (float)$data['peso_finca_kg'];
        if ($peso <= 0) Response::error('El peso debe ser mayor a 0.');
        $fields[] = 'peso_finca_kg = ?';
        $params[] = $peso;
    }

    if (empty($fields)) Response::error('No hay datos para actualizar.');

    $params[] = $id;
    $pdo->prepare('UPDATE animales SET ' . implode(', ', $fields) . ' WHERE id = ?')
        ->execute($params);

    // Retornar animal actualizado
    $stmt = $pdo->prepare('SELECT * FROM animales WHERE id = ?');
    $stmt->execute([$id]);
    Logger::log('editar', 'animales', $id);
    Response::success($stmt->fetch(), 'Animal actualizado.');
}
