<?php
// ============================================================
// api/reportes.php — Reportes y cierre de contratos
// ============================================================

require_once __DIR__ . '/../bootstrap.php';
header('Content-Type: application/json; charset=utf-8');

if (!Auth::check()) Response::unauthorized();
Auth::requirePermission('reportes', 'ver');

$action = input('action', '', 'GET');
$id     = (int)input('id', 0, 'GET');

match ($action) {
    'cierre'       => getCierreContrato($id),
    'resumen'      => getResumenGeneral(),
    'estado_contrato' => getEstadoContrato($id),
    default        => Response::error("Acción '{$action}' no válida.", 404),
};

// ── Cierre detallado de un contrato ──────────────────────────
function getCierreContrato(int $idContrato): void {
    if ($idContrato <= 0) Response::error('ID de contrato requerido.');
    $pdo = getDB();

    $stmt = $pdo->prepare(
        'SELECT cc.*, c.codigo, c.fecha_compra, c.cantidad_animales AS total_comprado,
                e.nombre AS empresa_compra, t.nombre AS tipo_animal
         FROM cierre_contrato cc
         JOIN contratos_compra c ON c.id = cc.id_contrato
         JOIN empresas e         ON e.id  = c.id_empresa_compra
         JOIN tipos_animal t     ON t.id  = c.id_tipo_animal
         WHERE cc.id_contrato = ?'
    );
    $stmt->execute([$idContrato]);
    $cierre = $stmt->fetch();
    if (!$cierre) Response::notFound('Este contrato aún no tiene cierre generado.');

    // Detalle por socio
    $stmt2 = $pdo->prepare(
        'SELECT csd.porcentaje, csd.ganancia, s.nombre AS socio, em.nombre AS empresa
         FROM cierre_socios_detalle csd
         JOIN socios s    ON s.id  = csd.id_socio
         JOIN empresas em ON em.id = s.id_empresa
         WHERE csd.id_cierre = ?'
    );
    $stmt2->execute([$cierre['id']]);
    $cierre['detalle_socios'] = $stmt2->fetchAll();

    // Liquidaciones del contrato
    $stmt3 = $pdo->prepare(
        'SELECT l.id, l.numero_factura, l.fecha_venta, l.peso_total_kg,
                l.valor_total_venta, cl.nombre AS cliente,
                (SELECT COUNT(*) FROM liquidacion_animales la WHERE la.id_liquidacion = l.id) AS animales
         FROM liquidaciones l
         JOIN clientes cl ON cl.id = l.id_cliente
         WHERE l.id_contrato = ? ORDER BY l.fecha_venta'
    );
    $stmt3->execute([$idContrato]);
    $cierre['liquidaciones'] = $stmt3->fetchAll();

    Response::success($cierre);
}

// ── Estado parcial de un contrato abierto ────────────────────
function getEstadoContrato(int $idContrato): void {
    if ($idContrato <= 0) Response::error('ID de contrato requerido.');
    $pdo = getDB();

    $stmt = $pdo->prepare(
        'SELECT c.id, c.codigo, c.fecha_compra, c.cantidad_animales,
                c.valor_total, c.costo_flete, c.estado,
                e.nombre AS empresa, t.nombre AS tipo_animal,
                (SELECT COUNT(*) FROM animales a WHERE a.id_contrato = c.id AND a.estado="activo")   AS activos,
                (SELECT COUNT(*) FROM animales a WHERE a.id_contrato = c.id AND a.estado="vendido")  AS vendidos,
                (SELECT COUNT(*) FROM animales a WHERE a.id_contrato = c.id AND a.estado="muerto")   AS muertos,
                (SELECT COALESCE(SUM(la.ganancia),0)
                 FROM liquidacion_animales la
                 JOIN liquidaciones l ON l.id = la.id_liquidacion
                 WHERE l.id_contrato = c.id) AS ganancia_acumulada,
                (SELECT COALESCE(SUM(la.costo_total),0)
                 FROM liquidacion_animales la
                 JOIN liquidaciones l ON l.id = la.id_liquidacion
                 WHERE l.id_contrato = c.id) AS costos_acumulados
         FROM contratos_compra c
         JOIN empresas e    ON e.id  = c.id_empresa_compra
         JOIN tipos_animal t ON t.id = c.id_tipo_animal
         WHERE c.id = ?'
    );
    $stmt->execute([$idContrato]);
    $data = $stmt->fetch();
    if (!$data) Response::notFound();

    // Socios del contrato
    $stmt2 = $pdo->prepare(
        'SELECT s.nombre, em.nombre AS empresa, cs.porcentaje
         FROM contrato_socios cs
         JOIN socios s    ON s.id  = cs.id_socio
         JOIN empresas em ON em.id = s.id_empresa
         WHERE cs.id_contrato = ?'
    );
    $stmt2->execute([$idContrato]);
    $data['socios'] = $stmt2->fetchAll();

    Response::success($data);
}

// ── Resumen general de todos los contratos ───────────────────
function getResumenGeneral(): void {
    $pdo = getDB();
    $stmt = $pdo->query(
        'SELECT 
            COUNT(*) AS total_contratos,
            SUM(CASE WHEN estado="abierto"  THEN 1 ELSE 0 END) AS abiertos,
            SUM(CASE WHEN estado="cerrado"  THEN 1 ELSE 0 END) AS cerrados,
            SUM(CASE WHEN estado="anulado"  THEN 1 ELSE 0 END) AS anulados,
            SUM(cantidad_animales) AS total_animales,
            SUM(valor_total)       AS valor_total_compras
         FROM contratos_compra'
    );
    $resumen = $stmt->fetch();

    $stmt2 = $pdo->query(
        'SELECT COALESCE(SUM(ganancia_total),0) AS ganancia_total,
                COALESCE(SUM(ingreso_total_ventas),0) AS ingresos_ventas
         FROM cierre_contrato'
    );
    $resumen = array_merge($resumen, $stmt2->fetch());

    // Últimos 5 contratos
    $stmt3 = $pdo->query(
        'SELECT c.id, c.codigo, c.fecha_compra, c.cantidad_animales, c.estado,
                e.nombre AS empresa, t.nombre AS tipo_animal
         FROM contratos_compra c
         JOIN empresas e ON e.id = c.id_empresa_compra
         JOIN tipos_animal t ON t.id = c.id_tipo_animal
         ORDER BY c.id DESC LIMIT 5'
    );
    $resumen['ultimos_contratos'] = $stmt3->fetchAll();

    Response::success($resumen);
}
