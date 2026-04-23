<?php
// ============================================================
// reportes/cierres.php — Informe ejecutivo de cierres
// ============================================================
require_once __DIR__ . '/../bootstrap.php';
Auth::requirePermission('reportes', 'ver');

$idContrato = (int)($_GET['contrato'] ?? 0);
$pageTitle  = 'Cierres de contrato';
$modulo     = 'cierres';
require_once __DIR__ . '/../views/layout/header.php';
?>

<!-- ── Título ─────────────────────────────────────────────── -->
<div class="flex items-center justify-between mb-5 no-print">
  <div>
    <h2 class="font-display text-tierra-800 text-xl font-bold">Cierres de contrato</h2>
    <p class="text-tierra-400 text-sm mt-0.5">Informe ejecutivo — resultado final por lote</p>
  </div>
  <div class="flex gap-2" id="btns-acciones" style="display:none!important"></div>
</div>

<!-- ── Filtros ────────────────────────────────────────────── -->
<div class="card mb-5 no-print" id="filtros-card">
  <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3 items-end">
    <div>
      <label class="form-label">Fecha cierre desde</label>
      <input type="date" id="f-desde" class="input-base">
    </div>
    <div>
      <label class="form-label">Fecha cierre hasta</label>
      <input type="date" id="f-hasta" class="input-base">
    </div>
    <div>
      <label class="form-label">Empresa</label>
      <select id="f-empresa" class="input-base">
        <option value="">Todas</option>
      </select>
    </div>
    <div>
      <label class="form-label">Tipo de animal</label>
      <select id="f-tipo" class="input-base">
        <option value="">Todos</option>
      </select>
    </div>
    <div>
      <label class="form-label">Socio</label>
      <select id="f-socio" class="input-base">
        <option value="">Todos</option>
      </select>
    </div>
    <div>
      <label class="form-label">Resultado</label>
      <select id="f-resultado" class="input-base">
        <option value="">Todos</option>
        <option value="positivo">Solo ganancias</option>
        <option value="negativo">Solo pérdidas</option>
      </select>
    </div>
  </div>
  <div class="flex gap-2 mt-3 justify-end">
    <button onclick="limpiarFiltros()" class="btn btn-outline btn-sm">Limpiar</button>
    <button onclick="buscar()" class="btn btn-tierra">Consultar</button>
  </div>
</div>

<!-- ── KPIs ───────────────────────────────────────────────── -->
<div id="kpi-section" class="hidden mb-5">
  <div id="kpi-cards" class="grid grid-cols-2 lg:grid-cols-5 gap-3 mb-3"></div>
  <div class="flex justify-end gap-2 no-print">
    <button onclick="imprimirLista()" class="btn btn-outline btn-sm">🖨️ Imprimir informe</button>
  </div>
  <!-- Enlace oculto para abrir página de impresión -->
  <a id="link-imprimir-lista" href="#" target="_blank" style="display:none"></a>
</div>

<!-- ── Tabla de cierres ───────────────────────────────────── -->
<div id="tabla-section" class="hidden card mb-5">
  <div class="flex items-center justify-between mb-4 no-print">
    <h3 class="font-display text-tierra-800 text-base font-semibold">Resultados</h3>
    <span id="tabla-count" class="text-xs text-tierra-400"></span>
  </div>
  <div class="overflow-x-auto">
    <table class="tabla-base print-tabla" id="tabla-cierres">
      <thead>
        <tr>
          <th>Contrato</th>
          <th>Empresa</th>
          <th>Tipo</th>
          <th class="text-right">Días</th>
          <th class="text-right">Animales</th>
          <th class="text-right">Inversión</th>
          <th class="text-right">Ingresos</th>
          <th class="text-right">Ganancia</th>
          <th class="text-right">ROI</th>
          <th class="no-print">Detalle</th>
        </tr>
      </thead>
      <tbody id="tbody-cierres"></tbody>
      <tfoot id="tfoot-cierres" class="print-tfoot"></tfoot>
    </table>
  </div>
</div>

<!-- ── Panel de detalle ───────────────────────────────────── -->
<div id="panel-detalle" class="hidden space-y-5">

  <!-- Botón volver -->
  <button onclick="volverLista()" class="btn btn-outline btn-sm no-print">← Volver al listado</button>

  <!-- Cabecera del cierre -->
  <div id="det-header" class="card"></div>

  <!-- Costos + Liquidaciones -->
  <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
    <div class="card">
      <h4 class="font-display text-tierra-700 font-semibold mb-3 text-sm">Desglose de costos</h4>
      <div id="det-costos" class="space-y-1 text-sm"></div>
    </div>
    <div class="card">
      <h4 class="font-display text-tierra-700 font-semibold mb-3 text-sm">Liquidaciones realizadas</h4>
      <div id="det-liquidaciones" class="space-y-1 text-sm"></div>
    </div>
  </div>

  <!-- Socios -->
  <div class="card">
    <h4 class="font-display text-tierra-800 font-semibold mb-4">Resultado por socio</h4>
    <div id="det-socios" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3"></div>
  </div>

  <!-- Acciones detalle -->
  <div class="flex justify-between items-center no-print">
    <button onclick="volverLista()" class="btn btn-outline btn-sm">← Volver al listado</button>
    <button onclick="imprimirDetalle()" class="btn btn-outline">🖨️ Imprimir cierre</button>
  </div>

</div>

<script src="<?= APP_URL ?>/js/app.js"></script>
<script>
// ─────────────────────────────────────────────────────────────
// Estado
// ─────────────────────────────────────────────────────────────
let _listaData   = null;
let _detalleData = null;

// ─────────────────────────────────────────────────────────────
// Init
// ─────────────────────────────────────────────────────────────
(async function init() {
  // Cargar catálogos para los filtros
  const [resE, resT, resS] = await Promise.all([
    App.get(APP_URL + '/api/catalogos.php', { recurso: 'empresas' }),
    App.get(APP_URL + '/api/catalogos.php', { recurso: 'tipos' }),
    App.get(APP_URL + '/api/catalogos.php', { recurso: 'socios' }),
  ]);
  if (resE.ok) App.populateSelect('f-empresa', resE.data.data, 'id', r => r.nombre);
  if (resT.ok) App.populateSelect('f-tipo',    resT.data.data, 'id', r => r.nombre);
  if (resS.ok) App.populateSelect('f-socio',   resS.data.data, 'id', r => `${r.nombre} (${r.empresa})`);

  // Si viene contrato por URL, abrir detalle directamente
  <?php if ($idContrato > 0): ?>
  await verDetalle(<?= $idContrato ?>);
  <?php else: ?>
  await buscar();
  <?php endif; ?>
})();

// ─────────────────────────────────────────────────────────────
// Filtros
// ─────────────────────────────────────────────────────────────
function buildParams() {
  return {
    action:      'lista_cierres',
    fecha_desde: document.getElementById('f-desde').value,
    fecha_hasta: document.getElementById('f-hasta').value,
    empresa:     document.getElementById('f-empresa').value,
    tipo_animal: document.getElementById('f-tipo').value,
    socio:       document.getElementById('f-socio').value,
    resultado:   document.getElementById('f-resultado').value,
  };
}

function limpiarFiltros() {
  ['f-desde','f-hasta','f-empresa','f-tipo','f-socio','f-resultado']
    .forEach(id => document.getElementById(id).value = '');
  buscar();
}

// ─────────────────────────────────────────────────────────────
// Buscar (vista lista)
// ─────────────────────────────────────────────────────────────
async function buscar() {
  document.getElementById('panel-detalle').classList.add('hidden');
  document.getElementById('kpi-section').classList.add('hidden');
  document.getElementById('tabla-section').classList.add('hidden');

  const res = await App.get(APP_URL + '/api/reportes.php', buildParams());
  if (!res.ok) { App.toast('Error al cargar los cierres.', 'error'); return; }

  _listaData = res.data.data;
  renderKpis(_listaData.kpis);
  renderTabla(_listaData.lista);

  document.getElementById('kpi-section').classList.remove('hidden');
  document.getElementById('tabla-section').classList.remove('hidden');
}

// ─────────────────────────────────────────────────────────────
// KPI cards
// ─────────────────────────────────────────────────────────────
function renderKpis(k) {
  const gananciaPos = parseFloat(k.total_ganancia || 0) >= 0;
  const roi = parseFloat(k.roi_global || 0);

  document.getElementById('kpi-cards').innerHTML = `
    <div class="stat-card text-center p-4">
      <p class="text-xs text-tierra-500 uppercase tracking-wide mb-1">Cierres</p>
      <p class="font-display text-tierra-900 text-3xl font-bold">${k.total_cierres || 0}</p>
      <p class="text-xs text-tierra-400 mt-1">
        <span class="text-verde-600 font-semibold">${k.con_ganancia||0} ganancia</span>
        · <span class="text-red-500 font-semibold">${k.con_perdida||0} pérdida</span>
      </p>
    </div>
    <div class="stat-card text-center p-4">
      <p class="text-xs text-tierra-500 uppercase tracking-wide mb-1">Inversión total</p>
      <p class="font-display text-tierra-900 text-xl font-bold">${App.moneda(k.total_costos)}</p>
      <p class="text-xs text-tierra-400 mt-1">${Number(k.total_animales||0).toLocaleString('es-CO')} animales</p>
    </div>
    <div class="stat-card text-center p-4">
      <p class="text-xs text-tierra-500 uppercase tracking-wide mb-1">Ingresos totales</p>
      <p class="font-display text-tierra-900 text-xl font-bold">${App.moneda(k.total_ingresos)}</p>
      <p class="text-xs text-tierra-400 mt-1">${Number(k.total_vendidos||0).toLocaleString('es-CO')} vendidos</p>
    </div>
    <div class="stat-card text-center p-4">
      <p class="text-xs text-tierra-500 uppercase tracking-wide mb-1">${gananciaPos ? 'Ganancia neta' : 'Pérdida neta'}</p>
      <p class="font-display text-xl font-bold ${gananciaPos ? 'text-verde-700' : 'text-red-600'}">${App.moneda(k.total_ganancia)}</p>
      <p class="text-xs text-tierra-400 mt-1">${k.dias_promedio||0} días prom. operación</p>
    </div>
    <div class="stat-card text-center p-4">
      <p class="text-xs text-tierra-500 uppercase tracking-wide mb-1">ROI global</p>
      <p class="font-display text-xl font-bold ${roi>=0?'text-verde-700':'text-red-600'}">${roi.toFixed(1)} %</p>
      <p class="text-xs text-tierra-400 mt-1">retorno sobre inversión</p>
    </div>`;
}

// ─────────────────────────────────────────────────────────────
// Tabla de cierres
// ─────────────────────────────────────────────────────────────
function renderTabla(lista) {
  document.getElementById('tabla-count').textContent = `${lista.length} registro(s)`;

  const tbody = document.getElementById('tbody-cierres');
  if (!lista.length) {
    tbody.innerHTML = '<tr><td colspan="10" class="text-center py-8 text-tierra-400">Sin resultados para los filtros seleccionados.</td></tr>';
    document.getElementById('tfoot-cierres').innerHTML = '';
    return;
  }

  tbody.innerHTML = lista.map(r => {
    const pos = parseFloat(r.ganancia_total || 0) >= 0;
    const roi = parseFloat(r.roi_pct || 0);
    return `<tr class="cursor-pointer hover:bg-tierra-50" onclick="verDetalle(${r.id})">
      <td>
        <span class="font-mono text-verde-700 font-semibold">${r.codigo}</span>
        <div class="text-xs text-tierra-400">${App.fecha(r.fecha_compra)} → ${App.fecha(r.fecha_cierre)}</div>
        ${r.socios ? `<div class="text-xs text-tierra-400 mt-0.5">${r.socios}</div>` : ''}
      </td>
      <td class="text-sm">${r.empresa}</td>
      <td class="text-sm">${r.tipo_animal}</td>
      <td class="text-right text-sm">${r.dias_operacion}</td>
      <td class="text-right text-sm">
        <span class="text-tierra-700">${r.total_animales}</span>
        <span class="text-tierra-300 mx-0.5">/</span>
        <span class="text-verde-600">${r.animales_vendidos}</span>
        <span class="text-tierra-300 mx-0.5">/</span>
        <span class="text-red-400">${r.animales_muertos}</span>
      </td>
      <td class="text-right text-sm">${App.moneda(r.costo_total)}</td>
      <td class="text-right text-sm">${App.moneda(r.ingreso_total_ventas)}</td>
      <td class="text-right text-sm font-bold ${pos ? 'text-verde-700' : 'text-red-600'}">${App.moneda(r.ganancia_total)}</td>
      <td class="text-right text-sm font-semibold ${roi>=0?'text-verde-600':'text-red-500'}">${roi.toFixed(1)}%</td>
      <td class="text-center no-print">
        <button class="btn btn-outline btn-xs" onclick="event.stopPropagation();verDetalle(${r.id})">Ver</button>
      </td>
    </tr>`;
  }).join('');

  // Totales en tfoot
  const totales = lista.reduce((acc, r) => {
    acc.dias     += parseFloat(r.dias_operacion || 0);
    acc.animales += parseFloat(r.total_animales || 0);
    acc.inversion += parseFloat(r.costo_total || 0);
    acc.ingresos += parseFloat(r.ingreso_total_ventas || 0);
    acc.ganancia += parseFloat(r.ganancia_total || 0);
    return acc;
  }, { dias: 0, animales: 0, inversion: 0, ingresos: 0, ganancia: 0 });

  const roi = totales.inversion ? (totales.ganancia / totales.inversion * 100) : 0;
  const pos = totales.ganancia >= 0;
  document.getElementById('tfoot-cierres').innerHTML = `
    <tr class="font-bold text-sm bg-tierra-800 text-tierra-100">
      <td colspan="3" class="px-4 py-2">TOTALES (${lista.length} contratos)</td>
      <td class="text-right px-4 py-2">${Math.round(totales.dias / lista.length)} d.p.</td>
      <td class="text-right px-4 py-2">${totales.animales.toLocaleString('es-CO')}</td>
      <td class="text-right px-4 py-2">${App.moneda(totales.inversion)}</td>
      <td class="text-right px-4 py-2">${App.moneda(totales.ingresos)}</td>
      <td class="text-right px-4 py-2 ${pos ? 'text-verde-300' : 'text-red-300'}">${App.moneda(totales.ganancia)}</td>
      <td class="text-right px-4 py-2 ${pos ? 'text-verde-300' : 'text-red-300'}">${roi.toFixed(1)}%</td>
      <td class="no-print"></td>
    </tr>`;
}

// ─────────────────────────────────────────────────────────────
// Detalle individual
// ─────────────────────────────────────────────────────────────
async function verDetalle(idContrato) {
  document.getElementById('kpi-section').classList.add('hidden');
  document.getElementById('tabla-section').classList.add('hidden');
  const panel = document.getElementById('panel-detalle');
  panel.classList.remove('hidden');
  document.getElementById('det-header').innerHTML =
    '<div class="flex justify-center py-4"><div class="loader-spinner"></div></div>';

  const res = await App.get(APP_URL + '/api/reportes.php', { action: 'cierre', id: idContrato });
  if (!res.ok) {
    document.getElementById('det-header').innerHTML =
      `<p class="text-center text-red-500 py-4">${res.data.message || 'Error al cargar.'}</p>`;
    return;
  }

  _detalleData = res.data.data;
  renderDetalle(_detalleData);
  panel.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

function renderDetalle(d) {
  const pos  = parseFloat(d.ganancia_total || 0) >= 0;
  const dias = Math.round((new Date(d.fecha_cierre) - new Date(d.fecha_compra)) / 86400000);
  const roi  = d.costo_total ? (d.ganancia_total / d.costo_total * 100) : 0;

  // ─ Cabecera
  document.getElementById('det-header').innerHTML = `
    <div class="flex flex-wrap gap-4 items-start justify-between">
      <div>
        <h3 class="font-display text-tierra-900 text-2xl font-bold">${d.codigo}</h3>
        <p class="text-tierra-500 text-sm">${d.tipo_animal} · ${d.empresa_compra}</p>
        <p class="text-tierra-400 text-xs mt-0.5">
          Compra: ${App.fecha(d.fecha_compra)} &nbsp;·&nbsp; Cierre: ${App.fecha(d.fecha_cierre)}
          &nbsp;·&nbsp; <strong>${dias} días de operación</strong>
        </p>
      </div>
      <div class="text-right">
        <p class="text-xs text-tierra-400 uppercase tracking-wide mb-1">${pos ? 'Ganancia neta' : 'Pérdida neta'}</p>
        <p class="font-display text-3xl font-bold ${pos ? 'text-verde-700' : 'text-red-600'}">${App.moneda(d.ganancia_total)}</p>
        <p class="text-xs text-tierra-400 mt-0.5">ROI: <strong class="${roi>=0?'text-verde-600':'text-red-500'}">${roi.toFixed(2)}%</strong></p>
      </div>
    </div>
    <div class="grid grid-cols-3 sm:grid-cols-6 gap-4 mt-5 pt-4 border-t border-tierra-100 text-center">
      ${[
        ['Comprados',    d.total_animales,        'text-tierra-800'],
        ['Vendidos',     d.animales_vendidos,      'text-verde-700'],
        ['Muertos',      d.animales_muertos,       'text-red-500'],
        ['Ingresos',     App.moneda(d.ingreso_total_ventas), 'text-verde-700'],
        ['Costo total',  App.moneda(d.costo_total),          'text-red-500'],
        ['Resultado',    App.moneda(d.ganancia_total),        pos?'text-verde-700 font-bold':'text-red-600 font-bold'],
      ].map(([l,v,c]) => `
        <div>
          <p class="text-xs text-tierra-400 uppercase tracking-wide">${l}</p>
          <p class="text-sm ${c} mt-0.5">${v}</p>
        </div>`).join('')}
    </div>`;

  // ─ Costos
  const otros = parseFloat(d.costo_total||0)
    - parseFloat(d.costo_total_compra||0)
    - parseFloat(d.costo_total_flete_entrada||0)
    - parseFloat(d.costo_total_manutencion||0)
    - parseFloat(d.costo_total_flete_salida||0);

  const filas = [
    ['Costo de compra',  d.costo_total_compra,          parseFloat(d.costo_total||1)],
    ['Flete de entrada', d.costo_total_flete_entrada,    parseFloat(d.costo_total||1)],
    ['Manutención',      d.costo_total_manutencion,      parseFloat(d.costo_total||1)],
    ['Flete de salida',  d.costo_total_flete_salida,     parseFloat(d.costo_total||1)],
  ];
  if (Math.abs(otros) >= 1) filas.push(['Otros gastos', otros, parseFloat(d.costo_total||1)]);

  document.getElementById('det-costos').innerHTML =
    filas.map(([l, v, tot]) => {
      const pct = tot ? (parseFloat(v||0) / tot * 100).toFixed(1) : '0.0';
      return `
      <div class="py-1.5 border-b border-tierra-100">
        <div class="flex justify-between text-sm">
          <span class="text-tierra-500">${l}</span>
          <span class="font-medium text-tierra-800">${App.moneda(v)}</span>
        </div>
        <div class="mt-0.5 h-1 bg-tierra-100 rounded-full overflow-hidden">
          <div class="h-full bg-tierra-400 rounded-full" style="width:${pct}%"></div>
        </div>
      </div>`;
    }).join('') +
    `<div class="flex justify-between pt-2 font-bold text-sm">
       <span>COSTO TOTAL</span>
       <span class="text-red-600">${App.moneda(d.costo_total)}</span>
     </div>
     <div class="flex justify-between text-xs text-tierra-400 mt-1">
       <span>Ingreso total ventas</span>
       <span class="text-verde-600 font-semibold">${App.moneda(d.ingreso_total_ventas)}</span>
     </div>`;

  // ─ Liquidaciones
  document.getElementById('det-liquidaciones').innerHTML =
    !d.liquidaciones?.length
      ? '<p class="text-tierra-400 text-sm">Sin liquidaciones registradas.</p>'
      : d.liquidaciones.map((l, i) => `
          <div class="py-1.5 border-b border-tierra-100">
            <div class="flex justify-between items-start">
              <div>
                <span class="text-xs font-semibold text-tierra-600">#${i+1} — ${App.fecha(l.fecha_venta)}</span>
                ${l.numero_factura ? `<span class="text-xs text-tierra-400 ml-1">Fac. ${l.numero_factura}</span>` : ''}
                <div class="text-xs text-tierra-400">${l.cliente || '—'} · ${l.animales} cab. · ${(parseFloat(l.peso_total_kg||0)).toLocaleString('es-CO',{maximumFractionDigits:0})} kg</div>
              </div>
              <span class="text-sm font-medium text-verde-700 whitespace-nowrap">${App.moneda(l.valor_total_venta)}</span>
            </div>
          </div>`).join('') +
        `<div class="flex justify-between pt-2 font-bold text-sm">
           <span>TOTAL VENTAS</span>
           <span class="text-verde-700">${App.moneda(d.ingreso_total_ventas)}</span>
         </div>`;

  // ─ Socios
  document.getElementById('det-socios').innerHTML = (d.detalle_socios||[]).map(s => {
    const sp = parseFloat(s.ganancia||0) >= 0;
    const sRoi = d.costo_total ? (s.ganancia / (d.costo_total * s.porcentaje / 100) * 100) : 0;
    return `
    <div class="p-4 rounded-xl border ${sp ? 'border-verde-200 bg-verde-50' : 'border-red-200 bg-red-50'}">
      <div class="flex items-center gap-2 mb-2">
        <span class="w-9 h-9 rounded-full ${sp ? 'bg-verde-100 text-verde-700' : 'bg-red-100 text-red-600'}
                     flex items-center justify-center font-bold text-sm flex-shrink-0">
          ${s.socio.charAt(0).toUpperCase()}
        </span>
        <div>
          <p class="text-sm font-semibold text-tierra-800">${s.socio}</p>
          <p class="text-xs text-tierra-400">${s.empresa} · ${s.porcentaje}%</p>
        </div>
      </div>
      <p class="font-display font-bold text-xl ${sp ? 'text-verde-700' : 'text-red-600'}">${App.moneda(s.ganancia)}</p>
      <p class="text-xs text-tierra-400 mt-0.5">
        ${sp ? 'Ganancia' : 'Pérdida'}
        · ROI: <span class="${sRoi>=0?'text-verde-600':'text-red-500'} font-semibold">${sRoi.toFixed(1)}%</span>
      </p>
    </div>`;
  }).join('') || '<p class="text-tierra-400 text-sm">Sin socios registrados.</p>';
}

function volverLista() {
  document.getElementById('panel-detalle').classList.add('hidden');
  document.getElementById('kpi-section').classList.remove('hidden');
  document.getElementById('tabla-section').classList.remove('hidden');
}

// ─────────────────────────────────────────────────────────────
// Impresión — abre página dedicada en nueva pestaña
// ─────────────────────────────────────────────────────────────
function imprimirLista() {
  const params = new URLSearchParams({
    fecha_desde: document.getElementById('f-desde').value,
    fecha_hasta: document.getElementById('f-hasta').value,
    empresa:     document.getElementById('f-empresa').value,
    tipo_animal: document.getElementById('f-tipo').value,
    socio:       document.getElementById('f-socio').value,
    resultado:   document.getElementById('f-resultado').value,
  });
  window.open(APP_URL + '/reportes/imprimir_lista_cierres.php?' + params.toString(), '_blank');
}

function imprimirDetalle() {
  if (!_detalleData) return;
  window.open(APP_URL + '/reportes/imprimir_cierre.php?id=' + _detalleData.id_contrato, '_blank');
}
</script>

<style>
@media print {
  /* La impresión se maneja en páginas dedicadas (imprimir_cierre.php / imprimir_lista_cierres.php) */
  body { display: none; }
}
</style>

<?php require_once __DIR__ . '/../views/layout/footer.php'; ?>
