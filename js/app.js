// ============================================================
// js/app.js — Cliente HTTP global y utilidades de UI
// ============================================================

const App = (() => {

  // ── HTTP client ──────────────────────────────────────────
  const http = async (url, options = {}) => {
    try {
      const res = await fetch(url, {
        headers: { 'Content-Type': 'application/json', ...options.headers },
        ...options,
      });
      const json = await res.json();
      return { ok: res.ok, status: res.status, data: json };
    } catch (err) {
      console.error('[HTTP Error]', err);
      return { ok: false, data: { message: 'Error de conexión con el servidor.' } };
    }
  };

  const get  = (url, params = {}) => {
    const qs = new URLSearchParams(params).toString();
    return http(qs ? `${url}?${qs}` : url, { method: 'GET' });
  };

  const post = (url, body) =>
    http(url, { method: 'POST', body: JSON.stringify(body) });

  const put  = (url, body) =>
    http(url, { method: 'PUT', body: JSON.stringify(body) });

  const del  = (url) => http(url, { method: 'DELETE' });

  // ── Formato ──────────────────────────────────────────────
  const moneda = (n) =>
    '$ ' + Number(n || 0).toLocaleString('es-CO', { minimumFractionDigits: 0 });

  const kg = (n) =>
    Number(n || 0).toLocaleString('es-CO', { minimumFractionDigits: 2 }) + ' kg';

  const fecha = (str) => {
    if (!str) return '—';
    const [y, m, d] = str.split('-');
    return `${d}/${m}/${y}`;
  };

  const pct = (n) => Number(n || 0).toFixed(2) + '%';

  // ── Notificaciones toast ─────────────────────────────────
  let toastTimeout;
  const toast = (msg, type = 'info') => {
    clearTimeout(toastTimeout);
    const el = document.getElementById('toast');
    if (!el) return;

    const colors = {
      success: 'bg-verde-700 text-white',
      error:   'bg-red-700 text-white',
      info:    'bg-tierra-700 text-white',
      warning: 'bg-amber-600 text-white',
    };

    el.className = `fixed bottom-6 right-6 z-50 px-5 py-3 rounded-lg shadow-xl
      text-sm font-medium transition-all duration-300 ${colors[type] || colors.info}`;
    el.textContent = msg;
    el.style.opacity = '1';
    el.style.transform = 'translateY(0)';

    toastTimeout = setTimeout(() => {
      el.style.opacity = '0';
      el.style.transform = 'translateY(10px)';
    }, 4000);
  };

  // ── Modal ─────────────────────────────────────────────────
  const modal = {
    open: (id) => {
      const el = document.getElementById(id);
      if (el) el.classList.remove('hidden');
    },
    close: (id) => {
      const el = document.getElementById(id);
      if (el) el.classList.add('hidden');
    },
  };

  // ── Loader ───────────────────────────────────────────────
  const loader = {
    show: (container = '#loader') => {
      const el = document.querySelector(container);
      if (el) el.classList.remove('hidden');
    },
    hide: (container = '#loader') => {
      const el = document.querySelector(container);
      if (el) el.classList.add('hidden');
    },
  };

  // ── Confirmar acción ─────────────────────────────────────
  const confirm = (msg) => window.confirm(msg);

  // ── Render tabla simple ──────────────────────────────────
  const renderTable = (tbodyId, rows, cols) => {
    const tbody = document.getElementById(tbodyId);
    if (!tbody) return;

    if (!rows || rows.length === 0) {
      tbody.innerHTML = `<tr><td colspan="${cols.length}"
        class="text-center py-8 text-tierra-400 text-sm">Sin registros</td></tr>`;
      return;
    }

    tbody.innerHTML = rows.map(row =>
      `<tr class="border-b border-tierra-100 hover:bg-tierra-50 transition-colors">
        ${cols.map(col => `<td class="px-4 py-3 text-sm text-tierra-800">${col.render ? col.render(row) : (row[col.key] ?? '—')}</td>`).join('')}
      </tr>`
    ).join('');
  };

  // ── Select dinámico ──────────────────────────────────────
  const populateSelect = (selectId, items, valueKey, labelKey, placeholder = 'Seleccione...') => {
    const sel = document.getElementById(selectId);
    if (!sel) return;
    const getLabel = typeof labelKey === 'function'
      ? labelKey
      : (item) => item[labelKey];
    sel.innerHTML = `<option value="">${placeholder}</option>` +
      items.map(i => `<option value="${i[valueKey]}">${getLabel(i)}</option>`).join('');
  };

  return { get, post, put, del, moneda, kg, fecha, pct, toast, modal, loader, confirm, renderTable, populateSelect };
})();
