<?php
// ============================================================
// fletes/index.php — Listado de fletes de salida
// ============================================================
require_once __DIR__ . '/../bootstrap.php';
Auth::requirePermission('fletes', 'ver');

$pageTitle = 'Fletes de salida';
$modulo    = 'fletes';
require_once __DIR__ . '/../views/layout/header.php';
?>

<div class="flex items-center justify-between mb-5">
  <div>
    <h2 class="font-display text-tierra-800 text-xl font-bold">Fletes de salida</h2>
    <p class="text-tierra-400 text-sm mt-0.5">Costos de transporte al momento de vender animales</p>
  </div>
  <?php if (Auth::can('fletes','crear')): ?>
  <button onclick="abrirModal()" class="btn btn-verde">+ Registrar flete</button>
  <?php endif; ?>
</div>

<div class="card overflow-hidden p-0">
  <div id="loader-fletes" class="flex justify-center py-8">
    <div class="w-5 h-5 border-2 border-tierra-200 border-t-verde-500 rounded-full animate-spin"></div>
  </div>
  <div class="overflow-x-auto">
    <table class="tabla-base">
      <thead>
        <tr>
          <th>Empresa</th>
          <th>Fecha</th>
          <th>Origen</th>
          <th>Destino</th>
          <th>Vehículo</th>
          <th class="text-right">Animales</th>
          <th class="text-right">Valor total</th>
          <th class="text-right">Valor/animal</th>
        </tr>
      </thead>
      <tbody id="tbody-fletes"></tbody>
    </table>
  </div>
</div>

<!-- Modal nuevo flete -->
<div id="modal-flete" class="hidden fixed inset-0 z-50 bg-black bg-opacity-40 flex items-center justify-center p-4">
  <div class="bg-white rounded-xl shadow-2xl w-full max-w-lg p-6">
    <h3 class="font-display text-tierra-800 font-semibold mb-4">Registrar flete de salida</h3>
    <div class="grid grid-cols-2 gap-3">
      <div class="col-span-2">
        <label class="form-label">Empresa *</label>
        <select id="flete-empresa" class="input-base">
          <option value="">Seleccione...</option>
        </select>
      </div>
      <div>
        <label class="form-label">Fecha *</label>
        <input type="date" id="flete-fecha" class="input-base" value="<?= date('Y-m-d') ?>">
      </div>
      <div>
        <label class="form-label">Vehículo / Placa</label>
        <input type="text" id="flete-vehiculo" class="input-base" placeholder="Camión TXK-213">
      </div>
      <div class="col-span-2">
        <label class="form-label">Origen *</label>
        <input type="text" id="flete-origen" class="input-base" placeholder="Finca / Vereda">
      </div>
      <div class="col-span-2">
        <label class="form-label">Destino *</label>
        <input type="text" id="flete-destino" class="input-base" placeholder="Frigorífico / Lugar de entrega">
      </div>
      <div>
        <label class="form-label">Cantidad de animales *</label>
        <input type="number" id="flete-cantidad" min="1" class="input-base"
               placeholder="15" oninput="calcFlete()">
      </div>
      <div>
        <label class="form-label">Valor total flete *</label>
        <input type="number" id="flete-valor" step="0.01" min="0" class="input-base"
               placeholder="525000" oninput="calcFlete()">
      </div>
      <div class="col-span-2">
        <div class="bg-tierra-800 rounded-lg p-3 flex justify-between items-center">
          <span class="text-tierra-300 text-sm">Valor por animal:</span>
          <span id="flete-calc-animal" class="text-white font-bold text-lg">—</span>
        </div>
      </div>
      <div class="col-span-2">
        <label class="form-label">Observación</label>
        <input type="text" id="flete-obs" class="input-base" placeholder="Opcional">
      </div>
    </div>
    <div class="flex justify-end gap-3 mt-5">
      <button onclick="cerrarModal()" class="btn btn-outline">Cancelar</button>
      <button id="btn-guardar-flete" onclick="guardarFlete()" class="btn btn-verde">Guardar flete</button>
    </div>
  </div>
</div>

<script src="<?= APP_URL ?>/js/app.js"></script>
<script>
(async function init() {
  // Cargar empresas para el modal
  const resEmp = await App.get(APP_URL + '/api/catalogos.php', { recurso: 'empresas' });
  if (resEmp.ok) App.populateSelect('flete-empresa', resEmp.data.data, 'id', 'nombre', 'Seleccione empresa...');

  await cargar();
})();

async function cargar() {
  const res = await App.get(APP_URL + '/api/catalogos.php', { recurso: 'fletes' });
  document.getElementById('loader-fletes').classList.add('hidden');
  if (!res.ok) return;

  App.renderTable('tbody-fletes', res.data.data, [
    { key: 'empresa' },
    { key: 'fecha',    render: r => App.fecha(r.fecha) },
    { key: 'origen' },
    { key: 'destino' },
    { key: 'vehiculo', render: r => r.vehiculo || '—' },
    { key: 'cantidad_animales', render: r => r.cantidad_animales + ' cab.' },
    { key: 'valor_total',      render: r => App.moneda(r.valor_total) },
    { key: 'valor_por_animal', render: r =>
        `<span class="font-medium text-verde-700">${App.moneda(r.valor_por_animal)}</span>` },
  ]);
}

function calcFlete() {
  const cantidad = parseFloat(document.getElementById('flete-cantidad').value) || 0;
  const valor    = parseFloat(document.getElementById('flete-valor').value)    || 0;
  document.getElementById('flete-calc-animal').textContent =
    (cantidad > 0) ? App.moneda(valor / cantidad) + ' / animal' : '—';
}

function abrirModal() {
  document.getElementById('modal-flete').classList.remove('hidden');
}
function cerrarModal() {
  document.getElementById('modal-flete').classList.add('hidden');
  ['flete-empresa','flete-fecha','flete-vehiculo','flete-origen',
   'flete-destino','flete-cantidad','flete-valor','flete-obs'].forEach(id => {
    const el = document.getElementById(id);
    if (el) el.value = id === 'flete-fecha' ? '<?= date('Y-m-d') ?>' : '';
  });
  document.getElementById('flete-calc-animal').textContent = '—';
}

async function guardarFlete() {
  const empresa  = document.getElementById('flete-empresa').value;
  const fecha    = document.getElementById('flete-fecha').value;
  const origen   = document.getElementById('flete-origen').value.trim();
  const destino  = document.getElementById('flete-destino').value.trim();
  const cantidad = document.getElementById('flete-cantidad').value;
  const valor    = document.getElementById('flete-valor').value;

  if (!empresa || !fecha || !origen || !destino || !cantidad || !valor) {
    App.toast('Complete todos los campos obligatorios.', 'error'); return;
  }

  const body = {
    id_empresa:        empresa,
    fecha,
    origen,
    destino,
    vehiculo:          document.getElementById('flete-vehiculo').value.trim() || null,
    cantidad_animales: parseInt(cantidad),
    valor_total:       parseFloat(valor),
    observacion:       document.getElementById('flete-obs').value.trim() || null,
  };

  const btn = document.getElementById('btn-guardar-flete');
  btn.disabled = true;
  const res = await App.post(APP_URL + '/api/catalogos.php?recurso=fletes', body);
  btn.disabled = false;

  if (res.ok) {
    App.toast(res.data.message, 'success');
    cerrarModal();
    cargar();
  } else {
    App.toast(res.data.message || 'Error al guardar.', 'error');
  }
}
</script>
<?php require_once __DIR__ . '/../views/layout/footer.php'; ?>
