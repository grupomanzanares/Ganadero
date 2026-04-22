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
    'cierre'              => getCierreContrato($id),
    'resumen'             => getResumenGeneral(),
    'estado_contrato'     => getEstadoContrato($id),
    'liquidaciones'       => getReporteLiquidaciones(),
    'liquidacion_detalle' => getDetalleLiquidacion($id),
    default               => Response::error("Acción '{$action}' no válida.", 404),
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

// ── Reporte listado de liquidaciones ─────────────────────────
function getReporteLiquidaciones(): void {
    $pdo = getDB();

    $where  = ['1=1'];
    $params = [];

    $empresaFactura = (int)input('empresa_factura', 0, 'GET');
    $idCliente      = (int)input('cliente',         0, 'GET');
    $estado         = input('estado',      '', 'GET');
    $fechaDesde     = input('fecha_desde', '', 'GET');
    $fechaHasta     = input('fecha_hasta', '', 'GET');
    $idContrato     = (int)input('contrato', 0, 'GET');

    if ($empresaFactura > 0) { $where[] = 'l.id_empresa_factura = ?'; $params[] = $empresaFactura; }
    if ($idCliente      > 0) { $where[] = 'l.id_cliente = ?';         $params[] = $idCliente;      }
    if ($estado        !== '') { $where[] = 'l.estado = ?';            $params[] = $estado;          }
    if ($fechaDesde    !== '') { $where[] = 'l.fecha_venta >= ?';      $params[] = $fechaDesde;      }
    if ($fechaHasta    !== '') { $where[] = 'l.fecha_venta <= ?';      $params[] = $fechaHasta;      }
    if ($idContrato     > 0) { $where[] = 'l.id_contrato = ?';        $params[] = $idContrato;      }

    $whereStr = implode(' AND ', $where);

    // KPIs agregados
    $stmtKpi = $pdo->prepare(
        "SELECT
            COUNT(DISTINCT l.id)                        AS total_liquidaciones,
            COALESCE(SUM(ag.total_animales),0)          AS total_animales,
            COALESCE(SUM(l.peso_total_kg),0)            AS total_kg,
            COALESCE(SUM(ag.total_ingresos),0)          AS total_ingresos,
            COALESCE(SUM(ag.total_costos),0)            AS total_costos,
            COALESCE(SUM(ag.total_ganancia),0)          AS total_ganancia
         FROM liquidaciones l
         LEFT JOIN empresas  e  ON e.id  = l.id_empresa_factura
         LEFT JOIN clientes  cl ON cl.id = l.id_cliente
         LEFT JOIN (
             SELECT id_liquidacion,
                    COUNT(*)           AS total_animales,
                    SUM(valor_venta)   AS total_ingresos,
                    SUM(costo_total)   AS total_costos,
                    SUM(ganancia)      AS total_ganancia
             FROM liquidacion_animales
             GROUP BY id_liquidacion
         ) ag ON ag.id_liquidacion = l.id
         WHERE {$whereStr}"
    );
    $stmtKpi->execute($params);
    $kpis = $stmtKpi->fetch();

    // Listado
    $stmt = $pdo->prepare(
        "SELECT l.id, l.numero_factura, l.fecha_venta, l.peso_total_kg,
                l.valor_venta_unitario_kg, l.otros_gastos, l.estado, l.observacion,
                c.codigo  AS contrato_codigo, c.id AS id_contrato,
                e.nombre  AS empresa_factura,
                cl.nombre AS cliente,
                ag.total_animales,
                ag.total_ingresos,
                ag.total_costos,
                ag.total_ganancia
         FROM liquidaciones l
         JOIN contratos_compra c ON c.id  = l.id_contrato
         LEFT JOIN empresas  e  ON e.id   = l.id_empresa_factura
         LEFT JOIN clientes  cl ON cl.id  = l.id_cliente
         LEFT JOIN (
             SELECT id_liquidacion,
                    COUNT(*)           AS total_animales,
                    SUM(valor_venta)   AS total_ingresos,
                    SUM(costo_total)   AS total_costos,
                    SUM(ganancia)      AS total_ganancia
             FROM liquidacion_animales
             GROUP BY id_liquidacion
         ) ag ON ag.id_liquidacion = l.id
         WHERE {$whereStr}
         ORDER BY l.fecha_venta DESC, l.id DESC"
    );
    $stmt->execute($params);

    Response::success([
        'kpis'          => $kpis,
        'liquidaciones' => $stmt->fetchAll(),
    ]);
}

// ── Detalle completo de una liquidación ──────────────────────
function getDetalleLiquidacion(int $id): void {
    if ($id <= 0) Response::error('ID inválido.');
    $pdo = getDB();

    $stmt = $pdo->prepare(
        'SELECT l.*,
                c.codigo AS contrato_codigo, c.id AS id_contrato,
                c.fecha_compra,
                e.nombre  AS empresa_factura,
                cl.nombre AS cliente,
                ec.nombre AS empresa_compra
         FROM liquidaciones l
         JOIN contratos_compra c ON c.id  = l.id_contrato
         LEFT JOIN empresas  e  ON e.id   = l.id_empresa_factura
         LEFT JOIN clientes  cl ON cl.id  = l.id_cliente
         LEFT JOIN empresas  ec ON ec.id  = c.id_empresa_compra
         WHERE l.id = ?'
    );
    $stmt->execute([$id]);
    $liq = $stmt->fetch();
    if (!$liq) Response::notFound();

    // Animales detallados
    $stmt2 = $pdo->prepare(
        'SELECT la.*, a.codigo AS animal_codigo
         FROM liquidacion_animales la
         JOIN animales a ON a.id = la.id_animal
         WHERE la.id_liquidacion = ?
         ORDER BY la.tipo_salida DESC, la.id'
    );
    $stmt2->execute([$id]);
    $liq['animales'] = $stmt2->fetchAll();

    // Totales
    $stmt3 = $pdo->prepare(
        'SELECT
            COUNT(*)                                                AS total_animales,
            SUM(CASE WHEN tipo_salida="venta"  THEN 1 ELSE 0 END) AS vendidos,
            SUM(CASE WHEN tipo_salida="muerte" THEN 1 ELSE 0 END) AS muertos,
            ROUND(SUM(valor_venta),2)                              AS total_ingresos,
            ROUND(SUM(costo_compra),2)                             AS total_costo_compra,
            ROUND(SUM(costo_flete_entrada),2)                      AS total_flete_entrada,
            ROUND(SUM(costo_manutencion),2)                        AS total_manutencion,
            ROUND(SUM(costo_flete_salida),2)                       AS total_flete_salida,
            ROUND(SUM(otros_gastos),2)                             AS total_otros_gastos,
            ROUND(SUM(costo_total),2)                              AS total_costos,
            ROUND(SUM(ganancia),2)                                 AS total_ganancia,
            ROUND(AVG(dias_manutencion),0)                         AS promedio_dias
         FROM liquidacion_animales
         WHERE id_liquidacion = ?'
    );
    $stmt3->execute([$id]);
    $liq['totales'] = $stmt3->fetch();

    // Socios por contrato: cada animal pertenece a su contrato con sus propios socios
    // Se agrupa por contrato → se calcula la ganancia de los animales de ese contrato
    // → se distribuye según el porcentaje de cada socio EN ESE contrato
    $stmt4 = $pdo->prepare(
        'SELECT
             c.id       AS id_contrato,
             c.codigo   AS contrato_codigo,
             s.id       AS id_socio,
             s.nombre   AS socio,
             em.nombre  AS empresa,
             cs.porcentaje,
             ROUND(SUM(la.ganancia), 2)                             AS ganancia_contrato,
             COUNT(la.id)                                           AS animales_contrato,
             ROUND(SUM(la.ganancia) * cs.porcentaje / 100, 2)      AS ganancia_estimada
         FROM liquidacion_animales la
         JOIN animales           a  ON a.id   = la.id_animal
         JOIN contratos_compra   c  ON c.id   = a.id_contrato
         JOIN contrato_socios    cs ON cs.id_contrato = c.id
         JOIN socios             s  ON s.id   = cs.id_socio
         JOIN empresas           em ON em.id  = s.id_empresa
         WHERE la.id_liquidacion = ?
         GROUP BY c.id, cs.id_socio
         ORDER BY c.codigo, s.nombre'
    );
    $stmt4->execute([$id]);
    $liq['socios'] = $stmt4->fetchAll();

    Response::success($liq);
}
