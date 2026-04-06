<?php
// ============================================================
// admin/empresas.php — CRUD Empresas
// ============================================================
require_once __DIR__ . '/../bootstrap.php';
Auth::requirePermission('empresas', 'ver');

$pageTitle = 'Empresas';
$modulo    = 'empresas';
require_once __DIR__ . '/../views/layout/header.php';
?>

<div class="flex items-center justify-between mb-5">
  <div>
    <h2 class="font-display text-tierra-800 text-xl font-bold">Empresas</h2>
    <p class="text-tierra-400 text-sm mt-0.5">Empresas que participan en compras y ventas</p>
  </div>
  <?php if (Auth::can('empresas','crear')): ?>
  <button onclick="abrirModal()" class="btn btn-verde">+ Nueva empresa</button>
  <?php endif; ?>
</div>

<div class="card overflow-hidden p-0">
  <div id="loader-emp" class="flex justify-center py-8">
    <div class="w-5 h-5 border-2 border-tierra-200 border-t-verde-500 rounded-full animate-spin"></div>
  </div>
  <div class="overflow-x-auto">
    <table class="tabla-base">
      <thead>
        <tr>
          <th>Nombre</th>
          <th>NIT</th>
          <th>Teléfono</th>
          <th>Dirección</th>
          <th class="text-center">Estado</th>
          <th></th>
        </tr>
      </thead>
      <tbody id="tbody-empresas"></tbody>
    </table>
  </div>
</div>

<!-- Modal -->
<div id="modal-empresa" class="hidden fixed inset-0 z-50 bg-black bg-opacity-40 flex items-center justify-center p-4">
  <div class="bg-white rounded-xl shadow-2xl w-full max-w-md p-6">
    <h3 class="font-display text-tierra-800 font-semibold mb-4" id="modal-titulo">Nueva empresa</h3>
    <input type="hidden" id="emp-id">
    <div class="space-y-3">
      <div>
        <label class="form-label">Nombre *</label>
        <input type="text" id="emp-nombre" class="input-base" placeholder="Nombre de la empresa">
      </div>
      <div class="grid grid-cols-2 gap-3">
        <div>
          <label class="form-label">NIT</label>
          <input type="text" id="emp-nit" class="input-base" placeholder="900123456-1">
        </div>
        <div>
          <label class="form-label">Teléfono</label>
          <input type="text" id="emp-telefono" class="input-base" placeholder="3101234567">
        </div>
      </div>
      <div>
        <label class="form-label">Dirección</label>
        <input type="text" id="emp-direccion" class="input-base" placeholder="Vereda / Ciudad">
      </div>
    </div>
    <div class="flex justify-end gap-3 mt-5">
      <button onclick="cerrarModal()" class="btn btn-outline">Cancelar</button>
      <button id="btn-guardar-emp" onclick="guardarEmpresa()" class="btn btn-verde">Guardar</button>
    </div>
  </div>
</div>

<script src="<?= APP_URL ?>/js/app.js"></script>
<script>
const EMP_API = APP_URL + '/api/catalogos.php?recurso=empresas';

async function cargar() {
  const res = await App.get(APP_URL + '/api/catalogos.php', { recurso: 'empresas' });
  document.getElementById('loader-emp').classList.add('hidden');
  if (!res.ok) return;

  App.renderTable('tbody-empresas', res.data.data, [
    { key: 'nombre' },
    { key: 'nit',      render: r => r.nit      || '—' },
    { key: 'telefono', render: r => r.telefono || '—' },
    { key: 'direccion',render: r => r.direccion|| '—' },
    { key: 'activa', render: r => r.activa == 1
        ? '<span class="px-2 py-0.5 rounded-full text-xs bg-verde-100 text-verde-700">Activa</span>'
        : '<span class="px-2 py-0.5 rounded-full text-xs bg-red-100 text-red-600">Inactiva</span>' },
    { render: r => `
        <div class="flex gap-1.5">
          <?php if (Auth::can('empresas','editar')): ?>
          <button onclick="editarEmpresa(${r.id},'${r.nombre}','${r.nit||''}','${r.telefono||''}','${r.direccion||''}')"
                  class="btn btn-outline btn-xs">Editar</button>
          <?php endif; ?>
        </div>` },
  ]);
}

function abrirModal(id='',nombre='',nit='',telefono='',direccion='') {
  document.getElementById('modal-empresa').classList.remove('hidden');
  document.getElementById('modal-titulo').textContent = id ? 'Editar empresa' : 'Nueva empresa';
  document.getElementById('emp-id').value        = id;
  document.getElementById('emp-nombre').value    = nombre;
  document.getElementById('emp-nit').value       = nit;
  document.getElementById('emp-telefono').value  = telefono;
  document.getElementById('emp-direccion').value = direccion;
}

function editarEmpresa(id,nombre,nit,telefono,direccion) { abrirModal(id,nombre,nit,telefono,direccion); }
function cerrarModal() { document.getElementById('modal-empresa').classList.add('hidden'); }

async function guardarEmpresa() {
  const id      = document.getElementById('emp-id').value;
  const nombre  = document.getElementById('emp-nombre').value.trim();
  if (!nombre) { App.toast('El nombre es obligatorio.', 'error'); return; }

  const body = {
    nombre,
    nit:       document.getElementById('emp-nit').value.trim()       || null,
    telefono:  document.getElementById('emp-telefono').value.trim()  || null,
    direccion: document.getElementById('emp-direccion').value.trim() || null,
  };

  const btn = document.getElementById('btn-guardar-emp');
  btn.disabled = true;

  const res = id
    ? await App.put(APP_URL + '/api/catalogos.php?recurso=empresas&id=' + id, body)
    : await App.post(APP_URL + '/api/catalogos.php?recurso=empresas', body);

  btn.disabled = false;
  if (res.ok) { App.toast(res.data.message, 'success'); cerrarModal(); cargar(); }
  else        { App.toast(res.data.message, 'error'); }
}

cargar();
</script>
<?php require_once __DIR__ . '/../views/layout/footer.php'; ?>
