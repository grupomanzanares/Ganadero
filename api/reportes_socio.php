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

match ($action) {
    'lista_socios' => getListaSocios(),
    'resumen'      => getResumenSocio($idSocio),
    'contratos'    => getContratosSocio($idSocio),
    'animales'     => getAnimalesSocio($idSocio),
    'ganancias'    => getGananciaSocio($idSocio),
    default        => Response::error("Acción '{$action}' no válida.", 404),
};

// ── Lista de socios disponibles ───────────────────────────
function getListaSocios(): void {
    $user = Auth::user();
    $pdo  = getDB();

    // Si es socio, solo ve su propio perfil
    if ($user['rol'] === 'socio') {
        $stmt = $pdo->prepare(
            'SELECT s.id, s.nombre, e.nombre AS empresa
             FROM socios s
             JOIN empresas e ON e.id = s.id_empresa
             WHERE s.id_usuario = ?'
        );
        $stmt->execute([$user['id']]);
    } else {
        $stmt = $pdo->query(
            'SELECT s.id, s.nombre, e.nombre AS empresa
             FROM socios s
             JOIN empresas e ON e.id = s.id_empresa
             WHERE s.activo = 1
             ORDER BY s.nombre'
        );
    }
    Response::success($stmt->fetchAll());
}

// ── Resumen general del socio ─────────────────────────────
function getResumenSocio(int $idSocio): void {
    if ($idSocio <= 0) Response::error('ID de socio requerido.');
    verificarAccesoSocio($idSocio);

    $pdo  = getDB();

    // Datos del socio
    $stmt = $pdo->prepare(
        'SELECT s.id, s.nombre, s.cedula, s.telefono,
                e.nombre AS empresa
         FROM socios s
         JOIN empresas e ON e.id = s.id_empresa
         WHERE s.id = ?'
    );
    $stmt->execute([$idSocio]);
    $socio = $stmt->fetch();
    if (!$socio) Response::notFound('Socio no encontrado.');

    // Total contratos donde participa
    $stmt2 = $pdo->prepare(
        'SELECT
            COUNT(DISTINCT cs.id_contrato) AS total_contratos,
            SUM(CASE WHEN cc.estado="abierto"  THEN 1 ELSE 0 END) AS contratos_abiertos,
            SUM(CASE WHEN cc.estado="cerrado"  THEN 1 ELSE 0 END) AS contratos_cerrados
         FROM contrato_socios cs
         JOIN contratos_compra cc ON cc.id = cs.id_contrato
         WHERE cs.id_socio = ?'
    );
    $stmt2->execute([$idSocio]);
    $contratos = $stmt2->fetch();

    // Animales que le corresponden (según % participación)
    // En cada contrato: animales_totales × (porcentaje/100) = animales del socio
    $stmt3 = $pdo->prepare(
        'SELECT
            ROUND(SUM(
              (SELECT COUNT(*) FROM animales a WHERE a.id_contrato = cs.id_contrato)
              * (cs.porcentaje / 100)
            ), 0) AS total_animales,
            ROUND(SUM(
              (SELECT COUNT(*) FROM animales a WHERE a.id_contrato = cs.id_contrato AND a.estado="activo")
              * (cs.porcentaje / 100)
            ), 0) AS animales_activos,
            ROUND(SUM(
              (SELECT COUNT(*) FROM animales a WHERE a.id_contrato = cs.id_contrato AND a.estado="vendido")
              * (cs.porcentaje / 100)
            ), 0) AS animales_vendidos,
            ROUND(SUM(
              (SELECT COUNT(*) FROM animales a WHERE a.id_contrato = cs.id_contrato AND a.estado="muerto")
              * (cs.porcentaje / 100)
            ), 0) AS animales_muertos
         FROM contrato_socios cs
         JOIN contratos_compra cc ON cc.id = cs.id_contrato
         WHERE cs.id_socio = ?'
    );
    $stmt3->execute([$idSocio]);
    $animales = $stmt3->fetch();

    // Ganancias y costos de los cierres (contratos cerrados)
    $stmt4 = $pdo->prepare(
        'SELECT
            COALESCE(SUM(csd.ganancia), 0)                AS ganancia_total,
            COALESCE(SUM(cc_cierre.costo_total), 0)       AS costo_total,
            COALESCE(SUM(cc_cierre.ingreso_total_ventas * (cs.porcentaje/100)), 0) AS ingresos_ventas
         FROM contrato_socios cs
         JOIN contratos_compra cc ON cc.id = cs.id_contrato
         JOIN cierre_contrato cc_cierre ON cc_cierre.id_contrato = cs.id_contrato
         JOIN cierre_socios_detalle csd
              ON csd.id_cierre = cc_cierre.id AND csd.id_socio = cs.id_socio
         WHERE cs.id_socio = ?'
    );
    $stmt4->execute([$idSocio]);
    $financiero = $stmt4->fetch();

    // Inversión activa (contratos abiertos) — valor de compra proporcional
    $stmt5 = $pdo->prepare(
        'SELECT COALESCE(SUM(cc.valor_total * (cs.porcentaje/100)), 0) AS inversion_activa
         FROM contrato_socios cs
         JOIN contratos_compra cc ON cc.id = cs.id_contrato
         WHERE cs.id_socio = ? AND cc.estado = "abierto"'
    );
    $stmt5->execute([$idSocio]);
    $inversion = $stmt5->fetchColumn();

    Response::success([
        'socio'            => $socio,
        'contratos'        => $contratos,
        'animales'         => $animales,
        'ganancia_total'   => (float)$financiero['ganancia_total'],
        'costo_total'      => (float)$financiero['costo_total'],
        'ingresos_ventas'  => (float)$financiero['ingresos_ventas'],
        'inversion_activa' => (float)$inversion,
    ]);
}

// ── Contratos del socio con detalle ──────────────────────
function getContratosSocio(int $idSocio): void {
    if ($idSocio <= 0) Response::error('ID de socio requerido.');
    verificarAccesoSocio($idSocio);

    $pdo  = getDB();
    $stmt = $pdo->prepare(
        'SELECT
            cc.id, cc.codigo, cc.fecha_compra, cc.estado,
            cc.cantidad_animales AS total_animales,
            cc.valor_total       AS valor_compra_lote,
            cc.costo_flete       AS flete_entrada_lote,
            cs.porcentaje,
            t.nombre  AS tipo_animal,
            e.nombre  AS empresa_compra,
            -- Animales que le corresponden al socio
            ROUND(cc.cantidad_animales * (cs.porcentaje/100), 0) AS animales_socio,
            ROUND(
              (SELECT COUNT(*) FROM animales a WHERE a.id_contrato=cc.id AND a.estado="activo")
              * (cs.porcentaje/100), 0
            ) AS activos_socio,
            ROUND(
              (SELECT COUNT(*) FROM animales a WHERE a.id_contrato=cc.id AND a.estado="vendido")
              * (cs.porcentaje/100), 0
            ) AS vendidos_socio,
            ROUND(
              (SELECT COUNT(*) FROM animales a WHERE a.id_contrato=cc.id AND a.estado="muerto")
              * (cs.porcentaje/100), 0
            ) AS muertos_socio,
            -- Valor proporcional de compra del socio
            ROUND(cc.valor_total * (cs.porcentaje/100), 2) AS inversion_socio,
            -- Ganancia del socio en contratos cerrados
            (SELECT COALESCE(csd.ganancia, NULL)
             FROM cierre_contrato ci
             JOIN cierre_socios_detalle csd
                  ON csd.id_cierre = ci.id AND csd.id_socio = cs.id_socio
             WHERE ci.id_contrato = cc.id
             LIMIT 1
            ) AS ganancia_socio,
            -- Costos acumulados proporcionales (contratos abiertos parcialmente liquidados)
            (SELECT COALESCE(SUM(la.costo_total) * (cs.porcentaje/100), 0)
             FROM liquidacion_animales la
             JOIN liquidaciones l ON l.id = la.id_liquidacion
             WHERE l.id_contrato = cc.id
            ) AS costos_acumulados_socio,
            (SELECT COALESCE(SUM(la.valor_venta) * (cs.porcentaje/100), 0)
             FROM liquidacion_animales la
             JOIN liquidaciones l ON l.id = la.id_liquidacion
             WHERE l.id_contrato = cc.id AND la.tipo_salida = "venta"
            ) AS ventas_acumuladas_socio
         FROM contrato_socios cs
         JOIN contratos_compra cc ON cc.id = cs.id_contrato
         JOIN tipos_animal t      ON t.id  = cc.id_tipo_animal
         JOIN empresas e          ON e.id  = cc.id_empresa_compra
         WHERE cs.id_socio = ?
         ORDER BY cc.fecha_compra DESC'
    );
    $stmt->execute([$idSocio]);
    Response::success($stmt->fetchAll());
}

// ── Animales activos del socio ────────────────────────────
function getAnimalesSocio(int $idSocio): void {
    if ($idSocio <= 0) Response::error('ID de socio requerido.');
    verificarAccesoSocio($idSocio);

    $pdo  = getDB();
    $stmt = $pdo->prepare(
        'SELECT
            a.id, a.codigo, a.peso_inicial_kg, a.peso_finca_kg,
            a.costo_compra_animal, a.costo_flete_animal, a.valor_promedio_kg,
            a.estado, a.creado_en,
            cc.codigo AS contrato_codigo, cc.fecha_compra,
            cc.id     AS id_contrato,
            cs.porcentaje,
            t.nombre  AS tipo_animal,
            e.nombre  AS empresa
         FROM contrato_socios cs
         JOIN contratos_compra cc ON cc.id = cs.id_contrato
         JOIN animales a          ON a.id_contrato = cc.id
         JOIN tipos_animal t      ON t.id  = cc.id_tipo_animal
         JOIN empresas e          ON e.id  = cc.id_empresa_compra
         WHERE cs.id_socio = ? AND a.estado = "activo"
         ORDER BY cc.fecha_compra DESC, a.id'
    );
    $stmt->execute([$idSocio]);
    Response::success($stmt->fetchAll());
}

// ── Detalle de ganancias por contrato cerrado ─────────────
function getGananciaSocio(int $idSocio): void {
    if ($idSocio <= 0) Response::error('ID de socio requerido.');
    verificarAccesoSocio($idSocio);

    $pdo  = getDB();
    $stmt = $pdo->prepare(
        'SELECT
            cc.id, cc.codigo, cc.fecha_compra,
            ci.fecha_cierre,
            ci.total_animales, ci.animales_vendidos, ci.animales_muertos,
            ci.costo_total_compra,
            ci.costo_total_flete_entrada,
            ci.costo_total_manutencion,
            ci.costo_total_flete_salida,
            COALESCE(ci.costo_total_otros, 0) AS costo_total_otros,
            ci.costo_total,
            ci.ingreso_total_ventas,
            ci.ganancia_total,
            csd.porcentaje,
            csd.ganancia AS ganancia_socio,
            -- Costos proporcionales del socio
            ROUND(ci.costo_total_compra         * (csd.porcentaje/100), 2) AS costo_compra_socio,
            ROUND(ci.costo_total_flete_entrada  * (csd.porcentaje/100), 2) AS costo_flete_ent_socio,
            ROUND(ci.costo_total_manutencion    * (csd.porcentaje/100), 2) AS costo_manten_socio,
            ROUND(ci.costo_total_flete_salida   * (csd.porcentaje/100), 2) AS costo_flete_sal_socio,
            ROUND(COALESCE(ci.costo_total_otros,0) * (csd.porcentaje/100), 2) AS costo_otros_socio,
            ROUND(ci.costo_total                * (csd.porcentaje/100), 2) AS costo_total_socio,
            ROUND(ci.ingreso_total_ventas       * (csd.porcentaje/100), 2) AS ingresos_socio,
            t.nombre AS tipo_animal,
            e.nombre AS empresa
         FROM contrato_socios cs
         JOIN contratos_compra cc   ON cc.id  = cs.id_contrato
         JOIN cierre_contrato ci    ON ci.id_contrato = cc.id
         JOIN cierre_socios_detalle csd
              ON csd.id_cierre = ci.id AND csd.id_socio = cs.id_socio
         JOIN tipos_animal t ON t.id = cc.id_tipo_animal
         JOIN empresas e     ON e.id = cc.id_empresa_compra
         WHERE cs.id_socio = ?
         ORDER BY ci.fecha_cierre DESC'
    );
    $stmt->execute([$idSocio]);
    Response::success($stmt->fetchAll());
}

// ── Verificar que el socio logueado solo vea sus datos ────
function verificarAccesoSocio(int $idSocio): void {
    $user = Auth::user();
    if ($user['rol'] !== 'socio') return; // admin/operador ven todo

    $pdo  = getDB();
    $stmt = $pdo->prepare('SELECT id FROM socios WHERE id = ? AND id_usuario = ?');
    $stmt->execute([$idSocio, $user['id']]);
    if (!$stmt->fetch()) Response::forbidden();
}
