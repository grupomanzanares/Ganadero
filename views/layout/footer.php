  </main>
</div><!-- /main-wrapper -->

<!-- ══ BOTTOM NAV (móvil) ════════════════════════════════════ -->
<nav id="bottom-nav">
  <a href="<?= APP_URL ?>/index.php"
     class="bnav-item <?= $modulo==='dashboard'?'active':'' ?>">
    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
        d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
    </svg>
    Inicio
  </a>

  <?php if (Auth::can('contratos','ver')): ?>
  <a href="<?= APP_URL ?>/contratos/index.php"
     class="bnav-item <?= in_array($modulo,['contratos','animales'])?'active':'' ?>">
    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
        d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414A1 1 0 0119 9.414V19a2 2 0 01-2 2z"/>
    </svg>
    Contratos
  </a>
  <?php endif; ?>

  <?php if (Auth::can('liquidaciones','ver')): ?>
  <a href="<?= APP_URL ?>/liquidaciones/index.php"
     class="bnav-item <?= $modulo==='liquidaciones'?'active':'' ?>">
    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
        d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
    </svg>
    Liquidar
  </a>
  <?php endif; ?>

  <?php if (Auth::can('reportes','ver')): ?>
  <a href="<?= APP_URL ?>/reportes/socios.php"
     class="bnav-item <?= $modulo==='reporte_socios'?'active':'' ?>">
    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
        d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10"/>
    </svg>
    Reportes
  </a>
  <?php endif; ?>

  <!-- Más opciones -->
  <button class="bnav-item" onclick="abrirSidebar()">
    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
        d="M4 6h16M4 12h16M4 18h7"/>
    </svg>
    Más
  </button>
</nav>

<!-- ══ SCRIPTS GLOBALES ══════════════════════════════════════ -->
<script>
// Sidebar
function abrirSidebar() {
  document.getElementById('sidebar').classList.add('open');
  document.getElementById('sidebar-overlay').classList.add('show');
  document.body.style.overflow = 'hidden';
}
function cerrarSidebar() {
  document.getElementById('sidebar').classList.remove('open');
  document.getElementById('sidebar-overlay').classList.remove('show');
  document.body.style.overflow = '';
}

// Logout
async function cerrarSesion() {
  try { await fetch(APP_URL + '/api/auth.php?action=logout', { credentials: 'same-origin' }); } catch(e) {}
  window.location.href = APP_URL + '/login.php';
}

// Cerrar sidebar con Escape
document.addEventListener('keydown', e => { if (e.key === 'Escape') cerrarSidebar(); });

// Prevenir scroll del body cuando sidebar está abierto en móvil
document.getElementById('sidebar').addEventListener('touchmove', e => e.stopPropagation(), { passive: true });
</script>
</body>
</html>
