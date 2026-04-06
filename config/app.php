<?php
// ============================================================
// config/app.php — Configuración global de la aplicación
// ============================================================

define('APP_NAME',    'GanaderoPro');
define('APP_VERSION', '1.0.0');

// APP_URL se detecta automáticamente según el protocolo del servidor
// Cambie manualmente solo si su servidor tiene una ruta distinta
if (!defined('APP_URL')) {
    $proto   = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host    = $_SERVER['HTTP_HOST'] ?? 'localhost';
    define('APP_URL', $proto . '://' . $host . '/ganadero');
}

// Orígenes permitidos para CORS (agregar los suyos si es necesario)
define('CORS_ORIGINS', [
    'http://localhost',
    'https://localhost',
    'http://localhost:80',
    'https://localhost:443',
    'http://127.0.0.1',
    'https://127.0.0.1',
]);

// Zona horaria (Colombia)
date_default_timezone_set('America/Bogota');

// Sesión segura
ini_set('session.cookie_httponly', 1);
ini_set('session.use_strict_mode', 1);
session_name('GANADERO_SESS');

// Manejo de errores (cambiar a false en producción)
define('DEBUG_MODE', FALSE);

if (DEBUG_MODE) {
    ini_set('display_errors', 1);
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', 0);
    error_reporting(0);
}
