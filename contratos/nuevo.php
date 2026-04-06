<?php
// ============================================================
// contratos/nuevo.php — Formulario de nuevo contrato
// ============================================================
require_once __DIR__ . '/../bootstrap.php';
Auth::requirePermission('contratos', 'crear');

$pageTitle = 'Nuevo contrato de compra';
$modulo    = 'contratos';
require_once __DIR__ . '/../views/layout/header.php';
?>

<!-- Breadcrumb -->
<nav class="flex items-center gap-2 text-xs text-tierra-400 mb-5">
  <a href="<?= APP_URL ?>/contratos/index.php" class="hover:text-tierra-600">Contratos</a>
  <span>/</span>
  <span class="text-tierra-700">Nuevo contrato</span>
</nav>

<form id="form-contrato" class="space-y-5">

  <!-- ENCABEZADO DEL CONTRATO -->
  <div class="card">
    <div class="flex items-center gap-2 mb-4">
      <span class="w-6 h-6 rounded-full bg-verde-100 text-verde-700 flex items-center
                   justify-center text-xs font-bold">1</span>
      <h3 class="font-display text-tierra-800 font-semibold">Información de la compra</h3>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
      <div>
        <label class="form-label">Empresa que compra *</label>
        <select id="id_empresa_compra" class="input-base" required>
          <option value="">Seleccione...</option>
        </select>
      </div>
      <div>
        <label class="form-label">Empresa que paga *</label>
        <select id="id_empresa_pago" class="input-base" required>
          <option value="">Seleccione...</option>
        </select>
      </div>
      <div>
        <label class="form-label">Proveedor (a quién se compra) *</label>
        <select id="id_proveedor" class="input-base" required>
          <option value="">Seleccione...</option>
        </select>
      </div>
      <div>
        <label class="form-label">Tipo de animal *</label>
        <select id="id_tipo_animal" class="input-base" required>
          <option value="">Seleccione...</option>
        </select>
      </div>
      <div>
        <label class="form-label">Edad aproximada (meses)</label>
        <input type="number" id="edad_meses" min="0" max="120"
               class="input-base" placeholder="Ej: 18">
      </div>
      <div>
        <label class="form-label">Fecha de compra *</label>
        <input type="date" id="fecha_compra" class="input-base" required
               value="<?= date('Y-m-d') ?>">
      </div>
      <div>
        <label class="form-label">Fecha de factura</label>
        <input type="date" id="fecha_factura" class="input-base">
      </div>
      <div>
        <label class="form-label">Número de factura</label>
        <input type="text" id="numero_factura" class="input-base" placeholder="FV-001">
      </div>
    </div>
  </div>

  <!-- VALORES DEL LOTE -->
  <div class="card">
    <div class="flex items-center gap-2 mb-4">
      <span class="w-6 h-6 rounded-full bg-verde-100 text-verde-700 flex items-center
                   justify-center text-xs font-bold">2</span>
      <h3 class="font-display text-tierra-800 font-semibold">Detalle del lote</h3>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-4">
      <div>
        <label class="form-label">Cantidad de animales *</label>
        <input type="number" id="cantidad_animales" min="1" class="input-base"
               placeholder="15" required oninput="FormContrato.calcular()">
      </div>
      <div>
        <label class="form-label">Peso total del lote (kg) *</label>
        <input type="number" id="peso_total_kg" step="0.01" min="0" class="input-base"
               placeholder="5000.00" required oninput="FormContrato.calcular()">
      </div>
      <div>
        <label class="form-label">Valor de compra ($ / kg) *</label>
        <input type="number" id="valor_unitario_kg" step="0.01" min="0" class="input-base"
               placeholder="4500" required oninput="FormContrato.calcular()">
        <p class="text-xs text-tierra-400 mt-0.5">Precio pagado por cada kg de ganado</p>
      </div>
      <div>
        <label class="form-label">Costo del flete entrada</label>
        <input type="number" id="costo_flete" step="0.01" min="0" class="input-base"
               placeholder="0" value="0" oninput="FormContrato.calcular()">
      </div>
    </div>

    <!-- Cálculos en tiempo real -->
    <div class="grid grid-cols-2 md:grid-cols-5 gap-3">
      <div class="bg-tierra-800 rounded-lg p-3 text-tierra-100">
        <p class="text-xs text-tierra-400 mb-0.5">Peso promedio / animal</p>
        <p class="font-display font-bold text-sm" id="calc-peso-promedio">—</p>
      </div>
      <div class="bg-tierra-800 rounded-lg p-3 text-tierra-100">
        <p class="text-xs text-tierra-400 mb-0.5">Costo compra / animal</p>
        <p class="font-display font-bold text-sm" id="calc-costo-animal">—</p>
      </div>
      <div class="bg-tierra-800 rounded-lg p-3 text-tierra-100">
        <p class="text-xs text-tierra-400 mb-0.5">Flete / animal</p>
        <p class="font-display font-bold text-sm" id="calc-flete-animal">—</p>
      </div>
      <div class="bg-tierra-800 rounded-lg p-3 text-tierra-100">
        <p class="text-xs text-tierra-400 mb-0.5">Costo total / animal</p>
        <p class="font-display font-bold text-sm text-verde-400" id="calc-costo-total-animal">—</p>
      </div>
      <div class="bg-verde-800 rounded-lg p-3 text-tierra-100 md:col-span-1">
        <p class="text-xs text-verde-300 mb-0.5">VALOR TOTAL DEL LOTE</p>
        <p class="font-display font-bold text-base" id="calc-valor-total">—</p>
      </div>
    </div>
  </div>

  <!-- SOCIOS DE LA SOCIEDAD -->
  <div class="card">
    <div class="flex items-center justify-between mb-4">
      <div class="flex items-center gap-2">
        <span class="w-6 h-6 rounded-full bg-verde-100 text-verde-700 flex items-center
                     justify-center text-xs font-bold">3</span>
        <h3 class="font-display text-tierra-800 font-semibold">Socios en este contrato</h3>
      </div>
      <div id="resumen-socios" class="text-sm text-tierra-400">Sin socios seleccionados</div>
    </div>

    <p class="text-xs text-tierra-400 mb-3">
      Seleccione los socios que participan. Las ganancias se repartirán en partes iguales.
    </p>

    <div id="lista-socios"
         class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-1 max-h-52 overflow-y-auto
                border border-tierra-100 rounded-lg p-3 bg-tierra-50">
      <p class="text-tierra-400 text-sm text-center py-4 col-span-3">Cargando socios...</p>
    </div>
  </div>

  <!-- OBSERVACIÓN -->
  <div class="card">
    <label class="form-label">Observaciones</label>
    <textarea id="observacion" rows="2" class="input-base resize-none"
              placeholder="Notas adicionales sobre esta compra..."></textarea>
  </div>

  <!-- ACCIONES -->
  <div class="flex items-center justify-end gap-3">
    <a href="<?= APP_URL ?>/contratos/index.php" class="btn btn-outline">Cancelar</a>
    <button type="submit" id="btn-guardar" class="btn btn-verde">
      Crear contrato
    </button>
  </div>

</form>

<script src="<?= APP_URL ?>/js/app.js"></script>
<script>
const API     = APP_URL + '/api/contratos.php';
const API_CAT = APP_URL + '/api/catalogos.php';

let sociosSeleccionados = [];

const FormContrato = {
  calcular() {
    const cantidad    = parseFloat(document.getElementById('cantidad_animales')?.value) || 0;
    const pesoTotal   = parseFloat(document.getElementById('peso_total_kg')?.value)     || 0;
    const valorKg     = parseFloat(document.getElementById('valor_unitario_kg')?.value) || 0;
    const flete       = parseFloat(document.getElementById('costo_flete')?.value)       || 0;

    // Fórmula correcta: precio por kg
    const pesoPromedio       = cantidad > 0 ? pesoTotal / cantidad : 0;
    const costoCompraAnimal  = valorKg * pesoPromedio;           // costo compra de 1 animal
    const fletePorAnimal     = cantidad > 0 ? flete / cantidad : 0;
    const costoTotalAnimal   = costoCompraAnimal + fletePorAnimal;
    const valorTotalLote     = valorKg * pesoTotal;              // valor total = $/kg × kg totales

    document.getElementById('calc-peso-promedio').textContent       = pesoPromedio > 0 ? App.kg(pesoPromedio) : '—';
    document.getElementById('calc-costo-animal').textContent        = costoCompraAnimal > 0 ? App.moneda(costoCompraAnimal) : '—';
    document.getElementById('calc-flete-animal').textContent        = App.moneda(fletePorAnimal);
    document.getElementById('calc-costo-total-animal').textContent  = costoTotalAnimal > 0 ? App.moneda(costoTotalAnimal) : '—';
    document.getElementById('calc-valor-total').textContent         = valorTotalLote > 0 ? App.moneda(valorTotalLote) : '—';
  },
};

// Toggle socio
function toggleSocio(chk) {
  if (chk.checked) {
    sociosSeleccionados.push(chk.value);
  } else {
    sociosSeleccionados = sociosSeleccionados.filter(s => s !== chk.value);
  }
  const n   = sociosSeleccionados.length;
  const pct = n > 0 ? (100/n).toFixed(2) : 0;
  document.getElementById('resumen-socios').innerHTML = n === 0
    ? '<span class="text-tierra-400">Sin socios seleccionados</span>'
    : `<span class="text-verde-700 font-medium">${n} socio(s) — ${pct}% c/u</span>`;
}

// Cargar catálogos
(async () => {
  const [empresas, proveedores, tipos, socios] = await Promise.all([
    App.get(API_CAT, { recurso: 'empresas' }),
    App.get(API_CAT, { recurso: 'proveedores' }),
    App.get(API_CAT, { recurso: 'tipos' }),
    App.get(API_CAT, { recurso: 'socios' }),
  ]);

  if (empresas.ok) {
    App.populateSelect('id_empresa_compra', empresas.data.data, 'id', 'nombre');
    App.populateSelect('id_empresa_pago',   empresas.data.data, 'id', 'nombre');
  }
  if (proveedores.ok) App.populateSelect('id_proveedor',   proveedores.data.data, 'id', 'nombre');
  if (tipos.ok)       App.populateSelect('id_tipo_animal', tipos.data.data,        'id', 'nombre');

  if (socios.ok) {
    const container = document.getElementById('lista-socios');
    if (socios.data.data.length === 0) {
      container.innerHTML = '<p class="text-tierra-400 text-sm text-center py-3 col-span-3">No hay socios registrados</p>';
    } else {
      container.innerHTML = socios.data.data.map(s => `
        <label class="flex items-center gap-2 p-2 rounded hover:bg-white cursor-pointer">
          <input type="checkbox" value="${s.id}"
                 class="accent-verde-600 w-4 h-4"
                 onchange="toggleSocio(this)">
          <div>
            <span class="text-sm text-tierra-800 font-medium">${s.nombre}</span>
            <span class="block text-xs text-tierra-400">${s.empresa}</span>
          </div>
        </label>`).join('');
    }
  }
})();

// Submit
document.getElementById('form-contrato').addEventListener('submit', async (e) => {
  e.preventDefault();

  if (sociosSeleccionados.length === 0) {
    App.toast('Seleccione al menos un socio.', 'error'); return;
  }

  const body = {
    id_empresa_compra: document.getElementById('id_empresa_compra').value,
    id_empresa_pago:   document.getElementById('id_empresa_pago').value,
    id_proveedor:      document.getElementById('id_proveedor').value,
    id_tipo_animal:    document.getElementById('id_tipo_animal').value,
    edad_meses:        document.getElementById('edad_meses').value      || null,
    fecha_compra:      document.getElementById('fecha_compra').value,
    fecha_factura:     document.getElementById('fecha_factura').value   || null,
    numero_factura:    document.getElementById('numero_factura').value  || null,
    cantidad_animales: document.getElementById('cantidad_animales').value,
    peso_total_kg:     document.getElementById('peso_total_kg').value,
    valor_unitario_kg: document.getElementById('valor_unitario_kg').value,
    costo_flete:       document.getElementById('costo_flete').value     || 0,
    observacion:       document.getElementById('observacion').value     || null,
    socios:            sociosSeleccionados,
  };

  const btn = document.getElementById('btn-guardar');
  btn.disabled    = true;
  btn.textContent = 'Guardando...';

  const res = await App.post(API, body);
  btn.disabled    = false;
  btn.textContent = 'Crear contrato';

  if (res.ok) {
    App.toast(res.data.message, 'success');
    setTimeout(() => {
      window.location.href = APP_URL + '/contratos/detalle.php?id=' + res.data.data.id;
    }, 800);
  } else {
    App.toast(res.data.message, 'error');
  }
});
</script>
<?php require_once __DIR__ . '/../views/layout/footer.php'; ?>
