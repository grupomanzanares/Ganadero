<?php
// ============================================================
// admin/socios.php — CRUD Socios
// ============================================================
require_once __DIR__ . '/../bootstrap.php';
Auth::requirePermission('socios', 'ver');

$pageTitle = 'Socios';
$modulo    = 'socios';
require_once __DIR__ . '/../views/layout/header.php';
?>

<div class="flex items-center justify-between mb-5">
  <div>
    <h2 class="font-display text-tierra-800 text-xl font-bold">Socios</h2>
    <p class="text-tierra-400 text-sm mt-0.5">Personas que participan en los contratos de compra</p>
  </div>
  <?php if (Auth::can('socios','crear')): ?>
  <button onclick="abrirModal()" class="btn btn-verde">+ Nuevo socio</button>
  <?php endif; ?>
</div>

<!-- Filtro empresa -->
<div class="card mb-4 py-3">
  <div class="flex gap-3 items-end">
    <div>
      <label class="form-label">Filtrar por empresa</label>
      <select id="filtro-empresa-socio" class="input-base w-52"
              onchange="cargar()">
        <option value="">Todas las empresas</option>
      </select>
    </div>
  </div>
</div>

<div class="card overflow-hidden p-0">
  <div id="loader-socios" class="flex justify-center py-8">
    <div class="w-5 h-5 border-2 border-tierra-200 border-t-verde-500 rounded-full animate-spin"></div>
  </div>
  <div class="overflow-x-auto">
    <table class="tabla-base">
      <thead>
        <tr>
          <th>Nombre</th>
          <th>Empresa</th>
          <th>Cédula</th>
          <th>Teléfono</th>
          <th>Email</th>
          <th class="text-center">Estado</th>
          <th></th>
        </tr>
      </thead>
      <tbody id="tbody-socios"></tbody>
    </table>
  </div>
</div>

<!-- Modal -->
<div id="modal-socio" class="hidden fixed inset-0 z-50 bg-black bg-opacity-40 flex items-center justify-center p-4">
  <div class="bg-white rounded-xl shadow-2xl w-full max-w-md p-6">
    <h3 class="font-display text-tierra-800 font-semibold mb-4" id="modal-titulo">Nuevo socio</h3>
    <input type="hidden" id="socio-id">
    <div class="space-y-3">
      <div>
        <label class="form-label">Empresa *</label>
        <select id="socio-empresa" class="input-base">
          <option value="">Seleccione...</option>
        </select>
      </div>
      <div>
        <label class="form-label">Nombre completo *</label>
        <input type="text" id="socio-nombre" class="input-base" placeholder="Nombre del socio">
      </div>
      <div class="grid grid-cols-2 gap-3">
        <div>
          <label class="form-label">Cédula</label>
          <input type="text" id="socio-cedula" class="input-base" placeholder="79456123">
        </div>
        <div>
          <label class="form-label">Teléfono</label>
          <input type="text" id="socio-telefono" class="input-base" placeholder="3101234567">
        </div>
      </div>
      <div>
        <label class="form-label">Email</label>
        <input type="email" id="socio-email" class="input-base" placeholder="socio@email.com">
      </div>
    </div>
    <div class="flex justify-end gap-3 mt-5">
      <button onclick="cerrarModal()" class="btn btn-outline">Cancelar</button>
      <button id="btn-guardar-socio" onclick="guardarSocio()" class="btn btn-verde">Guardar</button>
    </div>
  </div>
</div>

<script src="<?= APP_URL ?>/js/app.js"></script>
<script>
let empresasData = [];

(async function init() {
  const res = await App.get(APP_URL + '/api/catalogos.php', { recurso: 'empresas' });
  if (res.ok) {
    empresasData = res.data.data;
    App.populateSelect('filtro-empresa-socio', empresasData, 'id', 'nombre', 'Todas las empresas');
    App.populateSelect('socio-empresa', empresasData, 'id', 'nombre', 'Seleccione empresa...');
  }
  cargar();
})();

async function cargar() {
  const idEmpresa = document.getElementById('filtro-empresa-socio').value;
  const params = { recurso: 'socios' };
  if (idEmpresa) params.empresa = idEmpresa;

  const res = await App.get(APP_URL + '/api/catalogos.php', params);
  document.getElementById('loader-socios').classList.add('hidden');
  if (!res.ok) return;

  App.renderTable('tbody-socios', res.data.data, [
    { key: 'nombre' },
    { key: 'empresa' },
    { key: 'cedula',   render: r => r.cedula   || '—' },
    { key: 'telefono', render: r => r.telefono || '—' },
    { key: 'email',    render: r => r.email    || '—' },
    { key: 'activo', render: r => r.activo == 1
        ? '<span class="px-2 py-0.5 rounded-full text-xs bg-verde-100 text-verde-700">Activo</span>'
        : '<span class="px-2 py-0.5 rounded-full text-xs bg-red-100 text-red-600">Inactivo</span>' },
    { render: r => `
        <?php if (Auth::can('socios','editar')): ?>
        <button onclick="editarSocio(${JSON.stringify(r).replace(/"/g,'&quot;')})"
                class="btn btn-outline btn-xs">Editar</button>
        <?php endif; ?>` },
  ]);
}

function abrirModal() {
  document.getElementById('modal-socio').classList.remove('hidden');
  document.getElementById('modal-titulo').textContent = 'Nuevo socio';
  ['socio-id','socio-nombre','socio-cedula','socio-telefono','socio-email'].forEach(id => {
    document.getElementById(id).value = '';
  });
  document.getElementById('socio-empresa').value = '';
}

function editarSocio(s) {
  document.getElementById('modal-socio').classList.remove('hidden');
  document.getElementById('modal-titulo').textContent     = 'Editar socio';
  document.getElementById('socio-id').value               = s.id;
  document.getElementById('socio-empresa').value          = s.id_empresa || '';
  document.getElementById('socio-nombre').value           = s.nombre    || '';
  document.getElementById('socio-cedula').value           = s.cedula    || '';
  document.getElementById('socio-telefono').value         = s.telefono  || '';
  document.getElementById('socio-email').value            = s.email     || '';
}

function cerrarModal() { document.getElementById('modal-socio').classList.add('hidden'); }

async function guardarSocio() {
  const id      = document.getElementById('socio-id').value;
  const nombre  = document.getElementById('socio-nombre').value.trim();
  const empresa = document.getElementById('socio-empresa').value;
  if (!nombre)  { App.toast('El nombre es obligatorio.', 'error');   return; }
  if (!empresa) { App.toast('Seleccione una empresa.', 'error');     return; }

  const body = {
    id_empresa: empresa,
    nombre,
    cedula:   document.getElementById('socio-cedula').value.trim()   || null,
    telefono: document.getElementById('socio-telefono').value.trim() || null,
    email:    document.getElementById('socio-email').value.trim()    || null,
    activo:   1,
  };

  const btn = document.getElementById('btn-guardar-socio');
  btn.disabled = true;
  const res = id
    ? await App.put(APP_URL + '/api/catalogos.php?recurso=socios&id=' + id, body)
    : await App.post(APP_URL + '/api/catalogos.php?recurso=socios', body);

  btn.disabled = false;
  if (res.ok) { App.toast(res.data.message, 'success'); cerrarModal(); cargar(); }
  else        { App.toast(res.data.message, 'error'); }
}
</script>
<?php require_once __DIR__ . '/../views/layout/footer.php'; ?>
