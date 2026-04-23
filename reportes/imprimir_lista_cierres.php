<?php
// ============================================================
// reportes/imprimir_lista_cierres.php — Informe ejecutivo de cierres
// ============================================================
require_once __DIR__ . '/../bootstrap.php';
Auth::requirePermission('reportes', 'ver');

$pdo = getDB();

// ── Filtros ───────────────────────────────────────────────
$fechaDesde = $_GET['fecha_desde'] ?? '';
$fechaHasta = $_GET['fecha_hasta'] ?? '';
$empresa    = (int)($_GET['empresa']     ?? 0);
$tipoAnimal = (int)($_GET['tipo_animal'] ?? 0);
$socio      = (int)($_GET['socio']       ?? 0);
$resultado  = $_GET['resultado'] ?? '';

$where  = ['1=1'];
$params = [];

if ($fechaDesde)     { $where[] = 'ci.fecha_cierre >= ?';    $params[] = $fechaDesde; }
if ($fechaHasta)     { $where[] = 'ci.fecha_cierre <= ?';    $params[] = $fechaHasta; }
if ($empresa > 0)    { $where[] = 'c.id_empresa_compra = ?'; $params[] = $empresa;    }
if ($tipoAnimal > 0) { $where[] = 'c.id_tipo_animal = ?';    $params[] = $tipoAnimal; }
if ($socio > 0) {
    $where[]  = 'EXISTS (SELECT 1 FROM contrato_socios cs2 WHERE cs2.id_contrato = c.id AND cs2.id_socio = ?)';
    $params[] = $socio;
}
if ($resultado === 'positivo') { $where[] = 'ci.ganancia_total > 0'; }
if ($resultado === 'negativo') { $where[] = 'ci.ganancia_total < 0'; }

$w = implode(' AND ', $where);

// ── KPIs ──────────────────────────────────────────────────
$stmtK = $pdo->prepare(
    "SELECT COUNT(*) AS total_cierres,
            COALESCE(SUM(ci.total_animales),0)    AS total_animales,
            COALESCE(SUM(ci.animales_vendidos),0) AS total_vendidos,
            COALESCE(SUM(ci.animales_muertos),0)  AS total_muertos,
            COALESCE(SUM(ci.costo_total),0)       AS total_costos,
            COALESCE(SUM(ci.ingreso_total_ventas),0) AS total_ingresos,
            COALESCE(SUM(ci.ganancia_total),0)    AS total_ganancia,
            ROUND(AVG(DATEDIFF(ci.fecha_cierre,c.fecha_compra)),0) AS dias_promedio,
            ROUND(SUM(ci.ganancia_total)/NULLIF(SUM(ci.costo_total),0)*100,2) AS roi_global,
            SUM(ci.ganancia_total > 0) AS con_ganancia,
            SUM(ci.ganancia_total < 0) AS con_perdida
     FROM cierre_contrato ci
     JOIN contratos_compra c ON c.id = ci.id_contrato
     WHERE {$w}"
);
$stmtK->execute($params);
$k = $stmtK->fetch(PDO::FETCH_ASSOC);

// ── Lista ─────────────────────────────────────────────────
$stmt = $pdo->prepare(
    "SELECT c.id, c.codigo, c.fecha_compra,
            ci.fecha_cierre, ci.total_animales, ci.animales_vendidos, ci.animales_muertos,
            ci.costo_total, ci.ingreso_total_ventas, ci.ganancia_total,
            DATEDIFF(ci.fecha_cierre, c.fecha_compra) AS dias_operacion,
            ROUND(ci.ganancia_total / NULLIF(ci.costo_total,0) * 100, 2) AS roi_pct,
            e.nombre AS empresa, t.nombre AS tipo_animal,
            GROUP_CONCAT(s.nombre ORDER BY s.nombre SEPARATOR ', ') AS socios
     FROM cierre_contrato ci
     JOIN contratos_compra c  ON c.id  = ci.id_contrato
     JOIN empresas e          ON e.id  = c.id_empresa_compra
     JOIN tipos_animal t      ON t.id  = c.id_tipo_animal
     LEFT JOIN contrato_socios cs ON cs.id_contrato = c.id
     LEFT JOIN socios s           ON s.id = cs.id_socio
     WHERE {$w}
     GROUP BY ci.id
     ORDER BY ci.fecha_cierre DESC"
);
$stmt->execute($params);
$lista = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ── Helpers ───────────────────────────────────────────────
function m(mixed $n): string {
    return '$ ' . number_format((float)$n, 0, ',', '.');
}
function fdate(?string $d): string {
    if (!$d) return '—';
    [$y, $mo, $da] = explode('-', $d);
    return "{$da}/{$mo}/{$y}";
}

// Etiquetas de filtros aplicados
$filtrosLabel = [];
if ($fechaDesde) $filtrosLabel[] = 'Desde ' . fdate($fechaDesde);
if ($fechaHasta) $filtrosLabel[] = 'Hasta ' . fdate($fechaHasta);
if ($empresa > 0) {
    $r = $pdo->prepare('SELECT nombre FROM empresas WHERE id = ?');
    $r->execute([$empresa]);
    $filtrosLabel[] = 'Empresa: ' . ($r->fetchColumn() ?: $empresa);
}
if ($tipoAnimal > 0) {
    $r = $pdo->prepare('SELECT nombre FROM tipos_animal WHERE id = ?');
    $r->execute([$tipoAnimal]);
    $filtrosLabel[] = 'Tipo: ' . ($r->fetchColumn() ?: $tipoAnimal);
}
if ($socio > 0) {
    $r = $pdo->prepare('SELECT nombre FROM socios WHERE id = ?');
    $r->execute([$socio]);
    $filtrosLabel[] = 'Socio: ' . ($r->fetchColumn() ?: $socio);
}
if ($resultado === 'positivo') $filtrosLabel[] = 'Solo ganancias';
if ($resultado === 'negativo') $filtrosLabel[] = 'Solo pérdidas';

$esGananciaGlobal = (float)$k['total_ganancia'] >= 0;
$fechaImpresion   = date('d/m/Y H:i');
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Informe de Cierres — GanaderoPro</title>
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    body {
      font-family: 'Segoe UI', Arial, sans-serif;
      font-size: 11px; color: #1a1a2e;
      background: #f0f0f0; padding: 20px;
    }

    .page {
      background: #fff; width: 210mm; min-height: 297mm;
      margin: 0 auto; padding: 14mm 14mm 12mm;
      box-shadow: 0 4px 24px rgba(0,0,0,.12);
    }

    /* Cabecera */
    .doc-header {
      display: flex; justify-content: space-between; align-items: flex-start;
      border-bottom: 2.5px solid #1e293b; padding-bottom: 10px; margin-bottom: 12px;
    }
    .brand-name { font-size: 22px; font-weight: 800; color: #1e293b; letter-spacing: -.5px; line-height: 1; }
    .brand-name span { color: #3a7229; }
    .brand-sub { font-size: 9px; color: #64748b; letter-spacing: .12em; text-transform: uppercase; margin-top: 3px; }
    .doc-type { text-align: right; }
    .doc-type h1 { font-size: 16px; font-weight: 800; color: #1e293b; text-transform: uppercase; letter-spacing: .04em; }
    .doc-type .doc-sub { font-size: 9px; color: #64748b; margin-top: 3px; }

    /* Filtros aplicados */
    .filtros-bar {
      background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 5px;
      padding: 6px 10px; margin-bottom: 12px; font-size: 9px; color: #475569;
    }
    .filtros-bar strong { color: #1e293b; }

    /* KPI cards */
    .kpi-row {
      display: grid; grid-template-columns: repeat(5, 1fr);
      gap: 6px; margin-bottom: 14px;
    }
    .kpi-card {
      border: 1px solid #e2e8f0; border-radius: 5px; padding: 8px;
      text-align: center; page-break-inside: avoid;
    }
    .kpi-card.ganancia { border-color: #6ee7b7; background: #f0fdf4; }
    .kpi-card.perdida  { border-color: #fca5a5; background: #fff1f2; }
    .kpi-label { font-size: 7.5px; text-transform: uppercase; letter-spacing: .08em; color: #94a3b8; font-weight: 700; margin-bottom: 3px; }
    .kpi-val   { font-size: 14px; font-weight: 900; color: #1e293b; }
    .kpi-sub   { font-size: 8px; color: #64748b; margin-top: 2px; }
    .kpi-val.verde { color: #3a7229; }
    .kpi-val.rojo  { color: #dc2626; }

    /* Tabla */
    .seccion-titulo {
      font-size: 9px; font-weight: 700; text-transform: uppercase;
      letter-spacing: .1em; color: #fff; background: #1e293b;
      padding: 5px 10px; border-radius: 4px 4px 0 0;
    }
    .tabla-cierres {
      width: 100%; border-collapse: collapse;
      border: 1.5px solid #e2e8f0; border-top: none;
      font-size: 9px;
    }
    .tabla-cierres thead tr { background: #f1f5f9; }
    .tabla-cierres thead th {
      padding: 5px 6px; font-size: 7.5px; font-weight: 700;
      text-transform: uppercase; letter-spacing: .05em; color: #475569;
      border-bottom: 1.5px solid #cbd5e1; white-space: nowrap; text-align: right;
    }
    .tabla-cierres thead th:first-child { text-align: left; }
    .tabla-cierres thead th.l { text-align: left; }
    .tabla-cierres tbody tr { border-bottom: 1px solid #f1f5f9; }
    .tabla-cierres tbody tr:nth-child(even) { background: #fafafa; }
    .tabla-cierres tbody td {
      padding: 5px 6px; color: #334155; text-align: right; vertical-align: top;
    }
    .tabla-cierres tbody td:first-child { text-align: left; }
    .tabla-cierres tbody td.l { text-align: left; }
    .tabla-cierres tfoot tr { background: #1e293b; color: #e2e8f0; }
    .tabla-cierres tfoot td {
      padding: 5px 6px; font-weight: 700; font-size: 8.5px; text-align: right;
    }
    .tabla-cierres tfoot td:first-child { text-align: left; }
    .cod { font-family: 'Courier New', monospace; font-weight: 800; font-size: 9px; color: #1e293b; }
    .date-small { font-size: 8px; color: #94a3b8; margin-top: 1px; }
    .socios-small { font-size: 7.5px; color: #94a3b8; margin-top: 1px; font-style: italic; }
    .gan-pos { color: #3a7229; font-weight: 800; }
    .gan-neg { color: #dc2626; font-weight: 800; }
    .roi-pos { color: #3a7229; font-size: 8px; font-weight: 700; }
    .roi-neg { color: #dc2626; font-size: 8px; font-weight: 700; }

    /* Pie */
    .doc-footer {
      border-top: 1px solid #e2e8f0; padding-top: 8px; margin-top: 16px;
      display: flex; justify-content: space-between; align-items: flex-end;
      font-size: 8.5px; color: #94a3b8;
    }

    /* Barra acciones */
    .print-bar {
      width: 210mm; margin: 0 auto 12px; display: flex; gap: 8px; align-items: center;
    }
    .btn-print {
      display: inline-flex; align-items: center; gap: 6px;
      padding: 8px 18px; border-radius: 6px; background: #3a7229; color: #fff;
      font-size: 13px; font-weight: 600; cursor: pointer; border: none;
    }
    .btn-print:hover { background: #2a561e; }
    .btn-back {
      display: inline-flex; align-items: center; gap: 6px;
      padding: 8px 14px; border-radius: 6px; background: #fff; color: #334155;
      font-size: 13px; font-weight: 500; cursor: pointer; border: 1px solid #e2e8f0; text-decoration: none;
    }
    .btn-back:hover { background: #f8fafc; }

    @media print {
      body { background: white; padding: 0; margin: 0; font-size: 9px; }
      .page { box-shadow: none; margin: 0; padding: 8mm 10mm; width: 100%; min-height: auto; }
      .print-bar { display: none; }
      .seccion-titulo,
      .tabla-cierres thead tr,
      .tabla-cierres tfoot tr { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
      .kpi-card.ganancia, .kpi-card.perdida { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
      thead { display: table-header-group; }
      tfoot { display: table-footer-group; }
      @page { margin: 8mm 10mm; size: A4 landscape; }
    }
  </style>
</head>
<body>

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
    Informe ejecutivo · <?= count($lista) ?> cierre(s)
  </span>
</div>

<div class="page">

  <!-- ENCABEZADO -->
  <div class="doc-header">
    <div>
      <div class="brand-name">Ganader<span>o</span>Pro</div>
      <div class="brand-sub">Sistema de gestión ganadera</div>
    </div>
    <div class="doc-type">
      <h1>Informe de Cierres</h1>
      <div class="doc-sub">Generado el <?= $fechaImpresion ?></div>
    </div>
  </div>

  <!-- FILTROS -->
  <div class="filtros-bar">
    <strong>Filtros:</strong>
    <?= $filtrosLabel ? implode(' · ', array_map('htmlspecialchars', $filtrosLabel)) : 'Sin filtros — todos los cierres' ?>
  </div>

  <!-- KPI CARDS -->
  <div class="kpi-row">
    <div class="kpi-card">
      <div class="kpi-label">Cierres</div>
      <div class="kpi-val"><?= $k['total_cierres'] ?></div>
      <div class="kpi-sub">
        <span style="color:#3a7229;font-weight:700"><?= $k['con_ganancia'] ?> ganancia</span> ·
        <span style="color:#dc2626;font-weight:700"><?= $k['con_perdida'] ?> pérdida</span>
      </div>
    </div>
    <div class="kpi-card">
      <div class="kpi-label">Animales</div>
      <div class="kpi-val"><?= number_format($k['total_animales'], 0, ',', '.') ?></div>
      <div class="kpi-sub"><?= number_format($k['total_vendidos'], 0, ',', '.') ?> vendidos · <?= number_format($k['total_muertos'], 0, ',', '.') ?> muertos</div>
    </div>
    <div class="kpi-card">
      <div class="kpi-label">Inversión total</div>
      <div class="kpi-val" style="font-size:11px"><?= m($k['total_costos']) ?></div>
      <div class="kpi-sub">Prom. <?= $k['dias_promedio'] ?> días/lote</div>
    </div>
    <div class="kpi-card">
      <div class="kpi-label">Ingresos totales</div>
      <div class="kpi-val verde" style="font-size:11px"><?= m($k['total_ingresos']) ?></div>
    </div>
    <div class="kpi-card <?= $esGananciaGlobal ? 'ganancia' : 'perdida' ?>">
      <div class="kpi-label"><?= $esGananciaGlobal ? 'Ganancia neta' : 'Pérdida neta' ?></div>
      <div class="kpi-val <?= $esGananciaGlobal ? 'verde' : 'rojo' ?>" style="font-size:11px"><?= m($k['total_ganancia']) ?></div>
      <div class="kpi-sub" style="font-weight:700;color:<?= $esGananciaGlobal ? '#3a7229' : '#dc2626' ?>">
        ROI: <?= number_format((float)$k['roi_global'], 2, ',', '.') ?> %
      </div>
    </div>
  </div>

  <!-- TABLA -->
  <div class="seccion-titulo">Detalle por contrato — <?= count($lista) ?> registro(s)</div>
  <table class="tabla-cierres">
    <thead>
      <tr>
        <th class="l">Contrato</th>
        <th class="l">Empresa</th>
        <th class="l">Tipo</th>
        <th>Días</th>
        <th>Comp.</th>
        <th>Vend.</th>
        <th>Muer.</th>
        <th>Inversión</th>
        <th>Ingresos</th>
        <th>Ganancia</th>
        <th>ROI</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($lista as $r):
        $pos = (float)$r['ganancia_total'] >= 0;
        $roi = (float)$r['roi_pct'];
      ?>
      <tr>
        <td>
          <span class="cod"><?= htmlspecialchars($r['codigo']) ?></span>
          <div class="date-small"><?= fdate($r['fecha_compra']) ?> → <?= fdate($r['fecha_cierre']) ?></div>
          <?php if ($r['socios']): ?>
          <div class="socios-small"><?= htmlspecialchars($r['socios']) ?></div>
          <?php endif; ?>
        </td>
        <td class="l"><?= htmlspecialchars($r['empresa']) ?></td>
        <td class="l"><?= htmlspecialchars($r['tipo_animal']) ?></td>
        <td><?= $r['dias_operacion'] ?></td>
        <td><?= $r['total_animales'] ?></td>
        <td style="color:#3a7229;font-weight:700"><?= $r['animales_vendidos'] ?></td>
        <td style="color:#dc2626"><?= $r['animales_muertos'] ?></td>
        <td><?= m($r['costo_total']) ?></td>
        <td class="gan-pos"><?= m($r['ingreso_total_ventas']) ?></td>
        <td class="<?= $pos ? 'gan-pos' : 'gan-neg' ?>"><?= m($r['ganancia_total']) ?></td>
        <td class="<?= $roi >= 0 ? 'roi-pos' : 'roi-neg' ?>"><?= number_format($roi, 1, ',', '.') ?>%</td>
      </tr>
      <?php endforeach; ?>
      <?php if (!$lista): ?>
      <tr><td colspan="11" style="text-align:center;padding:16px;color:#94a3b8">Sin resultados para los filtros seleccionados.</td></tr>
      <?php endif; ?>
    </tbody>
    <?php if ($lista): ?>
    <tfoot>
      <tr>
        <td colspan="3">TOTALES (<?= count($lista) ?> contratos)</td>
        <td><?= round((float)$k['dias_promedio']) ?> p.</td>
        <td><?= number_format((float)$k['total_animales'], 0, ',', '.') ?></td>
        <td style="color:#6ee7b7"><?= number_format((float)$k['total_vendidos'], 0, ',', '.') ?></td>
        <td style="color:#fca5a5"><?= number_format((float)$k['total_muertos'], 0, ',', '.') ?></td>
        <td><?= m($k['total_costos']) ?></td>
        <td style="color:#6ee7b7"><?= m($k['total_ingresos']) ?></td>
        <td style="color:<?= $esGananciaGlobal ? '#6ee7b7' : '#fca5a5' ?>"><?= m($k['total_ganancia']) ?></td>
        <td style="color:<?= $esGananciaGlobal ? '#6ee7b7' : '#fca5a5' ?>;font-weight:800"><?= number_format((float)$k['roi_global'], 1, ',', '.') ?>%</td>
      </tr>
    </tfoot>
    <?php endif; ?>
  </table>

  <!-- PIE -->
  <div class="doc-footer">
    <div>
      <div>GanaderoPro — Sistema de gestión ganadera</div>
      <div>Documento generado el <?= $fechaImpresion ?></div>
    </div>
    <div style="text-align:right">
      <div><?= count($lista) ?> contratos cerrados</div>
      <div>Inversión total: <?= m($k['total_costos']) ?> · Ganancia: <?= m($k['total_ganancia']) ?></div>
    </div>
  </div>

</div>

<script>
if (new URLSearchParams(window.location.search).get('auto') === '1') {
  window.addEventListener('load', () => setTimeout(() => window.print(), 400));
}
</script>
</body>
</html>
