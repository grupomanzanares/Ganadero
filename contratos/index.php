<?php
// ============================================================
// contratos/index.php — Listado de contratos de compra
// ============================================================
require_once __DIR__ . '/../bootstrap.php';
Auth::requirePermission('contratos', 'ver');

$pageTitle = 'Contratos de compra';
$modulo    = 'contratos';
require_once __DIR__ . '/../views/layout/header.php';
?>

<div class="flex items-center justify-between mb-5">
  <div>
    <h2 class="font-display text-tierra-800 text-xl font-bold">Contratos de compra</h2>
    <p class="text-tierra-400 text-sm mt-0.5">Gestión de lotes de ganado adquiridos</p>
  </div>
  <?php if (Auth::can('contratos','crear')): ?>
  <a href="<?= APP_URL ?>/contratos/nuevo.php" class="btn btn-verde">
    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
    </svg>
    Nuevo contrato
  </a>
  <?php endif; ?>
</div>

<!-- Filtros -->
<div class="card mb-4">
  <div class="flex flex-wrap gap-3 items-end">
    <div>
      <label class="form-label">Estado</label>
      <select id="filtro-estado" class="input-base w-36">
        <option value="">Todos</option>
        <option value="abierto">Abierto</option>
        <option value="cerrado">Cerrado</option>
        <option value="anulado">Anulado</option>
      </select>
    </div>
    <div>
      <label class="form-label">Empresa</label>
      <select id="filtro-empresa" class="input-base w-44">
        <option value="">Todas</option>
      </select>
    </div>
    <button onclick="ContratosListado.cargar()" class="btn btn-outline btn-sm">
      Filtrar
    </button>
  </div>
</div>

<!-- Tabla -->
<div class="card overflow-hidden p-0">
  <div id="loader-contratos" class="hidden flex justify-center py-6">
    <div class="w-5 h-5 border-2 border-tierra-200 border-t-verde-500 rounded-full animate-spin"></div>
  </div>
  <div class="overflow-x-auto">
    <table class="tabla-base">
      <thead>
        <tr>
          <th>Código</th>
          <th>Empresa compra</th>
          <th>Tipo animal</th>
          <th>Proveedor</th>
          <th class="text-right">Animales</th>
          <th class="text-right">Peso total</th>
          <th class="text-right">Valor total</th>
          <th>Estado</th>
          <th>Fecha compra</th>
          <th></th>
        </tr>
      </thead>
      <tbody id="tbody-contratos">
        <tr><td colspan="10" class="text-center py-8 text-tierra-400">Cargando...</td></tr>
      </tbody>
    </table>
  </div>
</div>

<script src="<?= APP_URL ?>/js/app.js"></script>
<script>
const CAN_ELIMINAR = <?= Auth::can('contratos','eliminar') ? 'true' : 'false' ?>;

const ContratosListado = (() => {
  const API     = APP_URL + '/api/contratos.php';
  const API_CAT = APP_URL + '/api/catalogos.php';

  const init = async () => {
    const res = await App.get(API_CAT, { recurso: 'empresas' });
    if (res.ok) App.populateSelect('filtro-empresa', res.data.data, 'id', 'nombre', 'Todas');
    await cargar();

    document.getElementById('filtro-estado')?.addEventListener('change', cargar);
    document.getElementById('filtro-empresa')?.addEventListener('change', cargar);
  };

  const cargar = async () => {
    document.getElementById('loader-contratos').classList.remove('hidden');
    const estado  = document.getElementById('filtro-estado')?.value  || '';
    const empresa = document.getElementById('filtro-empresa')?.value || '';
    const res     = await App.get(API, { estado, empresa });
    document.getElementById('loader-contratos').classList.add('hidden');

    if (!res.ok) { App.toast(res.data.message, 'error'); return; }

    App.renderTable('tbody-contratos', res.data.data, [
      { key: 'codigo', render: r =>
        `<a href="${APP_URL}/contratos/detalle.php?id=${r.id}"
             class="font-mono text-verde-700 hover:underline font-medium">${r.codigo}</a>` },
      { key: 'empresa_compra' },
      { key: 'tipo_animal' },
      { key: 'proveedor' },
      { key: 'cantidad_animales', render: r =>
        `<span class="font-medium">${r.cantidad_animales}</span> cab.` },
      { key: 'peso_total_kg', render: r => App.kg(r.peso_total_kg) },
      { key: 'valor_total',   render: r => `<span class="font-medium">${App.moneda(r.valor_total)}</span>` },
      { key: 'estado', render: r => {
          const cls = {abierto:'bg-verde-100 text-verde-700',cerrado:'bg-tierra-200 text-tierra-700',
                       anulado:'bg-red-100 text-red-600'}[r.estado] || '';
          return `<span class="px-2 py-0.5 rounded-full text-xs font-semibold ${cls}">${r.estado}</span>`;
      }},
      { key: 'fecha_compra', render: r => App.fecha(r.fecha_compra) },
      { render: r => `
          <div class="flex gap-1.5">
            <a href="${APP_URL}/contratos/detalle.php?id=${r.id}"
               class="btn btn-tierra btn-xs">Ver</a>
            ${r.estado === 'abierto'
              ? `<a href="${APP_URL}/animales/pesaje.php?contrato=${r.id}"
                    class="btn btn-outline btn-xs">Pesaje</a>
                 <a href="${APP_URL}/liquidaciones/nuevo.php?contrato=${r.id}"
                    class="btn btn-verde btn-xs">Liquidar</a>`
              : `<a href="${APP_URL}/reportes/cierres.php?contrato=${r.id}"
                    class="btn btn-outline btn-xs">Cierre</a>`}
            ${CAN_ELIMINAR && r.estado !== 'anulado'
              ? `<button onclick="eliminarContrato(${r.id})"
                         class="btn btn-xs bg-red-100 text-red-700 hover:bg-red-200">Eliminar</button>`
              : ''}
          </div>` },
    ]);
  };

  return { init, cargar };
})();

async function eliminarContrato(id) {
  if (!App.confirm('¿Está seguro de que desea eliminar este contrato? Esta acción no se puede deshacer.')) return;
  const res = await App.del(APP_URL + `/api/contratos.php?id=${id}`);
  if (res.ok) {
    App.toast(res.data.message, 'success');
    ContratosListado.cargar();
  } else {
    App.toast(res.data.message, 'error');
  }
}

ContratosListado.init();
</script>
<?php require_once __DIR__ . '/../views/layout/footer.php'; ?>
