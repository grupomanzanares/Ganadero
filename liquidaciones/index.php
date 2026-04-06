<?php
// ============================================================
// liquidaciones/index.php — Listado de liquidaciones
// ============================================================
require_once __DIR__ . '/../bootstrap.php';
Auth::requirePermission('liquidaciones', 'ver');

$pageTitle = 'Liquidaciones';
$modulo    = 'liquidaciones';
require_once __DIR__ . '/../views/layout/header.php';
?>

<div class="flex items-center justify-between mb-5">
  <div>
    <h2 class="font-display text-tierra-800 text-xl font-bold">Liquidaciones</h2>
    <p class="text-tierra-400 text-sm mt-0.5">Registro de ventas y liquidaciones de animales</p>
  </div>
  <?php if (Auth::can('liquidaciones','crear')): ?>
  <a href="<?= APP_URL ?>/liquidaciones/nuevo.php" class="btn btn-verde">
    + Nueva liquidación
  </a>
  <?php endif; ?>
</div>

<div class="card overflow-hidden p-0">
  <div id="loader-liq" class="flex justify-center py-8">
    <div class="w-5 h-5 border-2 border-tierra-200 border-t-verde-500 rounded-full animate-spin"></div>
  </div>
  <div class="overflow-x-auto">
    <table class="tabla-base">
      <thead>
        <tr>
          <th>Contrato</th>
          <th>Factura</th>
          <th>Fecha venta</th>
          <th>Empresa factura</th>
          <th>Cliente</th>
          <th class="text-right">Animales</th>
          <th class="text-right">Peso total</th>
          <th class="text-right">Valor venta</th>
          <th>Estado</th>
          <th></th>
        </tr>
      </thead>
      <tbody id="tbody-liquidaciones"></tbody>
    </table>
  </div>
</div>

<script src="<?= APP_URL ?>/js/app.js"></script>
<script>
(async () => {
  const res = await App.get(APP_URL + '/api/liquidaciones.php');
  document.getElementById('loader-liq').classList.add('hidden');

  if (!res.ok) { App.toast(res.data.message, 'error'); return; }

  App.renderTable('tbody-liquidaciones', res.data.data, [
    { key: 'contrato_codigo', render: r =>
        `<a href="${APP_URL}/contratos/detalle.php?id=${r.id_contrato}"
             class="font-mono text-verde-700 hover:underline">${r.contrato_codigo}</a>` },
    { key: 'numero_factura', render: r => r.numero_factura || '—' },
    { key: 'fecha_venta',    render: r => App.fecha(r.fecha_venta) },
    { key: 'empresa_factura' },
    { key: 'cliente' },
    { key: 'total_animales', render: r => r.total_animales + ' cab.' },
    { key: 'peso_total_kg',  render: r => App.kg(r.peso_total_kg) },
    { key: 'valor_total_venta', render: r =>
        `<span class="font-medium text-verde-700">${App.moneda(r.valor_total_venta)}</span>` },
    { key: 'estado', render: r => {
        const cls = { confirmada:'bg-verde-100 text-verde-700',
                      borrador:'bg-amber-100 text-amber-700',
                      anulada:'bg-red-100 text-red-600' }[r.estado] || '';
        return `<span class="px-2 py-0.5 rounded-full text-xs font-semibold ${cls}">${r.estado}</span>`;
    }},
    { render: r =>
        `<a href="${APP_URL}/contratos/detalle.php?id=${r.id_contrato}"
             class="btn btn-tierra btn-xs">Ver contrato</a>` },
  ]);
})();
</script>

<?php require_once __DIR__ . '/../views/layout/footer.php'; ?>
