// ============================================================
// js/animales.js — Pesaje en finca y asignación de códigos
// ============================================================

const Animales = (() => {

  const API = APP_URL + '/api/animales.php';

  // ── Inicializar grilla de pesaje ─────────────────────────
  const initPesaje = async (idContrato) => {
    await cargarAnimales(idContrato);

    document.getElementById('btn-guardar-pesajes')
      ?.addEventListener('click', () => guardarTodo(idContrato));
  };

  const cargarAnimales = async (idContrato) => {
    App.loader.show('#loader-animales');
    const res = await App.get(API, { contrato: idContrato });
    App.loader.hide('#loader-animales');

    if (!res.ok) { App.toast(res.data.message, 'error'); return; }
    renderGrilla(res.data.data);
  };

  const renderGrilla = (animales) => {
    const container = document.getElementById('grilla-animales');
    if (!container) return;

    if (!animales || animales.length === 0) {
      container.innerHTML = '<p class="text-tierra-400 text-center py-8">Sin animales en este contrato.</p>';
      return;
    }

    container.innerHTML = `
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead>
            <tr class="bg-tierra-800 text-tierra-100">
              <th class="px-4 py-3 text-left">#</th>
              <th class="px-4 py-3 text-left">Código</th>
              <th class="px-4 py-3 text-right">Peso inicial</th>
              <th class="px-4 py-3 text-right">Peso finca (kg)</th>
              <th class="px-4 py-3 text-right">Valor/kg</th>
              <th class="px-4 py-3 text-center">Estado</th>
              <th class="px-4 py-3 text-center">Guardar</th>
            </tr>
          </thead>
          <tbody>
            ${animales.map((a, i) => `
              <tr class="border-b border-tierra-100 hover:bg-tierra-50" data-id="${a.id}">
                <td class="px-4 py-2 text-tierra-500">${i + 1}</td>
                <td class="px-4 py-2">
                  <input type="text" value="${a.codigo || ''}"
                         id="codigo-${a.id}"
                         placeholder="Código / arete"
                         ${a.estado !== 'activo' ? 'disabled' : ''}
                         class="input-tabla w-28">
                </td>
                <td class="px-4 py-2 text-right text-tierra-500">${App.kg(a.peso_inicial_kg)}</td>
                <td class="px-4 py-2">
                  <input type="number" step="0.01" min="0"
                         value="${a.peso_finca_kg || ''}"
                         id="peso-${a.id}"
                         placeholder="0.00"
                         ${a.estado !== 'activo' ? 'disabled' : ''}
                         oninput="Animales.calcularValorKg(${a.id}, ${a.costo_compra_animal}, ${a.costo_flete_animal})"
                         class="input-tabla w-24 text-right">
                </td>
                <td class="px-4 py-2 text-right font-medium text-verde-700" id="vkg-${a.id}">
                  ${a.valor_promedio_kg ? App.moneda(a.valor_promedio_kg) + '/kg' : '—'}
                </td>
                <td class="px-4 py-2 text-center">
                  ${badgeEstado(a.estado)}
                </td>
                <td class="px-4 py-2 text-center">
                  ${a.estado === 'activo'
                    ? `<button onclick="Animales.guardarAnimal(${a.id})"
                               class="btn-xs btn-verde">💾</button>`
                    : '—'}
                </td>
              </tr>`
            ).join('')}
          </tbody>
        </table>
      </div>
      <div class="mt-4 text-sm text-tierra-500 text-right">
        Total: <strong>${animales.length} animales</strong> —
        Con código: <strong>${animales.filter(a => a.codigo).length}</strong> —
        Con pesaje finca: <strong>${animales.filter(a => a.peso_finca_kg).length}</strong>
      </div>`;
  };

  const badgeEstado = (estado) => {
    const cls = {
      activo:  'bg-verde-100 text-verde-700',
      vendido: 'bg-tierra-200 text-tierra-600',
      muerto:  'bg-red-100 text-red-600',
    }[estado] || '';
    return `<span class="px-2 py-0.5 rounded-full text-xs ${cls}">${estado}</span>`;
  };

  // ── Calcular valor/kg en tiempo real ────────────────────
  const calcularValorKg = (id, costoCompra, costoFlete) => {
    const peso = parseFloat(document.getElementById(`peso-${id}`)?.value) || 0;
    const el   = document.getElementById(`vkg-${id}`);
    if (!el) return;

    if (peso > 0) {
      const vkg = (costoCompra + costoFlete) / peso;
      el.textContent = App.moneda(vkg) + '/kg';
    } else {
      el.textContent = '—';
    }
  };

  // ── Guardar un animal ────────────────────────────────────
  const guardarAnimal = async (id) => {
    const codigo = document.getElementById(`codigo-${id}`)?.value.trim();
    const peso   = document.getElementById(`peso-${id}`)?.value;

    if (!codigo && !peso) {
      App.toast('Ingrese código o peso para guardar.', 'warning'); return;
    }

    const body = {};
    if (codigo) body.codigo        = codigo;
    if (peso)   body.peso_finca_kg = parseFloat(peso);

    const res = await App.put(`${API}?id=${id}`, body);
    if (res.ok) {
      App.toast('Animal actualizado.', 'success');
    } else {
      App.toast(res.data.message, 'error');
    }
  };

  // ── Guardar todos los animales pendientes ────────────────
  const guardarTodo = async (idContrato) => {
    const filas = document.querySelectorAll('[data-id]');
    let pendientes = 0;
    const promesas = [];

    filas.forEach(fila => {
      const id     = fila.dataset.id;
      const codigo = document.getElementById(`codigo-${id}`)?.value.trim();
      const peso   = document.getElementById(`peso-${id}`)?.value;

      if (codigo || peso) {
        pendientes++;
        const body = {};
        if (codigo) body.codigo        = codigo;
        if (peso)   body.peso_finca_kg = parseFloat(peso);
        promesas.push(App.put(`${API}?id=${id}`, body));
      }
    });

    if (pendientes === 0) { App.toast('No hay cambios pendientes.', 'info'); return; }

    App.toast(`Guardando ${pendientes} animales...`, 'info');
    const resultados = await Promise.all(promesas);
    const errores = resultados.filter(r => !r.ok).length;

    if (errores === 0) {
      App.toast(`${pendientes} animales guardados correctamente.`, 'success');
      await cargarAnimales(idContrato);
    } else {
      App.toast(`${errores} error(es) al guardar. Verifique códigos duplicados.`, 'error');
    }
  };

  return { initPesaje, calcularValorKg, guardarAnimal };
})();
