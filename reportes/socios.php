<?php
// ============================================================
// reportes/socios.php — Reporte profesional por socio
// ============================================================
require_once __DIR__ . '/../bootstrap.php';
Auth::requirePermission('reportes', 'ver');
$pageTitle = 'Reporte por socio';
$modulo    = 'reporte_socios';
require_once __DIR__ . '/../views/layout/header.php';
?>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2.2.0/dist/chartjs-plugin-datalabels.min.js"></script>

<style>
/* ── Filtros ── */
.filtros { background:#fff; border:1px solid #e2e8f0; border-radius:.75rem;
           padding:1rem 1.25rem; margin-bottom:1.25rem;
           display:flex; flex-wrap:wrap; gap:.75rem; align-items:flex-end; }
/* ── KPI ── */
.kpi { background:#fff; border:1px solid #e2e8f0; border-radius:.625rem;
       padding:1.125rem 1.25rem; position:relative; overflow:hidden; }
.kpi::before { content:''; position:absolute; top:0; left:0; width:3px; height:100%;
               border-radius:2px 0 0 2px; background:var(--ac,#059669); }
.kpi-lbl { font-size:.68rem; text-transform:uppercase; letter-spacing:.07em; color:#64748b; }
.kpi-val { font-family:'Fraunces',Georgia,serif; font-size:1.55rem; font-weight:700;
           line-height:1.15; color:var(--ac,#059669); }
.kpi-sub { font-size:.71rem; color:#94a3b8; margin-top:.2rem; }
.prog    { background:#f1f5f9; border-radius:999px; height:5px; margin-top:.4rem; overflow:hidden; }
.prog-f  { height:100%; border-radius:999px; background:var(--ac,#059669); transition:width .5s; }
/* ── Gráfico card ── */
.gc { background:#fff; border:1px solid #e2e8f0; border-radius:.625rem;
      padding:1.125rem 1.25rem; }
.gc-t { font-size:.72rem; font-weight:700; text-transform:uppercase;
        letter-spacing:.06em; color:#475569; margin-bottom:.875rem; }
/* ── Tabla ── */
.tr th { background:#1e293b; color:#e2e8f0; padding:.6rem .875rem;
         font-size:.68rem; font-weight:600; letter-spacing:.05em; text-transform:uppercase; }
.tr td { padding:.55rem .875rem; font-size:.8rem; color:#334155;
         border-bottom:1px solid #f1f5f9; }
.tr tr:hover td { background:#f8fafc; }
.tr tfoot td { background:#f1f5f9; font-weight:700; font-size:.78rem;
               color:#1e293b; padding:.65rem .875rem; }
/* ── Badge ── */
.b { display:inline-block; padding:.1rem .5rem; border-radius:999px;
     font-size:.67rem; font-weight:600; }
.b-g  { background:#d1fae5; color:#065f46; }
.b-s  { background:#f1f5f9; color:#475569; }
.b-r  { background:#fee2e2; color:#991b1b; }
@media print {
  aside,header,.no-print { display:none!important; }
  .ml-64 { margin-left:0!important; }
}
</style>

<!-- ══ FILTROS ═══════════════════════════════════════════════ -->
<div class="filtros no-print">
  <div>
    <label class="form-label">Socio</label>
    <select id="f-socio" class="input-base w-56"><option value="">Cargando...</option></select>
  </div>
  <div>
    <label class="form-label">Desde</label>
    <input type="date" id="f-desde" class="input-base w-36" value="2025-01-01">
  </div>
  <div>
    <label class="form-label">Hasta</label>
    <input type="date" id="f-hasta" class="input-base w-36" value="<?= date('Y-m-d') ?>">
  </div>
  <div>
    <label class="form-label">Estado</label>
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
    Generar reporte
  </button>
  <button onclick="window.print()" class="btn btn-outline">
    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
        d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
    </svg>
    Imprimir
  </button>
  <button onclick="exportarCSVContratos()" class="btn btn-outline">↓ CSV contratos</button>
  <button onclick="exportarCSVAnimales()" class="btn btn-outline">↓ CSV animales activos</button>
</div>

<!-- ══ LOADER ═══════════════════════════════════════════════ -->
<div id="loader" class="hidden text-center py-16">
  <div class="inline-block w-10 h-10 border-2 border-slate-200 border-t-esm-500
              rounded-full animate-spin mb-3"></div>
  <p class="text-slate-400 text-sm">Generando reporte...</p>
</div>

<!-- ══ PLACEHOLDER ══════════════════════════════════════════ -->
<div id="placeholder" class="card text-center py-16">
  <svg class="w-12 h-12 text-slate-300 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
      d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414A1 1 0 0119 9.414V19a2 2 0 01-2 2z"/>
  </svg>
  <p class="text-slate-400 text-sm">Seleccione un socio y haga clic en <strong>Generar reporte</strong></p>
</div>

<!-- ══ REPORTE ═══════════════════════════════════════════════ -->
<div id="rp" class="hidden space-y-5">

  <!-- Cabecera -->
  <div id="rp-head" class="card"></div>

  <!-- KPIs animales -->
  <div>
    <p class="text-xs font-semibold text-slate-400 uppercase tracking-widest mb-3">📦 Posición en animales</p>
    <div class="grid grid-cols-2 lg:grid-cols-5 gap-3" id="kpi-anim"></div>
  </div>

  <!-- KPIs financieros -->
  <div>
    <p class="text-xs font-semibold text-slate-400 uppercase tracking-widest mb-3">💰 Posición financiera</p>
    <div class="grid grid-cols-2 lg:grid-cols-5 gap-3" id="kpi-fin"></div>
  </div>

  <!-- Gráficos fila 1 -->
  <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
    <div class="gc">
      <p class="gc-t">Estado de animales</p>
      <canvas id="ch1" height="200"></canvas>
      <div id="ch1-leg" class="flex justify-center gap-4 mt-3 text-xs text-slate-500 flex-wrap"></div>
    </div>
    <div class="gc lg:col-span-2">
      <p class="gc-t">Resultado por contrato — ingresos vs costos vs ganancia</p>
      <canvas id="ch2" height="160"></canvas>
    </div>
  </div>

  <!-- Gráficos fila 2 -->
  <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
    <div class="gc lg:col-span-2">
      <p class="gc-t">Evolución de ganancias (por cierre y acumulada)</p>
      <canvas id="ch3" height="160"></canvas>
    </div>
    <div class="gc">
      <p class="gc-t">Desglose de costos</p>
      <canvas id="ch4" height="200"></canvas>
    </div>
  </div>

  <!-- Gráficos fila 3 -->
  <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
    <div class="gc">
      <p class="gc-t">Animales por contrato (activos / vendidos / muertos)</p>
      <canvas id="ch5" height="180"></canvas>
    </div>
    <div class="gc">
      <p class="gc-t">Precio compra vs precio venta ($/kg)</p>
      <canvas id="ch6" height="180"></canvas>
    </div>
  </div>

  <!-- Tabla contratos -->
  <div class="overflow-hidden rounded-xl border border-slate-200">
    <div class="px-4 py-3 bg-slate-800 flex items-center justify-between">
      <span class="text-sm font-semibold text-white">Detalle por contrato</span>
      <span id="badge-con" class="text-xs bg-esm-700 text-white px-2 py-0.5 rounded-full"></span>
    </div>
    <div class="overflow-x-auto">
      <table class="tr w-full">
        <thead><tr>
          <th>Contrato</th><th>Tipo</th><th>Fecha</th>
          <th class="text-center">Part.</th>
          <th class="text-right">Anim.</th>
          <th class="text-right">Activos</th>
          <th class="text-right">Vendidos</th>
          <th class="text-right">Muertos</th>
          <th class="text-right">Inversión</th>
          <th class="text-right">Ingresos</th>
          <th class="text-right">Ganancia</th>
          <th class="text-right">Rentab.</th>
          <th>Estado</th>
        </tr></thead>
        <tbody id="tb-con"></tbody>
        <tfoot id="tf-con"></tfoot>
      </table>
    </div>
  </div>

  <!-- Tabla animales activos -->
  <div class="overflow-hidden rounded-xl border border-slate-200">
    <div class="px-4 py-3 bg-slate-700 flex items-center justify-between">
      <span class="text-sm font-semibold text-white">Animales activos del socio</span>
      <span id="badge-anim" class="text-xs bg-esm-600 text-white px-2 py-0.5 rounded-full"></span>
    </div>
    <div class="overflow-x-auto">
      <table class="tr w-full">
        <thead><tr>
          <th>Código</th><th>Contrato</th><th>Tipo</th>
          <th class="text-right">Peso finca</th>
          <th class="text-right">Costo compra</th>
          <th class="text-right">$/kg ingreso</th>
          <th>Fecha compra</th>
          <th class="text-right">Días en campo</th>
          <th class="text-right">Manten. acum.*</th>
        </tr></thead>
        <tbody id="tb-anim"></tbody>
      </table>
    </div>
    <p class="text-xs text-slate-400 px-4 py-2">* Estimado con tarifa vigente</p>
  </div>

</div>

<script src="<?= APP_URL ?>/js/app.js"></script>
<script>
// ════════════════════════════════════════════════════
// CONFIGURACIÓN
// ════════════════════════════════════════════════════
const API = APP_URL + '/api/reportes_socio.php';
Chart.register(ChartDataLabels);

let CH   = {};    // instancias activas de Chart
let DATOS = {};   // datos del último reporte

const C = {
  verde: '#059669', rojo: '#ef4444', azul: '#3b82f6',
  morado: '#8b5cf6', amber: '#f59e0b', indigo: '#6366f1',
  slate: '#1e293b', gray: '#94a3b8',
};

function mK(v) {
  const n = parseFloat(v)||0;
  if (Math.abs(n) >= 1e6) return '$' + (n/1e6).toFixed(2) + 'M';
  if (Math.abs(n) >= 1e3) return '$' + (n/1e3).toFixed(1) + 'K';
  return '$' + Math.round(n).toLocaleString('es-CO');
}
function dch(id) { if(CH[id]) { CH[id].destroy(); delete CH[id]; } }

// ════════════════════════════════════════════════════
// INIT
// ════════════════════════════════════════════════════
(async function() {
  const r = await App.get(API, { action: 'lista_socios' });
  if (!r.ok) return;
  const s = r.data.data;
  const el = document.getElementById('f-socio');
  el.innerHTML = '<option value="">Seleccione un socio...</option>'
    + s.map(x => `<option value="${x.id}">${x.nombre} — ${x.empresa}</option>`).join('');
  if (s.length === 1) { el.value = s[0].id; generar(); }
})();

// ════════════════════════════════════════════════════
// GENERAR REPORTE
// ════════════════════════════════════════════════════
async function generar() {
  const socio  = document.getElementById('f-socio').value;
  if (!socio) { App.toast('Seleccione un socio.', 'warning'); return; }

  const p = {
    socio,
    desde:  document.getElementById('f-desde').value,
    hasta:  document.getElementById('f-hasta').value,
    estado: document.getElementById('f-estado').value,
  };

  document.getElementById('placeholder').classList.add('hidden');
  document.getElementById('rp').classList.add('hidden');
  document.getElementById('loader').classList.remove('hidden');

  const [r1,r2,r3,r4] = await Promise.all([
    App.get(API, { action:'resumen',   ...p }),
    App.get(API, { action:'contratos', ...p }),
    App.get(API, { action:'animales',  ...p }),
    App.get(API, { action:'ganancias', ...p }),
  ]);

  document.getElementById('loader').classList.add('hidden');
  if (!r1.ok) { App.toast('Error al cargar reporte.','error'); return; }

  DATOS = {
    resumen:   r1.data.data,
    contratos: r2.data.data || [],
    animales:  r3.data.data || [],
    ganancias: r4.data.data || [],
  };

  document.getElementById('rp').classList.remove('hidden');

  renderHead();
  renderKpis();
  renderTablas();

  setTimeout(() => {
    ch1_donutAnimales();
    ch2_barrasContratos();
    ch3_lineaEvolucion();
    ch4_donutCostos();
    ch5_barrasAnimales();
    ch6_compraVenta();
  }, 80);
}

// ════════════════════════════════════════════════════
// HEADER DEL SOCIO
// ════════════════════════════════════════════════════
function renderHead() {
  const d   = DATOS.resumen;
  const s   = d.socio;
  const gan = (parseFloat(d.ganancia_total)||0) + (parseFloat(d.ganancia_parcial)||0);
  const desde = document.getElementById('f-desde').value;
  const hasta  = document.getElementById('f-hasta').value;

  document.getElementById('rp-head').innerHTML = `
    <div class="flex flex-wrap items-center justify-between gap-4">
      <div class="flex items-center gap-4">
        <div class="w-14 h-14 rounded-full bg-esm-100 flex items-center justify-center
                    text-esm-700 font-display font-bold text-2xl flex-shrink-0">
          ${s.nombre.charAt(0).toUpperCase()}
        </div>
        <div>
          <h2 class="font-display text-slate-900 text-2xl font-bold">${s.nombre}</h2>
          <p class="text-slate-500 text-sm">${s.empresa}
            ${s.cedula   ? ' · C.C. '+s.cedula   : ''}
            ${s.telefono ? ' · '+s.telefono : ''}
          </p>
          <p class="text-slate-400 text-xs mt-0.5">
            Período: ${desde ? App.fecha(desde) : 'Inicio'} → ${hasta ? App.fecha(hasta) : 'Hoy'}
            &nbsp;·&nbsp; Generado <?= date('d/m/Y H:i') ?>
          </p>
        </div>
      </div>
      <div class="text-right">
        <p class="text-xs text-slate-400 uppercase tracking-wide">${gan>=0?'Ganancia neta':'Pérdida neta'}</p>
        <p class="font-display font-bold text-3xl ${gan>=0?'text-esm-600':'text-red-600'}">
          ${App.moneda(Math.abs(gan))}
        </p>
        <p class="text-xs text-slate-400 mt-0.5">${d.contratos.contratos_cerrados||0} contrato(s) cerrado(s)</p>
      </div>
    </div>`;
}

// ════════════════════════════════════════════════════
// KPIs
// ════════════════════════════════════════════════════
function renderKpis() {
  const d   = DATOS.resumen;
  const a   = d.animales;
  const total   = parseInt(a.total_animales)||0;
  const activos  = parseInt(a.animales_activos)||0;
  const vendidos = parseInt(a.animales_vendidos)||0;
  const muertos  = parseInt(a.animales_muertos)||0;

  const kpis_anim = [
    { lbl:'Total animales',    val:total,    ac:C.azul,  sub:'todos los contratos' },
    { lbl:'Activos en campo',  val:activos,  ac:C.verde,  sub:total>0?(activos/total*100).toFixed(0)+'% del total':'' },
    { lbl:'Vendidos',          val:vendidos, ac:C.morado, sub:'liquidados' },
    { lbl:'Muertes / bajas',   val:muertos,  ac:C.rojo,   sub:'salida por muerte' },
    { lbl:'Contratos',         val:parseInt(d.contratos.total_contratos)||0,
      ac:C.amber, sub:`${d.contratos.contratos_abiertos||0} abiertos · ${d.contratos.contratos_cerrados||0} cerrados` },
  ];
  document.getElementById('kpi-anim').innerHTML = kpis_anim.map(k => `
    <div class="kpi" style="--ac:${k.ac}">
      <p class="kpi-lbl">${k.lbl}</p>
      <p class="kpi-val">${k.val}</p>
      <p class="kpi-sub">${k.sub}</p>
      ${total>0&&k.lbl!=='Contratos'?`<div class="prog"><div class="prog-f" style="width:${Math.min(100,(k.val/total)*100).toFixed(1)}%"></div></div>`:''}
    </div>`).join('');

  // Combinar cerrados (oficiales) + abiertos parciales
  const ganCerrado  = parseFloat(d.ganancia_total)||0;
  const costoCerrado= parseFloat(d.costo_total)||0;
  const ventaCerrado= parseFloat(d.ingresos_ventas)||0;
  const ganParcial  = parseFloat(d.ganancia_parcial)||0;
  const costoParcial= parseFloat(d.costos_parciales)||0;
  const ventaParcial= parseFloat(d.ingresos_parciales)||0;

  const gan   = ganCerrado + ganParcial;
  const costo = costoCerrado + costoParcial;
  const venta = ventaCerrado + ventaParcial;
  const inver = parseFloat(d.inversion_activa)||0;
  const rent  = costo>0?((gan/costo)*100).toFixed(1)+'%':'—';
  const margen= venta>0?((gan/venta)*100).toFixed(1)+'%':'—';

  const invAnim = parseFloat(d.inversion_animales_activos)||0;

  const kpis_fin = [
    { lbl:'Inversión activa', val:App.moneda(inver), ac:C.azul,
      sub:'valor nominal contratos abiertos' },
    { lbl:'Inv. animales activos', val:App.moneda(invAnim), ac:'#0284c7',
      sub:'costo compra + flete de animales en campo' },
    { lbl:'Ingresos ventas',  val:App.moneda(venta), ac:C.morado,
      sub:`${App.moneda(ventaCerrado)} cerrados · ${App.moneda(ventaParcial)} parciales` },
    { lbl:'Costos totales',   val:App.moneda(costo), ac:C.amber,
      sub:`${App.moneda(costoCerrado)} cerrados · ${App.moneda(costoParcial)} parciales` },
    { lbl: gan>=0?'Resultado consolidado':'Pérdida consolidada',
      val: App.moneda(Math.abs(gan)), ac: gan>=0?C.verde:C.rojo,
      sub:`Rentab. ${rent} · Margen ${margen}` },
  ];
  document.getElementById('kpi-fin').innerHTML = kpis_fin.map(k => `
    <div class="kpi" style="--ac:${k.ac}">
      <p class="kpi-lbl">${k.lbl}</p>
      <p class="kpi-val text-xl">${k.val}</p>
      <p class="kpi-sub">${k.sub}</p>
    </div>`).join('');
}

// ════════════════════════════════════════════════════
// TABLAS
// ════════════════════════════════════════════════════
function renderTablas() {
  const con = DATOS.contratos;
  document.getElementById('badge-con').textContent = con.length + ' contrato(s)';

  let tI=0, tG=0, tInv=0;
  document.getElementById('tb-con').innerHTML = con.length
    ? con.map(c => {
        const gan  = parseFloat(c.ganancia_socio||0)
                   || (parseFloat(c.ventas_acumuladas_socio||0) - parseFloat(c.costos_acumulados_socio||0));
        const ing  = parseFloat(c.ventas_acumuladas_socio||0) || parseFloat(c.ingresos_socio||0);
        const cos  = parseFloat(c.costos_acumulados_socio||0) || parseFloat(c.costo_total_socio||0);
        const ren  = cos>0?((gan/cos)*100).toFixed(1)+'%':'—';
        tI += ing; tG += gan; tInv += parseFloat(c.inversion_socio||0);
        const ganC = gan>=0?'text-esm-600 font-semibold':'text-red-600 font-semibold';
        const badge = c.estado==='abierto'?'<span class="b b-g">Abierto</span>':'<span class="b b-s">Cerrado</span>';
        return `<tr>
          <td><a href="${APP_URL}/contratos/detalle.php?id=${c.id}"
                 class="font-mono text-esm-600 hover:underline font-semibold">${c.codigo}</a></td>
          <td class="text-slate-500">${c.tipo_animal}</td>
          <td class="text-slate-500">${App.fecha(c.fecha_compra)}</td>
          <td class="text-center"><span class="b b-s">${parseFloat(c.porcentaje).toFixed(0)}%</span></td>
          <td class="text-right font-semibold">${parseInt(c.animales_socio)||0}</td>
          <td class="text-right text-esm-600 font-semibold">${parseInt(c.activos_socio)||0}</td>
          <td class="text-right text-purple-600">${parseInt(c.vendidos_socio)||0}</td>
          <td class="text-right text-red-500">${parseInt(c.muertos_socio)||0}</td>
          <td class="text-right">${App.moneda(c.inversion_socio||0)}</td>
          <td class="text-right text-esm-600">${App.moneda(ing)}</td>
          <td class="text-right ${ganC}">${App.moneda(gan)}</td>
          <td class="text-right">${ren}</td>
          <td>${badge}</td>
        </tr>`;
      }).join('')
    : '<tr><td colspan="13" class="text-center py-6 text-slate-400">Sin contratos en el período</td></tr>';

  if (con.length) {
    const ganTC = tG>=0?'color:#059669':'color:#ef4444';
    document.getElementById('tf-con').innerHTML = `
      <tr>
        <td colspan="8">TOTALES</td>
        <td class="text-right">${App.moneda(tInv)}</td>
        <td class="text-right" style="color:#059669">${App.moneda(tI)}</td>
        <td class="text-right" style="${ganTC}">${App.moneda(tG)}</td>
        <td class="text-right">${tI>0?((tG/tI)*100).toFixed(1)+'%':'—'}</td>
        <td></td>
      </tr>`;
  }

  // Animales activos
  const anim = DATOS.animales;
  document.getElementById('badge-anim').textContent = anim.length + ' animal(es)';
  const hoy = new Date();
  document.getElementById('tb-anim').innerHTML = anim.length
    ? anim.map(a => {
        const fcmp = new Date(a.fecha_compra);
        const dias = Math.round((hoy-fcmp)/86400000);
        const mant = App.moneda(Math.round((dias/(365/12))*11518));
        return `<tr>
          <td class="font-mono font-semibold">${a.codigo||'<span class="text-slate-300 italic text-xs">—</span>'}</td>
          <td><a href="${APP_URL}/contratos/detalle.php?id=${a.id_contrato}"
                 class="text-esm-600 hover:underline text-xs font-mono">${a.contrato_codigo}</a></td>
          <td class="text-slate-500">${a.tipo_animal}</td>
          <td class="text-right">${a.peso_finca_kg?App.kg(a.peso_finca_kg):'<span class="text-slate-300">—</span>'}</td>
          <td class="text-right">${App.moneda(a.costo_compra_animal)}</td>
          <td class="text-right font-medium">${a.valor_promedio_kg?App.moneda(a.valor_promedio_kg)+'/kg':'—'}</td>
          <td class="text-slate-500">${App.fecha(a.fecha_compra)}</td>
          <td class="text-right font-bold text-slate-700">${dias}</td>
          <td class="text-right text-amber-600">${mant}</td>
        </tr>`;
      }).join('')
    : '<tr><td colspan="9" class="text-center py-6 text-slate-400">Sin animales activos</td></tr>';
}

// ════════════════════════════════════════════════════
// GRÁFICOS
// ════════════════════════════════════════════════════
const TT = {
  plugins: { tooltip: { titleFont:{family:'DM Sans',size:11}, bodyFont:{family:'DM Sans',size:11} } }
};

// 1. Donut animales
function ch1_donutAnimales() {
  dch('c1');
  const a = DATOS.resumen.animales;
  const vals = [parseInt(a.animales_activos)||0, parseInt(a.animales_vendidos)||0, parseInt(a.animales_muertos)||0];
  const total = vals.reduce((s,x)=>s+x,0);
  const labs  = ['Activos','Vendidos','Muertos'];
  const cols  = [C.verde, C.morado, C.rojo];

  document.getElementById('ch1-leg').innerHTML = labs.map((l,i)=>
    `<div class="flex items-center gap-1.5">
       <span class="w-2.5 h-2.5 rounded-full" style="background:${cols[i]}"></span>
       <span>${l}: <strong>${vals[i]}</strong></span>
     </div>`).join('');

  CH.c1 = new Chart(document.getElementById('ch1'), {
    type:'doughnut',
    data:{ labels:labs, datasets:[{ data:vals, backgroundColor:cols, borderWidth:2, borderColor:'#fff', hoverOffset:6 }] },
    options:{
      cutout:'68%',
      plugins:{
        legend:{ display:false },
        datalabels:{ color:'#fff', font:{weight:'bold',size:12},
          formatter:(v)=>total>0&&v>0?Math.round(v/total*100)+'%':'' },
        tooltip:{ callbacks:{ label:ctx=>` ${ctx.label}: ${ctx.raw} animales` } },
      },
    },
  });
}

// 2. Barras resultado por contrato — cerrados (cierre real) + abiertos (acumulado parcial)
function ch2_barrasContratos() {
  dch('c2');
  // Contratos cerrados: datos del cierre oficial
  const cerrados = DATOS.ganancias.slice(0,10).map(g=>({
    cod : g.codigo,
    ing : parseFloat(g.ingresos_socio    || 0),
    cos : parseFloat(g.costo_total_socio || 0),
    gan : parseFloat(g.ganancia_socio    || 0),
    tipo: 'cerrado',
  }));
  // Contratos abiertos: datos acumulados de liquidaciones parciales
  const abiertos = DATOS.contratos
    .filter(c => c.estado === 'abierto')
    .slice(0, 5)
    .map(c => ({
      cod : c.codigo + '*',
      ing : parseFloat(c.ventas_acumuladas_socio  || 0),
      cos : parseFloat(c.costos_acumulados_socio  || 0),
      gan : parseFloat(c.ventas_acumuladas_socio  || 0)
           - parseFloat(c.costos_acumulados_socio || 0),
      tipo: 'abierto',
    }));

  const datos = [...cerrados, ...abiertos];
  if (!datos.length) {
    document.getElementById('ch2').closest('.gc').innerHTML +=
      '<p class="text-center text-slate-400 text-xs mt-4 pb-2">Sin liquidaciones registradas aún</p>';
    return;
  }

  CH.c2 = new Chart(document.getElementById('ch2'), {
    type: 'bar',
    data: {
      labels: datos.map(d => d.cod),
      datasets: [
        { label:'Ingresos',
          data: datos.map(d => d.ing),
          backgroundColor: datos.map(d => d.tipo==='cerrado'
            ? 'rgba(5,150,105,.75)' : 'rgba(5,150,105,.35)'),
          borderRadius: 3 },
        { label:'Costos',
          data: datos.map(d => d.cos),
          backgroundColor: datos.map(d => d.tipo==='cerrado'
            ? 'rgba(239,68,68,.65)' : 'rgba(239,68,68,.3)'),
          borderRadius: 3 },
        { label:'Ganancia / Resultado',
          data: datos.map(d => d.gan),
          backgroundColor: datos.map(d => d.gan >= 0
            ? 'rgba(99,102,241,.8)' : 'rgba(239,68,68,.85)'),
          borderRadius: 3 },
      ],
    },
    options: {
      ...TT,
      plugins: {
        legend: { position:'top', labels:{ font:{family:'DM Sans',size:10}, color:'#64748b',
          generateLabels: chart => {
            const base = Chart.defaults.plugins.legend.labels.generateLabels(chart);
            base.push({ text:'* abierto (parcial)', fillStyle:'transparent',
              strokeStyle:'#94a3b8', lineWidth:1, fontColor:'#94a3b8',
              pointStyle:'line', hidden:false, index:-1 });
            return base;
          }
        }},
        datalabels: { display: false },
        tooltip: { callbacks:{ label: ctx => ` ${ctx.dataset.label}: ${App.moneda(ctx.raw)}` } },
      },
      scales: {
        x: { ticks:{font:{size:9},color:'#64748b'}, grid:{display:false} },
        y: { ticks:{font:{size:9},color:'#64748b',callback:v=>mK(v)}, grid:{color:'#f1f5f9'} },
      },
    },
  });
}

// 3. Línea evolución
function ch3_lineaEvolucion() {
  dch('c3');
  const g = [...DATOS.ganancias].sort((a,b)=>a.fecha_cierre.localeCompare(b.fecha_cierre));
  if (!g.length) return;
  let acum=0;
  const labs=[], dGan=[], dAcum=[];
  g.forEach(x=>{ acum+=parseFloat(x.ganancia_socio); labs.push(App.fecha(x.fecha_cierre)); dGan.push(parseFloat(x.ganancia_socio)); dAcum.push(acum); });

  CH.c3 = new Chart(document.getElementById('ch3'), {
    type:'line',
    data:{
      labels:labs,
      datasets:[
        { label:'Ganancia por cierre', data:dGan, borderColor:C.verde,
          backgroundColor:'rgba(5,150,105,.08)', fill:true,
          pointBackgroundColor:C.verde, pointRadius:5, tension:.35 },
        { label:'Acumulada', data:dAcum, borderColor:C.indigo,
          backgroundColor:'transparent', borderDash:[5,4],
          pointBackgroundColor:C.indigo, pointRadius:4, tension:.35 },
      ],
    },
    options:{
      ...TT,
      plugins:{
        legend:{ position:'top', labels:{font:{family:'DM Sans',size:10},color:'#64748b'} },
        datalabels:{ display:false },
        tooltip:{ callbacks:{ label:ctx=>` ${ctx.dataset.label}: ${App.moneda(ctx.raw)}` } },
      },
      scales:{
        x:{ ticks:{font:{size:9},color:'#64748b'}, grid:{display:false} },
        y:{ ticks:{font:{size:9},color:'#64748b',callback:v=>mK(v)}, grid:{color:'#f1f5f9'} },
      },
    },
  });
}

// 4. Donut costos — usa ganancias (cerrados) + costos acumulados de abiertos
function ch4_donutCostos() {
  dch('c4');

  // Sumar componentes de contratos CERRADOS (datos exactos del cierre)
  const g  = DATOS.ganancias;
  const sm = k => g.reduce((s,x) => s + (parseFloat(x[k])||0), 0);

  let compra   = sm('costo_compra_socio');
  let fleteEnt = sm('costo_flete_ent_socio');
  let manten   = sm('costo_manten_socio');
  let fleteSal = sm('costo_flete_sal_socio');
  let otros    = sm('costo_otros_socio');

  // Sumar costos de contratos ABIERTOS (parciales, proporcionales)
  // Solo tenemos el total acumulado; lo agregamos como una masa única
  const costoAbiertos = DATOS.contratos
    .filter(c => c.estado === 'abierto')
    .reduce((s,c) => s + (parseFloat(c.costos_acumulados_socio)||0), 0);

  const totalGeneral = compra + fleteEnt + manten + fleteSal + otros + costoAbiertos;
  if (totalGeneral <= 0) {
    document.getElementById('ch4').closest('.gc').innerHTML +=
      '<p class="text-center text-slate-400 text-xs mt-4 pb-2">Sin costos registrados aún</p>';
    return;
  }

  const vals = [compra, fleteEnt, manten, fleteSal, otros, costoAbiertos];
  const labs = ['Compra','Flete entrada','Manutención','Flete salida','Otros (cierre)','Abiertos acum.'];
  const cols = [C.slate,'#3b82f6',C.amber,C.indigo,C.gray,'#94a3b8'];
  // Filtrar valores 0 para no contaminar el donut
  const idx   = vals.map((_,i)=>i).filter(i=>vals[i]>0);
  const vFilt = idx.map(i=>vals[i]);
  const lFilt = idx.map(i=>labs[i]);
  const cFilt = idx.map(i=>cols[i]);
  const total = vFilt.reduce((s,x)=>s+x,0);

  CH.c4 = new Chart(document.getElementById('ch4'), {
    type: 'doughnut',
    data: { labels:lFilt, datasets:[{
      data: vFilt, backgroundColor: cFilt,
      borderWidth:2, borderColor:'#fff', hoverOffset:5
    }]},
    options: {
      cutout: '60%',
      plugins: {
        legend: { position:'bottom', labels:{font:{family:'DM Sans',size:9},
          color:'#64748b', padding:6, boxWidth:9 }},
        datalabels: { color:'#fff', font:{weight:'bold',size:9},
          formatter: v => total>0&&v/total>=0.04 ? Math.round(v/total*100)+'%':'' },
        tooltip: { callbacks:{ label: ctx =>
          ` ${ctx.label}: ${App.moneda(ctx.raw)} (${(ctx.raw/total*100).toFixed(1)}%)` }},
      },
    },
  });
}

// 5. Barras apiladas animales por contrato
function ch5_barrasAnimales() {
  dch('c5');
  const con = DATOS.contratos.slice(0,12);
  if (!con.length) return;
  CH.c5 = new Chart(document.getElementById('ch5'), {
    type:'bar',
    data:{
      labels:con.map(c=>c.codigo),
      datasets:[
        { label:'Activos',  data:con.map(c=>parseInt(c.activos_socio)||0),  backgroundColor:'rgba(5,150,105,.75)', borderRadius:2 },
        { label:'Vendidos', data:con.map(c=>parseInt(c.vendidos_socio)||0), backgroundColor:'rgba(139,92,246,.7)', borderRadius:2 },
        { label:'Muertos',  data:con.map(c=>parseInt(c.muertos_socio)||0),  backgroundColor:'rgba(239,68,68,.6)',  borderRadius:2 },
      ],
    },
    options:{
      ...TT,
      plugins:{
        legend:{ position:'top', labels:{font:{family:'DM Sans',size:10},color:'#64748b'} },
        datalabels:{ anchor:'end',align:'top',color:'#475569',font:{size:8,weight:'600'},
          formatter:v=>v>0?v:'' },
        tooltip:{ callbacks:{ label:ctx=>` ${ctx.dataset.label}: ${ctx.raw}` } },
      },
      scales:{
        x:{ ticks:{font:{size:9},color:'#64748b'}, grid:{display:false} },
        y:{ ticks:{font:{size:9},color:'#64748b',stepSize:1}, grid:{color:'#f1f5f9'} },
      },
    },
  });
}

// 6. Precio compra vs venta — usa ganancias (datos del cierre oficial)
function ch6_compraVenta() {
  dch('c6');
  // Usar DATOS.ganancias: tienen valor_unitario_kg (compra) e ingreso/peso reales del cierre
  const cer = DATOS.ganancias.slice(0,10);
  if (!cer.length) {
    document.getElementById('ch6').closest('.gc').innerHTML +=
      '<p class="text-center text-slate-400 text-xs mt-4 pb-2">Sin contratos cerrados en el período</p>';
    return;
  }

  const precioCompra = cer.map(g => parseFloat(g.valor_unitario_kg || 0));
  const precioVenta  = cer.map(g => {
    // Usar peso_vendido_kg (kg reales de venta) para calcular $/kg correcto
    const ingresos     = parseFloat(g.ingreso_total_ventas || 0);
    const pesoVendido  = parseFloat(g.peso_vendido_kg     || 0);
    return pesoVendido > 0 ? Math.round(ingresos / pesoVendido) : 0;
  });

  CH.c6 = new Chart(document.getElementById('ch6'), {
    type: 'bar',
    data: {
      labels: cer.map(g => g.codigo),
      datasets: [
        { label:'$/kg compra',
          data: precioCompra,
          backgroundColor:'rgba(239,68,68,.65)', borderRadius:3 },
        { label:'$/kg venta prom.',
          data: precioVenta,
          backgroundColor:'rgba(5,150,105,.7)', borderRadius:3 },
      ],
    },
    options: {
      ...TT,
      plugins: {
        legend: { position:'top', labels:{font:{family:'DM Sans',size:10},color:'#64748b'} },
        datalabels: { anchor:'end', align:'top', color:'#475569',
          font:{size:8,weight:'600'}, formatter: v => v>0 ? mK(v) : '' },
        tooltip: { callbacks:{
          label: ctx => {
            const diff = precioVenta[ctx.dataIndex] - precioCompra[ctx.dataIndex];
            const extra = ctx.datasetIndex===1 && diff!==0
              ? ` (${diff>=0?'+':''}${mK(diff)}/kg vs compra)`
              : '';
            return ` ${ctx.dataset.label}: ${App.moneda(ctx.raw)}/kg${extra}`;
          }
        }},
      },
      scales: {
        x: { ticks:{font:{size:9},color:'#64748b'}, grid:{display:false} },
        y: { ticks:{font:{size:9},color:'#64748b',callback:v=>mK(v)}, grid:{color:'#f1f5f9'} },
      },
    },
  });
}

// ════════════════════════════════════════════════════
// EXPORTAR CSV
// ════════════════════════════════════════════════════
function csvDescarga(filas, nombre) {
  const csv = '﻿' + filas.join('\n');
  const el  = document.createElement('a');
  el.href   = URL.createObjectURL(new Blob([csv], {type:'text/csv;charset=utf-8;'}));
  el.download = nombre + '_' + new Date().toISOString().slice(0,10) + '.csv';
  el.click();
}

function exportarCSVContratos() {
  const c = DATOS.contratos||[];
  if (!c.length) { App.toast('Sin datos.','warning'); return; }
  const cab = ['Contrato','Tipo','Fecha','Part%','Animales','Activos','Vendidos','Muertos','Inversión','Ingresos','Costos','Ganancia','Estado'];
  const fil = c.map(x => {
    const gan = parseFloat(x.ganancia_socio||0)||(parseFloat(x.ventas_acumuladas_socio||0)-parseFloat(x.costos_acumulados_socio||0));
    const ing = parseFloat(x.ventas_acumuladas_socio||0)||parseFloat(x.ingresos_socio||0);
    const cos = parseFloat(x.costos_acumulados_socio||0)||parseFloat(x.costo_total_socio||0);
    return [x.codigo,x.tipo_animal,x.fecha_compra,x.porcentaje,
            x.animales_socio,x.activos_socio,x.vendidos_socio,x.muertos_socio,
            parseFloat(x.inversion_socio||0).toFixed(0),
            ing.toFixed(0), cos.toFixed(0), gan.toFixed(0), x.estado]
           .map(v=>`"${String(v).replace(/"/g,'""')}"`).join(',');
  });
  csvDescarga([cab.map(v=>`"${v}"`).join(','), ...fil],
    `contratos_socio_${document.getElementById('f-socio').value}`);
}

function exportarCSVAnimales() {
  const a = DATOS.animales||[];
  if (!a.length) { App.toast('Sin animales activos.','warning'); return; }
  const hoy = Date.now();
  const cab = ['Código','Contrato','Tipo','Empresa','Peso finca kg','Costo compra',
               '$/kg ingreso','Fecha compra','Días campo','Manutención est.'];
  const fil = a.map(x => {
    const dias = Math.max(0, Math.round((hoy - new Date(x.fecha_compra).getTime()) / 86400000));
    const mant = Math.round((dias/(365/12))*11518);
    return [x.codigo||'', x.contrato_codigo, x.tipo_animal, x.empresa,
            parseFloat(x.peso_finca_kg||0).toFixed(2),
            parseFloat(x.costo_compra_animal||0).toFixed(0),
            x.valor_promedio_kg ? parseFloat(x.valor_promedio_kg).toFixed(0) : '',
            x.fecha_compra, dias, mant]
           .map(v=>`"${String(v).replace(/"/g,'""')}"`).join(',');
  });
  csvDescarga([cab.map(v=>`"${v}"`).join(','), ...fil],
    `animales_activos_socio_${document.getElementById('f-socio').value}`);
}
</script>

<?php require_once __DIR__ . '/../views/layout/footer.php'; ?>
