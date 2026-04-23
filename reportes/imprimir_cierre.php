<?php
// ============================================================
// reportes/imprimir_cierre.php — Documento de cierre de contrato
// ============================================================
require_once __DIR__ . '/../bootstrap.php';
Auth::requirePermission('reportes', 'ver');

$idContrato = (int)($_GET['id'] ?? 0);
if ($idContrato <= 0) { http_response_code(400); die('ID inválido.'); }

$pdo = getDB();

// ── Cabecera del cierre ───────────────────────────────────
$stmt = $pdo->prepare(
    'SELECT ci.*,
            c.codigo, c.fecha_compra, c.cantidad_animales AS total_comprado,
            e.nombre AS empresa_compra,
            t.nombre AS tipo_animal
     FROM cierre_contrato ci
     JOIN contratos_compra c ON c.id = ci.id_contrato
     JOIN empresas e         ON e.id = c.id_empresa_compra
     JOIN tipos_animal t     ON t.id = c.id_tipo_animal
     WHERE ci.id_contrato = ?'
);
$stmt->execute([$idContrato]);
$ci = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$ci) { http_response_code(404); die('Este contrato no tiene cierre registrado.'); }

// ── Socios ────────────────────────────────────────────────
$stmtS = $pdo->prepare(
    'SELECT csd.porcentaje, csd.ganancia, s.nombre AS socio, em.nombre AS empresa
     FROM cierre_socios_detalle csd
     JOIN socios   s  ON s.id  = csd.id_socio
     JOIN empresas em ON em.id = s.id_empresa
     WHERE csd.id_cierre = ?
     ORDER BY s.nombre'
);
$stmtS->execute([$ci['id']]);
$socios = $stmtS->fetchAll(PDO::FETCH_ASSOC);

// ── Liquidaciones ─────────────────────────────────────────
$stmtL = $pdo->prepare(
    'SELECT l.id, l.numero_factura, l.fecha_venta, l.peso_total_kg,
            l.valor_total_venta, cl.nombre AS cliente,
            (SELECT COUNT(*) FROM liquidacion_animales la WHERE la.id_liquidacion = l.id) AS animales
     FROM liquidaciones l
     LEFT JOIN clientes cl ON cl.id = l.id_cliente
     WHERE l.id IN (
         SELECT DISTINCT la.id_liquidacion
         FROM liquidacion_animales la
         JOIN animales a ON a.id = la.id_animal
         WHERE a.id_contrato = ?
     )
     ORDER BY l.fecha_venta'
);
$stmtL->execute([$idContrato]);
$liquidaciones = $stmtL->fetchAll(PDO::FETCH_ASSOC);

// ── Helpers ───────────────────────────────────────────────
function m(mixed $n): string {
    return '$ ' . number_format((float)$n, 0, ',', '.');
}
function fdate(?string $d): string {
    if (!$d) return '—';
    [$y, $mo, $da] = explode('-', $d);
    return "{$da}/{$mo}/{$y}";
}

$esGanancia     = (float)$ci['ganancia_total'] >= 0;
$dias           = (int)round((strtotime($ci['fecha_cierre']) - strtotime($ci['fecha_compra'])) / 86400);
$roi            = $ci['costo_total'] ? round((float)$ci['ganancia_total'] / (float)$ci['costo_total'] * 100, 2) : 0;
$otrosCostos    = (float)$ci['costo_total']
                - (float)$ci['costo_total_compra']
                - (float)$ci['costo_total_flete_entrada']
                - (float)$ci['costo_total_manutencion']
                - (float)$ci['costo_total_flete_salida'];
$fechaImpresion = date('d/m/Y H:i');
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Cierre <?= htmlspecialchars($ci['codigo']) ?> — GanaderoPro</title>
  <style>
    /* ─── Reset ─────────────────────────────────────────── */
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
      font-size: 22px; font-weight: 800;
      color: #1e293b; letter-spacing: -.5px; line-height: 1;
    }
    .brand-name span { color: #3a7229; }
    .brand-sub {
      font-size: 9px; color: #64748b;
      letter-spacing: .12em; text-transform: uppercase; margin-top: 3px;
    }
    .doc-type { text-align: right; }
    .doc-type h1 {
      font-size: 16px; font-weight: 800;
      color: #1e293b; text-transform: uppercase; letter-spacing: .04em;
    }
    .doc-type .contrato-cod {
      font-size: 18px; font-weight: 900; color: #3a7229; letter-spacing: .02em;
    }
    .badge-cierre {
      display: inline-block; padding: 2px 8px; border-radius: 4px;
      font-size: 9px; font-weight: 700; text-transform: uppercase;
      letter-spacing: .06em; margin-top: 4px;
      background: #d1fae5; color: #065f46;
    }

    /* ─── Bloque info contrato ──────────────────────────── */
    .info-grid {
      display: grid; grid-template-columns: repeat(4, 1fr);
      border: 1.5px solid #e2e8f0; border-radius: 6px;
      overflow: hidden; margin-bottom: 10px;
    }
    .info-cell {
      padding: 8px 12px; border-right: 1px solid #e2e8f0;
    }
    .info-cell:last-child { border-right: none; }
    .info-label {
      font-size: 8px; font-weight: 700; text-transform: uppercase;
      letter-spacing: .1em; color: #94a3b8; margin-bottom: 3px;
    }
    .info-val {
      font-size: 11px; font-weight: 700; color: #1e293b;
    }
    .info-val.mono { font-family: 'Courier New', monospace; color: #3a7229; font-size: 12px; }
    .info-val.sub  { font-size: 10px; font-weight: 400; color: #475569; }

    /* ─── KPI bar ───────────────────────────────────────── */
    .kpi-bar {
      display: grid; grid-template-columns: repeat(6, 1fr);
      border: 1.5px solid #e2e8f0; border-top: none;
      border-radius: 0 0 6px 6px; overflow: hidden; margin-bottom: 14px;
    }
    .kpi-cell {
      padding: 7px 10px; border-right: 1px solid #e2e8f0; text-align: center;
    }
    .kpi-cell:last-child { border-right: none; }
    .kpi-label { font-size: 7.5px; text-transform: uppercase; letter-spacing: .07em; color: #94a3b8; font-weight: 600; }
    .kpi-val   { font-size: 12px; font-weight: 800; color: #1e293b; margin-top: 2px; }
    .kpi-val.verde { color: #3a7229; }
    .kpi-val.rojo  { color: #dc2626; }

    /* ─── Sección titulada ──────────────────────────────── */
    .seccion-titulo {
      font-size: 9px; font-weight: 700; text-transform: uppercase;
      letter-spacing: .1em; color: #fff; background: #1e293b;
      padding: 5px 10px; border-radius: 4px 4px 0 0;
    }

    /* ─── Bloque financiero 2 columnas ─────────────────── */
    .financiero {
      display: grid; grid-template-columns: 1fr 1fr;
      gap: 12px; margin-bottom: 14px;
    }

    /* ─── Tabla de costos ───────────────────────────────── */
    .costos-tabla {
      border: 1.5px solid #e2e8f0;
      border-top: none;
      border-radius: 0 0 6px 6px; overflow: hidden;
    }
    .costos-tabla table { width: 100%; border-collapse: collapse; font-size: 10px; }
    .costos-tabla tr { border-bottom: 1px solid #f1f5f9; }
    .costos-tabla tr:last-child { border-bottom: none; }
    .costos-tabla td { padding: 5px 10px; color: #334155; }
    .costos-tabla td:last-child { text-align: right; font-weight: 600; }
    .costos-tabla .pct-bar {
      height: 3px; background: #e2e8f0; border-radius: 2px; margin-top: 2px;
    }
    .costos-tabla .pct-fill { height: 3px; background: #94a3b8; border-radius: 2px; }
    .costos-tabla .total-row { background: #1e293b; color: #fff; font-weight: 800; font-size: 11px; }
    .costos-tabla .total-row td { color: #fff; }
    .costos-tabla .total-row td:last-child { color: #fca5a5; }

    /* ─── Resultado final ───────────────────────────────── */
    .resumen-resultado {
      border: 1.5px solid #e2e8f0; border-top: none;
      border-radius: 0 0 6px 6px; overflow: hidden;
    }
    .ingresos-row {
      display: flex; justify-content: space-between; align-items: center;
      padding: 8px 12px; border-bottom: 1px solid #f1f5f9;
    }
    .ingresos-row .lbl { font-size: 9px; color: #64748b; text-transform: uppercase; letter-spacing: .07em; }
    .ingresos-row .val { font-size: 13px; font-weight: 800; }
    .val-ingresos { color: #3a7229; }
    .val-costos   { color: #dc2626; }
    .resultado-final { padding: 10px 12px; text-align: center; }
    .resultado-final .rf-label {
      font-size: 9px; text-transform: uppercase; letter-spacing: .1em;
      font-weight: 700; margin-bottom: 3px;
    }
    .resultado-final .rf-valor { font-size: 24px; font-weight: 900; letter-spacing: -.5px; }
    .resultado-final .rf-roi   { font-size: 10px; font-weight: 700; margin-top: 3px; }
    .ganancia-bg { background: #ecfdf5; border-top: 2px solid #3a7229; }
    .perdida-bg  { background: #fff1f2; border-top: 2px solid #dc2626; }
    .rf-label-g  { color: #3a7229; }
    .rf-label-p  { color: #dc2626; }
    .rf-valor-g  { color: #3a7229; }
    .rf-valor-p  { color: #dc2626; }
    .rf-roi-g    { color: #3a7229; }
    .rf-roi-p    { color: #dc2626; }

    /* ─── Tabla de liquidaciones ────────────────────────── */
    .tabla-std {
      width: 100%; border-collapse: collapse;
      border: 1.5px solid #e2e8f0; border-top: none;
      border-radius: 0 0 6px 6px; font-size: 9.5px; margin-bottom: 14px;
    }
    .tabla-std thead tr { background: #f1f5f9; }
    .tabla-std thead th {
      padding: 5px 8px; text-align: left; font-size: 8px; font-weight: 700;
      text-transform: uppercase; letter-spacing: .05em; color: #475569;
      border-bottom: 1.5px solid #cbd5e1; white-space: nowrap;
    }
    .tabla-std thead th.r { text-align: right; }
    .tabla-std tbody tr { border-bottom: 1px solid #f1f5f9; }
    .tabla-std tbody tr:last-child { border-bottom: none; }
    .tabla-std tbody td { padding: 5px 8px; color: #334155; }
    .tabla-std tbody td.r { text-align: right; }
    .tabla-std tfoot tr { background: #1e293b; color: #e2e8f0; }
    .tabla-std tfoot td {
      padding: 5px 8px; font-weight: 700; font-size: 8.5px;
    }
    .tabla-std tfoot td.r { text-align: right; }

    /* ─── Socios ────────────────────────────────────────── */
    .socios-grid {
      display: grid; grid-template-columns: repeat(3, 1fr);
      gap: 8px; margin-bottom: 14px; padding: 10px;
      border: 1.5px solid #e2e8f0; border-top: none;
      border-radius: 0 0 6px 6px;
    }
    .socio-card {
      border: 1px solid #e2e8f0; border-radius: 5px;
      padding: 8px 10px; page-break-inside: avoid;
    }
    .socio-card.g { border-color: #6ee7b7; background: #f0fdf4; }
    .socio-card.p { border-color: #fca5a5; background: #fff1f2; }
    .socio-inicial {
      width: 24px; height: 24px; border-radius: 50%;
      display: inline-flex; align-items: center; justify-content: center;
      font-weight: 800; font-size: 11px; margin-bottom: 5px;
    }
    .socio-inicial.g { background: #d1fae5; color: #065f46; }
    .socio-inicial.p { background: #fee2e2; color: #991b1b; }
    .socio-nombre    { font-size: 11px; font-weight: 700; color: #1e293b; }
    .socio-empresa   { font-size: 8px; color: #94a3b8; margin-top: 1px; }
    .socio-part      { font-size: 9px; color: #64748b; margin-bottom: 4px; }
    .socio-ganancia  { font-size: 14px; font-weight: 900; }
    .socio-ganancia.g { color: #3a7229; }
    .socio-ganancia.p { color: #dc2626; }
    .socio-roi       { font-size: 8px; font-weight: 600; margin-top: 2px; }
    .socio-roi.g     { color: #3a7229; }
    .socio-roi.p     { color: #dc2626; }

    /* ─── Pie de página ─────────────────────────────────── */
    .doc-footer {
      border-top: 1px solid #e2e8f0; padding-top: 8px; margin-top: auto;
      display: flex; justify-content: space-between; align-items: flex-end;
      font-size: 8.5px; color: #94a3b8;
    }

    /* ─── Barra de acciones (solo pantalla) ─────────────── */
    .print-bar {
      width: 210mm; margin: 0 auto 12px;
      display: flex; gap: 8px; align-items: center;
    }
    .btn-print {
      display: inline-flex; align-items: center; gap: 6px;
      padding: 8px 18px; border-radius: 6px;
      background: #3a7229; color: #fff;
      font-size: 13px; font-weight: 600; cursor: pointer; border: none;
    }
    .btn-print:hover { background: #2a561e; }
    .btn-back {
      display: inline-flex; align-items: center; gap: 6px;
      padding: 8px 14px; border-radius: 6px;
      background: #fff; color: #334155;
      font-size: 13px; font-weight: 500; cursor: pointer;
      border: 1px solid #e2e8f0; text-decoration: none;
    }
    .btn-back:hover { background: #f8fafc; }

    /* ─── Print ─────────────────────────────────────────── */
    @media print {
      body { background: white; padding: 0; margin: 0; font-size: 10px; }
      .page { box-shadow: none; margin: 0; padding: 10mm 12mm; width: 100%; min-height: auto; }
      .print-bar { display: none; }
      .no-break { page-break-inside: avoid; }
      .seccion-titulo,
      .costos-tabla .total-row,
      .tabla-std thead tr,
      .tabla-std tfoot tr,
      .ganancia-bg, .perdida-bg,
      .socio-card.g, .socio-card.p,
      .socio-inicial { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
      thead { display: table-header-group; }
      tfoot { display: table-footer-group; }
      @page { margin: 8mm 10mm; size: A4; }
    }
  </style>
</head>
<body>

<!-- Barra de acciones -->
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
    Cierre <?= htmlspecialchars($ci['codigo']) ?> — <?= htmlspecialchars($ci['empresa_compra']) ?>
  </span>
</div>

<!-- ══ HOJA ═══════════════════════════════════════════════ -->
<div class="page">

  <!-- ENCABEZADO -->
  <div class="doc-header">
    <div>
      <div class="brand-name">Ganader<span>o</span>Pro</div>
      <div class="brand-sub">Sistema de gestión ganadera</div>
    </div>
    <div class="doc-type">
      <h1>Cierre de Contrato</h1>
      <div class="contrato-cod"><?= htmlspecialchars($ci['codigo']) ?></div>
      <div><span class="badge-cierre">Cerrado</span></div>
    </div>
  </div>

  <!-- INFO DEL CONTRATO -->
  <div class="info-grid no-break">
    <div class="info-cell">
      <div class="info-label">Empresa</div>
      <div class="info-val"><?= htmlspecialchars($ci['empresa_compra']) ?></div>
    </div>
    <div class="info-cell">
      <div class="info-label">Tipo de animal</div>
      <div class="info-val"><?= htmlspecialchars($ci['tipo_animal']) ?></div>
    </div>
    <div class="info-cell">
      <div class="info-label">Fecha compra → cierre</div>
      <div class="info-val"><?= fdate($ci['fecha_compra']) ?> → <?= fdate($ci['fecha_cierre']) ?></div>
    </div>
    <div class="info-cell">
      <div class="info-label">Días de operación</div>
      <div class="info-val"><?= $dias ?> días</div>
    </div>
  </div>

  <!-- KPI BAR -->
  <div class="kpi-bar no-break">
    <?php
    $kpis = [
      ['Comprados',   $ci['total_animales'],    ''],
      ['Vendidos',    $ci['animales_vendidos'],  'verde'],
      ['Muertos',     $ci['animales_muertos'],   'rojo'],
      ['Inversión',   m($ci['costo_total']),     'rojo'],
      ['Ingresos',    m($ci['ingreso_total_ventas']), 'verde'],
      [$esGanancia ? 'Ganancia' : 'Pérdida', m($ci['ganancia_total']), $esGanancia ? 'verde' : 'rojo'],
    ];
    foreach ($kpis as [$l, $v, $cls]): ?>
    <div class="kpi-cell">
      <div class="kpi-label"><?= $l ?></div>
      <div class="kpi-val <?= $cls ?>"><?= $v ?></div>
    </div>
    <?php endforeach; ?>
  </div>

  <!-- FINANCIERO: costos + resultado -->
  <div class="financiero no-break">

    <!-- Desglose de costos -->
    <div>
      <div class="seccion-titulo">Desglose de costos</div>
      <div class="costos-tabla">
        <?php
        $totalCosto = (float)$ci['costo_total'];
        $filas = [
          ['Costo compra animales', $ci['costo_total_compra']],
          ['Flete de entrada',      $ci['costo_total_flete_entrada']],
          ['Manutención ('.$dias.' días)', $ci['costo_total_manutencion']],
          ['Flete de salida',       $ci['costo_total_flete_salida']],
        ];
        if (abs($otrosCostos) >= 1) $filas[] = ['Otros gastos', $otrosCostos];
        ?>
        <table>
          <?php foreach ($filas as [$label, $val]):
            $pct = $totalCosto > 0 ? round((float)$val / $totalCosto * 100) : 0;
          ?>
          <tr>
            <td>
              <?= $label ?>
              <div class="pct-bar"><div class="pct-fill" style="width:<?= $pct ?>%"></div></div>
            </td>
            <td><?= m($val) ?> <span style="font-size:8px;color:#94a3b8">(<?= $pct ?>%)</span></td>
          </tr>
          <?php endforeach; ?>
          <tr class="total-row">
            <td>COSTO TOTAL</td>
            <td><?= m($ci['costo_total']) ?></td>
          </tr>
        </table>
      </div>
    </div>

    <!-- Resultado final -->
    <div>
      <div class="seccion-titulo">Resultado del contrato</div>
      <div class="resumen-resultado">
        <div class="ingresos-row">
          <span class="lbl">Total ingresos ventas</span>
          <span class="val val-ingresos"><?= m($ci['ingreso_total_ventas']) ?></span>
        </div>
        <div class="ingresos-row">
          <span class="lbl">Total costos</span>
          <span class="val val-costos"><?= m($ci['costo_total']) ?></span>
        </div>
        <div class="ingresos-row" style="border-bottom:none;padding-bottom:4px">
          <span class="lbl">
            <?= $ci['total_animales'] ?> cab. compradas ·
            <?= $ci['animales_vendidos'] ?> vendidas ·
            <?= $ci['animales_muertos'] ?> muertas
          </span>
          <span style="font-size:9px;color:#64748b"><?= $dias ?> días</span>
        </div>
        <div class="resultado-final <?= $esGanancia ? 'ganancia-bg' : 'perdida-bg' ?>">
          <div class="rf-label <?= $esGanancia ? 'rf-label-g' : 'rf-label-p' ?>">
            <?= $esGanancia ? 'Ganancia neta' : 'Pérdida neta' ?>
          </div>
          <div class="rf-valor <?= $esGanancia ? 'rf-valor-g' : 'rf-valor-p' ?>">
            <?= m($ci['ganancia_total']) ?>
          </div>
          <div class="rf-roi <?= $roi >= 0 ? 'rf-roi-g' : 'rf-roi-p' ?>">
            ROI: <?= number_format($roi, 2, ',', '.') ?> %
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- LIQUIDACIONES -->
  <?php if (!empty($liquidaciones)): ?>
  <div class="no-break">
    <div class="seccion-titulo">Liquidaciones realizadas — <?= count($liquidaciones) ?> venta(s)</div>
    <table class="tabla-std">
      <thead>
        <tr>
          <th>#</th>
          <th>Factura</th>
          <th>Fecha</th>
          <th>Cliente</th>
          <th class="r">Cabezas</th>
          <th class="r">Peso kg</th>
          <th class="r">Valor venta</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($liquidaciones as $i => $l): ?>
        <tr>
          <td style="color:#94a3b8"><?= $i + 1 ?></td>
          <td style="font-family:monospace;font-size:9px;font-weight:600"><?= htmlspecialchars($l['numero_factura'] ?: 'S/N') ?></td>
          <td><?= fdate($l['fecha_venta']) ?></td>
          <td><?= htmlspecialchars($l['cliente'] ?? '—') ?></td>
          <td class="r"><?= (int)$l['animales'] ?></td>
          <td class="r"><?= number_format((float)$l['peso_total_kg'], 0, ',', '.') ?> kg</td>
          <td class="r" style="color:#3a7229;font-weight:700"><?= m($l['valor_total_venta']) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
      <tfoot>
        <tr>
          <td colspan="4" style="font-weight:700">TOTAL</td>
          <td class="r"><?= array_sum(array_column($liquidaciones, 'animales')) ?></td>
          <td class="r"><?= number_format(array_sum(array_column($liquidaciones, 'peso_total_kg')), 0, ',', '.') ?> kg</td>
          <td class="r" style="color:#6ee7b7"><?= m(array_sum(array_column($liquidaciones, 'valor_total_venta'))) ?></td>
        </tr>
      </tfoot>
    </table>
  </div>
  <?php endif; ?>

  <!-- DISTRIBUCIÓN POR SOCIOS -->
  <?php if (!empty($socios)): ?>
  <div class="no-break">
    <div class="seccion-titulo">Distribución por socios</div>
    <div class="socios-grid">
      <?php foreach ($socios as $s):
        $sg    = (float)$s['ganancia'];
        $sgPos = $sg >= 0;
        $cBase = $ci['costo_total'] ? $ci['costo_total'] * $s['porcentaje'] / 100 : 0;
        $sRoi  = $cBase ? round($sg / $cBase * 100, 2) : 0;
      ?>
      <div class="socio-card <?= $sgPos ? 'g' : 'p' ?>">
        <div class="socio-inicial <?= $sgPos ? 'g' : 'p' ?>"><?= mb_strtoupper(mb_substr($s['socio'], 0, 1)) ?></div>
        <div class="socio-nombre"><?= htmlspecialchars($s['socio']) ?></div>
        <div class="socio-empresa"><?= htmlspecialchars($s['empresa']) ?></div>
        <div class="socio-part"><?= $s['porcentaje'] ?>% de participación</div>
        <div class="socio-ganancia <?= $sgPos ? 'g' : 'p' ?>"><?= m($sg) ?></div>
        <div class="socio-roi <?= $sRoi >= 0 ? 'g' : 'p' ?>">ROI: <?= number_format($sRoi, 2, ',', '.') ?> %</div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

  <!-- PIE DE PÁGINA -->
  <div class="doc-footer">
    <div>
      <div>GanaderoPro — Sistema de gestión ganadera</div>
      <div>Documento generado el <?= $fechaImpresion ?></div>
    </div>
    <div style="text-align:right">
      <div>Contrato: <?= htmlspecialchars($ci['codigo']) ?> | Cierre ID: <?= $ci['id'] ?></div>
      <div>Compra: <?= fdate($ci['fecha_compra']) ?> → Cierre: <?= fdate($ci['fecha_cierre']) ?> (<?= $dias ?> días)</div>
    </div>
  </div>

</div><!-- /page -->

<script>
if (new URLSearchParams(window.location.search).get('auto') === '1') {
  window.addEventListener('load', () => setTimeout(() => window.print(), 400));
}
</script>
</body>
</html>
