<?php
// ============================================================
// bootstrap.php — Punto de arranque común
// ============================================================

require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/core/Auth.php';
require_once __DIR__ . '/core/Response.php';
require_once __DIR__ . '/core/Helpers.php';
require_once __DIR__ . '/core/Logger.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ── Headers CORS ─────────────────────────────────────────────
// Permite peticiones fetch() desde el mismo servidor (http y https)
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';

if (in_array($origin, CORS_ORIGINS, true)) {
    header('Access-Control-Allow-Origin: ' . $origin);
} else {
    // Si no hay Origin (petición directa del servidor) permitir
    header('Access-Control-Allow-Origin: *');
}

header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Access-Control-Max-Age: 86400');

// Responder inmediatamente a preflight OPTIONS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}
