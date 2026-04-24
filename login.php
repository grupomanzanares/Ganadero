<?php
// ============================================================
// login.php — Inicio de sesión (responsive)
// ============================================================
require_once __DIR__ . '/bootstrap.php';

if (Auth::check()) {
    header('Location: ' . APP_URL . '/index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
  <meta name="theme-color" content="#1e293b">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <title>Iniciar sesión — GanaderoPro</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script>
    tailwind.config = {
      theme: { extend: {
        colors: {
          esm:  { 50:'#ecfdf5',100:'#d1fae5',500:'#10b981',600:'#059669',700:'#047857' },
          slate:{ 50:'#f8fafc',100:'#f1f5f9',200:'#e2e8f0',400:'#94a3b8',600:'#475569',700:'#334155',800:'#1e293b',900:'#0f172a' }
        },
        fontFamily: {
          display: ['Fraunces','Georgia','serif'],
          body:    ['DM Sans','system-ui','sans-serif'],
        }
      }}
    }
  </script>
  <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,700&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
  <style>
    * { box-sizing: border-box; }
    body { font-family:'DM Sans',system-ui,sans-serif; margin:0; min-height:100vh;
           background:linear-gradient(135deg,#0f172a 0%,#1e293b 50%,#0f2417 100%); }
    h1,h2 { font-family:'Fraunces',Georgia,serif; }

    .login-card {
      background:#fff; border-radius:1rem;
      box-shadow:0 25px 60px rgba(0,0,0,.35);
      overflow:hidden; width:100%; max-width:400px;
    }
    .login-header {
      background:linear-gradient(135deg,#1e293b,#0f172a);
      padding:2rem; text-align:center;
    }
    .login-body { padding:1.5rem; }
    @media(min-width:480px) { .login-body { padding:2rem; } }

    .inp {
      width:100%; padding:.75rem 1rem;
      border:1.5px solid #e2e8f0; border-radius:.5rem;
      font-size:16px;   /* 16px previene zoom en iOS */
      outline:none; transition:.15s; color:#0f172a; background:#f8fafc;
    }
    .inp:focus { border-color:#059669; background:#fff; box-shadow:0 0 0 3px rgba(5,150,105,.12); }
    .inp::placeholder { color:#94a3b8; }

    .btn-login {
      width:100%; padding:.875rem; border-radius:.5rem;
      background:linear-gradient(135deg,#059669,#047857);
      color:#fff; font-size:1rem; font-weight:600;
      border:none; cursor:pointer; transition:.2s;
      box-shadow:0 4px 14px rgba(5,150,105,.35);
    }
    .btn-login:hover { transform:translateY(-1px); box-shadow:0 6px 20px rgba(5,150,105,.4); }
    .btn-login:active { transform:translateY(0); }
    .btn-login:disabled { opacity:.6; cursor:not-allowed; transform:none; }
  </style>
</head>
<body class="flex items-center justify-center min-h-screen p-4">

  <div class="login-card">

    <!-- Cabecera -->
    <div class="login-header">
      <div class="inline-flex items-center justify-center w-16 h-16 rounded-2xl
                  bg-esm-600 bg-opacity-20 border border-white border-opacity-10 mb-4">
        <span style="font-size:2rem">🐄</span>
      </div>
      <h1 class="text-white text-2xl font-bold mb-1">Ganadero</h1>
      <p class="text-slate-400 text-sm">Sistema de gestión ganadera</p>
    </div>

    <!-- Formulario -->
    <div class="login-body">
      <h2 class="text-slate-800 text-lg font-semibold mb-5">Iniciar sesión</h2>

      <?php if (isset($_GET['expired'])): ?>
      <div class="mb-4 p-3 rounded-lg text-sm font-medium"
           style="background:#fffbeb;border:1px solid #fde68a;color:#92400e">
        Su sesión expiró por inactividad. Por favor inicie sesión nuevamente.
      </div>
      <?php endif; ?>

      <div id="msg-error"
           class="hidden mb-4 p-3 rounded-lg text-sm font-medium"
           style="background:#fef2f2;border:1px solid #fecaca;color:#dc2626"></div>

      <div style="display:flex;flex-direction:column;gap:1rem">
        <div>
          <label class="form-label" style="display:block;font-size:.8rem;font-weight:600;color:#475569;margin-bottom:.4rem;text-transform:uppercase;letter-spacing:.05em">
            Correo electrónico
          </label>
          <input type="email" id="email" class="inp"
                 placeholder="usuario@empresa.com"
                 autocomplete="email" inputmode="email">
        </div>
        <div>
          <label class="form-label" style="display:block;font-size:.8rem;font-weight:600;color:#475569;margin-bottom:.4rem;text-transform:uppercase;letter-spacing:.05em">
            Contraseña
          </label>
          <div style="position:relative">
            <input type="password" id="password" class="inp"
                   placeholder="••••••••"
                   autocomplete="current-password"
                   style="padding-right:3rem">
            <button type="button" id="btn-toggle-pwd"
                    onclick="togglePwd()"
                    style="position:absolute;right:.75rem;top:50%;transform:translateY(-50%);
                           background:none;border:none;cursor:pointer;color:#94a3b8;
                           padding:.25rem;display:flex;align-items:center">
              <svg id="ico-eye" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
              </svg>
            </button>
          </div>
        </div>
        <button id="btn-login" class="btn-login" onclick="doLogin()">
          Ingresar
        </button>
      </div>

      <p class="text-center text-slate-400 text-xs mt-6">
        Ganadero &copy; <?= date('Y') ?>
      </p>
    </div>
  </div>

<script>
const API_URL = '/ganadero/api/auth.php';

// Mostrar/ocultar contraseña
function togglePwd() {
  const inp = document.getElementById('password');
  inp.type  = inp.type === 'password' ? 'text' : 'password';
}

// Enter en el campo de contraseña
document.getElementById('password').addEventListener('keypress', e => {
  if (e.key === 'Enter') doLogin();
});
document.getElementById('email').addEventListener('keypress', e => {
  if (e.key === 'Enter') document.getElementById('password').focus();
});

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
      btn.textContent = 'Ingresar';
    }
  } catch (err) {
    console.error('Error login:', err);
    errEl.textContent = 'Error de conexión. Intente de nuevo.';
    errEl.classList.remove('hidden');
    btn.disabled    = false;
    btn.textContent = 'Ingresar';
  }
}
</script>
</body>
</html>
