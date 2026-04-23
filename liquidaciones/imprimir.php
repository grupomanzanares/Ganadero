<?php
// ============================================================
// liquidaciones/imprimir.php — Documento de liquidación
// ============================================================
require_once __DIR__ . '/../bootstrap.php';
Auth::requirePermission('liquidaciones', 'ver');

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { http_response_code(400); die('ID inválido.'); }

$pdo = getDB();

// ── Cabecera de la liquidación ────────────────────────────
$stmt = $pdo->prepare(
    'SELECT l.*,
            c.codigo AS contrato_codigo, c.id AS id_contrato,
            c.fecha_compra, c.cantidad_animales AS total_comprado,
            e.nombre  AS empresa_factura,  e.nit AS nit_empresa,
            cl.nombre AS cliente,          cl.nit AS nit_cliente,
            ec.nombre AS empresa_compra
     FROM liquidaciones l
     JOIN contratos_compra c ON c.id  = l.id_contrato
     LEFT JOIN empresas  e  ON e.id   = l.id_empresa_factura
     LEFT JOIN clientes  cl ON cl.id  = l.id_cliente
     LEFT JOIN empresas  ec ON ec.id  = c.id_empresa_compra
     WHERE l.id = ?'
);
$stmt->execute([$id]);
$liq = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$liq) { http_response_code(404); die('Liquidación no encontrada.'); }

// ── Animales ──────────────────────────────────────────────
$stmtA = $pdo->prepare(
    'SELECT la.*, a.codigo AS animal_codigo
     FROM liquidacion_animales la
     JOIN animales a ON a.id = la.id_animal
     WHERE la.id_liquidacion = ?
     ORDER BY la.tipo_salida DESC, la.id'
);
$stmtA->execute([$id]);
$animales = $stmtA->fetchAll(PDO::FETCH_ASSOC);

// ── Totales ───────────────────────────────────────────────
$stmtT = $pdo->prepare(
    'SELECT
        COUNT(*)                                               AS total_animales,
        SUM(CASE WHEN tipo_salida="venta"  THEN 1 ELSE 0 END) AS vendidos,
        SUM(CASE WHEN tipo_salida="muerte" THEN 1 ELSE 0 END) AS muertos,
        ROUND(SUM(valor_venta),2)      AS total_ingresos,
        ROUND(SUM(costo_compra),2)     AS tot_compra,
        ROUND(SUM(costo_flete_entrada),2) AS tot_flete_ent,
        ROUND(SUM(costo_manutencion),2)   AS tot_manutencion,
        ROUND(SUM(costo_flete_salida),2)  AS tot_flete_sal,
        ROUND(SUM(otros_gastos),2)        AS tot_otros,
        ROUND(SUM(costo_total),2)         AS total_costos,
        ROUND(SUM(ganancia),2)            AS total_ganancia,
        ROUND(AVG(dias_manutencion),0)    AS promedio_dias
     FROM liquidacion_animales WHERE id_liquidacion = ?'
);
$stmtT->execute([$id]);
$tot = $stmtT->fetch(PDO::FETCH_ASSOC);

// ── Socios por contrato ───────────────────────────────────
// Cada animal pertenece a un contrato con sus propios socios y porcentajes.
// Se agrupa por contrato, se suma la ganancia de sus animales en esta
// liquidación y se reparte según el porcentaje de cada socio en ese contrato.
$stmtS = $pdo->prepare(
    'SELECT
         c.id       AS id_contrato,
         c.codigo   AS contrato_codigo,
         s.nombre   AS socio,
         em.nombre  AS empresa,
         cs.porcentaje,
         COUNT(la.id)                                          AS animales_contrato,
         ROUND(SUM(la.ganancia), 2)                           AS ganancia_contrato,
         ROUND(SUM(la.ganancia) * cs.porcentaje / 100, 2)     AS ganancia_estimada
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
$stmtS->execute([$id]);
$sociosRaw = $stmtS->fetchAll(PDO::FETCH_ASSOC);

// Agrupar por contrato para mostrar en secciones
$sociosPorContrato = [];
foreach ($sociosRaw as $row) {
    $key = $row['id_contrato'];
    if (!isset($sociosPorContrato[$key])) {
        $sociosPorContrato[$key] = [
            'contrato_codigo'  => $row['contrato_codigo'],
            'ganancia_contrato'=> $row['ganancia_contrato'],
            'animales_contrato'=> $row['animales_contrato'],
            'socios'           => [],
        ];
    }
    $sociosPorContrato[$key]['socios'][] = $row;
}

// ── Helpers ───────────────────────────────────────────────
function m(mixed $n): string {
    return '$ ' . number_format((float)$n, 0, ',', '.');
}
function kg(mixed $n): string {
    return number_format((float)$n, 2, ',', '.') . ' kg';
}
function fdate(?string $d): string {
    if (!$d) return '—';
    [$y, $mo, $da] = explode('-', $d);
    return "{$da}/{$mo}/{$y}";
}

$esGanancia   = (float)$tot['total_ganancia'] >= 0;
$numFactura   = $liq['numero_factura'] ?: 'S/N';
$fechaImpresion = date('d/m/Y H:i');
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Liquidación <?= htmlspecialchars($numFactura) ?> — GanaderoPro</title>
  <style>
    /* ─── Reset & base ──────────────────────────────────── */
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    body {
      font-family: 'Segoe UI', Arial, sans-serif;
      font-size: 11px;
      color: #1a1a2e;
      background: #f0f0f0;
      padding: 20px;
    }

    /* ─── Hoja ──────────────────────────────────────────── */
    .page {
      background: #fff;
      width: 210mm;
      min-height: 297mm;
      margin: 0 auto;
      padding: 14mm 14mm 12mm;
      box-shadow: 0 4px 24px rgba(0,0,0,.12);
      position: relative;
    }

    /* ─── Cabecera del documento ────────────────────────── */
    .doc-header {
      display: flex;
      justify-content: space-between;
      align-items: flex-start;
      border-bottom: 2.5px solid #1e293b;
      padding-bottom: 10px;
      margin-bottom: 12px;
    }
    .brand-name {
      font-size: 22px;
      font-weight: 800;
      color: #1e293b;
      letter-spacing: -.5px;
      line-height: 1;
    }
    .brand-name span { color: #059669; }
    .brand-sub {
      font-size: 9px;
      color: #64748b;
      letter-spacing: .12em;
      text-transform: uppercase;
      margin-top: 3px;
    }
    .doc-type {
      text-align: right;
    }
    .doc-type h1 {
      font-size: 16px;
      font-weight: 800;
      color: #1e293b;
      text-transform: uppercase;
      letter-spacing: .04em;
    }
    .doc-type .factura-num {
      font-size: 18px;
      font-weight: 900;
      color: #059669;
      letter-spacing: .02em;
    }
    .doc-type .doc-estado {
      display: inline-block;
      padding: 2px 8px;
      border-radius: 4px;
      font-size: 9px;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: .06em;
      margin-top: 4px;
    }
    .estado-confirmada { background: #d1fae5; color: #065f46; }
    .estado-borrador   { background: #fef9c3; color: #854d0e; }
    .estado-anulada    { background: #fee2e2; color: #991b1b; }

    /* ─── Bloque info partes ────────────────────────────── */
    .partes {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 0;
      border: 1.5px solid #e2e8f0;
      border-radius: 6px;
      overflow: hidden;
      margin-bottom: 10px;
    }
    .parte {
      padding: 9px 12px;
      border-right: 1px solid #e2e8f0;
    }
    .parte:last-child { border-right: none; }
    .parte-label {
      font-size: 8px;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: .1em;
      color: #94a3b8;
      margin-bottom: 4px;
    }
    .parte-nombre {
      font-size: 12px;
      font-weight: 700;
      color: #1e293b;
    }
    .parte-nit {
      font-size: 9px;
      color: #64748b;
      margin-top: 1px;
    }

    /* ─── Datos del contrato ────────────────────────────── */
    .datos-contrato {
      display: grid;
      grid-template-columns: repeat(4, 1fr);
      gap: 0;
      border: 1.5px solid #e2e8f0;
      border-top: none;
      margin-bottom: 14px;
      border-radius: 0 0 6px 6px;
      overflow: hidden;
    }
    .dato {
      padding: 7px 12px;
      border-right: 1px solid #e2e8f0;
    }
    .dato:last-child { border-right: none; }
    .dato-label {
      font-size: 8px;
      text-transform: uppercase;
      letter-spacing: .08em;
      color: #94a3b8;
      font-weight: 600;
    }
    .dato-val {
      font-size: 11px;
      font-weight: 600;
      color: #334155;
      margin-top: 2px;
    }
    .dato-val.mono { font-family: 'Courier New', monospace; font-weight: 700; color: #1e293b; }

    /* ─── Sección titulada ──────────────────────────────── */
    .seccion-titulo {
      font-size: 9px;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: .1em;
      color: #fff;
      background: #1e293b;
      padding: 5px 10px;
      border-radius: 4px 4px 0 0;
      margin-bottom: 0;
    }

    /* ─── Tabla de animales ─────────────────────────────── */
    .tabla-animales {
      width: 100%;
      border-collapse: collapse;
      border: 1px solid #e2e8f0;
      font-size: 9.5px;
      margin-bottom: 14px;
    }
    .tabla-animales thead tr {
      background: #f1f5f9;
    }
    .tabla-animales thead th {
      padding: 5px 6px;
      text-align: right;
      font-size: 8px;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: .05em;
      color: #475569;
      border-bottom: 1.5px solid #cbd5e1;
      white-space: nowrap;
    }
    .tabla-animales thead th:first-child { text-align: left; }
    .tabla-animales tbody tr {
      border-bottom: 1px solid #f1f5f9;
    }
    .tabla-animales tbody tr:last-child { border-bottom: none; }
    .tabla-animales tbody tr.muerte { background: #fff8f8; }
    .tabla-animales tbody td {
      padding: 4px 4px;
      text-align: right;
      color: #334155;
      white-space: nowrap;
    }
    .tabla-animales tbody td:first-child { text-align: left; }
    .tabla-animales tfoot tr {
      background: #1e293b;
      color: #e2e8f0;
    }
    .tabla-animales tfoot td {
      padding: 5px 4px;
      text-align: right;
      font-weight: 700;
      font-size: 8.5px;
      white-space: nowrap;
    }
    .tabla-animales tfoot td:first-child { text-align: left; }
    .badge-muerte {
      display: inline-block;
      background: #fee2e2; color: #991b1b;
      padding: 1px 5px; border-radius: 3px;
      font-size: 7.5px; font-weight: 700; text-transform: uppercase;
    }
    .badge-venta {
      display: inline-block;
      background: #d1fae5; color: #065f46;
      padding: 1px 5px; border-radius: 3px;
      font-size: 7.5px; font-weight: 700; text-transform: uppercase;
    }
    .ganancia-pos { color: #059669; font-weight: 700; }
    .ganancia-neg { color: #dc2626; font-weight: 700; }

    /* ─── Bloque financiero ─────────────────────────────── */
    .financiero {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 12px;
      margin-bottom: 14px;
    }
    .costos-tabla {
      border: 1.5px solid #e2e8f0;
      border-radius: 0 0 6px 6px;
      overflow: hidden;
    }
    .costos-tabla table {
      width: 100%;
      border-collapse: collapse;
      font-size: 10px;
    }
    .costos-tabla tr {
      border-bottom: 1px solid #f1f5f9;
    }
    .costos-tabla tr:last-child { border-bottom: none; }
    .costos-tabla td {
      padding: 5px 10px;
    }
    .costos-tabla td:last-child { text-align: right; font-weight: 600; }
    .costos-tabla .total-row {
      background: #1e293b;
      color: #fff;
      font-weight: 800;
      font-size: 11px;
    }
    .costos-tabla .total-row td:last-child { color: #fca5a5; }

    .resumen-resultado {
      border: 1.5px solid #e2e8f0;
      border-radius: 0 0 6px 6px;
      overflow: hidden;
    }
    .ingresos-row {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 8px 12px;
      border-bottom: 1px solid #f1f5f9;
    }
    .ingresos-row .lbl { font-size: 9px; color: #64748b; text-transform: uppercase; letter-spacing: .07em; }
    .ingresos-row .val { font-size: 13px; font-weight: 800; }
    .val-ingresos { color: #059669; }
    .val-costos   { color: #dc2626; }
    .resultado-final {
      padding: 10px 12px;
      text-align: center;
    }
    .resultado-final .rf-label {
      font-size: 9px; text-transform: uppercase;
      letter-spacing: .1em; font-weight: 700;
      margin-bottom: 3px;
    }
    .resultado-final .rf-valor {
      font-size: 24px; font-weight: 900; letter-spacing: -.5px;
    }
    .ganancia-bg  { background: #ecfdf5; border-top: 2px solid #059669; }
    .perdida-bg   { background: #fff1f2; border-top: 2px solid #dc2626; }
    .rf-label-g   { color: #059669; }
    .rf-label-p   { color: #dc2626; }
    .rf-valor-g   { color: #059669; }
    .rf-valor-p   { color: #dc2626; }

    /* ─── Socios (tabla compacta) ───────────────────────── */
    .socios-tabla {
      width: 100%;
      border-collapse: collapse;
      border: 1.5px solid #e2e8f0;
      border-top: none;
      border-radius: 0 0 6px 6px;
      font-size: 9.5px;
      margin-bottom: 14px;
      overflow: hidden;
    }
    .socios-tabla thead tr { background: #f1f5f9; }
    .socios-tabla thead th {
      padding: 4px 8px;
      text-align: left;
      font-size: 8px; font-weight: 700;
      text-transform: uppercase; letter-spacing: .06em;
      color: #64748b; border-bottom: 1px solid #e2e8f0;
      white-space: nowrap;
    }
    .socios-tabla thead th:last-child { text-align: right; }
    .socios-tabla td {
      padding: 4px 8px;
      border-bottom: 1px solid #f1f5f9;
      vertical-align: middle;
      color: #334155;
    }
    .socios-tabla tr:last-child td { border-bottom: none; }
    .socios-tabla .td-contrato {
      background: #f8fafc;
      border-right: 1px solid #e2e8f0;
      border-bottom: 2px solid #e2e8f0 !important;
      vertical-align: middle;
      padding: 5px 8px;
      white-space: nowrap;
    }
    .socios-tabla .cod-contrato {
      font-family: monospace; font-size: 9px; font-weight: 800;
      color: #1e293b; background: #e2e8f0;
      padding: 1px 5px; border-radius: 3px;
      display: inline-block; margin-bottom: 2px;
    }
    .socios-tabla .meta-contrato {
      font-size: 8px; color: #94a3b8; line-height: 1.4;
    }
    .socios-tabla .meta-contrato span {
      font-weight: 700; color: #475569;
    }
    .socios-tabla .td-gan-cont {
      text-align: right; font-weight: 800;
      border-right: 1px solid #e2e8f0;
      white-space: nowrap;
    }
    .dot {
      display: inline-block; width: 9px; height: 9px;
      border-radius: 50%; margin-right: 5px;
      vertical-align: middle; flex-shrink: 0;
    }
    .dot-g { background: #059669; }
    .dot-p { background: #dc2626; }
    .td-nombre { min-width: 110px; }
    .td-empresa { font-size: 8px; color: #94a3b8; }
    .td-pct { text-align: center; color: #475569; font-weight: 600;
              white-space: nowrap; width: 40px; }
    .td-gan-socio { text-align: right; font-weight: 800;
                    white-space: nowrap; width: 80px; }
    .gan-pos { color: #059669; }
    .gan-neg { color: #dc2626; }
    .fila-total {
      background: #1e293b !important;
    }
    .fila-total td {
      border-bottom: none !important;
      padding: 5px 8px;
      color: #e2e8f0 !important;
      font-weight: 700;
      font-size: 9px;
    }

    /* ─── Observación ───────────────────────────────────── */
    .obs-box {
      border: 1px solid #fde68a;
      background: #fffbeb;
      border-radius: 6px;
      padding: 8px 12px;
      font-size: 10px;
      color: #78350f;
      margin-bottom: 14px;
    }
    .obs-box strong { display: block; font-size: 8px; text-transform: uppercase; letter-spacing: .08em; margin-bottom: 3px; color: #92400e; }

    /* ─── Pie de página ─────────────────────────────────── */
    .doc-footer {
      border-top: 1px solid #e2e8f0;
      padding-top: 8px;
      margin-top: auto;
      display: flex;
      justify-content: space-between;
      align-items: flex-end;
      font-size: 8.5px;
      color: #94a3b8;
    }

    /* ─── Botón imprimir (solo pantalla) ────────────────── */
    .print-bar {
      width: 210mm;
      margin: 0 auto 12px;
      display: flex;
      gap: 8px;
      align-items: center;
    }
    .btn-print {
      display: inline-flex; align-items: center; gap: 6px;
      padding: 8px 18px; border-radius: 6px;
      background: #059669; color: #fff;
      font-size: 13px; font-weight: 600;
      cursor: pointer; border: none;
    }
    .btn-print:hover { background: #047857; }
    .btn-back {
      display: inline-flex; align-items: center; gap: 6px;
      padding: 8px 14px; border-radius: 6px;
      background: #fff; color: #334155;
      font-size: 13px; font-weight: 500;
      cursor: pointer; border: 1px solid #e2e8f0;
      text-decoration: none;
    }
    .btn-back:hover { background: #f8fafc; }

    /* ─── Salto de página ───────────────────────────────── */
    .no-break { page-break-inside: avoid; }

    /* ─── Print ─────────────────────────────────────────── */
    @media print {
      body { background: white; padding: 0; margin: 0; font-size: 10px; }
      .page { box-shadow: none; margin: 0; padding: 10mm 12mm; width: 100%; min-height: auto; }
      .print-bar { display: none; }
      .tabla-animales { font-size: 8.5px; }
      thead { display: table-header-group; }
      tfoot { display: table-footer-group; }
      .no-break { page-break-inside: avoid; }
      .seccion-titulo { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
      .tabla-animales thead tr,
      .tabla-animales tfoot tr,
      .costos-tabla .total-row,
      .resultado-final,
      .fila-total,
      .dot { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
      @page { margin: 8mm 10mm; size: A4; }
    }
  </style>
</head>
<body>

<!-- Barra de acción (solo pantalla) -->
<div class="print-bar">
  <button class="btn-print" onclick="window.print()">
    <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
        d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
    </svg>
    Imprimir
  </button>
  <a href="javascript:history.back()" class="btn-back">
    <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24">
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
    </svg>
    Volver
  </a>
  <span style="font-size:12px;color:#64748b;margin-left:4px">
    Liquidación <?= htmlspecialchars($numFactura) ?> — <?= htmlspecialchars($liq['empresa_factura'] ?? 'Sin empresa') ?>
  </span>
</div>

<!-- ══ HOJA ═══════════════════════════════════════════════ -->
<div class="page">

  <!-- ENCABEZADO DEL DOCUMENTO -->
  <div class="doc-header">
    <div>
      <div class="brand-name">Ganader<span>o</span>Pro</div>
      <div class="brand-sub">Sistema de gestión ganadera</div>
    </div>
    <div class="doc-type">
      <h1>Liquidación de venta</h1>
      <div class="factura-num">
        <?= $liq['numero_factura'] ? 'Factura N.° ' . htmlspecialchars($liq['numero_factura']) : 'Sin número de factura' ?>
      </div>
      <div>
        <span class="doc-estado estado-<?= $liq['estado'] ?>">
          <?= ucfirst($liq['estado']) ?>
        </span>
      </div>
    </div>
  </div>

  <!-- PARTES: empresa factura / cliente -->
  <div class="partes no-break">
    <div class="parte">
      <div class="parte-label">Empresa que factura</div>
      <div class="parte-nombre"><?= htmlspecialchars($liq['empresa_factura'] ?? '—') ?></div>
      <?php if (!empty($liq['nit_empresa'])): ?>
      <div class="parte-nit">NIT: <?= htmlspecialchars($liq['nit_empresa']) ?></div>
      <?php endif; ?>
    </div>
    <div class="parte">
      <div class="parte-label">Cliente / Comprador</div>
      <div class="parte-nombre"><?= htmlspecialchars($liq['cliente'] ?? '—') ?></div>
      <?php if (!empty($liq['nit_cliente'])): ?>
      <div class="parte-nit">NIT: <?= htmlspecialchars($liq['nit_cliente']) ?></div>
      <?php endif; ?>
    </div>
  </div>

  <!-- DATOS DEL CONTRATO -->
  <div class="datos-contrato no-break">
    <div class="dato">
      <div class="dato-label">Contrato</div>
      <div class="dato-val mono"><?= htmlspecialchars($liq['contrato_codigo']) ?></div>
    </div>
    <div class="dato">
      <div class="dato-label">Empresa compra</div>
      <div class="dato-val"><?= htmlspecialchars($liq['empresa_compra'] ?? '—') ?></div>
    </div>
    <div class="dato">
      <div class="dato-label">Fecha de venta</div>
      <div class="dato-val"><?= fdate($liq['fecha_venta']) ?></div>
    </div>
    <div class="dato">
      <div class="dato-label">Valor venta / kg</div>
      <div class="dato-val"><?= m($liq['valor_venta_unitario_kg']) ?>/kg</div>
    </div>
  </div>

  <!-- DETALLE DE ANIMALES -->
  <div class="seccion-titulo">Detalle de animales — <?= count($animales) ?> cabezas
    (<?= $tot['vendidos'] ?> venta<?= $tot['vendidos'] != 1 ? 's' : '' ?><?= $tot['muertos'] > 0 ? ', ' . $tot['muertos'] . ' muerte' . ($tot['muertos'] != 1 ? 's' : '') : '' ?>)
  </div>
  <table class="tabla-animales">
    <thead>
      <tr>
        <th style="text-align:left;width:52px">Código</th>
        <th style="width:46px">Tipo</th>
        <th>Días<br>mant.</th>
        <th>Costo<br>compra</th>
        <th>Flete<br>entrada</th>
        <th>Manu-<br>tención</th>
        <th>Flete<br>salida</th>
        <th>Otros<br>gastos</th>
        <th>Costo<br>total</th>
        <th>Peso<br>salida</th>
        <th>Valor<br>venta</th>
        <th>Ganancia</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($animales as $a):
        $gan    = (float)$a['ganancia'];
        $esMuerte = $a['tipo_salida'] === 'muerte';
      ?>
      <tr class="<?= $esMuerte ? 'muerte' : '' ?>">
        <td style="font-family:monospace;font-weight:600;font-size:9px">
          <?= htmlspecialchars($a['animal_codigo'] ?: ('#'.$a['id_animal'])) ?>
        </td>
        <td style="text-align:center">
          <span class="<?= $esMuerte ? 'badge-muerte' : 'badge-venta' ?>">
            <?= $esMuerte ? 'Muerte' : 'Venta' ?>
          </span>
        </td>
        <td><?= (int)$a['dias_manutencion'] ?></td>
        <td><?= m($a['costo_compra']) ?></td>
        <td><?= m($a['costo_flete_entrada']) ?></td>
        <td><?= m($a['costo_manutencion']) ?></td>
        <td><?= m($a['costo_flete_salida']) ?></td>
        <td><?= m($a['otros_gastos']) ?></td>
        <td style="color:#dc2626;font-weight:700"><?= m($a['costo_total']) ?></td>
        <td><?= $esMuerte ? '—' : kg($a['peso_salida_kg']) ?></td>
        <td style="color:#059669;font-weight:600"><?= $esMuerte ? '—' : m($a['valor_venta']) ?></td>
        <td class="<?= $gan >= 0 ? 'ganancia-pos' : 'ganancia-neg' ?>"><?= m($gan) ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
    <tfoot>
      <tr>
        <td colspan="3" style="text-align:left">TOTALES</td>
        <td><?= m($tot['tot_compra']) ?></td>
        <td><?= m($tot['tot_flete_ent']) ?></td>
        <td><?= m($tot['tot_manutencion']) ?></td>
        <td><?= m($tot['tot_flete_sal']) ?></td>
        <td><?= m($tot['tot_otros']) ?></td>
        <td style="color:#fca5a5"><?= m($tot['total_costos']) ?></td>
        <td><?= kg(array_sum(array_column($animales,'peso_salida_kg'))) ?></td>
        <td style="color:#6ee7b7"><?= m($tot['total_ingresos']) ?></td>
        <td style="color:<?= $esGanancia ? '#6ee7b7' : '#fca5a5' ?>"><?= m($tot['total_ganancia']) ?></td>
      </tr>
    </tfoot>
  </table>

  <!-- RESUMEN FINANCIERO -->
  <div class="financiero no-break">

    <!-- Desglose de costos -->
    <div>
      <div class="seccion-titulo">Desglose de costos</div>
      <div class="costos-tabla">
        <table>
          <tr><td>Costo compra animales</td><td><?= m($tot['tot_compra']) ?></td></tr>
          <tr><td>Flete de entrada</td>     <td><?= m($tot['tot_flete_ent']) ?></td></tr>
          <tr><td>Manutención (prom. <?= (int)$tot['promedio_dias'] ?> días)</td>
                                            <td><?= m($tot['tot_manutencion']) ?></td></tr>
          <tr><td>Flete de salida</td>      <td><?= m($tot['tot_flete_sal']) ?></td></tr>
          <tr><td>Otros gastos</td>         <td><?= m($tot['tot_otros']) ?></td></tr>
          <tr class="total-row">
            <td>COSTO TOTAL</td>
            <td><?= m($tot['total_costos']) ?></td>
          </tr>
        </table>
      </div>
    </div>

    <!-- Resultado final -->
    <div>
      <div class="seccion-titulo">Resultado de la venta</div>
      <div class="resumen-resultado">
        <div class="ingresos-row">
          <span class="lbl">Total ingresos venta</span>
          <span class="val val-ingresos"><?= m($tot['total_ingresos']) ?></span>
        </div>
        <div class="ingresos-row">
          <span class="lbl">Total costos</span>
          <span class="val val-costos"><?= m($tot['total_costos']) ?></span>
        </div>
        <div class="ingresos-row" style="border-bottom:none;padding-bottom:4px">
          <span class="lbl">Animales: <?= $tot['total_animales'] ?> cab.
            (<?= $tot['vendidos'] ?> vendidos<?= $tot['muertos'] > 0 ? ', '.$tot['muertos'].' muertos' : '' ?>)
          </span>
          <span style="font-size:9px;color:#64748b"><?= kg($liq['peso_total_kg']) ?></span>
        </div>
        <div class="resultado-final <?= $esGanancia ? 'ganancia-bg' : 'perdida-bg' ?>">
          <div class="rf-label <?= $esGanancia ? 'rf-label-g' : 'rf-label-p' ?>">
            <?= $esGanancia ? 'Ganancia neta' : 'Pérdida neta' ?>
          </div>
          <div class="rf-valor <?= $esGanancia ? 'rf-valor-g' : 'rf-valor-p' ?>">
            <?= m($tot['total_ganancia']) ?>
          </div>
        </div>
      </div>
    </div>

  </div>

  <!-- DISTRIBUCIÓN POR SOCIOS (tabla compacta agrupada por contrato) -->
  <?php if (!empty($sociosPorContrato)):
    // Pre-calcular totales consolidados si hay más de un contrato
    $totalesSocio = [];
    foreach ($sociosPorContrato as $grupo) {
        foreach ($grupo['socios'] as $s) {
            $k = $s['socio'] . '||' . $s['empresa'];
            if (!isset($totalesSocio[$k])) {
                $totalesSocio[$k] = ['socio' => $s['socio'], 'empresa' => $s['empresa'], 'total' => 0.0];
            }
            $totalesSocio[$k]['total'] += (float)$s['ganancia_estimada'];
        }
    }
    $multiContrato = count($sociosPorContrato) > 1;
  ?>
  <div class="no-break">
    <div class="seccion-titulo">
      Distribución por socios<?php if ($multiContrato): ?> — <?= count($sociosPorContrato) ?> contratos<?php endif; ?>
    </div>
    <table class="socios-tabla">
      <thead>
        <tr>
          <th style="width:90px">Contrato</th>
          <th>Socio</th>
          <th style="width:36px;text-align:center">Part.</th>
          <th style="width:88px;text-align:right">Gan. contrato</th>
          <th style="width:88px;text-align:right">Gan. socio</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($sociosPorContrato as $contId => $grupo):
          $ganCont    = (float)$grupo['ganancia_contrato'];
          $ganContPos = $ganCont >= 0;
          $nSocios    = count($grupo['socios']);
          $firstSocio = true;
        ?>
        <?php foreach ($grupo['socios'] as $s):
          $sg    = (float)$s['ganancia_estimada'];
          $sgPos = $sg >= 0;
        ?>
        <tr>
          <?php if ($firstSocio): ?>
          <td class="td-contrato" rowspan="<?= $nSocios ?>">
            <span class="cod-contrato"><?= htmlspecialchars($grupo['contrato_codigo']) ?></span>
            <div class="meta-contrato">
              <span><?= $grupo['animales_contrato'] ?></span> anim.
            </div>
          </td>
          <?php endif; ?>
          <td class="td-nombre">
            <span class="dot <?= $sgPos ? 'dot-g' : 'dot-p' ?>"></span><?= htmlspecialchars($s['socio']) ?>
            <div class="td-empresa"><?= htmlspecialchars($s['empresa']) ?></div>
          </td>
          <td class="td-pct"><?= $s['porcentaje'] ?>%</td>
          <?php if ($firstSocio): ?>
          <td class="td-gan-cont <?= $ganContPos ? 'gan-pos' : 'gan-neg' ?>" rowspan="<?= $nSocios ?>"><?= m($ganCont) ?></td>
          <?php endif; ?>
          <td class="td-gan-socio <?= $sgPos ? 'gan-pos' : 'gan-neg' ?>"><?= m($sg) ?></td>
        </tr>
        <?php $firstSocio = false; endforeach; ?>
        <?php endforeach; ?>
      </tbody>
      <?php if ($multiContrato): ?>
      <tfoot>
        <?php foreach ($totalesSocio as $ts):
          $tsPos = $ts['total'] >= 0;
        ?>
        <tr class="fila-total">
          <?php if ($ts === reset($totalesSocio)): ?>
          <td rowspan="<?= count($totalesSocio) ?>" style="font-size:8px;color:#94a3b8;text-transform:uppercase;letter-spacing:.07em;vertical-align:middle">
            Total<br>consolidado
          </td>
          <?php endif; ?>
          <td colspan="2" style="color:#e2e8f0"><?= htmlspecialchars($ts['socio']) ?>
            <span style="font-size:7.5px;color:#64748b;margin-left:4px"><?= htmlspecialchars($ts['empresa']) ?></span>
          </td>
          <td style="text-align:right;color:<?= $tsPos ? '#6ee7b7' : '#fca5a5' ?>"><?= m($ts['total']) ?></td>
        </tr>
        <?php endforeach; ?>
      </tfoot>
      <?php endif; ?>
    </table>
  </div>
  <?php endif; ?>

  <!-- OBSERVACIÓN -->
  <?php if (!empty($liq['observacion'])): ?>
  <div class="obs-box no-break">
    <strong>Observaciones</strong>
    <?= nl2br(htmlspecialchars($liq['observacion'])) ?>
  </div>
  <?php endif; ?>

  <!-- PIE DEL DOCUMENTO -->
  <div class="doc-footer">
    <div>
      <div>GanaderoPro — Sistema de gestión ganadera</div>
      <div>Documento generado el <?= $fechaImpresion ?></div>
    </div>
    <div style="text-align:right">
      <div>Liquidación ID: <?= $liq['id'] ?> | Contrato: <?= htmlspecialchars($liq['contrato_codigo']) ?></div>
      <div>Fecha de compra: <?= fdate($liq['fecha_compra']) ?> | Fecha de venta: <?= fdate($liq['fecha_venta']) ?></div>
    </div>
  </div>

</div><!-- /page -->

<script>
// Verificar si viene con ?auto=1 para imprimir automáticamente
if (new URLSearchParams(window.location.search).get('auto') === '1') {
  window.addEventListener('load', () => setTimeout(() => window.print(), 400));
}
</script>
</body>
</html>
