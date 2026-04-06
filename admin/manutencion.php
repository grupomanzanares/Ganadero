<?php
// ============================================================
// admin/manutencion.php — Gestión de tarifas de manutención
// ============================================================
require_once __DIR__ . '/../bootstrap.php';
Auth::requirePermission('manutencion', 'ver');

$pageTitle = 'Tarifas de manutención';
$modulo    = 'manutencion';
require_once __DIR__ . '/../views/layout/header.php';
?>

<div class="flex items-center justify-between mb-5">
  <div>
    <h2 class="font-display text-tierra-800 text-xl font-bold">Tarifas de manutención</h2>
    <p class="text-tierra-400 text-sm mt-0.5">
      Valor diario por animal. El sistema usa la tarifa vigente a la fecha de liquidación.
    </p>
  </div>
  <?php if (Auth::can('manutencion','crear')): ?>
  <button onclick="App.modal.open('modal-nueva-tarifa')" class="btn btn-verde">
    + Nueva tarifa
  </button>
  <?php endif; ?>
</div>

<!-- Tarifa actual -->
<div id="tarifa-actual" class="card border-l-4 border-verde-500 mb-5">
  <p class="text-xs text-tierra-500 uppercase tracking-wide mb-1">Tarifa vigente hoy</p>
  <p class="font-display text-tierra-900 text-3xl font-bold" id="valor-vigente">Cargando...</p>
  <p class="text-tierra-400 text-xs mt-1">por animal / día</p>
</div>

<!-- Historial -->
<div class="card overflow-hidden p-0">
  <div class="p-4 bg-tierra-50 border-b border-tierra-100">
    <span class="text-sm font-medium text-tierra-700">Historial de tarifas</span>
  </div>
  <div class="overflow-x-auto">
    <table class="tabla-base">
      <thead>
        <tr>
          <th>Valor por día</th>
          <th>Vigente desde</th>
          <th>Observación</th>
          <th>Registrado</th>
        </tr>
      </thead>
      <tbody id="tbody-tarifas">
        <tr><td colspan="4" class="text-center py-6 text-tierra-400">Cargando...</td></tr>
      </tbody>
    </table>
  </div>
</div>

<!-- Modal nueva tarifa -->
<div id="modal-nueva-tarifa"
     class="hidden fixed inset-0 z-50 bg-black bg-opacity-40 flex items-center justify-center p-4">
  <div class="bg-white rounded-xl shadow-2xl w-full max-w-md p-6">
    <h3 class="font-display text-tierra-800 font-semibold mb-4">Nueva tarifa de manutención</h3>

    <div class="space-y-4">
      <div>
        <label class="form-label">Valor diario por animal *</label>
        <input type="number" id="nuevo-valor" step="0.01" min="0"
               class="input-base" placeholder="11518">
      </div>
      <div>
        <label class="form-label">Vigente desde *</label>
        <input type="date" id="nueva-fecha" class="input-base" value="<?= date('Y-m-d') ?>">
      </div>
      <div>
        <label class="form-label">Observación</label>
        <input type="text" id="nueva-obs" class="input-base" placeholder="Motivo del cambio">
      </div>
    </div>

    <div class="flex justify-end gap-3 mt-6">
      <button onclick="App.modal.close('modal-nueva-tarifa')" class="btn btn-outline">
        Cancelar
      </button>
      <button id="btn-guardar-tarifa" onclick="guardarTarifa()" class="btn btn-verde">
        Guardar tarifa
      </button>
    </div>
  </div>
</div>

<script src="<?= APP_URL ?>/js/app.js"></script>
<script>
const API = APP_URL + '/api/catalogos.php?recurso=manutencion';

(async () => {
  const res = await App.get(APP_URL + '/api/catalogos.php', { recurso: 'manutencion' });
  if (!res.ok) return;
  const tarifas = res.data.data;

  if (tarifas.length > 0) {
    document.getElementById('valor-vigente').textContent = App.moneda(tarifas[0].valor_dia) + ' / día';
  }

  App.renderTable('tbody-tarifas', tarifas, [
    { key: 'valor_dia', render: r =>
        `<span class="font-display font-bold text-tierra-900 text-base">${App.moneda(r.valor_dia)}</span>` },
    { key: 'fecha_vigencia', render: r => App.fecha(r.fecha_vigencia) },
    { key: 'observacion', render: r => r.observacion || '—' },
    { key: 'creado_en',   render: r => App.fecha(r.creado_en?.substr(0,10)) },
  ]);
})();

async function guardarTarifa() {
  const valor = document.getElementById('nuevo-valor').value;
  const fecha = document.getElementById('nueva-fecha').value;
  const obs   = document.getElementById('nueva-obs').value;

  if (!valor || !fecha) { App.toast('Valor y fecha son obligatorios.', 'error'); return; }

  const btn = document.getElementById('btn-guardar-tarifa');
  btn.disabled = true;

  const res = await App.post(APP_URL + '/api/catalogos.php?recurso=manutencion', {
    valor_dia: parseFloat(valor), fecha_vigencia: fecha, observacion: obs
  });

  btn.disabled = false;

  if (res.ok) {
    App.toast('Tarifa registrada correctamente.', 'success');
    App.modal.close('modal-nueva-tarifa');
    location.reload();
  } else {
    App.toast(res.data.message, 'error');
  }
}
</script>
<?php require_once __DIR__ . '/../views/layout/footer.php'; ?>
