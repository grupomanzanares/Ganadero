<?php
// ============================================================
// reportes/inventario_socio.php — Inventario e informe de contratos por socio
// ============================================================
require_once __DIR__ . '/../bootstrap.php';
Auth::requirePermission('reportes', 'ver');
$pageTitle = 'Inventario por socio';
$modulo    = 'inventario_socio';
require_once __DIR__ . '/../views/layout/header.php';
?>

<style>
/* ── Base ── */
.sec-title { font-size:.7rem; font-weight:700; text-transform:uppercase;
             letter-spacing:.08em; color:#94a3b8; margin-bottom:.75rem; }
/* ── KPI ── */
.kpi { background:#fff; border:1px solid #e2e8f0; border-radius:.625rem;
       padding:1rem 1.25rem; position:relative; overflow:hidden; }
.kpi::before { content:''; position:absolute; top:0; left:0; width:3px;
               height:100%; border-radius:2px 0 0 2px; background:var(--ac,#059669); }
.kpi-lbl { font-size:.67rem; text-transform:uppercase; letter-spacing:.07em; color:#64748b; }
.kpi-val { font-family:'Fraunces',Georgia,serif; font-size:1.45rem; font-weight:700;
           color:var(--ac,#059669); line-height:1.15; }
.kpi-sub { font-size:.68rem; color:#94a3b8; margin-top:.15rem; }
/* ── Tabs ── */
.tab-nav { display:flex; gap:0; border-bottom:2px solid #e2e8f0; margin-bottom:1.25rem; }
.tab-btn { padding:.55rem 1.25rem; font-size:.8rem; font-weight:600;
           border-bottom:2px solid transparent; margin-bottom:-2px;
           cursor:pointer; color:#64748b; background:none; border-top:none;
           border-left:none; border-right:none; transition:color .15s; }
.tab-btn.active { border-bottom-color:#059669; color:#059669; }
.tab-btn:hover:not(.active) { color:#334155; }
/* ── Tabla ── */
.t2 { width:100%; border-collapse:collapse; font-size:.78rem; }
.t2 thead tr { background:#1e293b; }
.t2 thead th { padding:.55rem .875rem; color:#e2e8f0; font-size:.65rem; font-weight:600;
               letter-spacing:.05em; text-transform:uppercase; white-space:nowrap; }
.t2 tbody tr { border-bottom:1px solid #f1f5f9; transition:background .1s; }
.t2 tbody tr:hover { background:#f8fafc; }
.t2 tbody td { padding:.5rem .875rem; color:#334155; }
.t2 tfoot tr { background:#f1f5f9; }
.t2 tfoot td { padding:.6rem .875rem; font-weight:700; font-size:.75rem; color:#1e293b; }
/* ── Grupo contrato ── */
.contrato-header { background:#f1f5f9; border-left:3px solid #059669;
                   padding:.6rem 1rem; display:flex; align-items:center;
                   justify-content:space-between; flex-wrap:wrap; gap:.5rem;
                   cursor:pointer; user-select:none; }
.contrato-header:hover { background:#e8f0fb; }
.contrato-header.cerrado { border-left-color:#94a3b8; }
.contrato-header.anulado { border-left-color:#ef4444; }
.contrato-animals { display:none; }
.contrato-animals.open { display:block; }
/* ── Badge ── */
.bd { display:inline-block; padding:.1rem .45rem; border-radius:999px;
      font-size:.62rem; font-weight:700; white-space:nowrap; }
.bd-g  { background:#d1fae5; color:#065f46; }
.bd-s  { background:#f1f5f9; color:#475569; }
.bd-r  { background:#fee2e2; color:#991b1b; }
.bd-a  { background:#ede9fe; color:#5b21b6; }
.bd-am { background:#fef9c3; color:#854d0e; }
/* ── Panel animal muerte ── */
.tr-muerte { background:#fff8f8!important; }
/* ── Resumen financiero ── */
.fin-card { background:#fff; border:1px solid #e2e8f0; border-radius:.625rem;
            padding:1.125rem 1.25rem; }
.fin-row  { display:flex; justify-content:space-between; align-items:center;
            padding:.45rem 0; border-bottom:1px solid #f1f5f9; font-size:.82rem; }
.fin-row:last-child { border-bottom:none; }
.fin-total { font-weight:800; font-size:.9rem; padding-top:.6rem; }
@media print {
  aside, header, .no-print { display:none!important; }
  .ml-64 { margin-left:0!important; }
  .contrato-animals { display:block!important; }
}
</style>

<!-- ══ FILTROS ═══════════════════════════════════════════════ -->
<div class="card mb-5 no-print">
  <div class="flex flex-wrap gap-3 items-end">
    <div>
      <label class="form-label">Socio</label>
      <select id="f-socio" class="input-base w-64"><option value="">Cargando...</option></select>
    </div>
    <div>
      <label class="form-label">Estado contrato</label>
      <select id="f-estado" class="input-base w-36">
        <option value="">Todos</option>
        <option value="abierto">Abiertos</option>
        <option value="cerrado">Cerrados</option>
      </select>
    </div>
    <button onclick="generar()" class="btn btn-verde">
      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
          d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10"/>
      </svg>
      Generar
    </button>
    <button onclick="exportarCSV()" id="btn-csv" class="btn btn-outline hidden">
      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
          d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
      </svg>
      Exportar CSV
    </button>
    <button onclick="exportarExcel()" id="btn-xls" class="btn btn-outline hidden"
            style="color:#217346;border-color:#217346">
      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
          d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414A1 1 0 0119 9.414V19a2 2 0 01-2 2z"/>
      </svg>
      Exportar Excel
    </button>
    <button onclick="window.print()" class="btn btn-outline">
      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
          d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
      </svg>
      Imprimir
    </button>
  </div>
</div>

<!-- ══ LOADER ════════════════════════════════════════════════ -->
<div id="loader" class="hidden text-center py-16">
  <div class="inline-block w-10 h-10 border-2 border-slate-200 border-t-esm-500
              rounded-full animate-spin mb-3"></div>
  <p class="text-slate-400 text-sm">Cargando inventario...</p>
</div>

<!-- ══ PLACEHOLDER ════════════════════════════════════════════ -->
<div id="placeholder" class="card text-center py-16">
  <svg class="w-14 h-14 text-slate-200 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.2"
      d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
  </svg>
  <p class="text-slate-400 text-sm">Seleccione un socio y haga clic en <strong>Generar</strong></p>
</div>

<!-- ══ CONTENIDO ══════════════════════════════════════════════ -->
<div id="main" class="hidden space-y-5">

  <!-- Cabecera socio -->
  <div id="socio-head" class="card"></div>

  <!-- KPIs -->
  <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3" id="kpi-row"></div>

  <!-- Tabs -->
  <div class="tab-nav no-print">
    <button class="tab-btn active" onclick="showTab('inventario')" id="tab-inventario">
      📦 Inventario de animales
    </button>
    <button class="tab-btn" onclick="showTab('contratos')" id="tab-contratos">
      📋 Informe de contratos
    </button>
    <button class="tab-btn" onclick="showTab('financiero')" id="tab-financiero">
      💰 Resumen financiero
    </button>
  </div>

  <!-- TAB: Inventario ─────────────────────────────────────── -->
  <div id="panel-inventario">
    <div id="inv-contratos" class="space-y-3"></div>
  </div>

  <!-- TAB: Contratos ──────────────────────────────────────── -->
  <div id="panel-contratos" class="hidden">
    <div class="overflow-hidden rounded-xl border border-slate-200">
      <div class="px-4 py-3 bg-slate-800 flex items-center justify-between">
        <span class="text-sm font-semibold text-white">Contratos del socio</span>
        <span id="badge-con" class="text-xs bg-esm-700 text-white px-2 py-0.5 rounded-full"></span>
      </div>
      <div class="overflow-x-auto">
        <table class="t2">
          <thead><tr>
            <th>Contrato</th>
            <th>Empresa</th>
            <th>Tipo</th>
            <th>Fecha compra</th>
            <th class="text-center">Part.</th>
            <th class="text-right">Animales</th>
            <th class="text-right">Activos</th>
            <th class="text-right">Vendidos</th>
            <th class="text-right">Muertos</th>
            <th class="text-right">Inversión</th>
            <th class="text-right">Ingresos</th>
            <th class="text-right">Costos</th>
            <th class="text-right">Ganancia</th>
            <th class="text-right">Rentab.</th>
            <th class="text-center">Estado</th>
          </tr></thead>
          <tbody id="tb-contratos"></tbody>
          <tfoot id="tf-contratos"></tfoot>
        </table>
      </div>
    </div>
  </div>

  <!-- TAB: Financiero ─────────────────────────────────────── -->
  <div id="panel-financiero" class="hidden">
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4" id="fin-grid"></div>
  </div>

</div><!-- /main -->

<!-- SheetJS para exportar Excel -->
<script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
<script src="<?= APP_URL ?>/js/app.js"></script>
<script>
const API  = APP_URL + '/api/reportes_socio.php';
let INV    = [];   // inventario raw
let CONS   = [];   // contratos summary
let SOCIO  = null;

// ── Init ─────────────────────────────────────────────────────
(async () => {
  const r = await App.get(API, { action: 'lista_socios' });
  if (!r.ok) return;
  const el = document.getElementById('f-socio');
  el.innerHTML = '<option value="">Seleccione un socio...</option>'
    + r.data.data.map(x => `<option value="${x.id}">${x.nombre} — ${x.empresa}</option>`).join('');
  if (r.data.data.length === 1) { el.value = r.data.data[0].id; generar(); }
})();

function showTab(tab) {
  ['inventario','contratos','financiero'].forEach(t => {
    document.getElementById('panel-'+t).classList.add('hidden');
    document.getElementById('tab-'+t).classList.remove('active');
  });
  document.getElementById('panel-'+tab).classList.remove('hidden');
  document.getElementById('tab-'+tab).classList.add('active');
}

// ── Generar ──────────────────────────────────────────────────
async function generar() {
  const idSocio = document.getElementById('f-socio').value;
  if (!idSocio) { App.toast('Seleccione un socio.','warning'); return; }
  const estadoF = document.getElementById('f-estado').value;

  document.getElementById('placeholder').classList.add('hidden');
  document.getElementById('main').classList.add('hidden');
  document.getElementById('loader').classList.remove('hidden');

  const [rInv, rCon, rRes] = await Promise.all([
    App.get(API, { action:'inventario', socio:idSocio }),
    App.get(API, { action:'contratos',  socio:idSocio }),
    App.get(API, { action:'resumen',    socio:idSocio }),
  ]);

  document.getElementById('loader').classList.add('hidden');
  if (!rInv.ok) { App.toast('Error al cargar datos.','error'); return; }

  let inv  = rInv.data.data  || [];
  let cons = rCon.data.data  || [];
  const res = rRes.data.data;
  SOCIO = res.socio;

  // Filtro por estado
  if (estadoF) {
    inv  = inv.filter(a => a.contrato_estado === estadoF);
    cons = cons.filter(c => c.estado === estadoF);
  }

  INV  = inv;
  CONS = cons;

  document.getElementById('main').classList.remove('hidden');
  document.getElementById('btn-csv').classList.remove('hidden');
  document.getElementById('btn-xls').classList.remove('hidden');

  renderHead(res);
  renderKpis(res, inv, cons);
  renderInventario(inv, estadoF);
  renderContratos(cons);
  renderFinanciero(res, cons);
}

// ── Cabecera socio ───────────────────────────────────────────
function renderHead(d) {
  const s = d.socio;
  document.getElementById('socio-head').innerHTML = `
    <div class="flex flex-wrap items-center justify-between gap-4">
      <div class="flex items-center gap-4">
        <div class="w-12 h-12 rounded-full bg-esm-100 flex items-center justify-center
                    text-esm-700 font-bold text-xl flex-shrink-0">${s.nombre.charAt(0)}</div>
        <div>
          <h2 class="font-display text-slate-900 text-xl font-bold">${s.nombre}</h2>
          <p class="text-slate-500 text-sm">${s.empresa}
            ${s.cedula ? ' · C.C. '+s.cedula : ''}
            ${s.telefono ? ' · '+s.telefono : ''}
          </p>
          <p class="text-slate-400 text-xs mt-0.5">Generado <?= date('d/m/Y H:i') ?></p>
        </div>
      </div>
      <div class="flex gap-3 no-print">
        <a href="${APP_URL}/reportes/socios.php" class="btn btn-outline btn-sm">Ver reporte analítico</a>
      </div>
    </div>`;
}

// ── KPIs ─────────────────────────────────────────────────────
function renderKpis(d, inv, cons) {
  const activos  = inv.filter(a => a.animal_estado === 'activo').length;
  const vendidos = inv.filter(a => a.animal_estado === 'vendido').length;
  const muertos  = inv.filter(a => a.animal_estado === 'muerto').length;
  const total    = inv.length;

  const ganTotal = (parseFloat(d.ganancia_total)||0) + (parseFloat(d.ganancia_parcial)||0);
  const inver    = parseFloat(d.inversion_activa)||0;
  const conAb    = cons.filter(c=>c.estado==='abierto').length;
  const conCe    = cons.filter(c=>c.estado==='cerrado').length;

  const kpis = [
    { lbl:'Animales totales',  val:total,    ac:'#3b82f6',  sub:'en todos los contratos' },
    { lbl:'Activos en campo',  val:activos,  ac:'#059669',  sub:`${total>0?(activos/total*100).toFixed(0):0}% del total` },
    { lbl:'Vendidos',          val:vendidos, ac:'#8b5cf6',  sub:'liquidados' },
    { lbl:'Muertes / Bajas',   val:muertos,  ac:'#ef4444',  sub:'salida por muerte' },
    { lbl:'Contratos',         val:cons.length, ac:'#f59e0b',
      sub:`${conAb} abiertos · ${conCe} cerrados` },
    { lbl: ganTotal>=0?'Resultado':'Pérdida',
      val: App.moneda(Math.abs(ganTotal)), ac: ganTotal>=0?'#059669':'#ef4444',
      sub: `Inversión activa: ${App.moneda(inver)}` },
  ];

  document.getElementById('kpi-row').innerHTML = kpis.map(k => `
    <div class="kpi" style="--ac:${k.ac}">
      <p class="kpi-lbl">${k.lbl}</p>
      <p class="kpi-val">${k.val}</p>
      <p class="kpi-sub">${k.sub}</p>
    </div>`).join('');
}

// ── Inventario agrupado por contrato (solo animales ACTIVOS) ──
function renderInventario(inv, estadoF) {
  const hoy = Date.now();
  // Solo animales activos
  const soloActivos = inv.filter(a => a.animal_estado === 'activo');
  // Agrupar por contrato
  const grupos = {};
  soloActivos.forEach(a => {
    const k = a.id_contrato;
    if (!grupos[k]) grupos[k] = {
      id: a.id_contrato, codigo: a.contrato_codigo,
      fecha: a.fecha_compra, estado: a.contrato_estado,
      empresa: a.empresa, tipo: a.tipo_animal,
      pct: a.porcentaje, animales: [],
    };
    grupos[k].animales.push(a);
  });

  const wrap = document.getElementById('inv-contratos');
  if (!Object.keys(grupos).length) {
    wrap.innerHTML = '<div class="card text-center py-8 text-slate-400">Sin animales activos en el período seleccionado</div>';
    return;
  }

  wrap.innerHTML = Object.values(grupos).map(g => {
    const activos  = g.animales.length; // todos son activos en este tab
    const bEst     = g.estado==='abierto'
      ? '<span class="bd bd-g">Abierto</span>'
      : '<span class="bd bd-s">Cerrado</span>';

    // Totales financieros del grupo
    let totGan = 0, totCosto = 0, totVenta = 0;
    g.animales.forEach(a => {
      if (a.costo_total_liquidado) totCosto += parseFloat(a.costo_total_liquidado||0);
      if (a.valor_venta)           totVenta += parseFloat(a.valor_venta||0);
      if (a.ganancia)              totGan   += parseFloat(a.ganancia||0);
    });

    // Totales del grupo (solo activos: costo acumulado estimado)
    let totCostoAcum = 0, totMant = 0;

    const rows = g.animales.map(a => {
      const dias = Math.max(0, Math.round((hoy - new Date(a.fecha_compra).getTime()) / 86400000));
      const mant = Math.round((dias / (365/12)) * 11518);
      const costoBase  = parseFloat(a.costo_compra_animal||0) + parseFloat(a.costo_flete_animal||0);
      const costoAcum  = costoBase + mant;
      totCostoAcum += costoAcum;
      totMant      += mant;

      return `<tr>
        <td class="font-mono font-semibold text-xs">${a.animal_codigo||('<span class="text-slate-300 italic">#'+a.id_animal+'</span>')}</td>
        <td class="text-right">${a.peso_inicial_kg ? App.kg(a.peso_inicial_kg) : '—'}</td>
        <td class="text-right">${a.peso_finca_kg   ? App.kg(a.peso_finca_kg)   : '—'}</td>
        <td class="text-right font-semibold text-slate-700">${dias}</td>
        <td class="text-right">${App.moneda(a.costo_compra_animal)}</td>
        <td class="text-right">${App.moneda(a.costo_flete_animal)}</td>
        <td class="text-right text-amber-600">${App.moneda(mant)}</td>
        <td class="text-right font-medium text-red-600">${App.moneda(costoAcum)}</td>
        <td class="text-right text-slate-500">${a.valor_promedio_kg ? App.moneda(a.valor_promedio_kg)+'/kg' : '—'}</td>
      </tr>`;
    }).join('');

    return `
    <div class="overflow-hidden rounded-xl border border-slate-200">
      <div class="contrato-header ${g.estado}" onclick="toggleContrato(${g.id})">
        <div class="flex items-center gap-3 flex-wrap">
          <a href="${APP_URL}/contratos/detalle.php?id=${g.id}"
             onclick="event.stopPropagation()"
             class="font-mono font-bold text-sm text-esm-700 hover:underline">${g.codigo}</a>
          ${bEst}
          <span class="text-xs text-slate-500">${g.tipo} · ${g.empresa}</span>
          <span class="text-xs text-slate-400">${App.fecha(g.fecha)}</span>
          <span class="bd bd-s text-xs">${parseFloat(g.pct).toFixed(0)}% participación</span>
        </div>
        <div class="flex items-center gap-4 text-xs text-slate-500">
          <span><strong class="text-esm-700">${activos}</strong> activos</span>
          <span class="text-amber-600 font-medium">Costo acum. est. ${App.moneda(totCostoAcum)}</span>
          <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
          </svg>
        </div>
      </div>
      <div class="contrato-animals" id="ca-${g.id}">
        <div class="overflow-x-auto">
          <table class="t2">
            <thead><tr>
              <th>Código</th>
              <th class="text-right">Peso inicial</th>
              <th class="text-right">Peso finca</th>
              <th class="text-right">Días campo</th>
              <th class="text-right">Costo compra</th>
              <th class="text-right">Flete entrada</th>
              <th class="text-right">Manutención *</th>
              <th class="text-right">Costo acum. *</th>
              <th class="text-right">$/kg ingreso</th>
            </tr></thead>
            <tbody>${rows}</tbody>
            <tfoot><tr>
              <td colspan="3">TOTALES — ${g.animales.length} animales activos</td>
              <td></td>
              <td></td>
              <td></td>
              <td class="text-right text-amber-600">${App.moneda(totMant)}</td>
              <td class="text-right font-bold text-red-600">${App.moneda(totCostoAcum)}</td>
              <td></td>
            </tr></tfoot>
          </table>
        </div>
        <p class="text-xs text-slate-400 px-4 py-1.5 bg-slate-50 border-t border-slate-100">
          * Estimado con tarifa vigente — días desde fecha de compra del contrato
        </p>
      </div>
    </div>`;
  }).join('');
}

function toggleContrato(id) {
  const el = document.getElementById('ca-'+id);
  el.classList.toggle('open');
}

// ── Tabla de contratos ────────────────────────────────────────
function renderContratos(cons) {
  document.getElementById('badge-con').textContent = cons.length + ' contrato(s)';

  let tAnim=0, tActivos=0, tVendidos=0, tMuertos=0;
  let tInv=0, tIng=0, tCos=0, tGan=0;

  const rows = cons.length ? cons.map(c => {
    const gan = parseFloat(c.ganancia_socio||0)
              || (parseFloat(c.ventas_acumuladas_socio||0) - parseFloat(c.costos_acumulados_socio||0));
    const ing = parseFloat(c.ventas_acumuladas_socio||0) || parseFloat(c.ingresos_socio||0);
    const cos = parseFloat(c.costos_acumulados_socio||0) || parseFloat(c.costo_total_socio||0);
    const inv = parseFloat(c.inversion_socio||0);
    const ren = cos>0 ? ((gan/cos)*100).toFixed(1)+'%' : '—';

    tAnim    += parseInt(c.animales_socio||0);
    tActivos += parseInt(c.activos_socio||0);
    tVendidos+= parseInt(c.vendidos_socio||0);
    tMuertos += parseInt(c.muertos_socio||0);
    tInv += inv; tIng += ing; tCos += cos; tGan += gan;

    const ganCls = gan>=0?'color:#059669':'color:#ef4444';
    const badge  = c.estado==='abierto'
      ? '<span class="bd bd-g">Abierto</span>'
      : '<span class="bd bd-s">Cerrado</span>';

    return `<tr>
      <td><a href="${APP_URL}/contratos/detalle.php?id=${c.id}"
             class="font-mono text-esm-600 hover:underline font-semibold text-xs">${c.codigo}</a></td>
      <td class="text-slate-500 text-xs">${c.empresa_compra||c.empresa||'—'}</td>
      <td class="text-slate-500 text-xs">${c.tipo_animal}</td>
      <td class="text-slate-500">${App.fecha(c.fecha_compra)}</td>
      <td class="text-center"><span class="bd bd-s">${parseFloat(c.porcentaje).toFixed(0)}%</span></td>
      <td class="text-right font-semibold">${parseInt(c.animales_socio)||0}</td>
      <td class="text-right text-esm-600">${parseInt(c.activos_socio)||0}</td>
      <td class="text-right text-purple-600">${parseInt(c.vendidos_socio)||0}</td>
      <td class="text-right text-red-500">${parseInt(c.muertos_socio)||0}</td>
      <td class="text-right">${App.moneda(inv)}</td>
      <td class="text-right text-esm-700">${App.moneda(ing)}</td>
      <td class="text-right text-red-600">${App.moneda(cos)}</td>
      <td class="text-right font-bold" style="${ganCls}">${App.moneda(gan)}</td>
      <td class="text-right text-slate-500">${ren}</td>
      <td class="text-center">${badge}</td>
    </tr>`;
  }).join('')
  : '<tr><td colspan="15" class="text-center py-6 text-slate-400">Sin contratos</td></tr>';

  document.getElementById('tb-contratos').innerHTML = rows;

  if (cons.length) {
    const ganTC = tGan>=0?'color:#059669':'color:#ef4444';
    const renT  = tCos>0?((tGan/tCos)*100).toFixed(1)+'%':'—';
    document.getElementById('tf-contratos').innerHTML = `<tr>
      <td colspan="5">TOTALES</td>
      <td class="text-right">${tAnim}</td>
      <td class="text-right text-esm-600">${tActivos}</td>
      <td class="text-right text-purple-600">${tVendidos}</td>
      <td class="text-right text-red-500">${tMuertos}</td>
      <td class="text-right">${App.moneda(tInv)}</td>
      <td class="text-right" style="color:#059669">${App.moneda(tIng)}</td>
      <td class="text-right text-red-600">${App.moneda(tCos)}</td>
      <td class="text-right font-bold" style="${ganTC}">${App.moneda(tGan)}</td>
      <td class="text-right">${renT}</td>
      <td></td>
    </tr>`;
  }
}

// ── Resumen financiero por estado ─────────────────────────────
function renderFinanciero(d, cons) {
  const cerrados = cons.filter(c => c.estado === 'cerrado');
  const abiertos = cons.filter(c => c.estado === 'abierto');

  function sumar(arr, campo) {
    return arr.reduce((s,c) => s + (parseFloat(c[campo])||0), 0);
  }

  // Cerrados: datos del cierre oficial
  const ganCe  = parseFloat(d.ganancia_total)||0;
  const cosCe  = parseFloat(d.costo_total)||0;
  const ingCe  = parseFloat(d.ingresos_ventas)||0;

  // Abiertos: datos acumulados de liquidaciones parciales
  const ganAb  = parseFloat(d.ganancia_parcial)||0;
  const cosAb  = parseFloat(d.costos_parciales)||0;
  const ingAb  = parseFloat(d.ingresos_parciales)||0;
  const inver  = parseFloat(d.inversion_activa)||0;

  const ganTotal = ganCe + ganAb;
  const cosTotal = cosCe + cosAb;
  const ingTotal = ingCe + ingAb;
  const rent = cosTotal>0?((ganTotal/cosTotal)*100).toFixed(1)+'%':'—';

  function fila(lbl, val, cls='') {
    return `<div class="fin-row">
      <span class="text-slate-500">${lbl}</span>
      <span class="font-semibold ${cls}">${App.moneda(val)}</span>
    </div>`;
  }

  document.getElementById('fin-grid').innerHTML = `
    <!-- Contratos cerrados -->
    <div class="fin-card">
      <p class="sec-title">Contratos cerrados (${cerrados.length})</p>
      ${fila('Ingresos por ventas', ingCe, 'text-esm-700')}
      ${fila('Costos totales', cosCe, 'text-red-600')}
      <div class="fin-row fin-total">
        <span>Ganancia neta</span>
        <span style="color:${ganCe>=0?'#059669':'#ef4444'}">${App.moneda(ganCe)}</span>
      </div>
      <div class="fin-row fin-total border-t border-slate-100 mt-1">
        <span class="text-slate-400 text-xs">Rentabilidad</span>
        <span class="text-slate-600 text-xs">${cosCe>0?((ganCe/cosCe)*100).toFixed(1)+'%':'—'}</span>
      </div>
    </div>

    <!-- Contratos abiertos (parcial) -->
    <div class="fin-card">
      <p class="sec-title">Contratos abiertos — liquidaciones parciales (${abiertos.length})</p>
      ${fila('Inversión nominal activa', inver, 'text-blue-600')}
      ${fila('Ingresos parciales vendidos', ingAb, 'text-esm-700')}
      ${fila('Costos acumulados', cosAb, 'text-red-600')}
      <div class="fin-row fin-total">
        <span>Resultado parcial</span>
        <span style="color:${ganAb>=0?'#059669':'#ef4444'}">${App.moneda(ganAb)}</span>
      </div>
    </div>

    <!-- Consolidado -->
    <div class="fin-card" style="border:2px solid ${ganTotal>=0?'#059669':'#ef4444'}">
      <p class="sec-title">Posición consolidada</p>
      ${fila('Total ingresos', ingTotal, 'text-esm-700')}
      ${fila('Total costos', cosTotal, 'text-red-600')}
      <div class="fin-row fin-total" style="font-size:.95rem">
        <span>${ganTotal>=0?'Ganancia total':'Pérdida total'}</span>
        <span style="color:${ganTotal>=0?'#059669':'#ef4444'};font-size:1.15rem">${App.moneda(Math.abs(ganTotal))}</span>
      </div>
      <div class="fin-row">
        <span class="text-slate-400 text-xs">Rentabilidad sobre costos</span>
        <span class="font-semibold text-xs" style="color:${ganTotal>=0?'#059669':'#ef4444'}">${rent}</span>
      </div>
      <div class="fin-row">
        <span class="text-slate-400 text-xs">Contratos</span>
        <span class="text-xs text-slate-500">${cerrados.length} cerrados · ${abiertos.length} abiertos</span>
      </div>
    </div>`;
}

// ── Exportar CSV (solo activos) ───────────────────────────────
function exportarCSV() {
  const activos = INV.filter(a => a.animal_estado === 'activo');
  if (!activos.length) { App.toast('Sin animales activos para exportar.','warning'); return; }
  const hoy = Date.now();
  const cab = ['Contrato','Empresa','Tipo','Fecha compra','Estado contrato','Part%',
               'Código animal','Peso inicial kg','Peso finca kg','Días campo',
               'Costo compra','Flete entrada','Manutención est.','Costo acum. est.','$/kg ingreso'];

  const filas = activos.map(a => {
    const dias = Math.max(0, Math.round((hoy - new Date(a.fecha_compra).getTime()) / 86400000));
    const mant = Math.round((dias/(365/12))*11518);
    const costoAcum = parseFloat(a.costo_compra_animal||0) + parseFloat(a.costo_flete_animal||0) + mant;
    return [
      a.contrato_codigo, a.empresa, a.tipo_animal, a.fecha_compra, a.contrato_estado,
      a.porcentaje, a.animal_codigo||'',
      a.peso_inicial_kg||'', a.peso_finca_kg||'', dias,
      parseFloat(a.costo_compra_animal||0).toFixed(0),
      parseFloat(a.costo_flete_animal||0).toFixed(0),
      mant.toFixed(0), costoAcum.toFixed(0),
      a.valor_promedio_kg ? parseFloat(a.valor_promedio_kg).toFixed(0) : '',
    ].map(v => `"${String(v).replace(/"/g,'""')}"`).join(',');
  });

  const csv = '﻿' + [cab.join(','), ...filas].join('\n');
  const a   = document.createElement('a');
  a.href    = URL.createObjectURL(new Blob([csv], {type:'text/csv;charset=utf-8;'}));
  a.download = `inventario_${SOCIO?.nombre.replace(/\s+/g,'_')}_${new Date().toISOString().slice(0,10)}.csv`;
  a.click();
}

// ── Exportar Excel ────────────────────────────────────────────
function exportarExcel() {
  if (!INV.length) { App.toast('Sin datos para exportar.','warning'); return; }
  const hoy = Date.now();

  // Hoja 1: Inventario
  const activos = INV.filter(a => a.animal_estado === 'activo');
  const hdrInv = ['Contrato','Empresa','Tipo','Fecha compra','Estado contrato','Part%',
                  'Código animal','Peso inicial kg','Peso finca kg','Días campo',
                  'Costo compra','Flete entrada','Manutención est.','Costo acum. est.','$/kg ingreso'];
  const dataInv = activos.map(a => {
    const dias = Math.max(0, Math.round((hoy - new Date(a.fecha_compra).getTime()) / 86400000));
    const mant = Math.round((dias/(365/12))*11518);
    const costoAcum = parseFloat(a.costo_compra_animal||0) + parseFloat(a.costo_flete_animal||0) + mant;
    return [a.contrato_codigo, a.empresa, a.tipo_animal, a.fecha_compra, a.contrato_estado,
            parseFloat(a.porcentaje), a.animal_codigo||'',
            parseFloat(a.peso_inicial_kg||0), parseFloat(a.peso_finca_kg||0), dias,
            parseFloat(a.costo_compra_animal||0), parseFloat(a.costo_flete_animal||0),
            parseFloat(mant), parseFloat(costoAcum.toFixed(0)),
            a.valor_promedio_kg ? parseFloat(a.valor_promedio_kg) : ''];
  });

  // Hoja 2: Contratos
  const hdrCon = ['Contrato','Empresa','Tipo','Fecha compra','Estado','Part%',
                  'Animales','Activos','Vendidos','Muertos',
                  'Inversión','Ingresos','Costos','Ganancia','Rentabilidad%'];
  const dataCon = CONS.map(c => {
    const gan = parseFloat(c.ganancia_socio||0)||(parseFloat(c.ventas_acumuladas_socio||0)-parseFloat(c.costos_acumulados_socio||0));
    const ing = parseFloat(c.ventas_acumuladas_socio||0)||parseFloat(c.ingresos_socio||0);
    const cos = parseFloat(c.costos_acumulados_socio||0)||parseFloat(c.costo_total_socio||0);
    const inv = parseFloat(c.inversion_socio||0);
    const ren = cos>0 ? parseFloat(((gan/cos)*100).toFixed(2)) : '';
    return [c.codigo, c.empresa_compra||c.empresa||'', c.tipo_animal, c.fecha_compra, c.estado,
            parseFloat(c.porcentaje), parseInt(c.animales_socio||0), parseInt(c.activos_socio||0),
            parseInt(c.vendidos_socio||0), parseInt(c.muertos_socio||0),
            inv, ing, cos, gan, ren];
  });

  const wb = XLSX.utils.book_new();

  const ws1 = XLSX.utils.aoa_to_sheet([hdrInv, ...dataInv]);
  ws1['!cols'] = hdrInv.map((_,i) => ({ wch: i < 2 ? 20 : i < 6 ? 14 : 12 }));
  XLSX.utils.book_append_sheet(wb, ws1, 'Inventario');

  const ws2 = XLSX.utils.aoa_to_sheet([hdrCon, ...dataCon]);
  ws2['!cols'] = hdrCon.map((_,i) => ({ wch: i < 4 ? 18 : 12 }));
  XLSX.utils.book_append_sheet(wb, ws2, 'Contratos');

  const nombre = `inventario_${(SOCIO?.nombre||'socio').replace(/\s+/g,'_')}_${new Date().toISOString().slice(0,10)}.xlsx`;
  XLSX.writeFile(wb, nombre);
}
</script>

<?php require_once __DIR__ . '/../views/layout/footer.php'; ?>
