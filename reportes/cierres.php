<?php
// ============================================================
// reportes/cierres.php — Cierre y reporte de contrato
// ============================================================
require_once __DIR__ . '/../bootstrap.php';
Auth::requirePermission('reportes', 'ver');

$idContrato = (int)($_GET['contrato'] ?? 0);
$pageTitle  = 'Cierres de contrato';
$modulo     = 'cierres';
require_once __DIR__ . '/../views/layout/header.php';
?>

<div class="flex items-center justify-between mb-5">
  <div>
    <h2 class="font-display text-tierra-800 text-xl font-bold">Cierres de contrato</h2>
    <p class="text-tierra-400 text-sm mt-0.5">Resultado final de cada lote de ganado</p>
  </div>
</div>

<!-- Buscador de contrato -->
<div class="card mb-5">
  <div class="flex gap-3 items-end">
    <div class="flex-1">
      <label class="form-label">Buscar contrato cerrado</label>
      <select id="sel-contrato-cierre" class="input-base">
        <option value="">Seleccione un contrato...</option>
      </select>
    </div>
    <button onclick="cargarCierre()" class="btn btn-tierra">
      Ver cierre
    </button>
  </div>
</div>

<!-- Panel de cierre -->
<div id="panel-cierre" class="<?= $idContrato ? '' : 'hidden' ?> space-y-5">

  <!-- Header cierre -->
  <div id="cierre-header" class="card">
    <div class="flex justify-center py-4">
      <div class="w-5 h-5 border-2 border-tierra-200 border-t-verde-500 rounded-full animate-spin"></div>
    </div>
  </div>

  <!-- Costos vs Ingresos -->
  <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
    <div class="card">
      <h4 class="font-display text-tierra-700 font-semibold mb-3 text-sm">Resumen de costos</h4>
      <div class="space-y-2 text-sm" id="resumen-costos"></div>
    </div>
    <div class="card">
      <h4 class="font-display text-tierra-700 font-semibold mb-3 text-sm">Liquidaciones realizadas</h4>
      <div id="lista-liquidaciones-cierre" class="space-y-2 text-sm"></div>
    </div>
  </div>

  <!-- Resultado por socio -->
  <div class="card">
    <h4 class="font-display text-tierra-800 font-semibold mb-4">Resultado por socio</h4>
    <div id="socios-resultado" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3"></div>
  </div>

  <!-- Botón imprimir -->
  <div class="flex justify-end">
    <button onclick="window.print()"
            class="btn btn-outline">
      🖨️ Imprimir cierre
    </button>
  </div>
</div>

<script src="<?= APP_URL ?>/js/app.js"></script>
<script>
// Cargar contratos cerrados
(async () => {
  const res = await App.get(APP_URL + '/api/contratos.php', { estado: 'cerrado' });
  if (res.ok) {
    App.populateSelect('sel-contrato-cierre', res.data.data, 'id',
      r => `${r.codigo} — ${r.empresa_compra} (${App.fecha(r.fecha_compra)})`);
  }

  <?php if ($idContrato > 0): ?>
  document.getElementById('sel-contrato-cierre').value = '<?= $idContrato ?>';
  await cargarCierre();
  <?php endif; ?>
})();

async function cargarCierre() {
  const id = document.getElementById('sel-contrato-cierre').value;
  if (!id) { App.toast('Seleccione un contrato.', 'warning'); return; }

  document.getElementById('panel-cierre').classList.remove('hidden');
  document.getElementById('cierre-header').innerHTML =
    '<div class="flex justify-center py-4"><div class="w-5 h-5 border-2 border-tierra-200 border-t-verde-500 rounded-full animate-spin"></div></div>';

  const res = await App.get(APP_URL + '/api/reportes.php', { action: 'cierre', id });
  if (!res.ok) {
    document.getElementById('cierre-header').innerHTML =
      `<p class="text-center text-red-500 py-4">${res.data.message}</p>`;
    return;
  }

  const d = res.data.data;
  const esGanancia = parseFloat(d.ganancia_total) >= 0;

  // Header
  document.getElementById('cierre-header').innerHTML = `
    <div class="flex flex-wrap gap-6 items-start justify-between">
      <div>
        <h3 class="font-display text-tierra-900 text-2xl font-bold">${d.codigo}</h3>
        <p class="text-tierra-500 text-sm">${d.tipo_animal} — ${d.empresa_compra}</p>
        <p class="text-tierra-400 text-xs mt-0.5">Compra: ${App.fecha(d.fecha_compra)} — Cierre: ${App.fecha(d.fecha_cierre)}</p>
      </div>
      <div class="text-right">
        <p class="text-xs text-tierra-400 uppercase tracking-wide mb-1">
          ${esGanancia ? 'Ganancia total' : 'Pérdida total'}
        </p>
        <p class="font-display text-3xl font-bold ${esGanancia ? 'text-verde-700' : 'text-red-600'}">
          ${App.moneda(d.ganancia_total)}
        </p>
        <p class="text-xs text-tierra-400 mt-0.5">${App.moneda(d.ganancia_por_socio)} por socio</p>
      </div>
    </div>
    <div class="grid grid-cols-3 md:grid-cols-6 gap-4 mt-5 pt-4 border-t border-tierra-100">
      ${[
        ['Total comprados', d.total_animales],
        ['Vendidos', `<span class="text-verde-700">${d.animales_vendidos}</span>`],
        ['Muertos', `<span class="text-red-500">${d.animales_muertos}</span>`],
        ['Ingresos ventas', App.moneda(d.ingreso_total_ventas)],
        ['Costo total', `<span class="text-red-500">${App.moneda(d.costo_total)}</span>`],
        ['Resultado', `<span class="${esGanancia?'text-verde-700':'text-red-600'} font-bold">${App.moneda(d.ganancia_total)}</span>`],
      ].map(([l,v]) => `
        <div class="text-center">
          <p class="text-xs text-tierra-400 uppercase tracking-wide">${l}</p>
          <p class="text-sm font-bold text-tierra-800 mt-0.5">${v}</p>
        </div>`).join('')}
    </div>`;

  // Costos
  document.getElementById('resumen-costos').innerHTML = [
    ['Costo compra',       d.costo_total_compra],
    ['Flete entrada',      d.costo_total_flete_entrada],
    ['Manutención',        d.costo_total_manutencion],
    ['Flete salida',       d.costo_total_flete_salida],
  ].map(([l,v]) => `
    <div class="flex justify-between py-1.5 border-b border-tierra-100">
      <span class="text-tierra-500">${l}</span>
      <span class="font-medium text-tierra-800">${App.moneda(v)}</span>
    </div>`).join('') +
    `<div class="flex justify-between pt-2 font-bold">
      <span>COSTO TOTAL</span>
      <span class="text-red-600">${App.moneda(d.costo_total)}</span>
    </div>`;

  // Liquidaciones
  document.getElementById('lista-liquidaciones-cierre').innerHTML =
    d.liquidaciones.length === 0
      ? '<p class="text-tierra-400">Sin liquidaciones</p>'
      : d.liquidaciones.map(l => `
        <div class="flex justify-between items-center py-1.5 border-b border-tierra-100">
          <div>
            <span class="text-tierra-700 text-xs font-medium">${App.fecha(l.fecha_venta)}</span>
            <span class="text-tierra-400 text-xs ml-2">${l.cliente}</span>
            <span class="text-tierra-400 text-xs ml-1">(${l.animales} cab.)</span>
          </div>
          <span class="font-medium text-verde-700 text-sm">${App.moneda(l.valor_total_venta)}</span>
        </div>`).join('') +
      `<div class="flex justify-between pt-2 font-bold">
        <span>TOTAL VENTAS</span>
        <span class="text-verde-700">${App.moneda(d.ingreso_total_ventas)}</span>
      </div>`;

  // Socios
  document.getElementById('socios-resultado').innerHTML = d.detalle_socios.map(s => {
    const positivo = parseFloat(s.ganancia) >= 0;
    return `
    <div class="p-4 rounded-xl border ${positivo ? 'border-verde-200 bg-verde-50' : 'border-red-200 bg-red-50'}">
      <div class="flex items-center gap-2 mb-2">
        <span class="w-8 h-8 rounded-full ${positivo ? 'bg-verde-100 text-verde-700' : 'bg-red-100 text-red-600'}
                     flex items-center justify-center font-bold text-sm">
          ${s.socio.charAt(0)}
        </span>
        <div>
          <p class="text-sm font-medium text-tierra-800">${s.socio}</p>
          <p class="text-xs text-tierra-400">${s.empresa} — ${s.porcentaje}%</p>
        </div>
      </div>
      <p class="font-display font-bold text-xl ${positivo ? 'text-verde-700' : 'text-red-600'}">
        ${App.moneda(s.ganancia)}
      </p>
      <p class="text-xs text-tierra-400">${positivo ? 'Ganancia' : 'Pérdida'}</p>
    </div>`;
  }).join('');
}
</script>

<style>
@media print {
  aside, header, button, nav, .btn { display: none !important; }
  .ml-64 { margin-left: 0 !important; }
  body { background: white; }
}
</style>
<?php require_once __DIR__ . '/../views/layout/footer.php'; ?>
