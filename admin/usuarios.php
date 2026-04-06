<?php
// ============================================================
// admin/usuarios.php — CRUD Usuarios
// ============================================================
require_once __DIR__ . '/../bootstrap.php';
Auth::requirePermission('usuarios', 'ver');

$pageTitle = 'Usuarios';
$modulo    = 'usuarios';
require_once __DIR__ . '/../views/layout/header.php';
?>

<div class="flex items-center justify-between mb-5">
  <div>
    <h2 class="font-display text-tierra-800 text-xl font-bold">Usuarios del sistema</h2>
    <p class="text-tierra-400 text-sm mt-0.5">Gestión de acceso y roles</p>
  </div>
  <?php if (Auth::can('usuarios','crear')): ?>
  <button onclick="abrirModal()" class="btn btn-verde">+ Nuevo usuario</button>
  <?php endif; ?>
</div>

<div class="card overflow-hidden p-0">
  <div id="loader-usr" class="flex justify-center py-8">
    <div class="w-5 h-5 border-2 border-tierra-200 border-t-verde-500 rounded-full animate-spin"></div>
  </div>
  <div class="overflow-x-auto">
    <table class="tabla-base">
      <thead>
        <tr>
          <th>Nombre</th>
          <th>Email</th>
          <th>Rol</th>
          <th class="text-center">Estado</th>
          <th></th>
        </tr>
      </thead>
      <tbody id="tbody-usuarios"></tbody>
    </table>
  </div>
</div>

<!-- Modal -->
<div id="modal-usuario" class="hidden fixed inset-0 z-50 bg-black bg-opacity-40 flex items-center justify-center p-4">
  <div class="bg-white rounded-xl shadow-2xl w-full max-w-md p-6">
    <h3 class="font-display text-tierra-800 font-semibold mb-4" id="modal-titulo">Nuevo usuario</h3>
    <input type="hidden" id="usr-id">
    <div class="space-y-3">
      <div>
        <label class="form-label">Nombre completo *</label>
        <input type="text" id="usr-nombre" class="input-base" placeholder="Nombre del usuario">
      </div>
      <div>
        <label class="form-label">Correo electrónico *</label>
        <input type="email" id="usr-email" class="input-base" placeholder="usuario@empresa.com">
      </div>
      <div>
        <label class="form-label" id="lbl-password">Contraseña *</label>
        <input type="password" id="usr-password" class="input-base"
               placeholder="Mínimo 8 caracteres">
        <p class="text-xs text-tierra-400 mt-1" id="hint-password">
          Al editar, dejar en blanco para no cambiar la contraseña.
        </p>
      </div>
      <div>
        <label class="form-label">Rol *</label>
        <select id="usr-rol" class="input-base">
          <option value="">Seleccione...</option>
          <option value="1">Administrador</option>
          <option value="2">Socio</option>
          <option value="3">Operador</option>
        </select>
      </div>
      <div>
        <label class="flex items-center gap-2 cursor-pointer">
          <input type="checkbox" id="usr-activo" checked class="accent-verde-600 w-4 h-4">
          <span class="text-sm text-tierra-700">Usuario activo</span>
        </label>
      </div>
    </div>
    <div class="flex justify-end gap-3 mt-5">
      <button onclick="cerrarModal()" class="btn btn-outline">Cancelar</button>
      <button id="btn-guardar-usr" onclick="guardarUsuario()" class="btn btn-verde">Guardar</button>
    </div>
  </div>
</div>

<script src="<?= APP_URL ?>/js/app.js"></script>
<script>
async function cargar() {
  const res = await fetch(APP_URL + '/api/usuarios.php');
  const data = await res.json();
  document.getElementById('loader-usr').classList.add('hidden');
  if (!data.success) return;

  App.renderTable('tbody-usuarios', data.data, [
    { key: 'nombre' },
    { key: 'email' },
    { key: 'rol' },
    { key: 'activo', render: r => r.activo == 1
        ? '<span class="px-2 py-0.5 rounded-full text-xs bg-verde-100 text-verde-700">Activo</span>'
        : '<span class="px-2 py-0.5 rounded-full text-xs bg-red-100 text-red-600">Inactivo</span>' },
    { render: r => `
        <?php if (Auth::can('usuarios','editar')): ?>
        <button onclick="editarUsuario(${JSON.stringify(r).replace(/"/g,'&quot;')})"
                class="btn btn-outline btn-xs">Editar</button>
        <?php endif; ?>` },
  ]);
}

function abrirModal() {
  document.getElementById('modal-usuario').classList.remove('hidden');
  document.getElementById('modal-titulo').textContent = 'Nuevo usuario';
  document.getElementById('hint-password').classList.add('hidden');
  ['usr-id','usr-nombre','usr-email','usr-password'].forEach(id =>
    document.getElementById(id).value = '');
  document.getElementById('usr-rol').value   = '';
  document.getElementById('usr-activo').checked = true;
}

function editarUsuario(u) {
  document.getElementById('modal-usuario').classList.remove('hidden');
  document.getElementById('modal-titulo').textContent     = 'Editar usuario';
  document.getElementById('hint-password').classList.remove('hidden');
  document.getElementById('usr-id').value                 = u.id;
  document.getElementById('usr-nombre').value             = u.nombre;
  document.getElementById('usr-email').value              = u.email;
  document.getElementById('usr-password').value           = '';
  document.getElementById('usr-rol').value                = u.id_rol;
  document.getElementById('usr-activo').checked           = u.activo == 1;
}

function cerrarModal() { document.getElementById('modal-usuario').classList.add('hidden'); }

async function guardarUsuario() {
  const id       = document.getElementById('usr-id').value;
  const nombre   = document.getElementById('usr-nombre').value.trim();
  const email    = document.getElementById('usr-email').value.trim();
  const password = document.getElementById('usr-password').value;
  const rol      = document.getElementById('usr-rol').value;

  if (!nombre) { App.toast('El nombre es obligatorio.', 'error');   return; }
  if (!email)  { App.toast('El email es obligatorio.', 'error');    return; }
  if (!rol)    { App.toast('Seleccione un rol.', 'error');          return; }
  if (!id && !password) { App.toast('La contraseña es obligatoria para nuevos usuarios.', 'error'); return; }

  const body = {
    nombre, email, rol,
    activo: document.getElementById('usr-activo').checked ? 1 : 0,
  };
  if (password) body.password = password;

  const btn = document.getElementById('btn-guardar-usr');
  btn.disabled = true;

  const url = id
    ? APP_URL + '/api/usuarios.php?id=' + id
    : APP_URL + '/api/usuarios.php';
  const res = await App[id ? 'put' : 'post'](url, body);

  btn.disabled = false;
  if (res.ok) { App.toast(res.data.message, 'success'); cerrarModal(); cargar(); }
  else        { App.toast(res.data.message, 'error'); }
}

cargar();
</script>
<?php require_once __DIR__ . '/../views/layout/footer.php'; ?>
