<?php
// ============================================================
// core/Logger.php — Registro de auditoría de actividad
// ============================================================

class Logger {

    public static function log(
        string  $accion,
        ?string $tabla      = null,
        ?int    $idRegistro = null,
        ?string $detalle    = null
    ): void {
        try {
            $user = Auth::user();
            $stmt = getDB()->prepare(
                'INSERT INTO log_actividad (id_usuario, accion, tabla, id_registro, detalle, ip)
                 VALUES (?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                $user['id'] ?? null,
                $accion,
                $tabla,
                $idRegistro,
                $detalle,
                $_SERVER['REMOTE_ADDR'] ?? null,
            ]);
        } catch (Throwable $e) {
            // Silencioso: no interrumpe flujo principal
            error_log('Logger error: ' . $e->getMessage());
        }
    }
}
