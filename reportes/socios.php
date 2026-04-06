<?php
// ============================================================
// reportes/socios.php — Reporte consolidado por socio
// ============================================================
require_once __DIR__ . '/../bootstrap.php';
Auth::requirePermission('reportes', 'ver');

$pageTitle = 'Reporte por socio';
$modulo    = 'reporte_socios';
require_once __DIR__ . '/../views/layout/header.php';
?>

<style>
.kpi { background:#fff; border:1px solid #e2e8f0; border-radius:.625rem;
       padding:1.25rem 1.5rem; position:relative; overflow:hidden; }
.kpi-accent { position:absolute; top:0; left:0; width:4px; height:100%; border-radius:2px 0 0 2px; }
.kpi-value  { font-family:'Fraunces',Georgia,serif; font-size:1.75rem; font-weight:700; line-height:1; }
.kpi-label  { font-size:.7rem; text-transform:uppercase; letter-spacing:.06em; color:#64748b; margin-top:.25rem; }
.kpi-sub    { font-size:.75rem; color:#94a3b8; margin-top:.2rem; }

.seccion { background:#fff; border:1px solid #e2e8f0; border-radius:.625rem;
           box-shadow:0 1px 4px rgba(15,23,42,.04); overflow:hidden; }
.seccion-header { padding:.875rem 1.25rem; border-bottom:1px solid #f1f5f9;
                  background:#f8fafc; display:flex; align-items:center; justify-content:space-between; }
.seccion-title  { font-size:.8125rem; font-weight:600; color:#334155; text-transform:uppercase;
                  letter-spacing:.05em; }
.seccion-body   { padding:1.25rem; }

.barra-container { background:#f1f5f9; border-radius:999px; height:8px; overflow:hidden; }
.barra-fill      { height:100%; border-radius:999px; transition:width .6s ease; }

@media print {
  aside, header, .no-print, #selector-socio-card { display:none !important; }
  .ml-64 { margin-left:0 !important; }
  body { background:#fff !important; }
  .kpi, .seccion { box-shadow:none !important; break-inside:avoid; }
}
</style>

<!-- Selector de socio -->
<div id="selector-socio-card" class="card mb-6">
  <div class="flex flex-wrap gap-4 items-end">
    <div class="flex-1 min-w-48">
      <label class="form-label">Seleccionar socio</label>
      <select id="sel-socio" class="input-base">
        <option value="">Cargando socios...</option>
      </select>
    </div>
    <button onclick="cargarReporte()" class="btn btn-verde no-print">
      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
          d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10"/>
      </svg>
      Ver reporte
    </button>
    <button onclick="window.print()" class="btn btn-outline no-print">
      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
          d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
      </svg>
      Imprimir
    </button>
  </div>
</div>

<!-- Contenido del reporte (se llena por JS) -->
<div id="reporte-contenido" class="hidden space-y-6">

  <!-- Cabecera del socio -->
  <div id="socio-header" class="card"></div>

  <!-- KPIs de animales -->
  <div>
    <h3 class="text-xs font-semibold text-slate-400 uppercase tracking-widest mb-3">
      📦 Posición en animales
    </h3>
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4" id="kpis-animales"></div>
  </div>

  <!-- KPIs financieros -->
  <div>
    <h3 class="text-xs font-semibold text-slate-400 uppercase tracking-widest mb-3">
      💰 Posición financiera
    </h3>
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4" id="kpis-financiero"></div>
  </div>

  <!-- Contratos activos -->
  <div class="seccion" id="seccion-contratos-activos">
    <div class="seccion-header">
      <span class="seccion-title">Contratos en curso (abiertos)</span>
      <span id="badge-activos" class="text-xs bg-esm-100 text-esm-700 px-2 py-0.5 rounded-full font-semibold"></span>
    </div>
    <div class="overflow-x-auto">
      <table class="tabla-base">
        <thead>
          <tr>
            <th>Contrato</th>
            <th>Tipo</th>
            <th>Empresa</th>
            <th class="text-center">Part.</th>
            <th class="text-right">Animales socio</th>
            <th class="text-right">Activos</th>
            <th class="text-right">Vendidos</th>
            <th class="text-right">Muertos</th>
            <th class="text-right">Inversión socio</th>
            <th class="text-right">Venta acum.</th>
            <th class="text-right">Ganancia acum.</th>
            <th>Estado</th>
          </tr>
        </thead>
        <tbody id="tbody-contratos-activos"></tbody>
      </table>
    </div>
  </div>

  <!-- Tabla de animales activos -->
  <div class="seccion">
    <div class="seccion-header">
      <span class="seccion-title">Animales activos del socio</span>
      <span id="badge-anim-activos" class="text-xs bg-blue-100 text-blue-700 px-2 py-0.5 rounded-full font-semibold"></span>
    </div>
    <div class="overflow-x-auto">
      <table class="tabla-base">
        <thead>
          <tr>
            <th>Código</th>
            <th>Contrato</th>
            <th>Tipo</th>
            <th class="text-right">Peso finca</th>
            <th class="text-right">Costo compra</th>
            <th class="text-right">Flete entrada</th>
            <th class="text-right">Valor/kg</th>
            <th>Fecha compra</th>
          </tr>
        </thead>
        <tbody id="tbody-animales-activos"></tbody>
      </table>
    </div>
  </div>

  <!-- Ganancias por contrato cerrado -->
  <div class="seccion" id="seccion-ganancias">
    <div class="seccion-header">
      <span class="seccion-title">Resultado por contrato cerrado</span>
      <span id="badge-cerrados" class="text-xs bg-slate-200 text-slate-600 px-2 py-0.5 rounded-full font-semibold"></span>
    </div>
    <div id="lista-ganancias" class="p-4 space-y-4"></div>
  </div>

</div>

<!-- Estado vacío -->
<div id="reporte-vacio" class="card text-center py-16">
  <svg class="w-12 h-12 text-slate-300 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
      d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414A1 1 0 0119 9.414V19a2 2 0 01-2 2z"/>
  </svg>
  <p class="text-slate-400 text-sm">Seleccione un socio para ver su reporte</p>
</div>

<script src="<?= APP_URL ?>/js/app.js"></script>
<script>
const API = APP_URL + '/api/reportes_socio.php';

// ── Cargar lista de socios ────────────────────────────────
(async function() {
  const res = await App.get(API, { action: 'lista_socios' });
  if (!res.ok) { App.toast('Error al cargar socios.','error'); return; }

  const socios = res.data.data;
  const sel    = document.getElementById('sel-socio');
  sel.innerHTML = '<option value="">Seleccione un socio...</option>' +
    socios.map(s => `<option value="${s.id}">${s.nombre} — ${s.empresa}</option>`).join('');

  // Si solo hay uno (rol socio), cargar automáticamente
  if (socios.length === 1) {
    sel.value = socios[0].id;
    cargarReporte();
  }
})();

// ── Cargar reporte completo ───────────────────────────────
async function cargarReporte() {
  const idSocio = document.getElementById('sel-socio').value;
  if (!idSocio) { App.toast('Seleccione un socio.','warning'); return; }

  document.getElementById('reporte-vacio').classList.add('hidden');
  document.getElementById('reporte-contenido').classList.remove('hidden');

  // Cargar todo en paralelo
  const [resumen, contratos, animales, ganancias] = await Promise.all([
    App.get(API, { action: 'resumen',   socio: idSocio }),
    App.get(API, { action: 'contratos', socio: idSocio }),
    App.get(API, { action: 'animales',  socio: idSocio }),
    App.get(API, { action: 'ganancias', socio: idSocio }),
  ]);

  if (!resumen.ok) { App.toast('Error al cargar reporte.','error'); return; }

  renderHeader(resumen.data.data);
  renderKpisAnimales(resumen.data.data);
  renderKpisFinanciero(resumen.data.data);
  renderContratosActivos(contratos.data.data || []);
  renderAnimalesActivos(animales.data.data || []);
  renderGanancias(ganancias.data.data || []);
}

// ── Header del socio ──────────────────────────────────────
function renderHeader(d) {
  const s = d.socio;
  document.getElementById('socio-header').innerHTML = `
    <div class="flex items-center gap-4">
      <div class="w-14 h-14 rounded-full bg-esm-100 flex items-center justify-center
                  text-esm-700 font-display font-bold text-2xl flex-shrink-0">
        ${s.nombre.charAt(0).toUpperCase()}
      </div>
      <div>
        <h2 class="font-display text-slate-900 text-2xl font-bold">${s.nombre}</h2>
        <p class="text-slate-500 text-sm">${s.empresa}
          ${s.cedula ? ' · C.C. ' + s.cedula : ''}
          ${s.telefono ? ' · ' + s.telefono : ''}
        </p>
        <p class="text-slate-400 text-xs mt-0.5">
          Reporte generado el <?= date('d/m/Y H:i') ?>
        </p>
      </div>
    </div>`;
}

// ── KPIs animales ─────────────────────────────────────────
function renderKpisAnimales(d) {
  const a = d.animales;
  const total = parseInt(a.total_animales) || 0;
  const activos   = parseInt(a.animales_activos)  || 0;
  const vendidos  = parseInt(a.animales_vendidos) || 0;
  const muertos   = parseInt(a.animales_muertos)  || 0;

  document.getElementById('kpis-animales').innerHTML = [
    { label:'Total animales (su parte)', value: total, color:'#3b82f6', sub:'en todos los contratos' },
    { label:'Animales activos',  value: activos,  color:'#059669', sub:'en campo / engorde' },
    { label:'Animales vendidos', value: vendidos, color:'#8b5cf6', sub:'liquidados y cerrados' },
    { label:'Bajas / muertes',   value: muertos,  color:'#ef4444', sub:'salida por muerte' },
  ].map(k => `
    <div class="kpi">
      <div class="kpi-accent" style="background:${k.color}"></div>
      <p class="kpi-label">${k.label}</p>
      <p class="kpi-value" style="color:${k.color}">${k.value}</p>
      <p class="kpi-sub">${k.sub}</p>
      ${total > 0 ? `
      <div class="barra-container mt-2">
        <div class="barra-fill" style="background:${k.color};width:${Math.min(100,(k.value/total)*100).toFixed(1)}%"></div>
      </div>` : ''}
    </div>`).join('');
}

// ── KPIs financieros ──────────────────────────────────────
function renderKpisFinanciero(d) {
  const gan    = parseFloat(d.ganancia_total)   || 0;
  const costos = parseFloat(d.costo_total)      || 0;
  const ventas = parseFloat(d.ingresos_ventas)  || 0;
  const invers = parseFloat(d.inversion_activa) || 0;
  const rentab = ventas > 0 ? ((gan / costos) * 100).toFixed(1) : '—';

  document.getElementById('kpis-financiero').innerHTML = [
    { label:'Inversión activa (abiertos)',  value: App.moneda(invers), color:'#3b82f6',
      sub:'valor de compra proporcional' },
    { label:'Ingresos por ventas',          value: App.moneda(ventas), color:'#8b5cf6',
      sub:'contratos cerrados' },
    { label:'Costos acumulados',            value: App.moneda(costos), color:'#f59e0b',
      sub:'compra + manten. + fletes' },
    { label: gan >= 0 ? 'Ganancia neta' : 'Pérdida neta',
      value: App.moneda(Math.abs(gan)),
      color: gan >= 0 ? '#059669' : '#ef4444',
      sub: 'Rentabilidad: ' + (rentab !== '—' ? rentab + '%' : '—') },
  ].map(k => `
    <div class="kpi">
      <div class="kpi-accent" style="background:${k.color}"></div>
      <p class="kpi-label">${k.label}</p>
      <p class="kpi-value text-xl" style="color:${k.color}">${k.value}</p>
      <p class="kpi-sub">${k.sub}</p>
    </div>`).join('');
}

// ── Contratos activos ─────────────────────────────────────
function renderContratosActivos(contratos) {
  const abiertos = contratos.filter(c => c.estado === 'abierto');
  document.getElementById('badge-activos').textContent = abiertos.length + ' contrato(s)';

  if (!abiertos.length) {
    document.getElementById('tbody-contratos-activos').innerHTML =
      '<tr><td colspan="12" class="text-center py-6 text-slate-400 text-sm">Sin contratos abiertos</td></tr>';
    return;
  }

  document.getElementById('tbody-contratos-activos').innerHTML = abiertos.map(c => {
    const ganAcum  = parseFloat(c.ventas_acumuladas_socio||0) - parseFloat(c.costos_acumulados_socio||0);
    const ganCls   = ganAcum >= 0 ? 'text-esm-700 font-semibold' : 'text-red-600 font-semibold';
    return `
    <tr>
      <td><a href="${APP_URL}/contratos/detalle.php?id=${c.id}"
             class="font-mono text-esm-600 hover:underline font-medium">${c.codigo}</a></td>
      <td>${c.tipo_animal}</td>
      <td class="text-slate-500">${c.empresa_compra}</td>
      <td class="text-center">
        <span class="text-xs bg-slate-100 text-slate-600 px-2 py-0.5 rounded-full font-semibold">
          ${parseFloat(c.porcentaje).toFixed(0)}%
        </span>
      </td>
      <td class="text-right font-semibold text-slate-800">${parseInt(c.animales_socio)||0}</td>
      <td class="text-right">
        <span class="text-esm-700 font-semibold">${parseInt(c.activos_socio)||0}</span>
      </td>
      <td class="text-right text-slate-500">${parseInt(c.vendidos_socio)||0}</td>
      <td class="text-right text-red-500">${parseInt(c.muertos_socio)||0}</td>
      <td class="text-right">${App.moneda(c.inversion_socio)}</td>
      <td class="text-right text-slate-600">${App.moneda(c.ventas_acumuladas_socio||0)}</td>
      <td class="text-right ${ganCls}">${App.moneda(ganAcum)}</td>
      <td><span class="text-xs badge-green px-2 py-0.5 rounded-full font-semibold">Abierto</span></td>
    </tr>`;
  }).join('');
}

// ── Animales activos ──────────────────────────────────────
function renderAnimalesActivos(animales) {
  document.getElementById('badge-anim-activos').textContent = animales.length + ' animal(es)';

  if (!animales.length) {
    document.getElementById('tbody-animales-activos').innerHTML =
      '<tr><td colspan="8" class="text-center py-6 text-slate-400 text-sm">Sin animales activos</td></tr>';
    return;
  }

  document.getElementById('tbody-animales-activos').innerHTML = animales.map(a => `
    <tr>
      <td class="font-mono font-medium text-slate-700">
        ${a.codigo || '<span class="text-slate-300 italic text-xs">Sin código</span>'}
      </td>
      <td>
        <a href="${APP_URL}/contratos/detalle.php?id=${a.id_contrato}"
           class="text-esm-600 hover:underline text-xs font-mono">${a.contrato_codigo}</a>
      </td>
      <td class="text-slate-500">${a.tipo_animal}</td>
      <td class="text-right">${a.peso_finca_kg ? App.kg(a.peso_finca_kg) : '<span class="text-slate-300">—</span>'}</td>
      <td class="text-right">${App.moneda(a.costo_compra_animal)}</td>
      <td class="text-right">${App.moneda(a.costo_flete_animal)}</td>
      <td class="text-right font-medium text-slate-700">
        ${a.valor_promedio_kg ? App.moneda(a.valor_promedio_kg)+'/kg' : '—'}
      </td>
      <td class="text-slate-500">${App.fecha(a.fecha_compra)}</td>
    </tr>`).join('');
}

// ── Ganancias por contrato cerrado ────────────────────────
function renderGanancias(ganancias) {
  document.getElementById('badge-cerrados').textContent = ganancias.length + ' contrato(s)';
  const contenedor = document.getElementById('lista-ganancias');

  if (!ganancias.length) {
    contenedor.innerHTML = `
      <div class="text-center py-8 text-slate-400">
        <svg class="w-10 h-10 mx-auto mb-2 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
            d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
        </svg>
        <p class="text-sm">No hay contratos cerrados aún</p>
      </div>`;
    return;
  }

  contenedor.innerHTML = ganancias.map(g => {
    const positivo  = parseFloat(g.ganancia_socio) >= 0;
    const rentab    = parseFloat(g.ingresos_socio) > 0
      ? ((parseFloat(g.ganancia_socio) / parseFloat(g.costo_total_socio)) * 100).toFixed(1) + '%'
      : '—';

    const costos = [
      { label:'Compra ganado',    valor: g.costo_compra_socio },
      { label:'Flete entrada',    valor: g.costo_flete_ent_socio },
      { label:'Manutención',      valor: g.costo_manten_socio },
      { label:'Flete salida',     valor: g.costo_flete_sal_socio },
      { label:'Otros gastos',     valor: g.costo_otros_socio },
    ].filter(x => parseFloat(x.valor) > 0);

    return `
    <div class="border border-slate-200 rounded-xl overflow-hidden">
      <!-- Header del contrato -->
      <div class="flex items-center justify-between px-5 py-3 bg-slate-50 border-b border-slate-200">
        <div class="flex items-center gap-3">
          <a href="${APP_URL}/contratos/detalle.php?id=${g.id}"
             class="font-mono font-bold text-slate-700 hover:text-esm-600">${g.codigo}</a>
          <span class="text-xs text-slate-400">${g.tipo_animal} · ${g.empresa}</span>
          <span class="text-xs text-slate-400">Cerrado: ${App.fecha(g.fecha_cierre)}</span>
        </div>
        <div class="flex items-center gap-4">
          <div class="text-right">
            <p class="text-xs text-slate-400">Participación</p>
            <p class="text-sm font-bold text-slate-700">${parseFloat(g.porcentaje).toFixed(0)}%</p>
          </div>
          <div class="text-right">
            <p class="text-xs text-slate-400">Rentabilidad</p>
            <p class="text-sm font-bold ${positivo ? 'text-esm-600' : 'text-red-600'}">${rentab}</p>
          </div>
          <div class="text-right">
            <p class="text-xs text-slate-400">${positivo ? 'Ganancia' : 'Pérdida'}</p>
            <p class="font-display font-bold text-xl ${positivo ? 'text-esm-600' : 'text-red-600'}">
              ${App.moneda(g.ganancia_socio)}
            </p>
          </div>
        </div>
      </div>

      <!-- Cuerpo: animales + costos vs ingresos -->
      <div class="grid grid-cols-1 md:grid-cols-3 gap-0 divide-y md:divide-y-0 md:divide-x divide-slate-100">

        <!-- Animales -->
        <div class="px-5 py-4">
          <p class="text-xs font-semibold text-slate-400 uppercase tracking-wide mb-3">Animales (total lote)</p>
          <div class="space-y-2">
            ${[
              ['Total',   g.total_animales,   '#64748b'],
              ['Vendidos',g.animales_vendidos,'#059669'],
              ['Muertos', g.animales_muertos, '#ef4444'],
            ].map(([l,v,c]) => `
              <div class="flex items-center justify-between">
                <span class="text-xs text-slate-500">${l}</span>
                <span class="text-sm font-semibold" style="color:${c}">${v}</span>
              </div>`).join('')}
            <div class="pt-2 border-t border-slate-100 flex items-center justify-between">
              <span class="text-xs text-slate-500">Le corresponden</span>
              <span class="text-sm font-bold text-blue-600">
                ~${Math.round(parseInt(g.total_animales) * parseFloat(g.porcentaje)/100)} animales
              </span>
            </div>
          </div>
        </div>

        <!-- Desglose costos -->
        <div class="px-5 py-4">
          <p class="text-xs font-semibold text-slate-400 uppercase tracking-wide mb-3">Costos (su parte)</p>
          <div class="space-y-1.5">
            ${costos.map(c => `
              <div class="flex items-center justify-between text-xs">
                <span class="text-slate-500">${c.label}</span>
                <span class="font-medium text-slate-700">${App.moneda(c.valor)}</span>
              </div>`).join('')}
            <div class="flex items-center justify-between text-xs pt-1.5 border-t border-slate-100 font-bold">
              <span class="text-slate-700">Total costos</span>
              <span class="text-red-600">${App.moneda(g.costo_total_socio)}</span>
            </div>
          </div>
        </div>

        <!-- Ingresos vs costos -->
        <div class="px-5 py-4">
          <p class="text-xs font-semibold text-slate-400 uppercase tracking-wide mb-3">Resultado (su parte)</p>
          <div class="space-y-2">
            <div class="flex justify-between items-center">
              <span class="text-xs text-slate-500">Ingresos ventas</span>
              <span class="text-sm font-semibold text-esm-600">${App.moneda(g.ingresos_socio)}</span>
            </div>
            <div class="flex justify-between items-center">
              <span class="text-xs text-slate-500">Total costos</span>
              <span class="text-sm font-semibold text-red-500">${App.moneda(g.costo_total_socio)}</span>
            </div>
            <!-- Barra visual ganancia vs costo -->
            ${parseFloat(g.ingresos_socio) > 0 ? `
            <div class="pt-1">
              <div class="flex text-xs text-slate-400 justify-between mb-1">
                <span>Costos</span><span>Ingresos</span>
              </div>
              <div class="barra-container">
                <div class="barra-fill ${positivo ? 'bg-esm-500' : 'bg-red-500'}"
                     style="width:${Math.min(100,(parseFloat(g.costo_total_socio)/parseFloat(g.ingresos_socio)*100)).toFixed(1)}%">
                </div>
              </div>
            </div>` : ''}
            <div class="flex justify-between items-center pt-2 border-t border-slate-100">
              <span class="text-sm font-bold text-slate-700">${positivo ? 'Ganancia' : 'Pérdida'}</span>
              <span class="font-display font-bold text-lg ${positivo ? 'text-esm-600' : 'text-red-600'}">
                ${App.moneda(g.ganancia_socio)}
              </span>
            </div>
          </div>
        </div>
      </div>
    </div>`;
  }).join('');
}
</script>

<?php require_once __DIR__ . '/../views/layout/footer.php'; ?>
