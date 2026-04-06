<?php
// ============================================================
// animales/pesaje.php — Registro de pesaje y código en finca
// ============================================================
require_once __DIR__ . '/../bootstrap.php';
Auth::requirePermission('animales', 'editar');

$idContrato = (int)($_GET['contrato'] ?? 0);
if ($idContrato <= 0) { header('Location: ' . APP_URL . '/contratos/index.php'); exit; }

$pageTitle = 'Pesaje en finca';
$modulo    = 'animales';
require_once __DIR__ . '/../views/layout/header.php';
?>

<nav class="flex items-center gap-2 text-xs text-tierra-400 mb-5">
  <a href="<?= APP_URL ?>/contratos/index.php" class="hover:text-tierra-600">Contratos</a>
  <span>/</span>
  <a href="<?= APP_URL ?>/contratos/detalle.php?id=<?= $idContrato ?>" class="hover:text-tierra-600">Detalle</a>
  <span>/</span>
  <span class="text-tierra-700">Pesaje en finca</span>
</nav>

<div class="flex items-center justify-between mb-5">
  <div>
    <h2 class="font-display text-tierra-800 text-xl font-bold">Pesaje y códigos en finca</h2>
    <p class="text-tierra-400 text-sm mt-0.5">
      Complete el código de arete y el peso real de cada animal.
      El peso inicial es el estimado al momento de la compra.
    </p>
  </div>
  <button id="btn-guardar-todo" class="btn btn-verde">
    💾 Guardar todos
  </button>
</div>

<!-- Info box -->
<div class="card border-l-4 border-verde-500 mb-4 py-3">
  <div class="flex flex-wrap gap-6 text-sm">
    <span class="text-tierra-500">Contrato: <strong id="info-codigo" class="text-tierra-800">—</strong></span>
    <span class="text-tierra-500">Empresa: <strong id="info-empresa" class="text-tierra-800">—</strong></span>
    <span class="text-tierra-500">Total animales: <strong id="info-total" class="text-tierra-800">—</strong></span>
    <span class="text-tierra-500">Con código: <strong id="info-con-codigo" class="text-verde-700">—</strong></span>
    <span class="text-tierra-500">Con pesaje: <strong id="info-con-peso" class="text-verde-700">—</strong></span>
  </div>
</div>

<!-- Tabla de pesaje -->
<div class="card overflow-hidden p-0">
  <div id="loader-pesaje" class="flex justify-center py-8">
    <div class="w-6 h-6 border-2 border-tierra-200 border-t-verde-500 rounded-full animate-spin"></div>
  </div>
  <div id="contenedor-pesaje" class="hidden overflow-x-auto">
    <table class="tabla-base">
      <thead>
        <tr>
          <th class="w-10">#</th>
          <th>Código / Arete</th>
          <th class="text-right">Peso inicial (kg)</th>
          <th class="text-right">Peso finca (kg)</th>
          <th class="text-right">Costo compra</th>
          <th class="text-right">Flete</th>
          <th class="text-right">Valor promedio/kg</th>
          <th class="text-center">Estado</th>
          <th class="text-center w-16">💾</th>
        </tr>
      </thead>
      <tbody id="tbody-pesaje"></tbody>
    </table>
  </div>
</div>

<script src="<?= APP_URL ?>/js/app.js"></script>
<script src="<?= APP_URL ?>/js/animales.js"></script>
<script>
const ID_CONTRATO = <?= $idContrato ?>;

(async () => {
  // Info del contrato
  const resC = await App.get(APP_URL + '/api/contratos.php', { id: ID_CONTRATO });
  if (resC.ok) {
    const c = resC.data.data;
    document.getElementById('info-codigo').textContent  = c.codigo;
    document.getElementById('info-empresa').textContent = c.empresa_compra;
    document.getElementById('info-total').textContent   = c.cantidad_animales;
  }

  // Animales
  await cargarAnimales();

  document.getElementById('btn-guardar-todo')
    ?.addEventListener('click', () => guardarTodo());
})();

async function cargarAnimales() {
  const res = await App.get(APP_URL + '/api/animales.php', { contrato: ID_CONTRATO });
  document.getElementById('loader-pesaje').classList.add('hidden');
  document.getElementById('contenedor-pesaje').classList.remove('hidden');

  if (!res.ok) { App.toast(res.data.message, 'error'); return; }

  const animales = res.data.data;
  const conCodigo = animales.filter(a => a.codigo).length;
  const conPeso   = animales.filter(a => a.peso_finca_kg).length;

  document.getElementById('info-con-codigo').textContent = conCodigo;
  document.getElementById('info-con-peso').textContent   = conPeso;

  const tbody = document.getElementById('tbody-pesaje');
  if (!animales.length) {
    tbody.innerHTML = '<tr><td colspan="9" class="text-center py-8 text-tierra-400">Sin animales</td></tr>';
    return;
  }

  tbody.innerHTML = animales.map((a, i) => {
    const disabled = a.estado !== 'activo' ? 'disabled' : '';
    const estadoCls = { activo:'bg-verde-100 text-verde-700', vendido:'bg-tierra-200 text-tierra-600',
                        muerto:'bg-red-100 text-red-600' }[a.estado] || '';
    return `
    <tr class="border-b border-tierra-100 hover:bg-tierra-50" data-id="${a.id}">
      <td class="px-4 py-2 text-tierra-400 text-sm">${i+1}</td>
      <td class="px-4 py-2">
        <input type="text" id="codigo-${a.id}" value="${a.codigo||''}"
               placeholder="Arete / código" ${disabled}
               class="input-tabla w-32 ${!disabled?'':'bg-tierra-50 text-tierra-400'}">
      </td>
      <td class="px-4 py-2 text-right text-tierra-500 text-sm">${App.kg(a.peso_inicial_kg)}</td>
      <td class="px-4 py-2">
        <input type="number" step="0.01" min="0"
               id="peso-${a.id}" value="${a.peso_finca_kg||''}"
               placeholder="0.00" ${disabled}
               oninput="calcVkg(${a.id}, ${a.costo_compra_animal}, ${a.costo_flete_animal})"
               class="input-tabla w-24 text-right ${!disabled?'':'bg-tierra-50 text-tierra-400'}">
      </td>
      <td class="px-4 py-2 text-right text-sm">${App.moneda(a.costo_compra_animal)}</td>
      <td class="px-4 py-2 text-right text-sm">${App.moneda(a.costo_flete_animal)}</td>
      <td class="px-4 py-2 text-right text-verde-700 font-medium text-sm" id="vkg-${a.id}">
        ${a.valor_promedio_kg ? App.moneda(a.valor_promedio_kg)+'/kg' : '—'}
      </td>
      <td class="px-4 py-2 text-center">
        <span class="px-2 py-0.5 rounded-full text-xs ${estadoCls}">${a.estado}</span>
      </td>
      <td class="px-4 py-2 text-center">
        ${!disabled
          ? `<button onclick="guardarAnimal(${a.id})"
                     class="btn btn-verde btn-xs" title="Guardar este animal">💾</button>`
          : '—'}
      </td>
    </tr>`;
  }).join('');
}

function calcVkg(id, costoCompra, costoFlete) {
  const peso = parseFloat(document.getElementById(`peso-${id}`)?.value) || 0;
  const el   = document.getElementById(`vkg-${id}`);
  if (!el) return;
  el.textContent = peso > 0
    ? App.moneda((costoCompra + costoFlete) / peso) + '/kg'
    : '—';
}

async function guardarAnimal(id) {
  const codigo = document.getElementById(`codigo-${id}`)?.value.trim();
  const peso   = document.getElementById(`peso-${id}`)?.value;
  if (!codigo && !peso) { App.toast('Ingrese código o peso.', 'warning'); return; }
  const body = {};
  if (codigo) body.codigo        = codigo;
  if (peso)   body.peso_finca_kg = parseFloat(peso);
  const res = await App.put(`${APP_URL}/api/animales.php?id=${id}`, body);
  res.ok ? App.toast('Animal guardado.', 'success') : App.toast(res.data.message, 'error');
}

async function guardarTodo() {
  const filas  = document.querySelectorAll('[data-id]');
  const proms  = [];
  filas.forEach(f => {
    const id     = f.dataset.id;
    const codigo = document.getElementById(`codigo-${id}`)?.value.trim();
    const peso   = document.getElementById(`peso-${id}`)?.value;
    if (!codigo && !peso) return;
    const body = {};
    if (codigo) body.codigo        = codigo;
    if (peso)   body.peso_finca_kg = parseFloat(peso);
    proms.push(App.put(`${APP_URL}/api/animales.php?id=${id}`, body));
  });
  if (!proms.length) { App.toast('Sin cambios pendientes.', 'info'); return; }
  App.toast(`Guardando ${proms.length} animales...`, 'info');
  const res = await Promise.all(proms);
  const err = res.filter(r => !r.ok).length;
  if (!err) {
    App.toast(`${proms.length} animales guardados.`, 'success');
    await cargarAnimales();
  } else {
    App.toast(`${err} errores al guardar.`, 'error');
  }
}
</script>
<?php require_once __DIR__ . '/../views/layout/footer.php'; ?>
