<?php
// ============================================================
// reportes/liquidaciones.php — Informe de liquidaciones
// ============================================================
require_once __DIR__ . '/../bootstrap.php';
Auth::requirePermission('reportes', 'ver');

$pageTitle = 'Informe de liquidaciones';
$modulo    = 'reporte_liquidaciones';
require_once __DIR__ . '/../views/layout/header.php';
?>

<!-- ══ ENCABEZADO ════════════════════════════════════════════ -->
<div class="flex flex-wrap items-start justify-between gap-3 mb-5">
  <div>
    <h2 class="font-display text-slate-800 text-xl font-bold">Informe de liquidaciones</h2>
    <p class="text-slate-400 text-sm mt-0.5">Ventas y resultados por empresa facturadora</p>
  </div>
  <div class="flex gap-2">
    <button onclick="exportarCSV()" class="btn btn-outline btn-sm" id="btn-csv">
      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
          d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
      </svg>
      Exportar CSV
    </button>
    <button onclick="window.print()" class="btn btn-outline btn-sm">
      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
          d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
      </svg>
      Imprimir
    </button>
  </div>
</div>

<!-- ══ FILTROS ════════════════════════════════════════════════ -->
<div class="card mb-5">
  <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3 items-end">
    <div>
      <label class="form-label">Empresa factura</label>
      <select id="f-empresa" class="input-base">
        <option value="">Todas</option>
      </select>
    </div>
    <div>
      <label class="form-label">Cliente</label>
      <select id="f-cliente" class="input-base">
        <option value="">Todos</option>
      </select>
    </div>
    <div>
      <label class="form-label">Contrato</label>
      <select id="f-contrato" class="input-base">
        <option value="">Todos</option>
      </select>
    </div>
    <div>
      <label class="form-label">Estado</label>
      <select id="f-estado" class="input-base">
        <option value="">Todos</option>
        <option value="confirmada">Confirmada</option>
        <option value="borrador">Borrador</option>
        <option value="anulada">Anulada</option>
      </select>
    </div>
    <div>
      <label class="form-label">Desde</label>
      <input type="date" id="f-desde" class="input-base">
    </div>
    <div>
      <label class="form-label">Hasta</label>
      <input type="date" id="f-hasta" class="input-base">
    </div>
  </div>
  <div class="flex gap-2 mt-3">
    <button onclick="cargarReporte()" class="btn btn-verde btn-sm">
      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
          d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2a1 1 0 01-.293.707L13 13.414V19a1 1 0 01-.553.894l-4 2A1 1 0 017 21v-7.586L3.293 6.707A1 1 0 013 6V4z"/>
      </svg>
      Aplicar filtros
    </button>
    <button onclick="limpiarFiltros()" class="btn btn-outline btn-sm">Limpiar</button>
  </div>
</div>

<!-- ══ KPI CARDS ══════════════════════════════════════════════ -->
<div id="kpi-section" class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3 mb-5">
  <?php
  $kpis = [
    ['id'=>'kpi-count',    'label'=>'Liquidaciones',    'color'=>'#059669'],
    ['id'=>'kpi-animales', 'label'=>'Animales',         'color'=>'#334155'],
    ['id'=>'kpi-kg',       'label'=>'Peso total (kg)',  'color'=>'#0284c7'],
    ['id'=>'kpi-ingresos', 'label'=>'Ingresos venta',   'color'=>'#059669'],
    ['id'=>'kpi-costos',   'label'=>'Costos totales',   'color'=>'#dc2626'],
    ['id'=>'kpi-ganancia', 'label'=>'Ganancia neta',    'color'=>'#7c3aed'],
  ];
  foreach ($kpis as $k): ?>
  <div class="stat-card" style="--ac:<?= $k['color'] ?>">
    <style>.stat-card { --ac: #059669; } .stat-card::before { background: var(--ac); }</style>
    <p class="text-xs text-slate-400 uppercase tracking-wide mb-1"><?= $k['label'] ?></p>
    <p id="<?= $k['id'] ?>" class="font-display font-bold text-slate-800 text-lg leading-tight">—</p>
  </div>
  <?php endforeach; ?>
</div>

<!-- ══ TABLA PRINCIPAL ════════════════════════════════════════ -->
<div class="card overflow-hidden p-0 mb-5" id="tabla-wrapper">
  <div class="px-4 py-3 border-b border-slate-100 flex items-center justify-between bg-slate-50">
    <span class="text-sm font-semibold text-slate-700" id="lbl-total-rows">Cargando...</span>
    <div id="loader-inline" class="w-4 h-4 border-2 border-slate-200 border-t-esm-500 rounded-full animate-spin"></div>
  </div>
  <div class="tabla-scroll">
    <table class="tabla-base" id="tabla-liq">
      <thead>
        <tr>
          <th>Factura</th>
          <th>Empresa factura</th>
          <th>Cliente</th>
          <th>Contrato</th>
          <th>Fecha venta</th>
          <th class="text-right">Animales</th>
          <th class="text-right">Peso (kg)</th>
          <th class="text-right">Ingresos</th>
          <th class="text-right">Costos</th>
          <th class="text-right">Ganancia</th>
          <th>Estado</th>
          <th></th>
        </tr>
      </thead>
      <tbody id="tbody-liq">
        <tr><td colspan="12" class="text-center py-10 text-slate-400">Cargando...</td></tr>
      </tbody>
      <tfoot id="tfoot-liq" class="hidden">
        <tr>
          <td colspan="5" class="text-right text-xs text-slate-500 uppercase tracking-wide">Totales</td>
          <td id="ft-animales" class="text-right"></td>
          <td id="ft-kg"       class="text-right"></td>
          <td id="ft-ingresos" class="text-right"></td>
          <td id="ft-costos"   class="text-right"></td>
          <td id="ft-ganancia" class="text-right"></td>
          <td colspan="2"></td>
        </tr>
      </tfoot>
    </table>
  </div>
</div>

<!-- ══ PANEL DETALLE (overlay lateral) ═══════════════════════ -->
<div id="overlay-detalle"
     onclick="cerrarDetalle()"
     class="fixed inset-0 bg-slate-900 bg-opacity-40 z-40 hidden"
     style="backdrop-filter:blur(2px)"></div>

<div id="panel-detalle"
     class="fixed top-0 right-0 h-full w-full sm:w-[680px] lg:w-[760px]
            bg-white z-50 shadow-2xl flex flex-col
            translate-x-full transition-transform duration-300 ease-in-out overflow-hidden">

  <!-- Header del panel -->
  <div class="flex items-center justify-between px-5 py-4 border-b border-slate-200 bg-slate-50 flex-shrink-0">
    <div>
      <h3 class="font-display text-slate-800 font-bold text-lg" id="det-titulo">Detalle de liquidación</h3>
      <p class="text-slate-400 text-xs mt-0.5" id="det-subtitulo"></p>
    </div>
    <button onclick="cerrarDetalle()"
            class="text-slate-400 hover:text-slate-700 p-2 rounded-lg hover:bg-slate-100 transition-colors">
      <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
      </svg>
    </button>
  </div>

  <!-- Tabs -->
  <div class="flex border-b border-slate-200 flex-shrink-0 px-5">
    <button onclick="showDetTab('resumen')" id="det-tab-resumen"
            class="det-tab px-3 py-2.5 text-sm font-medium border-b-2 border-esm-500 text-esm-700">
      Resumen
    </button>
    <button onclick="showDetTab('animales')" id="det-tab-animales"
            class="det-tab px-3 py-2.5 text-sm font-medium border-b-2 border-transparent text-slate-500 hover:text-slate-700">
      Animales
    </button>
    <button onclick="showDetTab('socios')" id="det-tab-socios"
            class="det-tab px-3 py-2.5 text-sm font-medium border-b-2 border-transparent text-slate-500 hover:text-slate-700">
      Socios
    </button>
  </div>

  <!-- Contenido scrolleable -->
  <div class="flex-1 overflow-y-auto">

    <!-- Loader panel -->
    <div id="det-loader" class="flex justify-center items-center py-20">
      <div class="w-6 h-6 border-2 border-slate-200 border-t-esm-500 rounded-full animate-spin"></div>
    </div>

    <!-- ── TAB: RESUMEN ── -->
    <div id="det-panel-resumen" class="hidden p-5 space-y-4">

      <!-- Info de la liquidación -->
      <div class="grid grid-cols-2 gap-3">
        <div class="bg-slate-50 rounded-lg p-3 border border-slate-100">
          <p class="text-xs text-slate-400 uppercase tracking-wide mb-1">Empresa factura</p>
          <p id="det-empresa" class="text-sm font-semibold text-slate-800">—</p>
        </div>
        <div class="bg-slate-50 rounded-lg p-3 border border-slate-100">
          <p class="text-xs text-slate-400 uppercase tracking-wide mb-1">Cliente</p>
          <p id="det-cliente" class="text-sm font-semibold text-slate-800">—</p>
        </div>
        <div class="bg-slate-50 rounded-lg p-3 border border-slate-100">
          <p class="text-xs text-slate-400 uppercase tracking-wide mb-1">Número de factura</p>
          <p id="det-factura" class="text-sm font-mono font-semibold text-slate-800">—</p>
        </div>
        <div class="bg-slate-50 rounded-lg p-3 border border-slate-100">
          <p class="text-xs text-slate-400 uppercase tracking-wide mb-1">Fecha de venta</p>
          <p id="det-fecha" class="text-sm font-semibold text-slate-800">—</p>
        </div>
        <div class="bg-slate-50 rounded-lg p-3 border border-slate-100">
          <p class="text-xs text-slate-400 uppercase tracking-wide mb-1">Contrato</p>
          <p id="det-contrato" class="text-sm font-mono font-semibold text-slate-800">—</p>
        </div>
        <div class="bg-slate-50 rounded-lg p-3 border border-slate-100">
          <p class="text-xs text-slate-400 uppercase tracking-wide mb-1">Empresa compra</p>
          <p id="det-empresa-compra" class="text-sm font-semibold text-slate-800">—</p>
        </div>
      </div>

      <!-- Métricas rápidas -->
      <div class="grid grid-cols-3 gap-3">
        <div class="text-center p-3 bg-esm-50 rounded-lg border border-esm-100">
          <p class="text-xs text-esm-600 uppercase tracking-wide">Animales vendidos</p>
          <p id="det-vendidos" class="font-display font-bold text-2xl text-esm-700 mt-0.5">—</p>
        </div>
        <div class="text-center p-3 bg-red-50 rounded-lg border border-red-100">
          <p class="text-xs text-red-500 uppercase tracking-wide">Muertes</p>
          <p id="det-muertos" class="font-display font-bold text-2xl text-red-600 mt-0.5">—</p>
        </div>
        <div class="text-center p-3 bg-slate-50 rounded-lg border border-slate-100">
          <p class="text-xs text-slate-500 uppercase tracking-wide">Días promedio</p>
          <p id="det-dias" class="font-display font-bold text-2xl text-slate-700 mt-0.5">—</p>
        </div>
      </div>

      <!-- Desglose financiero -->
      <div>
        <h4 class="font-display text-slate-700 font-semibold text-sm mb-2">Desglose de costos</h4>
        <div class="border border-slate-200 rounded-lg overflow-hidden">
          <table class="w-full text-sm">
            <tbody id="det-costos-tabla" class="divide-y divide-slate-100"></tbody>
            <tfoot>
              <tr class="bg-slate-50 border-t-2 border-slate-200">
                <td class="px-4 py-2.5 font-bold text-slate-700">COSTO TOTAL</td>
                <td id="det-costo-total" class="px-4 py-2.5 font-bold text-red-600 text-right"></td>
              </tr>
            </tfoot>
          </table>
        </div>
      </div>

      <!-- Ingresos vs Costos -->
      <div class="grid grid-cols-2 gap-3">
        <div class="p-4 rounded-xl border border-esm-200 bg-esm-50">
          <p class="text-xs text-esm-600 uppercase tracking-wide mb-1">Total ingresos venta</p>
          <p id="det-ingresos" class="font-display font-bold text-2xl text-esm-700">—</p>
        </div>
        <div id="det-ganancia-card" class="p-4 rounded-xl border">
          <p id="det-ganancia-label" class="text-xs uppercase tracking-wide mb-1">Ganancia neta</p>
          <p id="det-ganancia-val" class="font-display font-bold text-2xl">—</p>
        </div>
      </div>

      <!-- Observación -->
      <div id="det-obs-wrap" class="hidden">
        <h4 class="font-display text-slate-700 font-semibold text-sm mb-1">Observación</h4>
        <p id="det-obs" class="text-sm text-slate-600 bg-amber-50 border border-amber-100 rounded-lg p-3"></p>
      </div>
    </div>

    <!-- ── TAB: ANIMALES ── -->
    <div id="det-panel-animales" class="hidden">
      <div class="tabla-scroll">
        <table class="tabla-base text-xs">
          <thead>
            <tr>
              <th>Código</th>
              <th>Tipo</th>
              <th class="text-right">Días mant.</th>
              <th class="text-right">Costo compra</th>
              <th class="text-right">Flete ent.</th>
              <th class="text-right">Manutención</th>
              <th class="text-right">Flete sal.</th>
              <th class="text-right">Otros</th>
              <th class="text-right">Costo total</th>
              <th class="text-right">Peso sal. (kg)</th>
              <th class="text-right">Valor venta</th>
              <th class="text-right">Ganancia</th>
            </tr>
          </thead>
          <tbody id="det-tbody-animales"></tbody>
          <tfoot id="det-tfoot-animales" class="hidden">
            <tr>
              <td colspan="3" class="text-right text-xs text-slate-500 uppercase tracking-wide px-4 py-2">Totales</td>
              <td id="dft-compra"    class="text-right px-3 py-2"></td>
              <td id="dft-flete-ent" class="text-right px-3 py-2"></td>
              <td id="dft-mant"      class="text-right px-3 py-2"></td>
              <td id="dft-flete-sal" class="text-right px-3 py-2"></td>
              <td id="dft-otros"     class="text-right px-3 py-2"></td>
              <td id="dft-costo"     class="text-right px-3 py-2 text-red-600 font-bold"></td>
              <td id="dft-peso"      class="text-right px-3 py-2"></td>
              <td id="dft-venta"     class="text-right px-3 py-2 text-esm-700 font-bold"></td>
              <td id="dft-ganancia"  class="text-right px-3 py-2 font-bold"></td>
            </tr>
          </tfoot>
        </table>
      </div>
    </div>

    <!-- ── TAB: SOCIOS ── -->
    <div id="det-panel-socios" class="hidden p-5">
      <p class="text-xs text-slate-400 mb-3">
        Distribución de ganancia de esta liquidación según porcentaje de participación por contrato.
      </p>
      <div id="det-socios-grid" class="grid grid-cols-1 sm:grid-cols-2 gap-3"></div>
    </div>

  </div><!-- /scrolleable -->

  <!-- Footer del panel -->
  <div class="flex gap-2 justify-between px-5 py-3 border-t border-slate-100 bg-slate-50 flex-shrink-0">
    <a id="det-link-contrato" href="#" target="_blank"
       class="btn btn-outline btn-sm">Ver contrato</a>
    <div class="flex gap-2">
      <a id="det-link-imprimir" href="#" target="_blank"
         class="btn btn-verde btn-sm">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
            d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
        </svg>
        Imprimir liquidación
      </a>
      <button onclick="cerrarDetalle()" class="btn btn-outline btn-sm">Cerrar</button>
    </div>
  </div>
</div>

<script src="<?= APP_URL ?>/js/app.js"></script>
<script>
const API_REP = APP_URL + '/api/reportes.php';
const API_CAT = APP_URL + '/api/catalogos.php';

let datosActuales = [];

// ── Inicialización ──────────────────────────────────────────
(async () => {
  const [empresas, clientes, contratos] = await Promise.all([
    App.get(API_CAT, { recurso: 'empresas' }),
    App.get(API_CAT, { recurso: 'clientes' }),
    App.get(APP_URL + '/api/contratos.php'),
  ]);

  if (empresas.ok) App.populateSelect('f-empresa', empresas.data.data, 'id', 'nombre', 'Todas');
  if (clientes.ok) App.populateSelect('f-cliente', clientes.data.data, 'id', 'nombre', 'Todos');
  if (contratos.ok) {
    App.populateSelect('f-contrato', contratos.data.data, 'id',
      r => `${r.codigo} — ${r.empresa_compra}`, 'Todos');
  }

  // Fecha por defecto: año en curso
  const hoy = new Date();
  document.getElementById('f-desde').value = hoy.getFullYear() + '-01-01';
  document.getElementById('f-hasta').value = hoy.toISOString().slice(0,10);

  await cargarReporte();
})();

// ── Cargar reporte ──────────────────────────────────────────
async function cargarReporte() {
  const loader = document.getElementById('loader-inline');
  loader.classList.remove('hidden');
  document.getElementById('lbl-total-rows').textContent = 'Cargando...';

  const params = {
    action:          'liquidaciones',
    empresa_factura: document.getElementById('f-empresa').value,
    cliente:         document.getElementById('f-cliente').value,
    contrato:        document.getElementById('f-contrato').value,
    estado:          document.getElementById('f-estado').value,
    fecha_desde:     document.getElementById('f-desde').value,
    fecha_hasta:     document.getElementById('f-hasta').value,
  };

  const res = await App.get(API_REP, params);
  loader.classList.add('hidden');

  if (!res.ok) { App.toast(res.data.message, 'error'); return; }

  const { kpis, liquidaciones } = res.data.data;
  datosActuales = liquidaciones;

  renderKpis(kpis);
  renderTabla(liquidaciones, kpis);
}

// ── KPI Cards ────────────────────────────────────────────────
function renderKpis(k) {
  const ganancia   = parseFloat(k.total_ganancia);
  const esGanancia = ganancia >= 0;

  document.getElementById('kpi-count').textContent    = Number(k.total_liquidaciones).toLocaleString('es-CO');
  document.getElementById('kpi-animales').textContent = Number(k.total_animales).toLocaleString('es-CO');
  document.getElementById('kpi-kg').textContent       = Number(k.total_kg).toLocaleString('es-CO', {minimumFractionDigits:1}) + ' kg';
  document.getElementById('kpi-ingresos').textContent = App.moneda(k.total_ingresos);
  document.getElementById('kpi-costos').textContent   = App.moneda(k.total_costos);

  const elGan = document.getElementById('kpi-ganancia');
  elGan.textContent  = App.moneda(ganancia);
  elGan.style.color  = esGanancia ? '#059669' : '#dc2626';
}

// ── Tabla principal ──────────────────────────────────────────
function renderTabla(rows, kpis) {
  const tbody  = document.getElementById('tbody-liq');
  const tfoot  = document.getElementById('tfoot-liq');
  const lbl    = document.getElementById('lbl-total-rows');

  lbl.textContent = rows.length + ' liquidación' + (rows.length !== 1 ? 'es' : '');

  if (rows.length === 0) {
    tbody.innerHTML = `<tr><td colspan="12" class="text-center py-12 text-slate-400">
      Sin resultados para los filtros aplicados</td></tr>`;
    tfoot.classList.add('hidden');
    return;
  }

  tbody.innerHTML = rows.map(r => {
    const gan      = parseFloat(r.total_ganancia || 0);
    const esGan    = gan >= 0;
    const estadoCls = {
      confirmada: 'badge-green',
      borrador:   'badge-yellow',
      anulada:    'badge-red',
    }[r.estado] || 'badge-gray';

    return `<tr class="border-b border-slate-100 hover:bg-slate-50 transition-colors cursor-pointer"
                onclick="verDetalle(${r.id})">
      <td class="px-3 py-2.5">
        <span class="font-mono text-slate-700 text-xs">${r.numero_factura || '<span class="text-slate-300 italic">—</span>'}</span>
      </td>
      <td class="px-3 py-2.5 text-sm text-slate-700">${r.empresa_factura || '—'}</td>
      <td class="px-3 py-2.5 text-sm text-slate-700">${r.cliente || '—'}</td>
      <td class="px-3 py-2.5">
        <a href="${APP_URL}/contratos/detalle.php?id=${r.id_contrato}"
           onclick="event.stopPropagation()"
           class="font-mono text-esm-700 hover:underline text-xs">${r.contrato_codigo}</a>
      </td>
      <td class="px-3 py-2.5 text-sm text-slate-600">${App.fecha(r.fecha_venta)}</td>
      <td class="px-3 py-2.5 text-right text-sm font-medium">${r.total_animales || 0}</td>
      <td class="px-3 py-2.5 text-right text-sm">${App.kg(r.peso_total_kg)}</td>
      <td class="px-3 py-2.5 text-right text-sm text-esm-700 font-medium">${App.moneda(r.total_ingresos)}</td>
      <td class="px-3 py-2.5 text-right text-sm text-red-500">${App.moneda(r.total_costos)}</td>
      <td class="px-3 py-2.5 text-right text-sm font-bold ${esGan ? 'text-esm-700' : 'text-red-600'}">${App.moneda(gan)}</td>
      <td class="px-3 py-2.5">
        <span class="px-2 py-0.5 rounded-full text-xs font-semibold ${estadoCls}">${r.estado}</span>
      </td>
      <td class="px-3 py-2.5">
        <button onclick="event.stopPropagation(); verDetalle(${r.id})"
                class="btn btn-tierra btn-xs">Ver</button>
      </td>
    </tr>`;
  }).join('');

  // Totales en el pie de tabla
  document.getElementById('ft-animales').textContent = Number(kpis.total_animales).toLocaleString('es-CO');
  document.getElementById('ft-kg').textContent       = App.kg(kpis.total_kg);
  document.getElementById('ft-ingresos').textContent = App.moneda(kpis.total_ingresos);
  document.getElementById('ft-costos').textContent   = App.moneda(kpis.total_costos);

  const ftGan = document.getElementById('ft-ganancia');
  const ganT  = parseFloat(kpis.total_ganancia);
  ftGan.textContent = App.moneda(ganT);
  ftGan.style.color = ganT >= 0 ? '#059669' : '#dc2626';

  tfoot.classList.remove('hidden');
}

// ── Ver detalle de liquidación ──────────────────────────────
async function verDetalle(id) {
  // Mostrar panel
  document.getElementById('overlay-detalle').classList.remove('hidden');
  document.getElementById('panel-detalle').style.transform = 'translateX(0)';
  document.getElementById('det-loader').classList.remove('hidden');
  document.getElementById('det-panel-resumen').classList.add('hidden');
  document.getElementById('det-panel-animales').classList.add('hidden');
  document.getElementById('det-panel-socios').classList.add('hidden');
  showDetTab('resumen');

  const res = await App.get(API_REP, { action: 'liquidacion_detalle', id });
  document.getElementById('det-loader').classList.add('hidden');

  if (!res.ok) { App.toast(res.data.message, 'error'); return; }

  const d = res.data.data;
  const t = d.totales;
  const ganancia = parseFloat(t.total_ganancia);
  const esGan    = ganancia >= 0;

  // Header panel
  document.getElementById('det-titulo').textContent    = 'Liquidación #' + d.id + (d.numero_factura ? ' — ' + d.numero_factura : '');
  document.getElementById('det-subtitulo').textContent = `${d.empresa_factura || '—'} · ${App.fecha(d.fecha_venta)}`;
  document.getElementById('det-link-contrato').href    = APP_URL + '/contratos/detalle.php?id=' + d.id_contrato;
  document.getElementById('det-link-imprimir').href    = APP_URL + '/liquidaciones/imprimir.php?id=' + d.id;

  // Resumen - info
  document.getElementById('det-empresa').textContent       = d.empresa_factura || '—';
  document.getElementById('det-cliente').textContent       = d.cliente || '—';
  document.getElementById('det-factura').textContent       = d.numero_factura || '(sin número)';
  document.getElementById('det-fecha').textContent         = App.fecha(d.fecha_venta);
  document.getElementById('det-contrato').textContent      = d.contrato_codigo;
  document.getElementById('det-empresa-compra').textContent= d.empresa_compra || '—';

  // Métricas rápidas
  document.getElementById('det-vendidos').textContent = t.vendidos || 0;
  document.getElementById('det-muertos').textContent  = t.muertos  || 0;
  document.getElementById('det-dias').textContent     = Math.round(t.promedio_dias || 0);

  // Costos
  const costoItems = [
    ['Costo compra animales',  t.total_costo_compra],
    ['Flete de entrada',       t.total_flete_entrada],
    ['Manutención',            t.total_manutencion],
    ['Flete de salida',        t.total_flete_salida],
    ['Otros gastos',           t.total_otros_gastos],
  ];
  document.getElementById('det-costos-tabla').innerHTML = costoItems.map(([lbl, val]) =>
    `<tr>
       <td class="px-4 py-2 text-slate-600">${lbl}</td>
       <td class="px-4 py-2 text-right font-medium text-slate-800">${App.moneda(val)}</td>
     </tr>`
  ).join('');
  document.getElementById('det-costo-total').textContent = App.moneda(t.total_costos);

  // Ingresos y ganancia
  document.getElementById('det-ingresos').textContent = App.moneda(t.total_ingresos);

  const cardGan = document.getElementById('det-ganancia-card');
  cardGan.className = `p-4 rounded-xl border ${esGan ? 'border-esm-200 bg-esm-50' : 'border-red-200 bg-red-50'}`;
  document.getElementById('det-ganancia-label').className = `text-xs uppercase tracking-wide mb-1 ${esGan ? 'text-esm-600' : 'text-red-500'}`;
  document.getElementById('det-ganancia-label').textContent = esGan ? 'Ganancia neta' : 'Pérdida neta';
  document.getElementById('det-ganancia-val').className = `font-display font-bold text-2xl ${esGan ? 'text-esm-700' : 'text-red-600'}`;
  document.getElementById('det-ganancia-val').textContent = App.moneda(ganancia);

  // Observación
  if (d.observacion) {
    document.getElementById('det-obs-wrap').classList.remove('hidden');
    document.getElementById('det-obs').textContent = d.observacion;
  } else {
    document.getElementById('det-obs-wrap').classList.add('hidden');
  }

  // Animales
  renderDetalleAnimales(d.animales, t);

  // Socios
  renderDetalleSocios(d.socios);

  // Mostrar resumen
  document.getElementById('det-panel-resumen').classList.remove('hidden');
}

function renderDetalleAnimales(animales, totales) {
  const tbody = document.getElementById('det-tbody-animales');
  const tfoot = document.getElementById('det-tfoot-animales');

  if (!animales || animales.length === 0) {
    tbody.innerHTML = `<tr><td colspan="12" class="text-center py-6 text-slate-400 text-xs">Sin animales</td></tr>`;
    tfoot.classList.add('hidden');
    return;
  }

  tbody.innerHTML = animales.map(a => {
    const gan    = parseFloat(a.ganancia);
    const esGan  = gan >= 0;
    const esMuerte = a.tipo_salida === 'muerte';
    return `<tr class="border-b border-slate-100 ${esMuerte ? 'bg-red-50' : ''}">
      <td class="px-3 py-1.5">
        <span class="font-mono text-slate-700">${a.animal_codigo || ('#'+a.id_animal)}</span>
      </td>
      <td class="px-3 py-1.5">
        <span class="px-1.5 py-0.5 rounded text-xs ${esMuerte ? 'bg-red-100 text-red-600' : 'bg-esm-100 text-esm-700'}">
          ${esMuerte ? 'Muerte' : 'Venta'}
        </span>
      </td>
      <td class="px-3 py-1.5 text-right text-slate-600">${a.dias_manutencion}</td>
      <td class="px-3 py-1.5 text-right">${App.moneda(a.costo_compra)}</td>
      <td class="px-3 py-1.5 text-right">${App.moneda(a.costo_flete_entrada)}</td>
      <td class="px-3 py-1.5 text-right">${App.moneda(a.costo_manutencion)}</td>
      <td class="px-3 py-1.5 text-right">${App.moneda(a.costo_flete_salida)}</td>
      <td class="px-3 py-1.5 text-right">${App.moneda(a.otros_gastos)}</td>
      <td class="px-3 py-1.5 text-right font-medium text-red-600">${App.moneda(a.costo_total)}</td>
      <td class="px-3 py-1.5 text-right">${esMuerte ? '—' : App.kg(a.peso_salida_kg)}</td>
      <td class="px-3 py-1.5 text-right text-esm-700 font-medium">${esMuerte ? '—' : App.moneda(a.valor_venta)}</td>
      <td class="px-3 py-1.5 text-right font-bold ${esGan ? 'text-esm-700' : 'text-red-600'}">${App.moneda(gan)}</td>
    </tr>`;
  }).join('');

  // Pie totales animales
  document.getElementById('dft-compra').textContent    = App.moneda(totales.total_costo_compra);
  document.getElementById('dft-flete-ent').textContent = App.moneda(totales.total_flete_entrada);
  document.getElementById('dft-mant').textContent      = App.moneda(totales.total_manutencion);
  document.getElementById('dft-flete-sal').textContent = App.moneda(totales.total_flete_salida);
  document.getElementById('dft-otros').textContent     = App.moneda(totales.total_otros_gastos);
  document.getElementById('dft-costo').textContent     = App.moneda(totales.total_costos);
  document.getElementById('dft-peso').textContent      = App.kg(animales.reduce((s,a) => s + parseFloat(a.peso_salida_kg||0), 0));
  document.getElementById('dft-venta').textContent     = App.moneda(totales.total_ingresos);

  const dftGan = document.getElementById('dft-ganancia');
  dftGan.textContent = App.moneda(totales.total_ganancia);
  dftGan.style.color = parseFloat(totales.total_ganancia) >= 0 ? '#059669' : '#dc2626';

  tfoot.classList.remove('hidden');
}

function renderDetalleSocios(socios) {
  const grid = document.getElementById('det-socios-grid');
  if (!socios || socios.length === 0) {
    grid.innerHTML = '<p class="text-slate-400 text-sm col-span-2">Sin socios registrados.</p>';
    return;
  }
  grid.innerHTML = socios.map(s => {
    const gan   = parseFloat(s.ganancia_estimada);
    const esGan = gan >= 0;
    return `
    <div class="p-4 rounded-xl border ${esGan ? 'border-esm-200 bg-esm-50' : 'border-red-200 bg-red-50'}">
      <div class="flex items-center gap-2.5 mb-3">
        <span class="w-9 h-9 rounded-full flex items-center justify-center font-bold text-sm flex-shrink-0
                     ${esGan ? 'bg-esm-100 text-esm-700' : 'bg-red-100 text-red-600'}">
          ${s.socio.charAt(0)}
        </span>
        <div>
          <p class="text-sm font-semibold text-slate-800">${s.socio}</p>
          <p class="text-xs text-slate-400">${s.empresa}</p>
        </div>
      </div>
      <div class="flex items-end justify-between">
        <div>
          <p class="text-xs text-slate-400 mb-0.5">Participación</p>
          <p class="text-sm font-bold text-slate-700">${s.porcentaje}%</p>
        </div>
        <div class="text-right">
          <p class="text-xs ${esGan ? 'text-esm-600' : 'text-red-500'} mb-0.5">${esGan ? 'Ganancia' : 'Pérdida'}</p>
          <p class="font-display font-bold text-xl ${esGan ? 'text-esm-700' : 'text-red-600'}">${App.moneda(gan)}</p>
        </div>
      </div>
    </div>`;
  }).join('');
}

// ── Tabs del panel ──────────────────────────────────────────
function showDetTab(tab) {
  ['resumen','animales','socios'].forEach(t => {
    document.getElementById(`det-panel-${t}`)?.classList.add('hidden');
    const btn = document.getElementById(`det-tab-${t}`);
    if (btn) {
      btn.className = 'det-tab px-3 py-2.5 text-sm font-medium border-b-2 border-transparent text-slate-500 hover:text-slate-700';
    }
  });
  document.getElementById(`det-panel-${tab}`)?.classList.remove('hidden');
  const active = document.getElementById(`det-tab-${tab}`);
  if (active) {
    active.className = 'det-tab px-3 py-2.5 text-sm font-medium border-b-2 border-esm-500 text-esm-700';
  }
}

// ── Cerrar panel ────────────────────────────────────────────
function cerrarDetalle() {
  document.getElementById('panel-detalle').style.transform = 'translateX(100%)';
  document.getElementById('overlay-detalle').classList.add('hidden');
}

// ── Limpiar filtros ──────────────────────────────────────────
function limpiarFiltros() {
  document.getElementById('f-empresa').value  = '';
  document.getElementById('f-cliente').value  = '';
  document.getElementById('f-contrato').value = '';
  document.getElementById('f-estado').value   = '';
  document.getElementById('f-desde').value    = '';
  document.getElementById('f-hasta').value    = '';
  cargarReporte();
}

// ── Exportar CSV ─────────────────────────────────────────────
function exportarCSV() {
  if (datosActuales.length === 0) { App.toast('Sin datos para exportar.', 'warning'); return; }

  const cols = ['ID','Factura','Empresa factura','Cliente','Contrato','Fecha venta',
                'Animales','Peso kg','Ingresos','Costos','Ganancia','Estado'];
  const rows = datosActuales.map(r => [
    r.id, r.numero_factura || '', r.empresa_factura || '', r.cliente || '',
    r.contrato_codigo, r.fecha_venta, r.total_animales || 0,
    parseFloat(r.peso_total_kg || 0).toFixed(2),
    parseFloat(r.total_ingresos || 0).toFixed(2),
    parseFloat(r.total_costos   || 0).toFixed(2),
    parseFloat(r.total_ganancia || 0).toFixed(2),
    r.estado,
  ]);

  const csv  = [cols, ...rows].map(r => r.map(v => `"${String(v).replace(/"/g,'""')}"`).join(',')).join('\n');
  const blob = new Blob(['﻿' + csv], { type: 'text/csv;charset=utf-8;' });
  const url  = URL.createObjectURL(blob);
  const a    = document.createElement('a');
  a.href     = url;
  a.download = 'liquidaciones_' + new Date().toISOString().slice(0,10) + '.csv';
  a.click();
  URL.revokeObjectURL(url);
}

// Cerrar panel con Escape
document.addEventListener('keydown', e => { if (e.key === 'Escape') cerrarDetalle(); });
</script>

<style>
@media print {
  aside, header, #bottom-nav, .btn, #panel-detalle, #overlay-detalle,
  .card:has(#f-empresa), button { display: none !important; }
  #main-wrapper { margin-left: 0 !important; }
  #kpi-section .stat-card::before { print-color-adjust: exact; -webkit-print-color-adjust: exact; }
  body { background: white; }
  .tabla-scroll { overflow: visible; }
}
</style>

<?php require_once __DIR__ . '/../views/layout/footer.php'; ?>
