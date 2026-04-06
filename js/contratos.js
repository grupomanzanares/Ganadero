// ============================================================
// js/contratos.js — Módulo de contratos de compra
// ============================================================

const Contratos = (() => {

  const API      = APP_URL + '/api/contratos.php';
  const API_CAT  = APP_URL + '/api/catalogos.php';

  let sociosSeleccionados = [];

  // ── Inicializar listado ──────────────────────────────────
  const initListado = async () => {
    await cargarFiltros();
    await cargarListado();

    document.getElementById('btn-nuevo')?.addEventListener('click', () => {
      window.location.href = APP_URL + '/contratos/nuevo.php';
    });

    document.getElementById('filtro-estado')?.addEventListener('change', cargarListado);
    document.getElementById('filtro-empresa')?.addEventListener('change', cargarListado);
  };

  const cargarFiltros = async () => {
    const res = await App.get(API_CAT, { recurso: 'empresas' });
    if (res.ok) App.populateSelect('filtro-empresa', res.data.data, 'id', 'nombre', 'Todas las empresas');
  };

  const cargarListado = async () => {
    App.loader.show();
    const estado  = document.getElementById('filtro-estado')?.value  || '';
    const empresa = document.getElementById('filtro-empresa')?.value || '';

    const res = await App.get(API, { estado, empresa });
    App.loader.hide();

    if (!res.ok) { App.toast(res.data.message, 'error'); return; }

    App.renderTable('tbody-contratos', res.data.data, [
      { key: 'codigo' },
      { key: 'fecha_compra',      render: r => App.fecha(r.fecha_compra) },
      { key: 'empresa_compra' },
      { key: 'tipo_animal' },
      { key: 'proveedor' },
      { key: 'cantidad_animales', render: r => r.cantidad_animales + ' cab.' },
      { key: 'peso_total_kg',     render: r => App.kg(r.peso_total_kg) },
      { key: 'valor_total',       render: r => App.moneda(r.valor_total) },
      { key: 'estado',            render: r => badgeEstado(r.estado) },
      { key: 'acciones',          render: r => accionesRow(r) },
    ]);
  };

  const badgeEstado = (estado) => {
    const cls = {
      abierto: 'bg-verde-100 text-verde-700',
      cerrado: 'bg-tierra-200 text-tierra-700',
      anulado: 'bg-red-100 text-red-700',
    }[estado] || 'bg-gray-100 text-gray-600';
    return `<span class="px-2 py-0.5 rounded-full text-xs font-semibold ${cls}">${estado}</span>`;
  };

  const accionesRow = (r) =>
    `<div class="flex gap-2">
       <a href="${APP_URL}/contratos/detalle.php?id=${r.id}"
          class="btn-xs btn-tierra">Ver</a>
       ${r.estado === 'abierto'
         ? `<a href="${APP_URL}/contratos/editar.php?id=${r.id}"
               class="btn-xs btn-outline">Editar</a>`
         : ''}
     </div>`;

  // ── Inicializar formulario nuevo/editar ──────────────────
  const initForm = async (idContrato = null) => {
    await cargarCatalogos();
    configurarSociosSelector();
    configurarCalculos();

    const form = document.getElementById('form-contrato');
    form?.addEventListener('submit', (e) => {
      e.preventDefault();
      guardarContrato(idContrato);
    });

    if (idContrato) await cargarContrato(idContrato);
  };

  const cargarCatalogos = async () => {
    const [empresas, proveedores, tipos] = await Promise.all([
      App.get(API_CAT, { recurso: 'empresas' }),
      App.get(API_CAT, { recurso: 'proveedores' }),
      App.get(API_CAT, { recurso: 'tipos' }),
    ]);

    if (empresas.ok) {
      App.populateSelect('id_empresa_compra', empresas.data.data, 'id', 'nombre');
      App.populateSelect('id_empresa_pago',   empresas.data.data, 'id', 'nombre');
    }
    if (proveedores.ok) App.populateSelect('id_proveedor', proveedores.data.data, 'id', 'nombre');
    if (tipos.ok)       App.populateSelect('id_tipo_animal', tipos.data.data, 'id', 'nombre');

    // Cargar socios disponibles
    const socios = await App.get(API_CAT, { recurso: 'socios' });
    if (socios.ok) renderSociosDisponibles(socios.data.data);
  };

  const renderSociosDisponibles = (socios) => {
    const container = document.getElementById('lista-socios');
    if (!container) return;
    container.innerHTML = socios.map(s =>
      `<label class="flex items-center gap-2 p-2 rounded hover:bg-tierra-50 cursor-pointer">
        <input type="checkbox" value="${s.id}" data-nombre="${s.nombre}"
               class="chk-socio accent-verde-600 w-4 h-4"
               onchange="Contratos.toggleSocio(this)">
        <span class="text-sm text-tierra-800">${s.nombre}</span>
        <span class="text-xs text-tierra-400">${s.empresa}</span>
       </label>`
    ).join('');
  };

  const toggleSocio = (chk) => {
    if (chk.checked) {
      sociosSeleccionados.push({ id: chk.value, nombre: chk.dataset.nombre });
    } else {
      sociosSeleccionados = sociosSeleccionados.filter(s => s.id !== chk.value);
    }
    actualizarResumenSocios();
  };

  const actualizarResumenSocios = () => {
    const n   = sociosSeleccionados.length;
    const pct = n > 0 ? (100 / n).toFixed(2) : 0;
    const el  = document.getElementById('resumen-socios');
    if (!el) return;
    el.innerHTML = n === 0
      ? '<span class="text-tierra-400 text-sm">Sin socios seleccionados</span>'
      : `<span class="text-sm text-verde-700 font-medium">${n} socio(s) — ${pct}% c/u</span>`;
  };

  const configurarCalculos = () => {
    const campos = ['cantidad_animales', 'peso_total_kg', 'valor_unitario', 'costo_flete'];
    campos.forEach(id => {
      document.getElementById(id)?.addEventListener('input', calcularTotales);
    });
  };

  const calcularTotales = () => {
    const cantidad  = parseFloat(document.getElementById('cantidad_animales')?.value) || 0;
    const pesoTotal = parseFloat(document.getElementById('peso_total_kg')?.value)     || 0;
    const valorUnit = parseFloat(document.getElementById('valor_unitario')?.value)    || 0;
    const flete     = parseFloat(document.getElementById('costo_flete')?.value)       || 0;

    const valorTotal  = cantidad  * valorUnit;
    const pesoPromedio = cantidad > 0 ? pesoTotal / cantidad : 0;
    const valorKg     = pesoPromedio > 0 ? valorUnit / pesoPromedio : 0;
    const fletePorAn  = cantidad  > 0 ? flete / cantidad : 0;

    setText('calc-valor-total',   App.moneda(valorTotal));
    setText('calc-peso-promedio', App.kg(pesoPromedio));
    setText('calc-valor-kg',      App.moneda(valorKg) + '/kg');
    setText('calc-flete-animal',  App.moneda(fletePorAn));
  };

  const setText = (id, val) => {
    const el = document.getElementById(id);
    if (el) el.textContent = val;
  };

  const guardarContrato = async (idContrato) => {
    if (sociosSeleccionados.length === 0) {
      App.toast('Debe seleccionar al menos un socio.', 'error'); return;
    }

    const body = {
      id_empresa_compra: document.getElementById('id_empresa_compra')?.value,
      id_empresa_pago:   document.getElementById('id_empresa_pago')?.value,
      id_proveedor:      document.getElementById('id_proveedor')?.value,
      id_tipo_animal:    document.getElementById('id_tipo_animal')?.value,
      edad_meses:        document.getElementById('edad_meses')?.value   || null,
      fecha_compra:      document.getElementById('fecha_compra')?.value,
      fecha_factura:     document.getElementById('fecha_factura')?.value || null,
      numero_factura:    document.getElementById('numero_factura')?.value || null,
      cantidad_animales: document.getElementById('cantidad_animales')?.value,
      peso_total_kg:     document.getElementById('peso_total_kg')?.value,
      valor_unitario:    document.getElementById('valor_unitario')?.value,
      costo_flete:       document.getElementById('costo_flete')?.value   || 0,
      observacion:       document.getElementById('observacion')?.value   || null,
      socios:            sociosSeleccionados.map(s => s.id),
    };

    const btn = document.getElementById('btn-guardar');
    if (btn) btn.disabled = true;

    const res = idContrato
      ? await App.put(`${API}?id=${idContrato}`, body)
      : await App.post(API, body);

    if (btn) btn.disabled = false;

    if (res.ok) {
      App.toast(res.data.message, 'success');
      setTimeout(() => {
        window.location.href = APP_URL + '/contratos/detalle.php?id=' + (res.data.data?.id || idContrato);
      }, 1000);
    } else {
      App.toast(res.data.message, 'error');
    }
  };

  const cargarContrato = async (id) => {
    const res = await App.get(API, { id });
    if (!res.ok) { App.toast('No se pudo cargar el contrato.', 'error'); return; }
    const c = res.data.data;

    ['id_empresa_compra','id_empresa_pago','id_proveedor','id_tipo_animal',
     'edad_meses','fecha_compra','fecha_factura','numero_factura',
     'cantidad_animales','peso_total_kg','valor_unitario','costo_flete','observacion'].forEach(f => {
      const el = document.getElementById(f);
      if (el && c[f] !== undefined) el.value = c[f] ?? '';
    });

    // Marcar socios
    sociosSeleccionados = [];
    c.socios?.forEach(s => {
      sociosSeleccionados.push({ id: String(s.id_socio || s.id), nombre: s.socio });
      const chk = document.querySelector(`.chk-socio[value="${s.id_socio || s.id}"]`);
      if (chk) chk.checked = true;
    });
    actualizarResumenSocios();
    calcularTotales();
  };

  return { initListado, initForm, toggleSocio };
})();
