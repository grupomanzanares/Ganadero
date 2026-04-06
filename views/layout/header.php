<?php
// ============================================================
// views/layout/header.php — Encabezado y sidebar compartido
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
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($pageTitle ?? 'GanaderoPro') ?> — GanaderoPro</title>

  <script src="https://cdn.tailwindcss.com"></script>
  <script>
    tailwind.config = {
      theme: {
        extend: {
          colors: {
            slate: {
              50:'#f8fafc',75:'#f1f5f9',100:'#f1f5f9',200:'#e2e8f0',
              300:'#cbd5e1',400:'#94a3b8',500:'#64748b',600:'#475569',
              700:'#334155',800:'#1e293b',900:'#0f172a'
            },
            esm: {
              50:'#ecfdf5',100:'#d1fae5',200:'#a7f3d0',
              300:'#6ee7b7',400:'#34d399',500:'#10b981',
              600:'#059669',700:'#047857',800:'#065f46'
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
  <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,300;9..144,600;9..144,700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">

  <style>
    body { font-family:'DM Sans',system-ui,sans-serif; background:#f8fafc; color:#0f172a; }
    h1,h2,h3,h4 { font-family:'Fraunces',Georgia,serif; }

    /* ── Sidebar ── */
    .nav-link { border-left:2px solid transparent; transition:all .15s; text-decoration:none; }
    .nav-link:hover { border-left-color:#059669; background:rgba(255,255,255,.07); color:#fff; }
    .nav-link.active { border-left-color:#059669; background:rgba(5,150,105,.18); color:#fff; }

    /* ── Inputs ── */
    .input-base {
      width:100%; padding:.45rem .75rem;
      border:1px solid #e2e8f0; border-radius:.375rem;
      font-size:.875rem; background:#fff; outline:none; transition:.15s;
      color:#0f172a;
    }
    .input-base:focus { border-color:#059669; box-shadow:0 0 0 3px rgba(5,150,105,.12); }
    .input-base::placeholder { color:#94a3b8; }
    .input-base:disabled { background:#f8fafc; color:#94a3b8; }

    /* ── Botones ── */
    .btn { display:inline-flex; align-items:center; gap:.375rem;
           padding:.5rem 1rem; border-radius:.375rem; font-size:.8125rem;
           font-weight:500; cursor:pointer; transition:.15s; text-decoration:none;
           border:1px solid transparent; }
    .btn:disabled { opacity:.45; cursor:not-allowed; }
    .btn-primary { background:#059669; color:#fff; }
    .btn-primary:hover { background:#047857; }
    .btn-verde  { background:#059669; color:#fff; }
    .btn-verde:hover  { background:#047857; }
    .btn-tierra { background:#1e293b; color:#fff; }
    .btn-tierra:hover { background:#0f172a; }
    .btn-outline { background:#fff; color:#334155; border-color:#e2e8f0; }
    .btn-outline:hover { background:#f8fafc; border-color:#cbd5e1; }
    .btn-danger { background:#dc2626; color:#fff; }
    .btn-danger:hover { background:#b91c1c; }
    .btn-sm  { padding:.3rem .65rem; font-size:.75rem; }
    .btn-xs  { padding:.18rem .55rem; font-size:.7rem; border-radius:.25rem; }

    /* ── Cards ── */
    .card { background:#fff; border:1px solid #e2e8f0; border-radius:.625rem;
            padding:1.5rem; box-shadow:0 1px 4px rgba(15,23,42,.05); }

    /* ── Tablas ── */
    .tabla-base { width:100%; border-collapse:collapse; }
    .tabla-base thead tr { background:#1e293b; color:#e2e8f0; }
    .tabla-base thead th { padding:.75rem 1rem; text-align:left; font-size:.72rem;
                           font-weight:600; letter-spacing:.05em; text-transform:uppercase; }
    .tabla-base tbody tr { border-bottom:1px solid #f1f5f9; transition:.1s; }
    .tabla-base tbody tr:hover { background:#f8fafc; }
    .tabla-base tbody td { padding:.625rem 1rem; font-size:.8125rem; color:#334155; }
    .tabla-base tfoot tr { background:#f1f5f9; border-top:2px solid #e2e8f0; }
    .tabla-base tfoot td { padding:.625rem 1rem; font-size:.8rem; font-weight:600; color:#1e293b; }

    /* ── Form ── */
    .form-label { display:block; font-size:.775rem; font-weight:500; color:#475569; margin-bottom:.3rem; }
    .form-section { background:#f8fafc; border:1px solid #e2e8f0; border-radius:.5rem; padding:1rem 1.25rem; }

    /* ── Stat cards ── */
    .stat-card { background:#fff; border-radius:.625rem; padding:1.25rem;
                 border:1px solid #e2e8f0; position:relative; overflow:hidden;
                 box-shadow:0 1px 4px rgba(15,23,42,.04); }
    .stat-card::before { content:''; position:absolute; top:0; left:0;
                         width:3px; height:100%; background:#059669; }

    /* ── Toast ── */
    #toast { opacity:0; transform:translateY(8px); transition:.3s; pointer-events:none; }

    /* ── Scroll ── */
    ::-webkit-scrollbar { width:5px; height:5px; }
    ::-webkit-scrollbar-thumb { background:#cbd5e1; border-radius:3px; }

    /* ── Input tabla ── */
    .input-tabla { padding:.28rem .45rem; border:1px solid #e2e8f0;
                   border-radius:.25rem; font-size:.8rem; outline:none; background:#fff; }
    .input-tabla:focus { border-color:#059669; }

    /* ── Badges ── */
    .badge-green  { background:#d1fae5; color:#065f46; }
    .badge-blue   { background:#dbeafe; color:#1e40af; }
    .badge-red    { background:#fee2e2; color:#991b1b; }
    .badge-gray   { background:#f1f5f9; color:#475569; }
    .badge-yellow { background:#fef9c3; color:#854d0e; }

    /* ── Input sin flechas ── */
    input[type=number]::-webkit-inner-spin-button,
    input[type=number]::-webkit-outer-spin-button { -webkit-appearance:none; margin:0; }
    input[type=number] { -moz-appearance:textfield; appearance:textfield; }
  </style>
</head>
<body class="bg-slate-50 text-slate-900">

<!-- ── SIDEBAR ──────────────────────────────────────────────── -->
<aside id="sidebar"
  class="fixed top-0 left-0 h-screen w-64 bg-slate-800 flex flex-col z-40 overflow-y-auto">

  <!-- Marca -->
  <div class="px-6 py-5 border-b border-white border-opacity-10">
    <h1 class="font-display text-white text-xl font-bold tracking-tight leading-none">
      Ganader<span class="text-esm-400">o</span>Pro
    </h1>
    <p class="text-slate-400 text-xs mt-0.5 tracking-widest uppercase">Sistema de gestión</p>
  </div>

  <!-- Navegación -->
  <nav class="flex-1 py-3">

    <a href="<?= APP_URL ?>/index.php"
       class="nav-link flex items-center gap-2.5 px-6 py-2.5 text-slate-400 text-sm <?= $modulo==='dashboard'?'active':'' ?>">
      <svg class="w-4 h-4 opacity-70" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
          d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
      </svg>
      Dashboard
    </a>

    <?php if (Auth::can('contratos','ver')): ?>
    <p class="px-4 pt-4 pb-1 text-slate-500 text-xs uppercase tracking-widest font-semibold">Operaciones</p>

    <a href="<?= APP_URL ?>/contratos/index.php"
       class="nav-link flex items-center gap-2.5 px-6 py-2.5 text-slate-400 text-sm <?= $modulo==='contratos'?'active':'' ?>">
      <svg class="w-4 h-4 opacity-70" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
          d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414A1 1 0 0119 9.414V19a2 2 0 01-2 2z"/>
      </svg>
      Contratos de compra
    </a>
    <?php endif; ?>

    <?php if (Auth::can('animales','ver')): ?>
    <a href="<?= APP_URL ?>/contratos/index.php"
       class="nav-link flex items-center gap-2.5 px-6 py-2.5 text-slate-400 text-sm <?= $modulo==='animales'?'active':'' ?>">
      <svg class="w-4 h-4 opacity-70" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
          d="M3 21h18M3 10h18M3 7l9-4 9 4M4 10h16v11H4z"/>
      </svg>
      Pesaje / Animales
    </a>
    <?php endif; ?>

    <?php if (Auth::can('liquidaciones','ver')): ?>
    <a href="<?= APP_URL ?>/liquidaciones/index.php"
       class="nav-link flex items-center gap-2.5 px-6 py-2.5 text-slate-400 text-sm <?= $modulo==='liquidaciones'?'active':'' ?>">
      <svg class="w-4 h-4 opacity-70" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
          d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
      </svg>
      Liquidaciones
    </a>
    <?php endif; ?>

    <?php if (Auth::can('fletes','ver')): ?>
    <a href="<?= APP_URL ?>/fletes/index.php"
       class="nav-link flex items-center gap-2.5 px-6 py-2.5 text-slate-400 text-sm <?= $modulo==='fletes'?'active':'' ?>">
      <svg class="w-4 h-4 opacity-70" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
          d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
      </svg>
      Fletes de salida
    </a>
    <?php endif; ?>

    <?php if (Auth::can('reportes','ver')): ?>
    <p class="px-4 pt-4 pb-1 text-slate-500 text-xs uppercase tracking-widest font-semibold">Reportes</p>

    <a href="<?= APP_URL ?>/reportes/socios.php"
       class="nav-link flex items-center gap-2.5 px-6 py-2.5 text-slate-400 text-sm <?= $modulo==='reporte_socios'?'active':'' ?>">
      <svg class="w-4 h-4 opacity-70" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
          d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
      </svg>
      Reporte por socio
    </a>

    <a href="<?= APP_URL ?>/reportes/cierres.php"
       class="nav-link flex items-center gap-2.5 px-6 py-2.5 text-slate-400 text-sm <?= $modulo==='cierres'?'active':'' ?>">
      <svg class="w-4 h-4 opacity-70" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
          d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
      </svg>
      Cierres de contrato
    </a>
    <?php endif; ?>

    <?php if (Auth::can('empresas','ver')): ?>
    <p class="px-4 pt-4 pb-1 text-slate-500 text-xs uppercase tracking-widest font-semibold">Configuración</p>

    <a href="<?= APP_URL ?>/admin/empresas.php"
       class="nav-link flex items-center gap-2.5 px-6 py-2.5 text-slate-400 text-sm <?= $modulo==='empresas'?'active':'' ?>">
      <svg class="w-4 h-4 opacity-70" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
          d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
      </svg>
      Empresas
    </a>
    <a href="<?= APP_URL ?>/admin/socios.php"
       class="nav-link flex items-center gap-2.5 px-6 py-2.5 text-slate-400 text-sm <?= $modulo==='socios'?'active':'' ?>">
      <svg class="w-4 h-4 opacity-70" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
          d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
      </svg>
      Socios
    </a>
    <a href="<?= APP_URL ?>/admin/manutencion.php"
       class="nav-link flex items-center gap-2.5 px-6 py-2.5 text-slate-400 text-sm <?= $modulo==='manutencion'?'active':'' ?>">
      <svg class="w-4 h-4 opacity-70" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
          d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/>
      </svg>
      Tarifas manutención
    </a>
    <?php endif; ?>

    <?php if (Auth::can('usuarios','ver')): ?>
    <a href="<?= APP_URL ?>/admin/usuarios.php"
       class="nav-link flex items-center gap-2.5 px-6 py-2.5 text-slate-400 text-sm <?= $modulo==='usuarios'?'active':'' ?>">
      <svg class="w-4 h-4 opacity-70" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
          d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>
      </svg>
      Usuarios
    </a>
    <?php endif; ?>

  </nav>

  <!-- Usuario en sesión -->
  <div class="px-5 py-4 border-t border-white border-opacity-10">
    <div class="flex items-center gap-2.5 mb-2">
      <span class="w-7 h-7 rounded-full bg-esm-600 flex items-center justify-center
                   text-white text-xs font-bold flex-shrink-0">
        <?= strtoupper(substr($user['nombre'], 0, 1)) ?>
      </span>
      <div class="min-w-0">
        <p class="text-white text-xs font-medium truncate"><?= htmlspecialchars($user['nombre']) ?></p>
        <p class="text-slate-400 text-xs capitalize"><?= htmlspecialchars($user['rol']) ?></p>
      </div>
    </div>
    <a href="<?= APP_URL ?>/api/auth.php?action=logout"
       class="text-slate-500 hover:text-slate-200 text-xs flex items-center gap-1 mt-1">
      <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
          d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
      </svg>
      Cerrar sesión
    </a>
  </div>
</aside>

<!-- ── CONTENIDO PRINCIPAL ──────────────────────────────────── -->
<div class="ml-64 min-h-screen flex flex-col">

  <!-- Top Header -->
  <header class="sticky top-0 z-30 h-14 bg-white border-b border-slate-200
                 flex items-center justify-between px-6 shadow-sm">
    <div class="flex items-center gap-3">
      <button class="lg:hidden text-slate-400 hover:text-slate-600"
              onclick="document.getElementById('sidebar').classList.toggle('open')">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
        </svg>
      </button>
      <h2 class="font-display text-slate-800 text-base font-semibold">
        <?= htmlspecialchars($pageTitle ?? 'Dashboard') ?>
      </h2>
    </div>
    <div class="flex items-center gap-3">
      <span class="text-xs text-slate-400"><?= date('d/m/Y') ?></span>
      <span class="w-7 h-7 rounded-full bg-esm-100 flex items-center justify-center
                   text-esm-700 text-xs font-bold">
        <?= strtoupper(substr($user['nombre'], 0, 1)) ?>
      </span>
    </div>
  </header>

  <main class="flex-1 p-6">

<div id="toast" class="fixed bottom-6 right-6 z-50 px-5 py-3 rounded-lg shadow-xl text-sm font-medium"></div>
