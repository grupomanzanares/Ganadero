<?php
// ============================================================
// core/Helpers.php — Funciones utilitarias globales
// ============================================================

/** Sanitiza string de entrada */
function sanitize(mixed $value): string {
    return htmlspecialchars(strip_tags(trim((string)$value)), ENT_QUOTES, 'UTF-8');
}

/** Retorna valor de $_POST o $_GET sanitizado */
function input(string $key, mixed $default = null, string $method = 'POST'): mixed {
    $source = strtoupper($method) === 'GET' ? $_GET : $_POST;
    if (!isset($source[$key])) return $default;
    return is_array($source[$key])
        ? array_map('sanitize', $source[$key])
        : sanitize($source[$key]);
}

/** Lee cuerpo JSON del request */
function jsonInput(): array {
    $body = file_get_contents('php://input');
    if (empty($body)) return [];
    $data = json_decode($body, true);
    return is_array($data) ? $data : [];
}

/** Formatea número como moneda COP */
function moneda(float $valor): string {
    return '$ ' . number_format($valor, 0, ',', '.');
}

/** Formatea kg */
function kilos(float $valor): string {
    return number_format($valor, 2, ',', '.') . ' kg';
}

/** Calcula meses ganaderos: dias / (365/12) — sin redondeo */
function calcularMeses(int $dias): float {
    return $dias / (365.0 / 12.0);
}

/**
 * Calcula costo manutención POR ANIMAL sin redondeo intermedio.
 * El redondeo se hace solo al sumar el total de todos los animales.
 *
 * Fórmula: dias / (365/12) × tarifa_dia
 *
 * Ejemplo validado:
 *   145 dias, tarifa $11.518, 3 animales
 *   meses = 145 / 30.41666... = 4.767123...
 *   por animal = 4.767123 × 11518 = 54.907,73   (SIN redondear)
 *   total 3 animales = 54.907,73 × 3 = 164.723  (redondear al final) ✓
 *
 * IMPORTANTE: No llamar round() sobre este valor.
 * Redondear solo al acumular el total del lote.
 */
function calcularCostoManutencion(int $dias, float $tarifaDia): float {
    // Retorna valor exacto SIN redondear — el round va al sumar todos los animales
    return calcularMeses($dias) * $tarifaDia;
}

/**
 * Redondea el costo total de manutención de todo el lote.
 * Usar así:
 *   $costoUnitario = calcularCostoManutencion($dias, $tarifa);  // sin round
 *   ... acumular en loop ...
 *   $totalManten = round($sumaTotal, 0);
 */

/** Días entre dos fechas (Y-m-d) */
function diasEntre(string $fechaInicio, string $fechaFin): int {
    $d1 = new DateTime($fechaInicio);
    $d2 = new DateTime($fechaFin);
    return (int)$d1->diff($d2)->days;
}

/** Obtiene la tarifa de manutención vigente para una fecha */
function getTarifaManutencion(string $fecha): float {
    $stmt = getDB()->prepare(
        'SELECT valor_dia FROM tarifas_manutencion
         WHERE fecha_vigencia <= ?
         ORDER BY fecha_vigencia DESC LIMIT 1'
    );
    $stmt->execute([$fecha]);
    $row = $stmt->fetch();
    return $row ? (float)$row['valor_dia'] : 11518.00;
}

/** Genera código incremental de contrato: CC-YYYY-NNN */
function generarCodigoContrato(): string {
    $pdo  = getDB();
    $year = date('Y');
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM contratos_compra WHERE codigo LIKE :prefix"
    );
    $stmt->execute([':prefix' => "CC-{$year}-%"]);
    $count = (int)$stmt->fetchColumn() + 1;
    return sprintf('CC-%s-%03d', $year, $count);
}

/** CSRF token */
function csrfToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrf(string $token): bool {
    return isset($_SESSION['csrf_token']) &&
           hash_equals($_SESSION['csrf_token'], $token);
}
