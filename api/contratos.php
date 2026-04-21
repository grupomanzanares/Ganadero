<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../bootstrap.php';
header('Content-Type: application/json; charset=utf-8');

if (!Auth::check()) Response::unauthorized();

$method = $_SERVER['REQUEST_METHOD'];
$action = input('action', '', 'GET');
$id     = (int)input('id', 0, 'GET');

match ($method) {
    'GET'    => handleGet($action, $id),
    'POST'   => handlePost(),
    'PUT'    => handlePut($id),
    'DELETE' => handleDelete($id),
    default  => Response::error('Método no permitido.', 405),
};

// ── GET ──────────────────────────────────────────────────────
function handleGet(string $action, int $id): void {
    Auth::requirePermission('contratos', 'ver');
    $pdo = getDB();

    if ($id > 0) {
        $stmt = $pdo->prepare(
            'SELECT c.*,
                    e.nombre  AS empresa_compra,
                    ep.nombre AS empresa_pago,
                    p.nombre  AS proveedor,
                    t.nombre  AS tipo_animal
             FROM contratos_compra c
             JOIN empresas e    ON e.id  = c.id_empresa_compra
             JOIN empresas ep   ON ep.id = c.id_empresa_pago
             JOIN proveedores p ON p.id  = c.id_proveedor
             JOIN tipos_animal t ON t.id = c.id_tipo_animal
             WHERE c.id = ?'
        );
        $stmt->execute([$id]);
        $contrato = $stmt->fetch();
        if (!$contrato) Response::notFound();

        $stmt2 = $pdo->prepare(
            'SELECT cs.id, cs.porcentaje, s.nombre AS socio, em.nombre AS empresa
             FROM contrato_socios cs
             JOIN socios s    ON s.id  = cs.id_socio
             JOIN empresas em ON em.id = s.id_empresa
             WHERE cs.id_contrato = ?'
        );
        $stmt2->execute([$id]);
        $contrato['socios'] = $stmt2->fetchAll();

        $stmt3 = $pdo->prepare(
            'SELECT id, codigo, peso_inicial_kg, peso_finca_kg,
                    costo_compra_animal, costo_flete_animal, valor_promedio_kg, estado
             FROM animales WHERE id_contrato = ? ORDER BY id'
        );
        $stmt3->execute([$id]);
        $contrato['animales'] = $stmt3->fetchAll();

        Response::success($contrato);
    }

    $where  = ['1=1'];
    $params = [];

    $estado = input('estado', '', 'GET');
    if ($estado !== '') { $where[] = 'c.estado = ?'; $params[] = $estado; }

    $empresa = (int)input('empresa', 0, 'GET');
    if ($empresa > 0) { $where[] = 'c.id_empresa_compra = ?'; $params[] = $empresa; }

    $stmt = $pdo->prepare(
        'SELECT c.id, c.codigo, c.fecha_compra, c.cantidad_animales,
                c.peso_total_kg, c.valor_total, c.costo_flete, c.estado,
                e.nombre AS empresa_compra, p.nombre AS proveedor, t.nombre AS tipo_animal
         FROM contratos_compra c
         JOIN empresas e    ON e.id  = c.id_empresa_compra
         JOIN proveedores p ON p.id  = c.id_proveedor
         JOIN tipos_animal t ON t.id = c.id_tipo_animal
         WHERE ' . implode(' AND ', $where) . ' ORDER BY c.fecha_compra DESC, c.id DESC'
    );
    $stmt->execute($params);
    Response::success($stmt->fetchAll());
}

// ── POST ─────────────────────────────────────────────────────
function handlePost(): void {
    Auth::requirePermission('contratos', 'crear');
    $data = jsonInput();

    // Campos obligatorios — costo_flete puede ser 0, se trata aparte
    $required = [
        'id_empresa_compra','id_proveedor','id_empresa_pago',
        'id_tipo_animal','fecha_compra','cantidad_animales',
        'peso_total_kg','valor_unitario_kg','socios'
    ];

    foreach ($required as $field) {
        // Considera vacío si no existe, es null, o es string vacío
        // Permite explícitamente el valor 0 (numérico o string "0")
        $val = $data[$field] ?? null;
        if ($val === null || $val === '') {
            Response::error("El campo '{$field}' es obligatorio.");
        }
    }

    if (empty($data['socios']) || !is_array($data['socios'])) {
        Response::error('Debe seleccionar al menos un socio.');
    }

    // costo_flete es opcional — si no viene o viene vacío, se trata como 0
    $flete = isset($data['costo_flete']) && $data['costo_flete'] !== ''
        ? (float)$data['costo_flete']
        : 0.0;

    $pdo = getDB();
    $pdo->beginTransaction();

    try {
        $codigo       = generarCodigoContrato();
        $pesoTotal    = (float)$data['peso_total_kg'];
        $cantidad     = (int)$data['cantidad_animales'];
        $valorKg      = (float)$data['valor_unitario_kg'];  // precio por kg

        // Costo de compra por animal = valorKg × peso_promedio
        $pesoPromedio      = $cantidad > 0 ? $pesoTotal / $cantidad : 0;
        $costoCompraAnimal = round($valorKg * $pesoPromedio, 2);
        // Flete por animal (puede ser 0)
        $fletePorAnimal    = $cantidad > 0 ? round($flete / $cantidad, 2) : 0;

        // Valor total del lote = valorKg × pesoTotal
        // (guardado en contratos_compra como columna generada si existe,
        //  o calculado aquí para el INSERT)

        $stmt = $pdo->prepare(
            'INSERT INTO contratos_compra
             (codigo, id_empresa_compra, id_proveedor, id_empresa_pago,
              id_tipo_animal, edad_meses, fecha_compra, fecha_factura,
              numero_factura, cantidad_animales, peso_total_kg,
              valor_unitario_kg, costo_flete, observacion, creado_por)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
        );
        $stmt->execute([
            $codigo,
            $data['id_empresa_compra'],
            $data['id_proveedor'],
            $data['id_empresa_pago'],
            $data['id_tipo_animal'],
            $data['edad_meses']     ?? null,
            $data['fecha_compra'],
            $data['fecha_factura']  ?? null,
            $data['numero_factura'] ?? null,
            $cantidad,
            $pesoTotal,
            $valorKg,
            $flete,           // puede ser 0.00
            $data['observacion'] ?? null,
            Auth::user()['id'],
        ]);
        $idContrato = (int)$pdo->lastInsertId();

        // Socios (partes iguales)
        $cantSocios = count($data['socios']);
        $porcentaje = round(100 / $cantSocios, 2);
        $stmtS      = $pdo->prepare(
            'INSERT INTO contrato_socios (id_contrato, id_socio, porcentaje) VALUES (?,?,?)'
        );
        foreach ($data['socios'] as $idSocio) {
            $stmtS->execute([$idContrato, (int)$idSocio, $porcentaje]);
        }

        // Animales (uno por cabeza)
        $stmtA = $pdo->prepare(
            'INSERT INTO animales
             (id_contrato, peso_inicial_kg, costo_compra_animal, costo_flete_animal)
             VALUES (?,?,?,?)'
        );
        for ($i = 0; $i < $cantidad; $i++) {
            $stmtA->execute([
                $idContrato,
                round($pesoPromedio, 2),
                $costoCompraAnimal,  // valorKg × peso_promedio
                $fletePorAnimal,     // puede ser 0
            ]);
        }

        $pdo->commit();
        Logger::log('crear', 'contratos_compra', $idContrato, "Código: {$codigo}");
        Response::success(['id' => $idContrato, 'codigo' => $codigo], 'Contrato creado exitosamente.');

    } catch (Throwable $e) {
        $pdo->rollBack();
        error_log($e->getMessage());
        Response::serverError('No se pudo guardar el contrato.');
    }
}

// ── PUT ──────────────────────────────────────────────────────
function handlePut(int $id): void {
    Auth::requirePermission('contratos', 'editar');
    if ($id <= 0) Response::error('ID inválido.');

    $data = jsonInput();
    $pdo  = getDB();

    $stmt = $pdo->prepare('SELECT estado FROM contratos_compra WHERE id = ?');
    $stmt->execute([$id]);
    $contrato = $stmt->fetch();
    if (!$contrato) Response::notFound();
    if ($contrato['estado'] !== 'abierto') Response::error('Solo se pueden editar contratos abiertos.');

    $pdo->prepare(
        'UPDATE contratos_compra
         SET fecha_factura=?, numero_factura=?, observacion=?, edad_meses=?
         WHERE id=?'
    )->execute([
        $data['fecha_factura']  ?? null,
        $data['numero_factura'] ?? null,
        $data['observacion']    ?? null,
        $data['edad_meses']     ?? null,
        $id,
    ]);

    Logger::log('editar', 'contratos_compra', $id);
    Response::success(null, 'Contrato actualizado.');
}

// ── DELETE ────────────────────────────────────────────────────
function handleDelete(int $id): void {
    Auth::requirePermission('contratos', 'eliminar');
    if ($id <= 0) Response::error('ID inválido.');

    $pdo  = getDB();
    $stmt = $pdo->prepare('SELECT estado FROM contratos_compra WHERE id = ?');
    $stmt->execute([$id]);
    $contrato = $stmt->fetch();
    if (!$contrato) Response::notFound();

    $stmt2 = $pdo->prepare('SELECT COUNT(*) FROM animales WHERE id_contrato = ?');
    $stmt2->execute([$id]);
    if ((int)$stmt2->fetchColumn() > 0) {
        $pdo->prepare('UPDATE contratos_compra SET estado="anulado" WHERE id=?')->execute([$id]);
        Logger::log('anular', 'contratos_compra', $id);
        Response::success(null, 'Contrato anulado.');
    }

    $pdo->prepare('DELETE FROM contratos_compra WHERE id = ?')->execute([$id]);
    Logger::log('eliminar', 'contratos_compra', $id);
    Response::success(null, 'Contrato eliminado.');
}
