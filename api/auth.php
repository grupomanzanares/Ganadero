<?php
// ============================================================
// api/auth.php — Login y logout
// ============================================================

require_once __DIR__ . '/../bootstrap.php';

$method = $_SERVER['REQUEST_METHOD'];
$action = input('action', 'login', 'GET');

// ── LOGOUT ────────────────────────────────────────────────
if ($action === 'logout') {
    Auth::logout();
    // Siempre redirigir al login (sea fetch o link directo)
    header('Location: /ganadero/login.php');
    exit;
}

header('Content-Type: application/json; charset=utf-8');

// ── LOGIN ─────────────────────────────────────────────────
if ($method === 'POST') {
    $data = jsonInput();

    if (empty($data['email']) || empty($data['password'])) {
        Response::error('Email y contraseña son requeridos.');
    }

    $ok = Auth::login(
        filter_var($data['email'], FILTER_SANITIZE_EMAIL),
        $data['password']
    );

    if (!$ok) {
        Response::error('Credenciales inválidas.', 401);
    }

    $user = Auth::user();
    Logger::log('login', 'usuarios', $user['id']);
    Response::success([
        'id'     => $user['id'],
        'nombre' => $user['nombre'],
        'email'  => $user['email'],
        'rol'    => $user['rol'],
    ], 'Sesión iniciada.');
}

// ── VERIFICAR SESIÓN ──────────────────────────────────────
if ($method === 'GET' && $action === 'me') {
    if (!Auth::check()) Response::unauthorized();
    Response::success(Auth::user());
}

Response::error('Método no permitido.', 405);
