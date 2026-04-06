<?php
// ============================================================
// login.php — Inicio de sesión
// ============================================================
require_once __DIR__ . '/bootstrap.php';

// Si ya está autenticado, redirigir
if (Auth::check()) {
    header('Location: ' . APP_URL . '/index.php');
    exit;
}

$error = '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Iniciar sesión — GanaderoPro</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script>
    tailwind.config = {
      theme: {
        extend: {
          colors: {
            tierra: { 50:'#fdf8f0',100:'#f5e6c8',200:'#e8c98a',300:'#d4a843',
                      400:'#b88930',500:'#8b6320',600:'#6b4c18',700:'#523b12',
                      800:'#3a2a0c',900:'#231908' },
            verde:  { 400:'#4d9038',500:'#3a7229',600:'#2a561e',700:'#1e4015' }
          },
          fontFamily: {
            display: ['Fraunces','Georgia','serif'],
            body:    ['DM Sans','system-ui','sans-serif'],
          }
        }
      }
    }
  </script>
  <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,300;9..144,700&family=DM+Sans:wght@400;500&display=swap" rel="stylesheet">
  <style>
    body { font-family:'DM Sans',system-ui,sans-serif; }
    h1,h2 { font-family:'Fraunces',Georgia,serif; }
    .input-login {
      width:100%; padding:.6rem .875rem;
      border:1px solid #e8c98a; border-radius:.375rem;
      font-size:.9rem; outline:none; transition:.15s;
      background:#fdf8f0;
    }
    .input-login:focus {
      border-color:#4d9038; background:#fff;
      box-shadow:0 0 0 3px rgba(77,144,56,.12);
    }
    .bg-pattern {
      background-image:
        radial-gradient(circle at 20% 20%, rgba(74,144,56,.08) 0%, transparent 50%),
        radial-gradient(circle at 80% 80%, rgba(58,42,12,.06) 0%, transparent 50%);
    }
  </style>
</head>
<body class="bg-tierra-50 bg-pattern min-h-screen flex items-center justify-center px-4">

  <div class="w-full max-w-sm">

    <!-- Logo / Marca -->
    <div class="text-center mb-8">
      <div class="inline-flex items-center justify-center w-14 h-14 rounded-2xl
                  bg-tierra-800 shadow-lg mb-4">
        <span class="text-3xl">🐄</span>
      </div>
      <h1 class="text-tierra-800 text-2xl font-bold font-display">GanaderoPro</h1>
      <p class="text-tierra-400 text-sm mt-1">Sistema de gestión ganadera</p>
    </div>

    <!-- Card de login -->
    <div class="bg-white rounded-xl shadow-sm border border-tierra-100 p-8">
      <h2 class="text-tierra-800 text-lg font-semibold font-display mb-6">Iniciar sesión</h2>

      <div id="msg-error" class="hidden mb-4 p-3 bg-red-50 border border-red-200
           rounded-lg text-red-700 text-sm"></div>

      <div class="space-y-4">
        <div>
          <label class="block text-tierra-600 text-xs font-medium mb-1.5 uppercase tracking-wide">
            Correo electrónico
          </label>
          <input type="email" id="email" placeholder="usuario@empresa.com"
                 class="input-login" autocomplete="email">
        </div>
        <div>
          <label class="block text-tierra-600 text-xs font-medium mb-1.5 uppercase tracking-wide">
            Contraseña
          </label>
          <input type="password" id="password" placeholder="••••••••"
                 class="input-login" autocomplete="current-password">
        </div>
        <button id="btn-login"
                class="w-full py-2.5 bg-verde-600 hover:bg-verde-700 text-white
                       rounded-lg font-medium text-sm transition-colors mt-2">
          Ingresar al sistema
        </button>
      </div>
    </div>

    <p class="text-center text-tierra-400 text-xs mt-6">
      GanaderoPro &copy; <?= date('Y') ?>
    </p>
  </div>

<script>
  // Usar ruta relativa para evitar conflictos http vs https
  const API_URL = '/ganadero/api/auth.php';

  document.getElementById('password')
    .addEventListener('keypress', e => { if (e.key === 'Enter') doLogin(); });
  document.getElementById('btn-login')
    .addEventListener('click', doLogin);

  async function doLogin() {
    const email    = document.getElementById('email').value.trim();
    const password = document.getElementById('password').value;
    const errEl    = document.getElementById('msg-error');
    const btn      = document.getElementById('btn-login');

    errEl.classList.add('hidden');

    if (!email || !password) {
      errEl.textContent = 'Ingrese su correo y contraseña.';
      errEl.classList.remove('hidden');
      return;
    }

    btn.disabled    = true;
    btn.textContent = 'Verificando...';

    try {
      const res  = await fetch(API_URL, {
        method:      'POST',
        headers:     { 'Content-Type': 'application/json' },
        credentials: 'same-origin',
        body:        JSON.stringify({ email, password }),
      });
      const data = await res.json();

      if (res.ok && data.success) {
        btn.textContent = '✓ Acceso concedido';
        window.location.href = '/ganadero/index.php';
      } else {
        errEl.textContent = data.message || 'Credenciales inválidas.';
        errEl.classList.remove('hidden');
        btn.disabled    = false;
        btn.textContent = 'Ingresar al sistema';
      }
    } catch (err) {
      console.error('Error login:', err);
      errEl.textContent = 'Error de conexión. Intente de nuevo.';
      errEl.classList.remove('hidden');
      btn.disabled    = false;
      btn.textContent = 'Ingresar al sistema';
    }
  }
</script>
</body>
</html>
