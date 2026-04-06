// ============================================================
// js/liquidaciones.js — Módulo de liquidación / ventas
// ============================================================

const Liquidaciones = (() => {

  const API     = APP_URL + '/api/liquidaciones.php';
  const API_CAT = APP_URL + '/api/catalogos.php';

  let animalesSeleccionados = [];

  // ── Inicializar formulario de liquidación ────────────────
  const initForm = async (idContrato) => {
    await cargarCatalogos();

    document.getElementById('btn-buscar-animal')
      ?.addEventListener('click', buscarAnimal);

    document.getElementById('codigo-animal-input')
      ?.addEventListener('keypress', (e) => {
        if (e.key === 'Enter') { e.preventDefault(); buscarAnimal(); }
      });

    document.getElementById('fecha-venta')
      ?.addEventListener('change', actualizarPrecios);

    document.getElementById('valor-venta-kg')
      ?.addEventListener('input', actualizarTotales);

    document.getElementById('btn-liquidar')
      ?.addEventListener('click', () => confirmarLiquidacion(idContrato));

    // Cargar fletes disponibles
    cargarFletes();
  };

  const cargarCatalogos = async () => {
    const [empresas, clientes] = await Promise.all([
      App.get(API_CAT, { recurso: 'empresas' }),
      App.get(API_CAT, { recurso: 'clientes' }),
    ]);
    if (empresas.ok) App.populateSelect('id_empresa_factura', empresas.data.data, 'id', 'nombre');
    if (clientes.ok) App.populateSelect('id_cliente', clientes.data.data, 'id', 'nombre');
  };

  const cargarFletes = async () => {
    const res = await App.get(API_CAT, { recurso: 'fletes' });
    if (res.ok) {
      App.populateSelect('id_flete_salida', res.data.data, 'id',
        r => `${App.fecha(r.fecha)} — ${r.origen} → ${r.destino} (${App.moneda(r.valor_por_animal)}/animal)`,
        'Sin flete de salida');
    }
  };

  // ── Buscar animal por código ─────────────────────────────
  const buscarAnimal = async () => {
    const codigo    = document.getElementById('codigo-animal-input')?.value.trim();
    const fechaVenta = document.getElementById('fecha-venta')?.value;

    if (!codigo) { App.toast('Ingrese un código de animal.', 'warning'); return; }
    if (!fechaVenta) { App.toast('Seleccione la fecha de venta primero.', 'warning'); return; }

    // Verificar que no esté ya en la lista
    if (animalesSeleccionados.find(a => a.codigo === codigo)) {
      App.toast('Este animal ya fue agregado.', 'warning'); return;
    }

    const res = await App.get(API, {
      action: 'preview',
      codigos: codigo,
      fecha_venta: fechaVenta,
    });

    if (!res.ok) { App.toast(res.data.message, 'error'); return; }

    const animal = res.data.data[0];
    if (!animal) { App.toast('Animal no encontrado o ya liquidado.', 'error'); return; }

    animalesSeleccionados.push(animal);
    renderTablaAnimales();
    actualizarTotales();
    document.getElementById('codigo-animal-input').value = '';
  };

  const renderTablaAnimales = () => {
    const tbody = document.getElementById('tbody-animales-liq');
    if (!tbody) return;

    if (animalesSeleccionados.length === 0) {
      tbody.innerHTML = `<tr><td colspan="8" class="text-center py-6 text-tierra-400 text-sm">
        Busque animales por código para agregarlos</td></tr>`;
      return;
    }

    tbody.innerHTML = animalesSeleccionados.map((a, idx) =>
      `<tr class="border-b border-tierra-100">
        <td class="px-3 py-2 text-sm font-mono text-tierra-800">${a.codigo}</td>
        <td class="px-3 py-2 text-sm">${a.contrato_codigo}</td>
        <td class="px-3 py-2 text-sm text-right">${App.kg(a.peso_finca_kg)}</td>
        <td class="px-3 py-2 text-sm text-right">${a.dias_manutencion} días</td>
        <td class="px-3 py-2 text-sm text-right">${App.moneda(a.costo_manutencion)}</td>
        <td class="px-3 py-2 text-sm text-right font-medium">${App.moneda(a.costo_total)}</td>
        <td class="px-3 py-2 text-sm text-center">
          <select class="sel-tipo-salida text-xs border border-tierra-200 rounded px-1 py-0.5"
                  data-idx="${idx}" onchange="Liquidaciones.setTipoSalida(this)">
            <option value="venta"  ${a.tipo_salida!=='muerte'?'selected':''}>Venta</option>
            <option value="muerte" ${a.tipo_salida==='muerte'?'selected':''}>Muerte</option>
          </select>
        </td>
        <td class="px-3 py-2 text-center">
          <button onclick="Liquidaciones.quitarAnimal(${idx})"
                  class="text-red-400 hover:text-red-600 text-xs">✕</button>
        </td>
      </tr>`
    ).join('');
  };

  const setTipoSalida = (sel) => {
    animalesSeleccionados[sel.dataset.idx].tipo_salida = sel.value;
    actualizarTotales();
  };

  const quitarAnimal = (idx) => {
    animalesSeleccionados.splice(idx, 1);
    renderTablaAnimales();
    actualizarTotales();
  };

  const actualizarPrecios = async () => {
    const fechaVenta = document.getElementById('fecha-venta')?.value;
    if (!fechaVenta || animalesSeleccionados.length === 0) return;

    // Re-consultar costos con nueva fecha
    const codigos = animalesSeleccionados.map(a => a.codigo).join(',');
    const res = await App.get(API, { action: 'preview', codigos, fecha_venta: fechaVenta });
    if (res.ok) {
      const prevData = {};
      res.data.data.forEach(a => { prevData[a.codigo] = a; });
      animalesSeleccionados = animalesSeleccionados.map(a => ({
        ...prevData[a.codigo] || a,
        tipo_salida: a.tipo_salida,
      }));
      renderTablaAnimales();
      actualizarTotales();
    }
  };

  const actualizarTotales = () => {
    const precioKg   = parseFloat(document.getElementById('valor-venta-kg')?.value) || 0;
    const soloVentas = animalesSeleccionados.filter(a => a.tipo_salida !== 'muerte');

    const pesoTotal   = soloVentas.reduce((s, a) => s + (parseFloat(a.peso_finca_kg) || 0), 0);
    const costoTotal  = animalesSeleccionados.reduce((s, a) => s + (parseFloat(a.costo_total) || 0), 0);
    const valorVenta  = pesoTotal * precioKg;
    const ganancia    = valorVenta - costoTotal;

    setText('calc-total-animales', animalesSeleccionados.length);
    setText('calc-peso-total',     App.kg(pesoTotal));
    setText('calc-costo-total',    App.moneda(costoTotal));
    setText('calc-valor-venta',    App.moneda(valorVenta));
    setText('calc-ganancia',       App.moneda(ganancia));

    const el = document.getElementById('calc-ganancia');
    if (el) {
      el.className = ganancia >= 0
        ? 'font-bold text-verde-600'
        : 'font-bold text-red-600';
    }
  };

  const setText = (id, val) => {
    const el = document.getElementById(id);
    if (el) el.textContent = val;
  };

  // ── Confirmar y enviar liquidación ───────────────────────
  const confirmarLiquidacion = async (idContrato) => {
    if (animalesSeleccionados.length === 0) {
      App.toast('Agregue al menos un animal.', 'error'); return;
    }

    const fechaVenta = document.getElementById('fecha-venta')?.value;
    const valorKg    = document.getElementById('valor-venta-kg')?.value;
    const empresa    = document.getElementById('id_empresa_factura')?.value;
    const cliente    = document.getElementById('id_cliente')?.value;

    if (!fechaVenta || !valorKg || !empresa || !cliente) {
      App.toast('Complete todos los campos obligatorios.', 'error'); return;
    }

    const soloVentas  = animalesSeleccionados.filter(a => a.tipo_salida !== 'muerte');
    const pesoTotal   = soloVentas.reduce((s, a) => s + (parseFloat(a.peso_finca_kg) || 0), 0);

    const body = {
      id_contrato:            idContrato || animalesSeleccionados[0].id_contrato,
      id_empresa_factura:     empresa,
      id_cliente:             cliente,
      id_flete_salida:        document.getElementById('id_flete_salida')?.value || null,
      numero_factura:         document.getElementById('numero-factura')?.value || null,
      fecha_venta:            fechaVenta,
      peso_total_kg:          pesoTotal,
      valor_venta_unitario_kg: parseFloat(valorKg),
      observacion:            document.getElementById('observacion-liq')?.value || null,
      animales: animalesSeleccionados.map(a => ({
        id_animal:   a.id_animal,
        tipo_salida: a.tipo_salida || 'venta',
      })),
    };

    const btn = document.getElementById('btn-liquidar');
    if (btn) btn.disabled = true;
    App.toast('Procesando liquidación...', 'info');

    const res = await App.post(API, body);
    if (btn) btn.disabled = false;

    if (res.ok) {
      App.toast(res.data.message, 'success');
      if (res.data.data?.contrato_cerrado) {
        App.toast('¡Contrato cerrado exitosamente!', 'success');
      }
      setTimeout(() => {
        window.location.href = APP_URL + '/liquidaciones/detalle.php?id=' + res.data.data.id;
      }, 1500);
    } else {
      App.toast(res.data.message, 'error');
    }
  };

  return { initForm, setTipoSalida, quitarAnimal };
})();
