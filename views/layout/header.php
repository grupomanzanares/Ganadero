<?php
// ============================================================
// views/layout/header.php — Layout responsive mobile-first
// ============================================================
if (!defined('APP_URL')) {
    require_once __DIR__ . '/../../bootstrap.php';
}
Auth::requireLogin();
$user   = Auth::user();
$modulo = $modulo ?? '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
  <meta name="theme-color" content="#1e293b">
  <meta name="mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
  <title><?= htmlspecialchars($pageTitle ?? 'GanaderoPro') ?> — GanaderoPro</title>

  <script src="https://cdn.tailwindcss.com"></script>
  <script>
    tailwind.config = {
      theme: {
        extend: {
          colors: {
            slate: {
              50:'#f8fafc',100:'#f1f5f9',200:'#e2e8f0',300:'#cbd5e1',
              400:'#94a3b8',500:'#64748b',600:'#475569',700:'#334155',
              800:'#1e293b',900:'#0f172a'
            },
            esm: {
              50:'#ecfdf5',100:'#d1fae5',200:'#a7f3d0',300:'#6ee7b7',
              400:'#34d399',500:'#10b981',600:'#059669',700:'#047857',800:'#065f46'
            }
          },
          fontFamily: {
            display: ['Fraunces','Georgia','serif'],
            body:    ['DM Sans','system-ui','sans-serif'],
          }
        }
      }
    }
  </script>

  <script>const APP_URL = '<?= APP_URL ?>';</script>
  <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,600;9..144,700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">

  <style>
  /* ══ RESET & BASE ══════════════════════════════════════════ */
  *, *::before, *::after { box-sizing: border-box; }
  body {
    font-family: 'DM Sans', system-ui, sans-serif;
    background: #f8fafc; color: #0f172a;
    margin: 0; min-height: 100vh;
    /* Espacio para bottom nav en móvil */
    padding-bottom: 64px;
  }
  @media (min-width: 1024px) {
    body { padding-bottom: 0; }
  }
  h1,h2,h3,h4 { font-family: 'Fraunces', Georgia, serif; }

  /* ══ SIDEBAR ════════════════════════════════════════════════ */
  #sidebar {
    position: fixed; top: 0; left: 0;
    height: 100vh; width: 260px;
    background: #1e293b;
    display: flex; flex-direction: column;
    z-index: 50; overflow-y: auto;
    transform: translateX(-100%);
    transition: transform .28s cubic-bezier(.4,0,.2,1);
    /* Scroll suave en iOS */
    -webkit-overflow-scrolling: touch;
  }
  @media (min-width: 1024px) {
    #sidebar { transform: translateX(0); }
  }
  #sidebar.open { transform: translateX(0); }

  /* Overlay al abrir sidebar en móvil */
  #sidebar-overlay {
    display: none; position: fixed; inset: 0;
    background: rgba(0,0,0,.45); z-index: 49;
    backdrop-filter: blur(2px);
  }
  #sidebar-overlay.show { display: block; }

  .nav-link {
    border-left: 2px solid transparent;
    transition: all .15s; text-decoration: none;
    display: flex; align-items: center; gap: 10px;
    padding: 11px 24px; color: #94a3b8; font-size: .875rem;
    /* Área táctil mínima de 44px */
    min-height: 44px;
  }
  .nav-link:hover  { border-left-color:#059669; background:rgba(255,255,255,.06); color:#fff; }
  .nav-link.active { border-left-color:#059669; background:rgba(5,150,105,.16); color:#fff; }

  /* ══ MAIN LAYOUT ════════════════════════════════════════════ */
  #main-wrapper {
    min-height: 100vh; display: flex; flex-direction: column;
    transition: margin-left .28s cubic-bezier(.4,0,.2,1);
  }
  @media (min-width: 1024px) {
    #main-wrapper { margin-left: 260px; }
  }

  /* ══ TOP HEADER ═════════════════════════════════════════════ */
  #top-header {
    position: sticky; top: 0; z-index: 30;
    height: 56px; background: #fff;
    border-bottom: 1px solid #e2e8f0;
    display: flex; align-items: center;
    justify-content: space-between;
    padding: 0 16px;
    box-shadow: 0 1px 3px rgba(15,23,42,.06);
  }
  @media (min-width: 640px) {
    #top-header { padding: 0 24px; }
  }

  /* ══ BOTTOM NAV (solo móvil) ════════════════════════════════ */
  #bottom-nav {
    position: fixed; bottom: 0; left: 0; right: 0;
    height: 64px; background: #1e293b;
    display: flex; align-items: stretch;
    z-index: 40;
    border-top: 1px solid rgba(255,255,255,.08);
    /* Safe area en iPhone X+ */
    padding-bottom: env(safe-area-inset-bottom);
  }
  @media (min-width: 1024px) {
    #bottom-nav { display: none; }
  }
  .bnav-item {
    flex: 1; display: flex; flex-direction: column;
    align-items: center; justify-content: center;
    gap: 3px; color: #64748b; text-decoration: none;
    font-size: .6rem; font-weight: 500;
    letter-spacing: .03em; text-transform: uppercase;
    transition: color .15s; min-height: 44px;
    border: none; background: transparent; cursor: pointer;
  }
  .bnav-item.active, .bnav-item:active { color: #10b981; }
  .bnav-item svg { width: 22px; height: 22px; }

  /* ══ CONTENIDO ══════════════════════════════════════════════ */
  #page-content {
    flex: 1; padding: 16px;
  }
  @media (min-width: 640px) {
    #page-content { padding: 20px; }
  }
  @media (min-width: 1024px) {
    #page-content { padding: 24px; }
  }

  /* ══ COMPONENTES ════════════════════════════════════════════ */
  .input-base {
    width: 100%; padding: .5rem .75rem;
    border: 1px solid #e2e8f0; border-radius: .4rem;
    font-size: .875rem; background: #fff; outline: none;
    color: #0f172a; transition: .15s;
    /* Prevenir zoom en iOS al enfocar */
    font-size: 16px;
  }
  @media (min-width: 640px) {
    .input-base { font-size: .875rem; }
  }
  .input-base:focus { border-color: #059669; box-shadow: 0 0 0 3px rgba(5,150,105,.12); }
  .input-base::placeholder { color: #94a3b8; }
  .input-base:disabled { background: #f8fafc; color: #94a3b8; }

  select.input-base { cursor: pointer; }

  .btn {
    display: inline-flex; align-items: center; gap: .375rem;
    padding: .5rem 1rem; border-radius: .4rem; font-size: .8125rem;
    font-weight: 500; cursor: pointer; transition: .15s;
    text-decoration: none; border: 1px solid transparent;
    white-space: nowrap; min-height: 40px;
  }
  .btn:disabled { opacity: .45; cursor: not-allowed; }
  .btn-verde   { background: #059669; color: #fff; }
  .btn-verde:hover   { background: #047857; }
  .btn-tierra  { background: #1e293b; color: #fff; }
  .btn-tierra:hover  { background: #0f172a; }
  .btn-outline { background: #fff; color: #334155; border-color: #e2e8f0; }
  .btn-outline:hover { background: #f8fafc; border-color: #cbd5e1; }
  .btn-danger  { background: #dc2626; color: #fff; }
  .btn-danger:hover  { background: #b91c1c; }
  .btn-sm { padding: .3rem .65rem; font-size: .75rem; min-height: 34px; }
  .btn-xs { padding: .18rem .55rem; font-size: .7rem; border-radius: .25rem; min-height: 28px; }

  .card {
    background: #fff; border: 1px solid #e2e8f0;
    border-radius: .625rem; padding: 1rem;
    box-shadow: 0 1px 4px rgba(15,23,42,.05);
  }
  @media (min-width: 640px) {
    .card { padding: 1.5rem; }
  }

  .tabla-base { width: 100%; border-collapse: collapse; }
  .tabla-base thead tr { background: #1e293b; color: #e2e8f0; }
  .tabla-base thead th {
    padding: .625rem .875rem; text-align: left; font-size: .7rem;
    font-weight: 600; letter-spacing: .05em; text-transform: uppercase;
    white-space: nowrap;
  }
  .tabla-base tbody tr { border-bottom: 1px solid #f1f5f9; transition: .1s; }
  .tabla-base tbody tr:hover { background: #f8fafc; }
  .tabla-base tbody td { padding: .6rem .875rem; font-size: .8125rem; color: #334155; }
  .tabla-base tfoot tr { background: #f1f5f9; border-top: 2px solid #e2e8f0; }
  .tabla-base tfoot td { padding: .625rem .875rem; font-size: .8rem; font-weight: 600; color: #1e293b; }

  /* Tablas scrolleables en móvil */
  .tabla-scroll { overflow-x: auto; -webkit-overflow-scrolling: touch; }

  .form-label { display: block; font-size: .775rem; font-weight: 500; color: #475569; margin-bottom: .3rem; }
  .form-section { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: .5rem; padding: 1rem; }

  .stat-card {
    background: #fff; border-radius: .625rem; padding: 1rem 1.25rem;
    border: 1px solid #e2e8f0; position: relative; overflow: hidden;
  }
  .stat-card::before {
    content: ''; position: absolute; top: 0; left: 0;
    width: 3px; height: 100%; background: #059669;
  }

  .badge-green  { background: #d1fae5; color: #065f46; }
  .badge-blue   { background: #dbeafe; color: #1e40af; }
  .badge-red    { background: #fee2e2; color: #991b1b; }
  .badge-gray   { background: #f1f5f9; color: #475569; }
  .badge-yellow { background: #fef9c3; color: #854d0e; }

  #toast {
    opacity: 0; transform: translateY(8px); transition: .3s;
    pointer-events: none; max-width: calc(100vw - 2rem);
  }

  /* Input tabla sin flechas */
  input[type=number]::-webkit-inner-spin-button,
  input[type=number]::-webkit-outer-spin-button { -webkit-appearance: none; margin: 0; }
  input[type=number] { -moz-appearance: textfield; appearance: textfield; }

  .input-tabla {
    padding: .3rem .5rem; border: 1px solid #e2e8f0;
    border-radius: .25rem; font-size: .875rem; outline: none; background: #fff;
    min-height: 36px;
  }
  .input-tabla:focus { border-color: #059669; }

  ::-webkit-scrollbar { width: 4px; height: 4px; }
  ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 3px; }

  /* Separadores de grupo en sidebar */
  .nav-group-label {
    padding: 14px 16px 4px;
    font-size: .65rem; font-weight: 700; text-transform: uppercase;
    letter-spacing: .09em; color: #475569;
  }
  </style>
</head>
<body class="bg-slate-50 text-slate-900">

<!-- ══ OVERLAY ════════════════════════════════════════════════ -->
<div id="sidebar-overlay" onclick="cerrarSidebar()"></div>

<!-- ══ SIDEBAR ════════════════════════════════════════════════ -->
<aside id="sidebar">

  <!-- Logo -->
  <div class="px-6 py-5 border-b border-white border-opacity-10 flex-shrink-0">
    <div class="flex items-center justify-between">
      <div>
        <h1 class="font-display text-white text-xl font-bold tracking-tight leading-none">
          Ganader<span class="text-esm-400">o</span>Pro
        </h1>
        <p class="text-slate-500 text-xs mt-0.5 tracking-widest uppercase">Sistema de gestión</p>
      </div>
      <!-- Botón cerrar en móvil -->
      <button onclick="cerrarSidebar()"
              class="lg:hidden text-slate-500 hover:text-white p-1 rounded"
              style="min-height:44px;min-width:44px;display:flex;align-items:center;justify-content:center">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
        </svg>
      </button>
    </div>
  </div>

  <!-- Navegación -->
  <nav class="flex-1 py-2 overflow-y-auto">

    <a href="<?= APP_URL ?>/index.php" onclick="cerrarSidebar()"
       class="nav-link <?= $modulo==='dashboard'?'active':'' ?>">
      <svg class="w-4 h-4 flex-shrink-0 opacity-70" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
          d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
      </svg>
      Dashboard
    </a>

    <?php if (Auth::can('contratos','ver') || Auth::can('animales','ver') || Auth::can('liquidaciones','ver') || Auth::can('fletes','ver')): ?>
    <p class="nav-group-label">Operaciones</p>
    <?php endif; ?>

    <?php if (Auth::can('contratos','ver')): ?>
    <a href="<?= APP_URL ?>/contratos/index.php" onclick="cerrarSidebar()"
       class="nav-link <?= $modulo==='contratos'?'active':'' ?>">
      <svg class="w-4 h-4 flex-shrink-0 opacity-70" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
          d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414A1 1 0 0119 9.414V19a2 2 0 01-2 2z"/>
      </svg>
      Contratos de compra
    </a>
    <?php endif; ?>

    <?php if (Auth::can('animales','ver')): ?>
    <a href="<?= APP_URL ?>/animales/pesaje.php" onclick="cerrarSidebar()"
       class="nav-link <?= $modulo==='animales'?'active':'' ?>">
      <svg class="w-4 h-4 flex-shrink-0 opacity-70" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
          d="M3 21h18M3 10h18M3 7l9-4 9 4M4 10h16v11H4z"/>
      </svg>
      Pesaje / Animales
    </a>
    <?php endif; ?>

    <?php if (Auth::can('liquidaciones','ver')): ?>
    <a href="<?= APP_URL ?>/liquidaciones/index.php" onclick="cerrarSidebar()"
       class="nav-link <?= $modulo==='liquidaciones'?'active':'' ?>">
      <svg class="w-4 h-4 flex-shrink-0 opacity-70" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
          d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
      </svg>
      Liquidaciones
    </a>
    <?php endif; ?>

    <?php if (Auth::can('fletes','ver')): ?>
    <a href="<?= APP_URL ?>/fletes/index.php" onclick="cerrarSidebar()"
       class="nav-link <?= $modulo==='fletes'?'active':'' ?>">
      <svg class="w-4 h-4 flex-shrink-0 opacity-70" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
          d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
      </svg>
      Fletes de salida
    </a>
    <?php endif; ?>

    <?php if (Auth::can('reportes','ver')): ?>
    <p class="nav-group-label">Reportes</p>

    <a href="<?= APP_URL ?>/reportes/socios.php" onclick="cerrarSidebar()"
       class="nav-link <?= $modulo==='reporte_socios'?'active':'' ?>">
      <svg class="w-4 h-4 flex-shrink-0 opacity-70" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
          d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
      </svg>
      Reporte por socio
    </a>

    <a href="<?= APP_URL ?>/reportes/cierres.php" onclick="cerrarSidebar()"
       class="nav-link <?= $modulo==='cierres'?'active':'' ?>">
      <svg class="w-4 h-4 flex-shrink-0 opacity-70" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
          d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
      </svg>
      Cierres de contrato
    </a>
    <?php endif; ?>

    <?php if (Auth::can('empresas','ver')): ?>
    <p class="nav-group-label">Configuración</p>

    <a href="<?= APP_URL ?>/admin/empresas.php" onclick="cerrarSidebar()"
       class="nav-link <?= $modulo==='empresas'?'active':'' ?>">
      <svg class="w-4 h-4 flex-shrink-0 opacity-70" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
          d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
      </svg>
      Empresas
    </a>
    <a href="<?= APP_URL ?>/admin/socios.php" onclick="cerrarSidebar()"
       class="nav-link <?= $modulo==='socios'?'active':'' ?>">
      <svg class="w-4 h-4 flex-shrink-0 opacity-70" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
          d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
      </svg>
      Socios
    </a>
    <a href="<?= APP_URL ?>/admin/manutencion.php" onclick="cerrarSidebar()"
       class="nav-link <?= $modulo==='manutencion'?'active':'' ?>">
      <svg class="w-4 h-4 flex-shrink-0 opacity-70" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
          d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/>
      </svg>
      Tarifas manutención
    </a>
    <?php endif; ?>

    <?php if (Auth::can('usuarios','ver')): ?>
    <a href="<?= APP_URL ?>/admin/usuarios.php" onclick="cerrarSidebar()"
       class="nav-link <?= $modulo==='usuarios'?'active':'' ?>">
      <svg class="w-4 h-4 flex-shrink-0 opacity-70" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
          d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>
      </svg>
      Usuarios
    </a>
    <?php endif; ?>

  </nav>

  <!-- Usuario + logout -->
  <div class="px-5 py-4 border-t border-white border-opacity-10 flex-shrink-0">
    <div class="flex items-center gap-2.5">
      <span class="w-8 h-8 rounded-full bg-esm-600 flex items-center justify-center
                   text-white text-sm font-bold flex-shrink-0">
        <?= strtoupper(substr($user['nombre'], 0, 1)) ?>
      </span>
      <div class="min-w-0 flex-1">
        <p class="text-white text-xs font-medium truncate"><?= htmlspecialchars($user['nombre']) ?></p>
        <p class="text-slate-400 text-xs capitalize"><?= htmlspecialchars($user['rol']) ?></p>
      </div>
      <button onclick="cerrarSesion()"
              title="Cerrar sesión"
              class="text-slate-500 hover:text-red-400 transition-colors flex-shrink-0"
              style="background:none;border:none;cursor:pointer;padding:8px;min-height:44px;min-width:44px;display:flex;align-items:center;justify-content:center">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
            d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
        </svg>
      </button>
    </div>
  </div>
</aside>

<!-- ══ MAIN ═══════════════════════════════════════════════════ -->
<div id="main-wrapper">

  <!-- Top header -->
  <header id="top-header">
    <div class="flex items-center gap-3">
      <!-- Botón menú hamburger (solo móvil/tablet) -->
      <button onclick="abrirSidebar()" id="btn-menu"
              class="lg:hidden flex items-center justify-center rounded-lg
                     text-slate-500 hover:text-slate-700 hover:bg-slate-100 transition-colors"
              style="width:40px;height:40px;flex-shrink:0">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
        </svg>
      </button>
      <!-- Logo solo en móvil -->
      <span class="lg:hidden font-display text-slate-800 font-bold text-base">
        Ganader<span class="text-esm-600">o</span>Pro
      </span>
      <!-- Título en desktop -->
      <h2 class="hidden lg:block font-display text-slate-800 text-base font-semibold">
        <?= htmlspecialchars($pageTitle ?? 'Dashboard') ?>
      </h2>
    </div>
    <div class="flex items-center gap-2">
      <span class="hidden sm:block text-xs text-slate-400"><?= date('d/m/Y') ?></span>
      <span class="w-8 h-8 rounded-full bg-esm-100 flex items-center justify-center
                   text-esm-700 text-sm font-bold flex-shrink-0">
        <?= strtoupper(substr($user['nombre'], 0, 1)) ?>
      </span>
    </div>
  </header>

  <!-- Título de página visible en móvil (debajo del header) -->
  <div class="lg:hidden px-4 py-2 bg-white border-b border-slate-100">
    <p class="font-display text-slate-700 text-sm font-semibold">
      <?= htmlspecialchars($pageTitle ?? 'Dashboard') ?>
    </p>
  </div>

  <!-- Contenido -->
  <main id="page-content">
    <div id="toast" class="fixed bottom-20 right-4 lg:bottom-6 lg:right-6 z-50
                           px-4 py-3 rounded-lg shadow-xl text-sm font-medium"></div>
