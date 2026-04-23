<?php
// ============================================================
// api/reportes_socio.php — Reporte consolidado por socio
// ============================================================
require_once __DIR__ . '/../bootstrap.php';
header('Content-Type: application/json; charset=utf-8');

if (!Auth::check()) Response::unauthorized();
Auth::requirePermission('reportes', 'ver');

$action  = input('action', '', 'GET');
$idSocio = (int)input('socio', 0, 'GET');
$desde   = input('desde',  '', 'GET');   // YYYY-MM-DD
$hasta   = input('hasta',  '', 'GET');   // YYYY-MM-DD
$estado  = input('estado', '', 'GET');   // abierto | cerrado | ''

match ($action) {
    'lista_socios' => getListaSocios(),
    'resumen'      => getResumenSocio($idSocio, $desde, $hasta, $estado),
    'contratos'    => getContratosSocio($idSocio, $desde, $hasta, $estado),
    'animales'     => getAnimalesSocio($idSocio),
    'ganancias'    => getGananciaSocio($idSocio, $desde, $hasta),
    default        => Response::error("Acción '{$action}' no válida.", 404),
};

// ─────────────────────────────────────────────────────────────
// Helpers
// ─────────────────────────────────────────────────────────────
function verificarAccesoSocio(int $idSocio): void {
    $user = Auth::user();
    if ($user['rol'] !== 'socio') return;
    $pdo  = getDB();
    $stmt = $pdo->prepare('SELECT id FROM socios WHERE id = ? AND id_usuario = ?');
    $stmt->execute([$idSocio, $user['id']]);
    if (!$stmt->fetch()) Response::forbidden();
}

/** Construye WHERE + params con filtros opcionales */
function buildWhere(array $extra = [], array $extraParams = []): array {
    global $desde, $hasta, $estado;
    $w = $extra; $p = $extraParams;
    if ($desde)  { $w[] = 'cc.fecha_compra >= ?'; $p[] = $desde; }
    if ($hasta)  { $w[] = 'cc.fecha_compra <= ?'; $p[] = $hasta; }
    if ($estado) { $w[] = 'cc.estado = ?';        $p[] = $estado; }
    return [implode(' AND ', $w), $p];
}

// ─────────────────────────────────────────────────────────────
// Lista de socios
// ─────────────────────────────────────────────────────────────
function getListaSocios(): void {
    $user = Auth::user();
    $pdo  = getDB();
    if ($user['rol'] === 'socio') {
        $stmt = $pdo->prepare(
            'SELECT s.id, s.nombre, e.nombre AS empresa
             FROM socios s JOIN empresas e ON e.id=s.id_empresa
             WHERE s.id_usuario = ?'
        );
        $stmt->execute([$user['id']]);
    } else {
        $stmt = $pdo->query(
            'SELECT s.id, s.nombre, e.nombre AS empresa
             FROM socios s JOIN empresas e ON e.id=s.id_empresa
             WHERE s.activo=1 ORDER BY s.nombre'
        );
    }
    Response::success($stmt->fetchAll());
}

// ─────────────────────────────────────────────────────────────
// Resumen general
// ─────────────────────────────────────────────────────────────
function getResumenSocio(int $id, string $desde, string $hasta, string $estado): void {
    if ($id <= 0) Response::error('ID de socio requerido.');
    verificarAccesoSocio($id);
    $pdo = getDB();

    // Datos del socio
    $stmt = $pdo->prepare(
        'SELECT s.id, s.nombre, s.cedula, s.telefono, e.nombre AS empresa
         FROM socios s JOIN empresas e ON e.id=s.id_empresa WHERE s.id=?'
    );
    $stmt->execute([$id]);
    $socio = $stmt->fetch();
    if (!$socio) Response::notFound('Socio no encontrado.');

    [$w, $p] = buildWhere(['cs.id_socio = ?'], [$id]);

    // Totales de contratos
    $r1 = $pdo->prepare(
        "SELECT COUNT(DISTINCT cs.id_contrato) AS total_contratos,
                SUM(cc.estado='abierto') AS contratos_abiertos,
                SUM(cc.estado='cerrado') AS contratos_cerrados
         FROM contrato_socios cs
         JOIN contratos_compra cc ON cc.id=cs.id_contrato
         WHERE {$w}"
    );
    $r1->execute($p); $contratos = $r1->fetch();

    // Animales proporcionales
    $r2 = $pdo->prepare(
        "SELECT
           ROUND(SUM((SELECT COUNT(*) FROM animales a WHERE a.id_contrato=cs.id_contrato)*(cs.porcentaje/100)),0) AS total_animales,
           ROUND(SUM((SELECT COUNT(*) FROM animales a WHERE a.id_contrato=cs.id_contrato AND a.estado='activo')*(cs.porcentaje/100)),0)  AS animales_activos,
           ROUND(SUM((SELECT COUNT(*) FROM animales a WHERE a.id_contrato=cs.id_contrato AND a.estado='vendido')*(cs.porcentaje/100)),0) AS animales_vendidos,
           ROUND(SUM((SELECT COUNT(*) FROM animales a WHERE a.id_contrato=cs.id_contrato AND a.estado='muerto')*(cs.porcentaje/100)),0)  AS animales_muertos
         FROM contrato_socios cs
         JOIN contratos_compra cc ON cc.id=cs.id_contrato
         WHERE {$w}"
    );
    $r2->execute($p); $animales = $r2->fetch();

    // Financiero (contratos cerrados con cierre generado)
    $r3 = $pdo->prepare(
        "SELECT COALESCE(SUM(csd.ganancia),0) AS ganancia_total,
                COALESCE(SUM(ci.costo_total*(cs.porcentaje/100)),0) AS costo_total,
                COALESCE(SUM(ci.ingreso_total_ventas*(cs.porcentaje/100)),0) AS ingresos_ventas
         FROM contrato_socios cs
         JOIN contratos_compra cc ON cc.id=cs.id_contrato
         JOIN cierre_contrato ci ON ci.id_contrato=cc.id
         JOIN cierre_socios_detalle csd ON csd.id_cierre=ci.id AND csd.id_socio=cs.id_socio
         WHERE {$w}"
    );
    $r3->execute($p); $fin = $r3->fetch();

    // Inversión activa
    $filtAb = ['cs.id_socio=?', "cc.estado='abierto'"];
    $pAb    = [$id];
    if ($desde) { $filtAb[] = 'cc.fecha_compra >= ?'; $pAb[] = $desde; }
    if ($hasta) { $filtAb[] = 'cc.fecha_compra <= ?'; $pAb[] = $hasta; }
    $r4 = $pdo->prepare(
        'SELECT COALESCE(SUM(cc.valor_total*(cs.porcentaje/100)),0)
         FROM contrato_socios cs JOIN contratos_compra cc ON cc.id=cs.id_contrato
         WHERE ' . implode(' AND ', $filtAb)
    );
    $r4->execute($pAb);
    $inversion = (float)$r4->fetchColumn();

    Response::success([
        'socio'           => $socio,
        'contratos'       => $contratos,
        'animales'        => $animales,
        'ganancia_total'  => (float)$fin['ganancia_total'],
        'costo_total'     => (float)$fin['costo_total'],
        'ingresos_ventas' => (float)$fin['ingresos_ventas'],
        'inversion_activa'=> $inversion,
    ]);
}

// ─────────────────────────────────────────────────────────────
// Contratos del socio
// ─────────────────────────────────────────────────────────────
function getContratosSocio(int $id, string $desde, string $hasta, string $estado): void {
    if ($id <= 0) Response::error('ID de socio requerido.');
    verificarAccesoSocio($id);
    $pdo = getDB();
    [$w, $p] = buildWhere(['cs.id_socio = ?'], [$id]);

    $stmt = $pdo->prepare(
        "SELECT
           cc.id, cc.codigo, cc.fecha_compra, cc.estado,
           cc.cantidad_animales, cc.peso_total_kg,
           cc.valor_unitario_kg, cc.valor_total, cc.costo_flete,
           cs.porcentaje,
           t.nombre AS tipo_animal,
           e.nombre AS empresa_compra,
           ROUND(cc.cantidad_animales*(cs.porcentaje/100),0) AS animales_socio,
           ROUND((SELECT COUNT(*) FROM animales a WHERE a.id_contrato=cc.id AND a.estado='activo')*(cs.porcentaje/100),0)  AS activos_socio,
           ROUND((SELECT COUNT(*) FROM animales a WHERE a.id_contrato=cc.id AND a.estado='vendido')*(cs.porcentaje/100),0) AS vendidos_socio,
           ROUND((SELECT COUNT(*) FROM animales a WHERE a.id_contrato=cc.id AND a.estado='muerto')*(cs.porcentaje/100),0)  AS muertos_socio,
           ROUND(cc.valor_total*(cs.porcentaje/100),2) AS inversion_socio,
           (SELECT csd.ganancia FROM cierre_contrato ci
            JOIN cierre_socios_detalle csd ON csd.id_cierre=ci.id AND csd.id_socio=cs.id_socio
            WHERE ci.id_contrato=cc.id LIMIT 1) AS ganancia_socio,
           (SELECT COALESCE(SUM(la.costo_total)*(cs.porcentaje/100),0)
            FROM liquidacion_animales la JOIN liquidaciones l ON l.id=la.id_liquidacion
            WHERE l.id_contrato=cc.id) AS costos_acumulados_socio,
           (SELECT COALESCE(SUM(la.valor_venta)*(cs.porcentaje/100),0)
            FROM liquidacion_animales la JOIN liquidaciones l ON l.id=la.id_liquidacion
            WHERE l.id_contrato=cc.id AND la.tipo_salida='venta') AS ventas_acumuladas_socio
         FROM contrato_socios cs
         JOIN contratos_compra cc ON cc.id=cs.id_contrato
         JOIN tipos_animal t ON t.id=cc.id_tipo_animal
         JOIN empresas e ON e.id=cc.id_empresa_compra
         WHERE {$w}
         ORDER BY cc.fecha_compra DESC"
    );
    $stmt->execute($p);
    Response::success($stmt->fetchAll());
}

// ─────────────────────────────────────────────────────────────
// Animales activos (sin filtro de fecha, siempre los activos)
// ─────────────────────────────────────────────────────────────
function getAnimalesSocio(int $id): void {
    if ($id <= 0) Response::error('ID de socio requerido.');
    verificarAccesoSocio($id);
    $pdo  = getDB();
    $stmt = $pdo->prepare(
        "SELECT a.id, a.codigo, a.peso_inicial_kg, a.peso_finca_kg,
                a.costo_compra_animal, a.costo_flete_animal, a.valor_promedio_kg,
                a.estado, a.creado_en,
                cc.codigo AS contrato_codigo, cc.fecha_compra, cc.id AS id_contrato,
                cs.porcentaje, t.nombre AS tipo_animal, e.nombre AS empresa
         FROM contrato_socios cs
         JOIN contratos_compra cc ON cc.id=cs.id_contrato
         JOIN animales a ON a.id_contrato=cc.id
         JOIN tipos_animal t ON t.id=cc.id_tipo_animal
         JOIN empresas e ON e.id=cc.id_empresa_compra
         WHERE cs.id_socio=? AND a.estado='activo'
         ORDER BY cc.fecha_compra DESC, a.id"
    );
    $stmt->execute([$id]);
    Response::success($stmt->fetchAll());
}

// ─────────────────────────────────────────────────────────────
// Ganancias contratos cerrados
// ─────────────────────────────────────────────────────────────
function getGananciaSocio(int $id, string $desde, string $hasta): void {
    if ($id <= 0) Response::error('ID de socio requerido.');
    verificarAccesoSocio($id);
    $pdo = getDB();

    $filtros = ['cs.id_socio=?', "cc.estado='cerrado'"];
    $params  = [$id];
    if ($desde) { $filtros[] = 'ci.fecha_cierre >= ?'; $params[] = $desde; }
    if ($hasta) { $filtros[] = 'ci.fecha_cierre <= ?'; $params[] = $hasta; }
    $w = implode(' AND ', $filtros);

    $stmt = $pdo->prepare(
        "SELECT
           cc.id, cc.codigo, cc.fecha_compra,
           cc.valor_unitario_kg, cc.peso_total_kg,
           ci.fecha_cierre,
           ci.total_animales, ci.animales_vendidos, ci.animales_muertos,
           ci.costo_total_compra, ci.costo_total_flete_entrada,
           ci.costo_total_manutencion, ci.costo_total_flete_salida,
           ci.costo_total, ci.ingreso_total_ventas, ci.ganancia_total,
           csd.porcentaje, csd.ganancia AS ganancia_socio,
           ROUND(ci.costo_total_compra         *(csd.porcentaje/100),2) AS costo_compra_socio,
           ROUND(ci.costo_total_flete_entrada  *(csd.porcentaje/100),2) AS costo_flete_ent_socio,
           ROUND(ci.costo_total_manutencion    *(csd.porcentaje/100),2) AS costo_manten_socio,
           ROUND(ci.costo_total_flete_salida   *(csd.porcentaje/100),2) AS costo_flete_sal_socio,
           ROUND((ci.costo_total
                  - ci.costo_total_compra
                  - ci.costo_total_flete_entrada
                  - ci.costo_total_manutencion
                  - ci.costo_total_flete_salida) *(csd.porcentaje/100),2) AS costo_otros_socio,
           ROUND(ci.costo_total                *(csd.porcentaje/100),2) AS costo_total_socio,
           ROUND(ci.ingreso_total_ventas        *(csd.porcentaje/100),2) AS ingresos_socio,
           (SELECT COALESCE(SUM(la.peso_salida_kg),0)
            FROM liquidacion_animales la
            JOIN liquidaciones lq ON lq.id=la.id_liquidacion
            WHERE lq.id_contrato=cc.id AND la.tipo_salida='venta')       AS peso_vendido_kg,
           t.nombre AS tipo_animal, e.nombre AS empresa
         FROM contrato_socios cs
         JOIN contratos_compra cc ON cc.id=cs.id_contrato
         JOIN cierre_contrato ci ON ci.id_contrato=cc.id
         JOIN cierre_socios_detalle csd ON csd.id_cierre=ci.id AND csd.id_socio=cs.id_socio
         JOIN tipos_animal t ON t.id=cc.id_tipo_animal
         JOIN empresas e ON e.id=cc.id_empresa_compra
         WHERE {$w}
         ORDER BY ci.fecha_cierre DESC"
    );
    $stmt->execute($params);
    Response::success($stmt->fetchAll());
}
