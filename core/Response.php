<?php
// ============================================================
// core/Response.php — Respuestas JSON estandarizadas para APIs
// ============================================================

class Response {

    public static function json(mixed $data, int $code = 200): void {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    public static function success(mixed $data = null, string $message = 'OK'): void {
        self::json(['success' => true, 'message' => $message, 'data' => $data]);
    }

    public static function error(string $message, int $code = 400, mixed $errors = null): void {
        self::json(['success' => false, 'message' => $message, 'errors' => $errors], $code);
    }

    public static function notFound(string $message = 'Registro no encontrado.'): void {
        self::error($message, 404);
    }

    public static function unauthorized(): void {
        self::error('No autorizado. Inicie sesión.', 401);
    }

    public static function forbidden(): void {
        self::error('Sin permiso para esta acción.', 403);
    }

    public static function serverError(string $message = 'Error interno del servidor.'): void {
        self::error($message, 500);
    }
}
