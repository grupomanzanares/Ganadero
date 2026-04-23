<?php
// ============================================================
// contratos/detalle.php — Detalle de un contrato
// ============================================================
require_once __DIR__ . '/../bootstrap.php';
Auth::requirePermission('contratos', 'ver');

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { header('Location: ' . APP_URL . '/contratos/index.php'); exit; }

$pageTitle = 'Detalle de contrato';
$modulo    = 'contratos';
require_once __DIR__ . '/../views/layout/header.php';
?>

<nav class="flex items-center gap-2 text-xs text-tierra-400 mb-5">
  <a href="<?= APP_URL ?>/contratos/index.php" class="hover:text-tierra-600">Contratos</a>
  <span>/</span>
  <span id="breadcrumb-codigo" class="text-tierra-700">Cargando...</span>
</nav>

<!-- Encabezado contrato -->
<div id="header-contrato" class="card mb-5">
  <div class="flex justify-center py-6">
    <div class="w-6 h-6 border-2 border-tierra-200 border-t-verde-500 rounded-full animate-spin"></div>
  </div>
</div>

<!-- Tabs -->
<div class="flex gap-1 mb-4 border-b border-tierra-200">
  <button onclick="showTab('animales')" id="tab-animales"
          class="tab-btn px-4 py-2 text-sm font-medium border-b-2 border-verde-500 text-verde-700">
    Animales del lote
  </button>
  <button onclick="showTab('socios')" id="tab-socios"
          class="tab-btn px-4 py-2 text-sm font-medium border-b-2 border-transparent text-tierra-500 hover:text-tierra-700">
    Socios
  </button>
  <button onclick="showTab('liquidaciones')" id="tab-liquidaciones"
          class="tab-btn px-4 py-2 text-sm font-medium border-b-2 border-transparent text-tierra-500 hover:text-tierra-700">
    Liquidaciones
  </button>
</div>

<!-- TAB ANIMALES -->
<div id="panel-animales" class="card overflow-hidden p-0">
  <div class="p-4 border-b border-tierra-100 flex items-center justify-between bg-tierra-50">
    <span class="text-sm font-medium text-tierra-700">Registro de animales</span>
    <?php if (Auth::can('animales','editar')): ?>
    <a id="link-pesaje" href="#" class="btn btn-verde btn-sm">
      ✏️ Ir a pesaje
    </a>
    <?php endif; ?>
  </div>
  <div class="overflow-x-auto">
    <table class="tabla-base">
      <thead>
        <tr>
          <th>#</th>
          <th>Código</th>
          <th class="text-right">Peso inicial</th>
          <th class="text-right">Peso finca</th>
          <th class="text-right">Costo compra</th>
          <th class="text-right">Flete</th>
          <th class="text-right">Valor/kg</th>
          <th>Estado</th>
        </tr>
      </thead>
      <tbody id="tbody-animales">
        <tr><td colspan="8" class="text-center py-6 text-tierra-400">Cargando...</td></tr>
      </tbody>
    </table>
  </div>
</div>

<!-- TAB SOCIOS -->
<div id="panel-socios" class="hidden card">
  <h3 class="font-display text-tierra-800 font-semibold mb-4">Socios del contrato</h3>
  <div id="lista-socios-detalle"></div>
</div>

<!-- TAB LIQUIDACIONES -->
<div id="panel-liquidaciones" class="hidden card overflow-hidden p-0">
  <div class="p-4 border-b border-tierra-100 flex items-center justify-between bg-tierra-50">
    <span class="text-sm font-medium text-tierra-700">Liquidaciones registradas</span>
    <?php if (Auth::can('liquidaciones','crear')): ?>
    <a id="link-liquidar" href="#" class="btn btn-verde btn-sm">+ Nueva liquidación</a>
    <?php endif; ?>
  </div>
  <div class="overflow-x-auto">
    <table class="tabla-base">
      <thead>
        <tr>
          <th>Factura</th>
          <th>Fecha venta</th>
          <th>Cliente</th>
          <th class="text-right">Peso total</th>
          <th class="text-right">Valor venta</th>
          <th class="text-right">Animales</th>
          <th>Estado</th>
          <th></th>
        </tr>
      </thead>
      <tbody id="tbody-liquidaciones">
        <tr><td colspan="8" class="text-center py-6 text-tierra-400 text-sm">Sin liquidaciones</td></tr>
      </tbody>
    </table>
  </div>
</div>

<script src="<?= APP_URL ?>/js/app.js"></script>
<script>
const ID           = <?= $id ?>;
const CAN_ELIMINAR = <?= Auth::can('contratos','eliminar') ? 'true' : 'false' ?>;
const CAN_EDITAR   = <?= Auth::can('contratos','editar')   ? 'true' : 'false' ?>;
let contratoData   = null;

function showTab(tab) {
  ['animales','socios','liquidaciones'].forEach(t => {
    document.getElementById(`panel-${t}`)?.classList.add('hidden');
    const btn = document.getElementById(`tab-${t}`);
    if (btn) {
      btn.className = 'tab-btn px-4 py-2 text-sm font-medium border-b-2 border-transparent text-tierra-500 hover:text-tierra-700';
    }
  });
  document.getElementById(`panel-${tab}`)?.classList.remove('hidden');
  const activeBtn = document.getElementById(`tab-${tab}`);
  if (activeBtn) {
    activeBtn.className = 'tab-btn px-4 py-2 text-sm font-medium border-b-2 border-verde-500 text-verde-700';
  }
}

(async () => {
  const res = await App.get(APP_URL + '/api/contratos.php', { id: ID });
  if (!res.ok) { App.toast('No se pudo cargar el contrato.', 'error'); return; }

  const c = res.data.data;
  contratoData = c;

  document.getElementById('breadcrumb-codigo').textContent = c.codigo;
  document.getElementById('link-pesaje')?.setAttribute('href', `${APP_URL}/animales/pesaje.php?contrato=${ID}`);
  document.getElementById('link-liquidar')?.setAttribute('href', `${APP_URL}/liquidaciones/nuevo.php?contrato=${ID}`);

  const estadoBadge = {
    abierto: 'bg-verde-100 text-verde-700',
    cerrado: 'bg-tierra-200 text-tierra-700',
    anulado: 'bg-red-100 text-red-600',
  }[c.estado] || '';

  document.getElementById('header-contrato').innerHTML = `
    <div class="flex flex-wrap gap-6 items-start justify-between">
      <div>
        <div class="flex items-center gap-3 mb-1">
          <h2 class="font-display text-tierra-900 text-2xl font-bold">${c.codigo}</h2>
          <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold ${estadoBadge}">${c.estado}</span>
        </div>
        <p class="text-tierra-500 text-sm">${c.tipo_animal} — Proveedor: <strong>${c.proveedor}</strong></p>
      </div>
      <div class="flex gap-3 flex-wrap">
        ${c.estado === 'abierto'
          ? `<a href="${APP_URL}/liquidaciones/nuevo.php?contrato=${ID}"
                class="btn btn-verde btn-sm">Liquidar animales</a>`
          : `<a href="${APP_URL}/reportes/cierres.php?contrato=${ID}"
                class="btn btn-tierra btn-sm">Ver cierre</a>`}
        ${(() => {
            const sinActivos = c.animales.every(a => a.estado !== 'activo');
            return CAN_EDITAR && c.estado === 'abierto' && sinActivos
              ? `<button onclick="cerrarContrato()"
                         class="btn btn-sm bg-amber-100 text-amber-800 hover:bg-amber-200 border border-amber-300">
                   Cerrar contrato
                 </button>`
              : '';
          })()}
        ${CAN_ELIMINAR && c.estado !== 'anulado'
          ? `<button onclick="eliminarContrato()"
                     class="btn btn-sm bg-red-100 text-red-700 hover:bg-red-200 border border-red-200">
               Eliminar contrato
             </button>`
          : ''}
      </div>
    </div>
    <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-4 mt-5 pt-4 border-t border-tierra-100">
      ${[
        ['Empresa compra',    c.empresa_compra],
        ['Empresa pago',      c.empresa_pago],
        ['Fecha compra',      App.fecha(c.fecha_compra)],
        ['Cantidad',          c.cantidad_animales + ' animales'],
        ['Peso total',        App.kg(c.peso_total_kg)],
        ['Valor compra/kg',   App.moneda(c.valor_unitario_kg) + '/kg'],
        ['Valor total lote',  App.moneda(c.valor_total)],
        ['Costo flete',       App.moneda(c.costo_flete)],
        ['Factura',           c.numero_factura || '—'],
        ['Fecha factura',     App.fecha(c.fecha_factura)],
      ].map(([lbl, val]) => `
        <div>
          <p class="text-xs text-tierra-400 uppercase tracking-wide">${lbl}</p>
          <p class="text-sm font-medium text-tierra-800 mt-0.5">${val}</p>
        </div>`).join('')}
    </div>`;

  // Tabla animales
  App.renderTable('tbody-animales', c.animales, [
    { render: (r, i) => `<span class="text-tierra-400">${(i||0)+1}</span>` },
    { key: 'codigo', render: r => r.codigo
        ? `<span class="font-mono text-tierra-800">${r.codigo}</span>`
        : `<span class="text-tierra-300 italic">Sin código</span>` },
    { key: 'peso_inicial_kg',   render: r => App.kg(r.peso_inicial_kg) },
    { key: 'peso_finca_kg',     render: r => r.peso_finca_kg ? App.kg(r.peso_finca_kg) : '—' },
    { key: 'costo_compra_animal', render: r => App.moneda(r.costo_compra_animal) },
    { key: 'costo_flete_animal',  render: r => App.moneda(r.costo_flete_animal) },
    { key: 'valor_promedio_kg',   render: r => r.valor_promedio_kg ? App.moneda(r.valor_promedio_kg)+'/kg' : '—' },
    { key: 'estado', render: r => {
        const cls = {activo:'bg-verde-100 text-verde-700',vendido:'bg-tierra-200 text-tierra-600',
                     muerto:'bg-red-100 text-red-600'}[r.estado]||'';
        return `<span class="px-2 py-0.5 rounded-full text-xs ${cls}">${r.estado}</span>`;
    }},
  ]);

  // Socios
  document.getElementById('lista-socios-detalle').innerHTML = c.socios.length === 0
    ? '<p class="text-tierra-400 text-sm">No hay socios registrados.</p>'
    : `<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
        ${c.socios.map(s => `
          <div class="flex items-center gap-3 p-3 bg-tierra-50 rounded-lg border border-tierra-100">
            <span class="w-9 h-9 rounded-full bg-verde-100 text-verde-700 flex items-center
                         justify-center font-bold text-sm">${s.socio.charAt(0)}</span>
            <div>
              <p class="text-sm font-medium text-tierra-800">${s.socio}</p>
              <p class="text-xs text-tierra-400">${s.empresa} — ${s.porcentaje}%</p>
            </div>
          </div>`).join('')}
       </div>`;

  // Liquidaciones
  const resLiq = await App.get(APP_URL + '/api/liquidaciones.php', { contrato: ID });

  if (resLiq.ok) {
    App.renderTable('tbody-liquidaciones', resLiq.data.data, [
      { key: 'numero_factura', render: r => r.numero_factura || '—' },
      { key: 'fecha_venta',    render: r => App.fecha(r.fecha_venta) },
      { key: 'cliente' },
      { key: 'peso_total_kg',    render: r => App.kg(r.peso_total_kg) },
      { key: 'valor_total_venta',render: r => App.moneda(r.valor_total_venta) },
      { key: 'total_animales',   render: r => r.total_animales + ' cab.' },
      { key: 'estado', render: r => {
          const cls = {confirmada:'bg-verde-100 text-verde-700',borrador:'bg-amber-100 text-amber-700',
                       anulada:'bg-red-100 text-red-600'}[r.estado]||'';
          return `<span class="px-2 py-0.5 rounded-full text-xs font-semibold ${cls}">${r.estado}</span>`;
      }},
      { render: r => `
        <div class="flex gap-1">
          <a href="${APP_URL}/reportes/liquidaciones.php?open=${r.id}"
             class="btn btn-tierra btn-xs">Ver</a>
          <a href="${APP_URL}/liquidaciones/imprimir.php?id=${r.id}" target="_blank"
             class="btn btn-xs btn-outline" title="Imprimir">
            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
            </svg>
          </a>
        </div>` },
    ]);
  }
})();

async function cerrarContrato() {
  if (!App.confirm('¿Cerrar este contrato? Se generará el cierre con todos los animales ya liquidados. Esta acción no se puede deshacer.')) return;
  const res = await App.post(APP_URL + `/api/contratos.php?action=cerrar&id=${ID}`, {});
  if (res.ok) {
    App.toast(res.data.message || 'Contrato cerrado correctamente.', 'success');
    setTimeout(() => window.location.reload(), 1200);
  } else {
    App.toast(res.data.message || 'No se pudo cerrar el contrato.', 'error');
  }
}

async function eliminarContrato() {
  if (!App.confirm('¿Está seguro de que desea eliminar este contrato? Esta acción no se puede deshacer.')) return;
  const res = await App.del(APP_URL + `/api/contratos.php?id=${ID}`);
  if (res.ok) {
    App.toast(res.data.message, 'success');
    setTimeout(() => { window.location.href = APP_URL + '/contratos/index.php'; }, 1200);
  } else {
    App.toast(res.data.message, 'error');
  }
}
</script>
<?php require_once __DIR__ . '/../views/layout/footer.php'; ?>
