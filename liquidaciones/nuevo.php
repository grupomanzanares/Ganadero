<?php
// ============================================================
// liquidaciones/nuevo.php — Formulario de nueva liquidación
// ============================================================
require_once __DIR__ . '/../bootstrap.php';
Auth::requirePermission('liquidaciones', 'crear');

$idContrato = (int)($_GET['contrato'] ?? 0);
$pageTitle  = 'Nueva liquidación';
$modulo     = 'liquidaciones';
require_once __DIR__ . '/../views/layout/header.php';
?>

<style>
/* ── Inputs de la tabla sin flechas spinner ── */
input[type=number]::-webkit-inner-spin-button,
input[type=number]::-webkit-outer-spin-button { -webkit-appearance:none; margin:0; }
input[type=number] { -moz-appearance:textfield; appearance:textfield; }

/* ── Input de peso (salida y canal): limpio, fluido ── */
.inp-tabla {
  width: 78px;
  padding: 4px 6px;
  border: 1px solid #e2e8f0;
  border-radius: 4px;
  font-size: 0.8rem;
  text-align: right;
  background: #fff;
  outline: none;
  transition: border-color .12s, box-shadow .12s;
  display: block;
}
.inp-tabla:focus {
  border-color: #059669;
  box-shadow: 0 0 0 2px rgba(5,150,105,.15);
}
.inp-tabla.error {
  border-color: #ef4444;
  background: #fef2f2;
}
.inp-tabla.canal {
  border-color: #e0e7ff;
  background: #f5f7ff;
}
.inp-tabla.canal:focus {
  border-color: #6366f1;
  box-shadow: 0 0 0 2px rgba(99,102,241,.15);
}

/* ── Tabla con columnas fijas de inputs ── */
.col-val   { text-align: right; white-space: nowrap; }
.col-input { padding: 3px 4px !important; }
</style>

<nav class="flex items-center gap-2 text-xs text-slate-400 mb-5">
  <a href="<?= APP_URL ?>/contratos/index.php" class="hover:text-slate-600">Contratos</a>
  <span>/</span>
  <?php if ($idContrato > 0): ?>
  <a href="<?= APP_URL ?>/contratos/detalle.php?id=<?= $idContrato ?>" class="hover:text-slate-600">Detalle</a>
  <span>/</span>
  <?php endif; ?>
  <span class="text-slate-700">Nueva liquidación</span>
</nav>

<!-- ══ PASO 1 + 2 ═════════════════════════════════════════════ -->
<div class="grid grid-cols-1 lg:grid-cols-3 gap-5 mb-5">

  <div class="lg:col-span-2 card">
    <div class="flex items-center gap-2 mb-3">
      <span class="w-6 h-6 rounded-full bg-esm-100 text-esm-700 flex items-center justify-center text-xs font-bold">1</span>
      <h3 class="font-display text-slate-800 font-semibold">Agregar animales</h3>
    </div>
    <div class="flex gap-2">
      <input type="text" id="codigo-animal-input" class="input-base flex-1"
             placeholder="Código / arete — presione Enter o Agregar">
      <button id="btn-buscar-animal" class="btn btn-verde">Agregar</button>
    </div>
  </div>

  <div class="card">
    <div class="flex items-center gap-2 mb-3">
      <span class="w-6 h-6 rounded-full bg-esm-100 text-esm-700 flex items-center justify-center text-xs font-bold">2</span>
      <h3 class="font-display text-slate-800 font-semibold text-sm">Datos de la venta</h3>
    </div>
    <div class="space-y-2">
      <div>
        <label class="form-label">Fecha de venta *</label>
        <input type="date" id="fecha-venta" class="input-base" value="<?= date('Y-m-d') ?>">
      </div>
      <div>
        <label class="form-label">Precio venta ($ / kg) *</label>
        <input type="text" id="valor-venta-kg" inputmode="decimal"
               class="input-base" placeholder="8500" oninput="soloNum(this); calcularVentas()">
      </div>
      <div>
        <label class="form-label">Empresa factura *</label>
        <select id="id_empresa_factura" class="input-base"><option value="">Cargando...</option></select>
      </div>
      <div>
        <label class="form-label">Cliente *</label>
        <select id="id_cliente" class="input-base"><option value="">Cargando...</option></select>
      </div>
      <div>
        <label class="form-label">N° Factura</label>
        <input type="text" id="numero-factura" class="input-base" placeholder="FV-001">
      </div>
    </div>
  </div>
</div>

<!-- ══ PASO 3: Costos adicionales ════════════════════════════ -->
<div class="card mb-5">
  <div class="flex items-center gap-2 mb-3">
    <span class="w-6 h-6 rounded-full bg-esm-100 text-esm-700 flex items-center justify-center text-xs font-bold">3</span>
    <h3 class="font-display text-slate-800 font-semibold">Costos adicionales</h3>
    <span class="text-xs text-slate-400">(se prorratean entre todos los animales)</span>
  </div>
  <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
    <div>
      <label class="form-label">Flete de salida</label>
      <select id="id_flete_salida" class="input-base" onchange="onCambioFlete()">
        <option value="">Sin flete</option>
      </select>
      <p class="text-xs text-slate-400 mt-1" id="txt-flete-info"></p>
    </div>
    <div>
      <label class="form-label">Otros gastos ($)</label>
      <input type="text" id="otros-gastos" inputmode="decimal"
             class="input-base" placeholder="0"
             oninput="soloNum(this); onCambioOtros()">
      <p class="text-xs text-slate-400 mt-1">Vet., vacunas, comisiones…</p>
    </div>
    <div>
      <label class="form-label">Observaciones</label>
      <textarea id="observacion-liq" rows="2" class="input-base resize-none" placeholder="Opcional..."></textarea>
    </div>
  </div>
</div>

<!-- ══ PASO 4: Peso de salida ════════════════════════════════ -->
<div class="card mb-5">
  <div class="flex items-center gap-2 mb-3">
    <span class="w-6 h-6 rounded-full bg-esm-100 text-esm-700 flex items-center justify-center text-xs font-bold">4</span>
    <h3 class="font-display text-slate-800 font-semibold">Peso de salida</h3>
  </div>
  <div class="flex flex-wrap gap-4 items-start">
    <div class="flex gap-3">
      <label class="flex items-center gap-2 cursor-pointer border-2 border-esm-500 bg-esm-50
                    rounded-lg px-4 py-2" id="lbl-modo-total">
        <input type="radio" name="modo_peso" value="total" checked id="radio-total"
               class="accent-esm-600" onchange="cambiarModoPeso('total')">
        <span class="text-sm font-medium text-esm-800">Peso total del lote</span>
      </label>
      <label class="flex items-center gap-2 cursor-pointer border-2 border-slate-200
                    rounded-lg px-4 py-2" id="lbl-modo-individual">
        <input type="radio" name="modo_peso" value="individual" id="radio-individual"
               class="accent-esm-600" onchange="cambiarModoPeso('individual')">
        <span class="text-sm font-medium text-slate-700">Peso individual</span>
      </label>
    </div>
    <div id="bloque-peso-total" class="flex items-center gap-3">
      <div>
        <label class="form-label">Peso total venta (kg) *</label>
        <input type="text" id="peso-total-lote" inputmode="decimal"
               class="input-base w-44" placeholder="Ej: 3850.50"
               oninput="soloNum(this); distribuirPesoTotal()">
      </div>
      <span class="text-xs text-slate-500 font-medium mt-4" id="txt-peso-distribuido"></span>
    </div>
    <div id="bloque-peso-individual" class="hidden">
      <div class="bg-esm-50 border border-esm-200 rounded-lg px-4 py-2">
        <p class="text-sm font-semibold text-esm-800 mb-0.5">✏️ Digite en la columna P.Salida</p>
        <p class="text-xs text-esm-600">
          Use <kbd class="bg-esm-100 px-1 rounded">Tab</kbd> o
          <kbd class="bg-esm-100 px-1 rounded">Enter</kbd> para pasar al siguiente animal.
        </p>
      </div>
    </div>
  </div>
</div>

<!-- ══ TABLA ═════════════════════════════════════════════════ -->
<div class="card overflow-hidden p-0 mb-5">
  <div class="px-4 py-3 border-b border-slate-200 bg-slate-800 flex items-center justify-between">
    <span class="text-sm font-semibold text-slate-100">Detalle de costos por animal</span>
    <span id="contador-animales" class="text-xs text-slate-400">0 animales</span>
  </div>
  <div class="overflow-x-auto">
    <table class="w-full text-xs border-collapse">
      <thead>
        <tr class="text-slate-300 text-center" style="background:#1e293b">
          <th class="px-3 py-2 text-left" rowspan="2">Código</th>
          <!-- COSTO COMPRA -->
          <th class="px-2 py-1 text-center border-l border-slate-600" colspan="3"
              style="background:#4a3010">COSTO COMPRA</th>
          <!-- MANUTENCIÓN -->
          <th class="px-2 py-1 text-center border-l border-slate-600" colspan="3"
              style="background:#2a4a10">MANUTENCIÓN</th>
          <!-- COSTOS VENTA -->
          <th class="px-2 py-1 text-center border-l border-slate-600" colspan="2"
              style="background:#10304a">COSTOS VENTA</th>
          <!-- COSTO TOTAL -->
          <th class="px-2 py-1 text-center border-l border-slate-600" rowspan="2"
              style="background:#5a1010">COSTO<br>TOTAL</th>
          <!-- PESOS DE SALIDA -->
          <th class="px-2 py-1 text-center border-l border-slate-600" colspan="2"
              style="background:#2d3748">PESOS SALIDA</th>
          <!-- VENTA -->
          <th class="px-2 py-1 text-center border-l border-slate-600" colspan="3"
              style="background:#104a20">VENTA</th>
          <th rowspan="2" class="w-20 text-center px-2">TIPO</th>
          <th rowspan="2" class="w-6"></th>
        </tr>
        <tr class="text-center" style="background:#263341;color:#94a3b8">
          <th class="px-2 py-1 border-l border-slate-600">P.Finca</th>
          <th class="px-2 py-1">Compra</th>
          <th class="px-2 py-1">Fl.Ent.</th>
          <th class="px-2 py-1 border-l border-slate-600">Días</th>
          <th class="px-2 py-1">Meses</th>
          <th class="px-2 py-1">Valor</th>
          <th class="px-2 py-1 border-l border-slate-600">Fl.Sal.</th>
          <th class="px-2 py-1">Otros</th>
          <!-- peso salida -->
          <th class="px-2 py-1 border-l border-slate-600" id="th-peso-salida">
            P.Salida (kg) ✏️
          </th>
          <!-- peso canal — solo estadístico -->
          <th class="px-2 py-1" title="Solo estadístico, no afecta cálculos"
              style="color:#a5b4fc">
            P.Canal (kg) 📊
          </th>
          <!-- venta -->
          <th class="px-2 py-1 border-l border-slate-600">$/kg</th>
          <th class="px-2 py-1">Valor</th>
          <th class="px-2 py-1" style="color:#6ee7b7">Ganancia</th>
        </tr>
      </thead>
      <tbody id="tbody-animales-liq">
        <tr>
          <td colspan="17" class="text-center py-10 text-slate-400">
            Agregue animales por código para comenzar
          </td>
        </tr>
      </tbody>
      <tfoot id="tfoot-totales" class="hidden">
        <tr style="background:#1e293b;color:#e2e8f0" class="text-xs font-semibold">
          <td class="px-3 py-2">TOTAL</td>
          <td class="px-2 py-2 text-right" id="tf-pfinca">—</td>
          <td class="px-2 py-2 text-right" id="tf-compra">—</td>
          <td class="px-2 py-2 text-right" id="tf-flent">—</td>
          <td class="px-2 py-2 text-right" id="tf-dias">—</td>
          <td class="px-2 py-2 text-right">—</td>
          <td class="px-2 py-2 text-right" id="tf-mant">—</td>
          <td class="px-2 py-2 text-right" id="tf-flsal">—</td>
          <td class="px-2 py-2 text-right" id="tf-otros">—</td>
          <td class="px-2 py-2 text-right" style="color:#f87171" id="tf-costo">—</td>
          <td class="px-2 py-2 text-right" id="tf-psal">—</td>
          <td class="px-2 py-2 text-right" style="color:#a5b4fc" id="tf-pcanal">—</td>
          <td class="px-2 py-2 text-right">—</td>
          <td class="px-2 py-2 text-right" style="color:#6ee7b7" id="tf-venta">—</td>
          <td class="px-2 py-2 text-right" id="tf-gan">—</td>
          <td colspan="2"></td>
        </tr>
      </tfoot>
    </table>
  </div>
</div>

<!-- ══ RESUMEN + CONFIRMAR ════════════════════════════════════ -->
<div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
  <div class="lg:col-span-2 card py-3">
    <p class="text-xs font-semibold text-slate-500 mb-2">📐 Fórmulas aplicadas</p>
    <div class="grid grid-cols-2 gap-2 text-xs text-slate-500">
      <code class="bg-slate-50 rounded px-2 py-1">Manutención = días ÷ (365/12) × tarifa</code>
      <code class="bg-slate-50 rounded px-2 py-1">Costo total = Compra + Fl.Ent + Manten + Fl.Sal + Otros</code>
      <code class="bg-slate-50 rounded px-2 py-1">Valor venta = Peso salida × $/kg venta</code>
      <code class="bg-slate-50 rounded px-2 py-1 text-indigo-500">P.Canal = solo estadístico (no afecta cálculos)</code>
    </div>
  </div>
  <div class="space-y-3">
    <div class="rounded-xl p-4 text-slate-100" style="background:#1e293b">
      <h4 class="text-xs font-semibold mb-3 text-slate-400 uppercase tracking-wide">Resumen</h4>
      <div class="space-y-1.5 text-sm">
        <div class="flex justify-between">
          <span class="text-slate-400">Animales (venta/total)</span>
          <span id="res-animales" class="font-semibold">0 / 0</span>
        </div>
        <div class="flex justify-between">
          <span class="text-slate-400">Peso total salida</span>
          <span id="res-peso" class="font-semibold">0 kg</span>
        </div>
        <div class="flex justify-between border-t border-slate-700 pt-2">
          <span class="text-slate-400">Costo total</span>
          <span id="res-costo" class="font-semibold text-red-400">$ 0</span>
        </div>
        <div class="flex justify-between">
          <span class="text-slate-400">Valor venta</span>
          <span id="res-venta" class="font-semibold text-esm-400">$ 0</span>
        </div>
        <div class="flex justify-between border-t border-slate-700 pt-2">
          <span class="text-slate-300 font-bold">Ganancia estimada</span>
          <span id="res-ganancia" class="font-bold text-xl">$ 0</span>
        </div>
      </div>
    </div>
    <button id="btn-liquidar" class="btn btn-verde w-full justify-center py-3 text-base font-semibold">
      ✓ Confirmar liquidación
    </button>
  </div>
</div>

<script src="<?= APP_URL ?>/js/app.js"></script>
<script>
// ════════════════════════════════════════════════════════════
// ESTADO GLOBAL
// ════════════════════════════════════════════════════════════
const LIQ_API      = APP_URL + '/api/liquidaciones.php';
const LIQ_API_CAT  = APP_URL + '/api/catalogos.php';
const LIQ_CONTRATO = <?= $idContrato ?: 'null' ?>;

let animalesLiq = [];   // array de animales con todos sus datos
let modoPeso    = 'total';
let fletesData  = [];

// ── Solo dígitos y un punto decimal ──────────────────────
function soloNum(el) {
  let v = el.value.replace(/[^0-9.]/g, '');
  const p = v.split('.');
  if (p.length > 2) v = p[0] + '.' + p.slice(1).join('');
  el.value = v;
}

// ════════════════════════════════════════════════════════════
// INICIALIZACIÓN
// ════════════════════════════════════════════════════════════
(async function init() {
  const [resEmp, resCli, resFle] = await Promise.all([
    App.get(LIQ_API_CAT, { recurso: 'empresas' }),
    App.get(LIQ_API_CAT, { recurso: 'clientes' }),
    App.get(LIQ_API_CAT, { recurso: 'fletes'   }),
  ]);

  if (resEmp.ok && resEmp.data.data?.length)
    App.populateSelect('id_empresa_factura', resEmp.data.data, 'id', 'nombre', 'Seleccione...');
  else
    document.getElementById('id_empresa_factura').innerHTML = '<option value="">Sin empresas</option>';

  if (resCli.ok && resCli.data.data?.length)
    App.populateSelect('id_cliente', resCli.data.data, 'id', 'nombre', 'Seleccione...');
  else
    document.getElementById('id_cliente').innerHTML = '<option value="">Sin clientes</option>';

  if (resFle.ok && resFle.data.data?.length) {
    fletesData = resFle.data.data;
    App.populateSelect('id_flete_salida', fletesData, 'id',
      f => f.fecha + ' — ' + f.origen + ' → ' + f.destino + ' (' + App.moneda(f.valor_por_animal) + '/animal)',
      'Sin flete');
  }

  document.getElementById('btn-buscar-animal').addEventListener('click', buscarAnimal);
  document.getElementById('codigo-animal-input').addEventListener('keypress',
    e => { if (e.key === 'Enter') { e.preventDefault(); buscarAnimal(); } });
  document.getElementById('fecha-venta').addEventListener('change', recalcularFecha);
  document.getElementById('btn-liquidar').addEventListener('click', confirmarLiquidacion);
})();

// ════════════════════════════════════════════════════════════
// MODO DE PESO
// ════════════════════════════════════════════════════════════
function cambiarModoPeso(modo) {
  modoPeso = modo;
  const lblT = document.getElementById('lbl-modo-total');
  const lblI = document.getElementById('lbl-modo-individual');
  const blqT = document.getElementById('bloque-peso-total');
  const blqI = document.getElementById('bloque-peso-individual');

  if (modo === 'total') {
    lblT.className = lblT.className.replace('border-slate-200','border-esm-500');
    lblT.classList.add('bg-esm-50');
    lblI.classList.remove('border-esm-500','bg-esm-50');
    lblI.classList.add('border-slate-200');
    blqT.classList.remove('hidden'); blqI.classList.add('hidden');
    // En modo total los inputs de salida se deshabilitan
    document.querySelectorAll('.inp-salida').forEach(el => { el.disabled = true; el.style.background='#f8fafc'; });
  } else {
    lblI.classList.add('border-esm-500','bg-esm-50');
    lblI.classList.remove('border-slate-200');
    lblT.classList.remove('border-esm-500','bg-esm-50');
    lblT.classList.add('border-slate-200');
    blqI.classList.remove('hidden'); blqT.classList.add('hidden');
    // Habilitar inputs de salida
    document.querySelectorAll('.inp-salida').forEach(el => { el.disabled = false; el.style.background=''; });
    // Foco en el primero
    setTimeout(() => {
      const primer = document.querySelector('.inp-salida:not([disabled])');
      if (primer) primer.focus();
    }, 80);
  }
  actualizarTotalesTabla();
  actualizarResumen();
}

// ════════════════════════════════════════════════════════════
// COSTOS (flete y otros) — sin re-render de filas
// ════════════════════════════════════════════════════════════
function onCambioFlete() {
  const idFlete = document.getElementById('id_flete_salida').value;
  const infoEl  = document.getElementById('txt-flete-info');
  if (idFlete) {
    const f = fletesData.find(x => String(x.id) === String(idFlete));
    if (f) {
      infoEl.textContent  = App.moneda(f.valor_por_animal) + ' por animal incluido en costos';
      infoEl.className    = 'text-xs text-esm-700 mt-1 font-medium';
    }
  } else {
    infoEl.textContent = '';
  }
  recalcularCostosYActualizar();
}

function onCambioOtros() {
  recalcularCostosYActualizar();
}

// Recalcula costos en el array y actualiza los spans de la tabla SIN re-renderizar inputs
function recalcularCostosYActualizar() {
  const idFlete    = document.getElementById('id_flete_salida').value;
  const otrosTotal = parseFloat(document.getElementById('otros-gastos').value) || 0;
  const cant       = animalesLiq.length;
  const otrosPorAn = cant > 0 ? otrosTotal / cant : 0;

  let fletePorAn = 0;
  if (idFlete) {
    const f = fletesData.find(x => String(x.id) === String(idFlete));
    if (f) fletePorAn = parseFloat(f.valor_por_animal);
  }

  animalesLiq.forEach((a, i) => {
    a.costo_flete_salida = fletePorAn;
    a.otros_gastos       = otrosPorAn;
    a.costo_total = (parseFloat(a.costo_compra)        || 0)
                  + (parseFloat(a.costo_flete_entrada) || 0)
                  + (parseFloat(a.costo_manutencion_exacto) || parseFloat(a.costo_manutencion) || 0)
                  + fletePorAn
                  + otrosPorAn;

    // Actualizar solo los spans de costo en esa fila (sin tocar inputs)
    setSpan('span-flsal-'  + i, App.moneda(fletePorAn));
    setSpan('span-otros-'  + i, App.moneda(otrosPorAn));
    setSpan('span-ctotal-' + i, App.moneda(a.costo_total));
    // Recalcular ganancia
    const pesoSal   = a.tipo_salida === 'muerte' ? 0 : (a.peso_salida_kg || 0);
    const precioKg  = parseFloat(document.getElementById('valor-venta-kg').value) || 0;
    const valorVent = pesoSal * precioKg;
    const ganancia  = valorVent - a.costo_total;
    setSpan('span-venta-'  + i, a.tipo_salida==='muerte' ? '$ 0' : App.moneda(valorVent));
    const elGan = document.getElementById('span-gan-' + i);
    if (elGan) {
      elGan.textContent = App.moneda(ganancia);
      elGan.className   = 'col-val ' + (ganancia >= 0 ? 'text-esm-700 font-semibold' : 'text-red-600 font-semibold');
    }
  });

  actualizarTotalesTabla();
  actualizarResumen();
}

function setSpan(id, val) {
  const el = document.getElementById(id);
  if (el) el.textContent = val;
}

// ════════════════════════════════════════════════════════════
// PESO TOTAL — distribuir sin re-render
// ════════════════════════════════════════════════════════════
function distribuirPesoTotal() {
  const pesoTotal  = parseFloat(document.getElementById('peso-total-lote').value) || 0;
  const soloVentas = animalesLiq.filter(a => a.tipo_salida !== 'muerte');
  const cant       = soloVentas.length;
  const txtEl      = document.getElementById('txt-peso-distribuido');

  if (cant > 0 && pesoTotal > 0) {
    const pesoPorAn = Math.round((pesoTotal / cant) * 100) / 100;
    animalesLiq.forEach((a, i) => {
      if (a.tipo_salida !== 'muerte') {
        a.peso_salida_kg = pesoPorAn;
        // Actualizar el span (en modo total no hay input activo)
        setSpan('span-psal-' + i, App.kg(pesoPorAn));
      }
    });
    txtEl.textContent = cant + ' animal(es) × ' + App.kg(pesoPorAn) + ' c/u';
  } else {
    txtEl.textContent = '';
    animalesLiq.forEach((a, i) => {
      if (a.tipo_salida !== 'muerte') {
        a.peso_salida_kg = 0;
        setSpan('span-psal-' + i, '—');
      }
    });
  }
  // Solo actualizar ventas y totales, sin re-render
  calcularVentas();
}

// ════════════════════════════════════════════════════════════
// BUSCAR ANIMAL — agrega fila al DOM sin destruir existentes
// ════════════════════════════════════════════════════════════
async function buscarAnimal() {
  const codigo     = document.getElementById('codigo-animal-input').value.trim();
  const fechaVenta = document.getElementById('fecha-venta').value;
  const idFlete    = document.getElementById('id_flete_salida').value;
  const otrosTotal = parseFloat(document.getElementById('otros-gastos').value) || 0;

  if (!codigo)     { App.toast('Ingrese el código del animal.', 'warning');          return; }
  if (!fechaVenta) { App.toast('Seleccione la fecha de venta primero.', 'warning'); return; }
  if (animalesLiq.find(a => a.codigo === codigo)) {
    App.toast('Este animal ya está en la lista.', 'warning'); return;
  }

  const btn = document.getElementById('btn-buscar-animal');
  btn.disabled = true; btn.textContent = 'Buscando...';

  const params = { action: 'preview', codigos: codigo, fecha_venta: fechaVenta };
  if (idFlete)    params.id_flete_salida = idFlete;
  if (otrosTotal) params.otros_gastos    = otrosTotal;

  const res = await App.get(LIQ_API, params);
  btn.disabled = false; btn.textContent = 'Agregar';

  if (!res.ok || !res.data.data?.length) {
    App.toast((res.data?.message) || 'Animal no encontrado o ya liquidado.', 'error'); return;
  }

  const d = res.data.data[0];
  const cant = animalesLiq.length;

  // Recalcular otros_gastos prorrateando entre cant+1 animales
  const otrosNuevos = cant + 1 > 0 ? otrosTotal / (cant + 1) : 0;
  // Actualizar spans de otros en filas existentes
  animalesLiq.forEach((a, i) => {
    a.otros_gastos = otrosNuevos;
    a.costo_total  = (a.costo_compra||0) + (a.costo_flete_entrada||0)
                   + (a.costo_manutencion_exacto||a.costo_manutencion||0)
                   + (a.costo_flete_salida||0) + otrosNuevos;
    setSpan('span-otros-'  + i, App.moneda(otrosNuevos));
    setSpan('span-ctotal-' + i, App.moneda(a.costo_total));
  });

  const animal = {
    ...d,
    tipo_salida:              'venta',
    peso_salida_kg:           0,
    peso_canal_kg:            0,   // nuevo campo estadístico
    costo_manutencion_exacto: d.costo_manutencion,
    costo_flete_salida:       d.costo_flete_salida || 0,
    otros_gastos:             otrosNuevos,
    costo_total:              (d.costo_compra||d.costo_compra_animal||0)
                              + (d.costo_flete_entrada||0)
                              + (d.costo_manutencion||0)
                              + (d.costo_flete_salida||0)
                              + otrosNuevos,
  };
  animalesLiq.push(animal);

  const idx = animalesLiq.length - 1;
  agregarFilaTabla(animal, idx);

  document.getElementById('codigo-animal-input').value = '';
  document.getElementById('contador-animales').textContent = animalesLiq.length + ' animal(es)';
  document.getElementById('tfoot-totales').classList.remove('hidden');

  if (modoPeso === 'total') {
    distribuirPesoTotal();
  } else {
    // Foco en el nuevo input de salida
    setTimeout(() => {
      const el = document.getElementById('inp-sal-' + idx);
      if (el) el.focus();
    }, 60);
  }

  actualizarTotalesTabla();
  actualizarResumen();
  App.toast('Animal ' + animal.codigo + ' agregado.', 'success');
}

// ── Agrega UNA fila al tbody sin destruir el resto ────────
function agregarFilaTabla(a, i) {
  const tbody   = document.getElementById('tbody-animales-liq');
  const esMuerto = a.tipo_salida === 'muerte';
  const precioKg = parseFloat(document.getElementById('valor-venta-kg').value) || 0;
  const ganancia = (a.peso_salida_kg * precioKg) - a.costo_total;
  const ganCls   = ganancia >= 0 ? 'col-val text-esm-700 font-semibold' : 'col-val text-red-600 font-semibold';

  // Si es primer animal, limpiar el mensaje de "Agregue animales"
  if (i === 0) tbody.innerHTML = '';

  const tr = document.createElement('tr');
  tr.id        = 'fila-' + i;
  tr.className = 'border-b border-slate-100 hover:bg-slate-50';
  tr.innerHTML = `
    <td class="px-3 py-2 font-mono font-medium text-slate-700 text-xs">${a.codigo || '—'}</td>
    <!-- Costo compra -->
    <td class="px-2 col-val text-slate-400">${App.kg(a.peso_finca_kg || 0)}</td>
    <td class="px-2 col-val">${App.moneda(a.costo_compra || 0)}</td>
    <td class="px-2 col-val">${App.moneda(a.costo_flete_entrada || 0)}</td>
    <!-- Manutención -->
    <td class="px-2 col-val text-slate-500">${a.dias_manutencion || 0}</td>
    <td class="px-2 col-val text-slate-500">${(parseFloat(a.meses_manutencion)||0).toFixed(2)}</td>
    <td class="px-2 col-val">${App.moneda(a.costo_manutencion || 0)}</td>
    <!-- Costos venta -->
    <td id="span-flsal-${i}" class="px-2 col-val ${(a.costo_flete_salida||0)>0?'':'text-slate-300'}">${App.moneda(a.costo_flete_salida||0)}</td>
    <td id="span-otros-${i}" class="px-2 col-val ${(a.otros_gastos||0)>0?'':'text-slate-300'}">${App.moneda(a.otros_gastos||0)}</td>
    <!-- Costo total -->
    <td id="span-ctotal-${i}" class="px-2 col-val font-semibold text-red-600">${App.moneda(a.costo_total)}</td>
    <!-- P.Salida -->
    <td class="col-input py-1.5 px-2">
      ${esMuerto
        ? '<span class="text-slate-300 block text-center">—</span>'
        : `<input type="text" inputmode="decimal"
                  id="inp-sal-${i}"
                  class="inp-tabla inp-salida${modoPeso==='total'?' disabled:bg-slate-50':''}"
                  placeholder="0.00"
                  ${modoPeso==='total' ? 'disabled' : ''}
                  autocomplete="off"
                  oninput="onPesoSalidaInput(${i},this)"
                  onkeydown="navegarCampo(event,'inp-sal-',${i})"
                  tabindex="${i*2 + 10}">`
      }
    </td>
    <!-- P.Canal (solo estadístico) -->
    <td class="col-input py-1.5 px-2">
      ${esMuerto
        ? '<span class="text-slate-300 block text-center">—</span>'
        : `<input type="text" inputmode="decimal"
                  id="inp-can-${i}"
                  class="inp-tabla canal"
                  placeholder="0.00"
                  autocomplete="off"
                  oninput="onPesoCanal(${i},this)"
                  onkeydown="navegarCampo(event,'inp-can-',${i})"
                  tabindex="${i*2 + 11}"
                  title="Peso en canal — solo estadístico, no afecta cálculos">`
      }
    </td>
    <!-- Venta -->
    <td class="px-2 col-val text-slate-400">${precioKg > 0 ? App.moneda(precioKg)+'/kg' : '—'}</td>
    <td id="span-venta-${i}" class="px-2 col-val ${esMuerto?'text-slate-300':'text-esm-700'}">$ 0</td>
    <td id="span-gan-${i}" class="${ganCls}">$ 0</td>
    <!-- Tipo -->
    <td class="px-2 py-1 text-center">
      <select class="inp-tabla text-xs w-16" onchange="cambiarTipo(${i},this.value)">
        <option value="venta"  ${!esMuerto?'selected':''}>Venta</option>
        <option value="muerte" ${esMuerto?'selected':''}>Muerte</option>
      </select>
    </td>
    <td class="px-2 py-1 text-center">
      <button onclick="quitarAnimal(${i})"
              class="text-red-400 hover:text-red-600 font-bold text-sm">✕</button>
    </td>`;
  tbody.appendChild(tr);
}

// ════════════════════════════════════════════════════════════
// INPUTS DE PESO — SIN RE-RENDER, actualiza solo spans
// ════════════════════════════════════════════════════════════

// Peso de salida: digitación fluida sin perder foco
function onPesoSalidaInput(idx, el) {
  soloNum(el);
  const peso = parseFloat(el.value) || 0;
  animalesLiq[idx].peso_salida_kg = peso;

  el.className = (peso > 0 || animalesLiq[idx].tipo_salida === 'muerte')
    ? 'inp-tabla inp-salida'
    : 'inp-tabla inp-salida error';

  // Actualizar solo venta y ganancia de ESTA fila
  const precioKg  = parseFloat(document.getElementById('valor-venta-kg').value) || 0;
  const valorVent = peso * precioKg;
  const ganancia  = valorVent - animalesLiq[idx].costo_total;

  setSpan('span-venta-' + idx, App.moneda(valorVent));
  const elGan = document.getElementById('span-gan-' + idx);
  if (elGan) {
    elGan.textContent = App.moneda(ganancia);
    elGan.className   = 'col-val ' + (ganancia >= 0 ? 'text-esm-700 font-semibold' : 'text-red-600 font-semibold');
  }

  // Actualizar totales y resumen sin tocar inputs
  actualizarTotalesTabla();
  actualizarResumen();
}

// Peso en canal: solo estadístico, guarda en el array pero no afecta nada
function onPesoCanal(idx, el) {
  soloNum(el);
  animalesLiq[idx].peso_canal_kg = parseFloat(el.value) || 0;
  // Actualizar total de canal en tfoot
  const totalCanal = animalesLiq.reduce((s, a) => s + (a.peso_canal_kg || 0), 0);
  const elTf = document.getElementById('tf-pcanal');
  if (elTf) elTf.textContent = totalCanal > 0 ? App.kg(totalCanal) : '—';
}

// Actualizar precio/kg en todas las filas sin re-render
function calcularVentas() {
  const precioKg = parseFloat(document.getElementById('valor-venta-kg').value) || 0;
  animalesLiq.forEach((a, i) => {
    const pesoSal   = a.tipo_salida === 'muerte' ? 0 : (a.peso_salida_kg || 0);
    const valorVent = pesoSal * precioKg;
    const ganancia  = valorVent - (a.costo_total || 0);
    setSpan('span-venta-' + i, a.tipo_salida === 'muerte' ? '$ 0' : App.moneda(valorVent));
    const elGan = document.getElementById('span-gan-' + i);
    if (elGan) {
      elGan.textContent = App.moneda(ganancia);
      elGan.className   = 'col-val ' + (ganancia >= 0 ? 'text-esm-700 font-semibold' : 'text-red-600 font-semibold');
    }
  });
  actualizarTotalesTabla();
  actualizarResumen();
}

// ════════════════════════════════════════════════════════════
// NAVEGACIÓN CON TAB/ENTER ENTRE INPUTS DE PESO
// ════════════════════════════════════════════════════════════
function navegarCampo(e, prefijo, idx) {
  if (e.key === 'Tab' || e.key === 'Enter') {
    e.preventDefault();
    const siguiente = document.getElementById(prefijo + (idx + 1));
    if (siguiente) siguiente.focus();
    else {
      // Pasar al primer campo del siguiente tipo o al botón confirmar
      const salida  = document.getElementById('inp-sal-' + (idx + 1));
      const canal   = document.getElementById('inp-can-' + (idx + 1));
      const destino = salida || canal || document.getElementById('btn-liquidar');
      if (destino) destino.focus();
    }
  }
}

// ════════════════════════════════════════════════════════════
// CAMBIAR TIPO (venta/muerte) — actualiza fila sin re-render
// ════════════════════════════════════════════════════════════
function cambiarTipo(idx, val) {
  animalesLiq[idx].tipo_salida = val;
  if (val === 'muerte') {
    animalesLiq[idx].peso_salida_kg = 0;
    const elSal = document.getElementById('inp-sal-' + idx);
    if (elSal) { elSal.value = ''; elSal.disabled = true; }
    const elCan = document.getElementById('inp-can-' + idx);
    if (elCan) { elCan.value = ''; elCan.disabled = true; }
    setSpan('span-venta-' + idx, '$ 0');
    const elGan = document.getElementById('span-gan-' + idx);
    if (elGan) {
      const gan = 0 - animalesLiq[idx].costo_total;
      elGan.textContent = App.moneda(gan);
      elGan.className   = 'col-val text-red-600 font-semibold';
    }
    // Marcar fila con opacidad
    const fila = document.getElementById('fila-' + idx);
    if (fila) fila.style.opacity = '0.55';
  } else {
    const elSal = document.getElementById('inp-sal-' + idx);
    if (elSal) elSal.disabled = (modoPeso === 'total');
    const elCan = document.getElementById('inp-can-' + idx);
    if (elCan) elCan.disabled = false;
    const fila = document.getElementById('fila-' + idx);
    if (fila) fila.style.opacity = '';
  }
  if (modoPeso === 'total') distribuirPesoTotal();
  actualizarTotalesTabla();
  actualizarResumen();
}

// ════════════════════════════════════════════════════════════
// QUITAR ANIMAL — elimina fila del DOM y reindexar
// ════════════════════════════════════════════════════════════
function quitarAnimal(idx) {
  animalesLiq.splice(idx, 1);
  // Re-render completo (no hay inputs activos que proteger en este caso)
  renderTablaCompleta();
  actualizarTotalesTabla();
  actualizarResumen();
}

// ── Render completo (solo al quitar animales) ─────────────
function renderTablaCompleta() {
  const tbody = document.getElementById('tbody-animales-liq');
  tbody.innerHTML = '';
  document.getElementById('contador-animales').textContent = animalesLiq.length + ' animal(es)';

  if (!animalesLiq.length) {
    tbody.innerHTML = '<tr><td colspan="17" class="text-center py-10 text-slate-400">Agregue animales por código para comenzar</td></tr>';
    document.getElementById('tfoot-totales').classList.add('hidden');
    return;
  }

  animalesLiq.forEach((a, i) => agregarFilaTabla(a, i));

  // Restaurar valores en los inputs
  animalesLiq.forEach((a, i) => {
    const elSal = document.getElementById('inp-sal-' + i);
    if (elSal && a.peso_salida_kg > 0) elSal.value = a.peso_salida_kg;
    const elCan = document.getElementById('inp-can-' + i);
    if (elCan && a.peso_canal_kg > 0) elCan.value = a.peso_canal_kg;
  });

  if (modoPeso === 'total') distribuirPesoTotal();
  else calcularVentas();
}

// ════════════════════════════════════════════════════════════
// ACTUALIZAR TOTALES TFOOT — sin tocar el tbody
// ════════════════════════════════════════════════════════════
function actualizarTotalesTabla() {
  const precioKg  = parseFloat(document.getElementById('valor-venta-kg').value) || 0;
  const soloVentas = animalesLiq.filter(a => a.tipo_salida !== 'muerte');

  const totPFinca  = animalesLiq.reduce((s,a) => s+(parseFloat(a.peso_finca_kg)||0), 0);
  const totCompra  = animalesLiq.reduce((s,a) => s+(parseFloat(a.costo_compra)||0), 0);
  const totFlEnt   = animalesLiq.reduce((s,a) => s+(parseFloat(a.costo_flete_entrada)||0), 0);
  const totMant    = animalesLiq.reduce((s,a) => s+(parseFloat(a.costo_manutencion)||0), 0);
  const totFlSal   = animalesLiq.reduce((s,a) => s+(parseFloat(a.costo_flete_salida)||0), 0);
  const totOtros   = animalesLiq.reduce((s,a) => s+(parseFloat(a.otros_gastos)||0), 0);
  const totCosto   = animalesLiq.reduce((s,a) => s+(parseFloat(a.costo_total)||0), 0);
  const totPSal    = soloVentas.reduce((s,a) => s+(a.peso_salida_kg||0), 0);
  const totPCanal  = animalesLiq.reduce((s,a) => s+(a.peso_canal_kg||0), 0);
  const totVenta   = soloVentas.reduce((s,a) => s+(a.peso_salida_kg||0)*precioKg, 0);
  const totGan     = totVenta - totCosto;

  const s = (id, v) => { const el=document.getElementById(id); if(el) el.textContent=v; };
  s('tf-pfinca', App.kg(totPFinca));
  s('tf-compra', App.moneda(totCompra));
  s('tf-flent',  App.moneda(totFlEnt));
  s('tf-dias',   '—');
  s('tf-mant',   App.moneda(totMant));
  s('tf-flsal',  App.moneda(totFlSal));
  s('tf-otros',  App.moneda(totOtros));
  s('tf-costo',  App.moneda(totCosto));
  s('tf-psal',   totPSal > 0 ? App.kg(totPSal) : '—');
  s('tf-pcanal', totPCanal > 0 ? App.kg(totPCanal) : '—');
  s('tf-venta',  App.moneda(totVenta));

  const elGan = document.getElementById('tf-gan');
  if (elGan) {
    elGan.textContent = App.moneda(totGan);
    elGan.style.color = totGan >= 0 ? '#6ee7b7' : '#f87171';
  }
}

// ════════════════════════════════════════════════════════════
// RESUMEN LATERAL
// ════════════════════════════════════════════════════════════
function actualizarResumen() {
  const precioKg   = parseFloat(document.getElementById('valor-venta-kg').value) || 0;
  const soloVentas = animalesLiq.filter(a => a.tipo_salida !== 'muerte');
  const pesoTotal  = soloVentas.reduce((s,a) => s+(a.peso_salida_kg||0), 0);
  const costoTotal = animalesLiq.reduce((s,a) => s+(parseFloat(a.costo_total)||0), 0);
  const valorVenta = pesoTotal * precioKg;
  const ganancia   = valorVenta - costoTotal;

  const s = (id,v) => { const el=document.getElementById(id); if(el) el.textContent=v; };
  s('res-animales', soloVentas.length + ' / ' + animalesLiq.length);
  s('res-peso',     App.kg(pesoTotal));
  s('res-costo',    App.moneda(costoTotal));
  s('res-venta',    App.moneda(valorVenta));

  const elG = document.getElementById('res-ganancia');
  if (elG) {
    elG.textContent = App.moneda(ganancia);
    elG.className   = ganancia >= 0
      ? 'font-bold text-xl text-esm-400'
      : 'font-bold text-xl text-red-400';
  }
}

// ════════════════════════════════════════════════════════════
// RECALCULAR AL CAMBIAR FECHA
// ════════════════════════════════════════════════════════════
async function recalcularFecha() {
  if (!animalesLiq.length) return;
  const fechaVenta = document.getElementById('fecha-venta').value;
  const idFlete    = document.getElementById('id_flete_salida').value;
  const otrosTotal = parseFloat(document.getElementById('otros-gastos').value) || 0;
  if (!fechaVenta) return;

  const codigos = animalesLiq.map(a => a.codigo).join(',');
  const params  = { action: 'preview', codigos, fecha_venta: fechaVenta };
  if (idFlete)    params.id_flete_salida = idFlete;
  if (otrosTotal) params.otros_gastos    = otrosTotal;

  const res = await App.get(LIQ_API, params);
  if (!res.ok) return;

  const mapa = {};
  res.data.data.forEach(a => { mapa[a.codigo] = a; });

  animalesLiq.forEach((a, i) => {
    const nuevo = mapa[a.codigo];
    if (!nuevo) return;
    a.dias_manutencion       = nuevo.dias_manutencion;
    a.meses_manutencion      = nuevo.meses_manutencion;
    a.costo_manutencion      = nuevo.costo_manutencion;
    a.costo_manutencion_exacto = nuevo.costo_manutencion;
    a.costo_total = (a.costo_compra||0) + (a.costo_flete_entrada||0)
                  + nuevo.costo_manutencion
                  + (a.costo_flete_salida||0) + (a.otros_gastos||0);
    // Actualizar días, meses y manutención en la fila sin tocar inputs
    setSpan('span-ctotal-' + i, App.moneda(a.costo_total));
  });

  // Re-render completo porque cambian días/meses que son spans simples
  const pesosGuardados = animalesLiq.map(a => ({ sal: a.peso_salida_kg, can: a.peso_canal_kg }));
  renderTablaCompleta();
  // Restaurar pesos digitados
  pesosGuardados.forEach((p, i) => {
    animalesLiq[i].peso_salida_kg = p.sal;
    animalesLiq[i].peso_canal_kg  = p.can;
    const es = document.getElementById('inp-sal-' + i);
    const ec = document.getElementById('inp-can-' + i);
    if (es && p.sal > 0) es.value = p.sal;
    if (ec && p.can > 0) ec.value = p.can;
  });

  actualizarTotalesTabla();
  actualizarResumen();
}

// ════════════════════════════════════════════════════════════
// CONFIRMAR LIQUIDACIÓN
// ════════════════════════════════════════════════════════════
async function confirmarLiquidacion() {
  if (!animalesLiq.length) { App.toast('Agregue al menos un animal.', 'error'); return; }

  const fechaVenta = document.getElementById('fecha-venta').value;
  const valorKg    = document.getElementById('valor-venta-kg').value;
  const empresa    = document.getElementById('id_empresa_factura').value;
  const cliente    = document.getElementById('id_cliente').value;

  if (!fechaVenta) { App.toast('Ingrese la fecha de venta.', 'error');         return; }
  if (!empresa)    { App.toast('Seleccione la empresa que factura.', 'error'); return; }
  if (!cliente)    { App.toast('Seleccione el cliente.', 'error');             return; }
  if (!valorKg || parseFloat(valorKg) <= 0) {
    App.toast('Ingrese el precio de venta por kg.', 'error'); return;
  }

  const soloVentas = animalesLiq.filter(a => a.tipo_salida !== 'muerte');

  if (modoPeso === 'total') {
    const pt = parseFloat(document.getElementById('peso-total-lote').value) || 0;
    if (pt <= 0 && soloVentas.length > 0) {
      App.toast('Ingrese el peso total de venta.', 'error'); return;
    }
  } else {
    const sinPeso = soloVentas.filter(a => !a.peso_salida_kg || a.peso_salida_kg <= 0);
    if (sinPeso.length > 0) {
      sinPeso.forEach(a => {
        const idx = animalesLiq.indexOf(a);
        const el  = document.getElementById('inp-sal-' + idx);
        if (el) { el.classList.add('error'); el.focus(); }
      });
      App.toast('Faltan pesos de salida en ' + sinPeso.length + ' animal(es).', 'error'); return;
    }
  }

  const pesoTotalLote = modoPeso === 'total'
    ? parseFloat(document.getElementById('peso-total-lote').value) || 0
    : soloVentas.reduce((s, a) => s + (a.peso_salida_kg || 0), 0);

  const idContrato = LIQ_CONTRATO || animalesLiq[0].id_contrato;

  const body = {
    id_contrato:             idContrato,
    id_empresa_factura:      empresa,
    id_cliente:              cliente,
    id_flete_salida:         document.getElementById('id_flete_salida').value || null,
    numero_factura:          document.getElementById('numero-factura').value  || null,
    fecha_venta:             fechaVenta,
    modo_peso:               modoPeso,
    peso_total_kg:           pesoTotalLote,
    valor_venta_unitario_kg: parseFloat(valorKg),
    otros_gastos:            parseFloat(document.getElementById('otros-gastos').value) || 0,
    observacion:             document.getElementById('observacion-liq').value || null,
    animales: animalesLiq.map(a => ({
      id_animal:      a.id_animal,
      tipo_salida:    a.tipo_salida || 'venta',
      peso_salida_kg: a.tipo_salida === 'muerte' ? 0 : (a.peso_salida_kg || 0),
      peso_canal_kg:  a.peso_canal_kg || 0,   // campo estadístico
    })),
  };

  const btn = document.getElementById('btn-liquidar');
  btn.disabled = true; btn.textContent = 'Procesando...';

  const res = await App.post(LIQ_API, body);
  btn.disabled = false; btn.textContent = '✓ Confirmar liquidación';

  if (res.ok) {
    App.toast(res.data.message, 'success');
    if (res.data.data?.contrato_cerrado) App.toast('¡Contrato cerrado!', 'success');
    setTimeout(() => {
      window.location.href = APP_URL + '/contratos/detalle.php?id=' + idContrato;
    }, 1200);
  } else {
    App.toast((res.data?.message) || 'Error al guardar.', 'error');
    console.error(res.data);
  }
}
</script>

<?php require_once __DIR__ . '/../views/layout/footer.php'; ?>
