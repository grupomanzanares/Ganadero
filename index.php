<?php
// ============================================================
// index.php — Dashboard principal
// ============================================================
require_once __DIR__ . '/bootstrap.php';

$pageTitle = 'Dashboard';
$modulo    = 'dashboard';
require_once __DIR__ . '/views/layout/header.php';
?>

<!-- Stats -->
<div id="stats-grid" class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
  <div class="stat-card col-span-2 lg:col-span-4 h-16 animate-pulse bg-tierra-100 rounded-lg"></div>
</div>

<!-- Fila principal -->
<div class="grid grid-cols-1 lg:grid-cols-3 gap-5">

  <!-- Últimos contratos -->
  <div class="lg:col-span-2 card">
    <div class="flex items-center justify-between mb-4">
      <h3 class="font-display text-tierra-800 text-base font-semibold">Contratos recientes</h3>
      <?php if (Auth::can('contratos','crear')): ?>
      <a href="<?= APP_URL ?>/contratos/nuevo.php" class="btn btn-verde btn-sm">+ Nuevo contrato</a>
      <?php endif; ?>
    </div>
    <div class="overflow-x-auto">
      <table class="tabla-base">
        <thead>
          <tr>
            <th>Código</th>
            <th>Empresa</th>
            <th>Tipo</th>
            <th class="text-right">Animales</th>
            <th>Estado</th>
            <th>Fecha</th>
          </tr>
        </thead>
        <tbody id="tbody-ultimos-contratos">
          <tr><td colspan="6" class="text-center py-6 text-tierra-400 text-sm">Cargando...</td></tr>
        </tbody>
      </table>
    </div>
    <a href="<?= APP_URL ?>/contratos/index.php"
       class="mt-3 inline-block text-xs text-verde-600 hover:text-verde-700">
      Ver todos los contratos →
    </a>
  </div>

  <!-- Panel lateral -->
  <div class="space-y-4">

    <div class="card border-l-4 border-verde-500">
      <p class="text-xs text-tierra-500 uppercase tracking-wide mb-1">Tarifa manutención vigente</p>
      <p class="font-display text-tierra-800 text-2xl font-bold" id="tarifa-vigente">—</p>
      <p class="text-xs text-tierra-400 mt-0.5">por animal / día</p>
    </div>

    <div class="card">
      <h4 class="font-display text-tierra-700 text-sm font-semibold mb-3">Resumen general</h4>
      <ul class="space-y-2 text-sm">
        <li class="flex justify-between text-tierra-600">
          <span>Contratos abiertos</span>
          <span class="font-semibold" id="r-abiertos">—</span>
        </li>
        <li class="flex justify-between text-tierra-600">
          <span>Contratos cerrados</span>
          <span class="font-semibold" id="r-cerrados">—</span>
        </li>
        <li class="flex justify-between text-tierra-600 border-t border-tierra-100 pt-2">
          <span>Total animales comprados</span>
          <span class="font-semibold" id="r-animales">—</span>
        </li>
        <li class="flex justify-between border-t border-tierra-100 pt-2">
          <span class="text-tierra-600">Ganancia acumulada</span>
          <span class="font-bold" id="r-ganancia">—</span>
        </li>
      </ul>
    </div>

    <div class="card">
      <h4 class="font-display text-tierra-700 text-sm font-semibold mb-3">Accesos rápidos</h4>
      <div class="space-y-1">
        <?php if (Auth::can('contratos','crear')): ?>
        <a href="<?= APP_URL ?>/contratos/nuevo.php"
           class="flex items-center gap-2 p-2 rounded-lg hover:bg-tierra-50 text-sm text-tierra-700 transition-colors">
          📋 Registrar compra
        </a>
        <?php endif; ?>
        <?php if (Auth::can('liquidaciones','crear')): ?>
        <a href="<?= APP_URL ?>/liquidaciones/nuevo.php"
           class="flex items-center gap-2 p-2 rounded-lg hover:bg-tierra-50 text-sm text-tierra-700 transition-colors">
          💰 Nueva liquidación
        </a>
        <?php endif; ?>
        <?php if (Auth::can('fletes','crear')): ?>
        <a href="<?= APP_URL ?>/fletes/index.php"
           class="flex items-center gap-2 p-2 rounded-lg hover:bg-tierra-50 text-sm text-tierra-700 transition-colors">
          🚛 Registrar flete
        </a>
        <?php endif; ?>
        <?php if (Auth::can('reportes','ver')): ?>
        <a href="<?= APP_URL ?>/reportes/cierres.php"
           class="flex items-center gap-2 p-2 rounded-lg hover:bg-tierra-50 text-sm text-tierra-700 transition-colors">
          📊 Ver cierres
        </a>
        <?php endif; ?>
      </div>
    </div>

  </div>
</div>

<script src="<?= APP_URL ?>/js/app.js"></script>
<script>
(async function initDashboard() {
  const res = await App.get(APP_URL + '/api/reportes.php', { action: 'resumen' });
  if (!res.ok) { App.toast('No se pudo cargar el resumen.', 'error'); return; }
  const d = res.data.data;

  // Stats cards
  document.getElementById('stats-grid').innerHTML = `
    <div class="stat-card">
      <p class="text-xs text-tierra-500 uppercase tracking-wide mb-1">Contratos abiertos</p>
      <p class="font-display text-tierra-900 text-3xl font-bold">${d.abiertos || 0}</p>
    </div>
    <div class="stat-card">
      <p class="text-xs text-tierra-500 uppercase tracking-wide mb-1">Total animales</p>
      <p class="font-display text-tierra-900 text-3xl font-bold">${Number(d.total_animales||0).toLocaleString('es-CO')}</p>
    </div>
    <div class="stat-card">
      <p class="text-xs text-tierra-500 uppercase tracking-wide mb-1">Valor total compras</p>
      <p class="font-display text-tierra-900 text-2xl font-bold">${App.moneda(d.valor_total_compras)}</p>
    </div>
    <div class="stat-card">
      <p class="text-xs text-tierra-500 uppercase tracking-wide mb-1">Ganancia acumulada</p>
      <p class="font-display text-${Number(d.ganancia_total||0)>=0?'verde':'red'}-700 text-2xl font-bold">
        ${App.moneda(d.ganancia_total)}
      </p>
    </div>`;

  document.getElementById('r-abiertos').textContent = d.abiertos || 0;
  document.getElementById('r-cerrados').textContent = d.cerrados || 0;
  document.getElementById('r-animales').textContent = Number(d.total_animales||0).toLocaleString('es-CO');
  const elG = document.getElementById('r-ganancia');
  elG.textContent = App.moneda(d.ganancia_total);
  elG.className   = `font-bold ${Number(d.ganancia_total||0)>=0 ? 'text-verde-600' : 'text-red-600'}`;

  // Últimos contratos
  App.renderTable('tbody-ultimos-contratos', d.ultimos_contratos || [], [
    { key: 'codigo', render: r =>
        `<a href="${APP_URL}/contratos/detalle.php?id=${r.id}"
             class="font-mono text-verde-700 hover:underline">${r.codigo}</a>` },
    { key: 'empresa' },
    { key: 'tipo_animal' },
    { key: 'cantidad_animales', render: r => r.cantidad_animales + ' cab.' },
    { key: 'estado', render: r => {
        const cls = {abierto:'bg-verde-100 text-verde-700',cerrado:'bg-tierra-200 text-tierra-700',
                     anulado:'bg-red-100 text-red-600'}[r.estado]||'';
        return `<span class="px-2 py-0.5 rounded-full text-xs font-semibold ${cls}">${r.estado}</span>`;
    }},
    { key: 'fecha_compra', render: r => App.fecha(r.fecha_compra) },
  ]);

  // Tarifa manutención
  const resM = await App.get(APP_URL + '/api/catalogos.php', { recurso: 'manutencion' });
  if (resM.ok && resM.data.data?.length) {
    document.getElementById('tarifa-vigente').textContent =
      App.moneda(resM.data.data[0].valor_dia) + ' / día';
  }
})();
</script>

<?php require_once __DIR__ . '/views/layout/footer.php'; ?>
