<?php
// ============================================================
// api/liquidaciones.php — Liquidación y venta de animales
// ============================================================

require_once __DIR__ . '/../bootstrap.php';
header('Content-Type: application/json; charset=utf-8');

if (!Auth::check()) Response::unauthorized();

$method = $_SERVER['REQUEST_METHOD'];
$action = input('action', '', 'GET');
$id     = (int)input('id', 0, 'GET');

match ($method) {
    'GET'  => handleGet($action, $id),
    'POST' => handlePost(),
    default => Response::error('Método no permitido.', 405),
};

// ── GET ──────────────────────────────────────────────────────
function handleGet(string $action, int $id): void {
    Auth::requirePermission('liquidaciones', 'ver');
    $pdo = getDB();

    // Previsualización de costos antes de confirmar
    if ($action === 'preview') {
        $codigosRaw  = input('codigos', '', 'GET');
        $fechaVenta  = input('fecha_venta', date('Y-m-d'), 'GET');
        // Flete de salida e otros gastos opcionales para preview en tiempo real
        $idFletePrev     = (int)input('id_flete_salida', 0, 'GET');
        $otrosGastosPrev = (float)input('otros_gastos', 0, 'GET');

        if (empty($codigosRaw)) Response::error('Debe indicar los códigos de animal.');

        $codigos = array_values(array_filter(array_map('trim', explode(',', $codigosRaw))));
        if (empty($codigos)) Response::error('Códigos inválidos.');

        // Obtener flete por animal si se seleccionó
        $fleteSalidaPrev = 0;
        if ($idFletePrev > 0) {
            $sfp = $pdo->prepare('SELECT valor_por_animal FROM fletes_salida WHERE id = ?');
            $sfp->execute([$idFletePrev]);
            $fp = $sfp->fetch();
            if ($fp) $fleteSalidaPrev = (float)$fp['valor_por_animal'];
        }

        $placeholders = implode(',', array_fill(0, count($codigos), '?'));
        $stmt = $pdo->prepare(
            "SELECT a.id, a.codigo, a.peso_finca_kg, a.peso_inicial_kg,
                    a.costo_compra_animal, a.costo_flete_animal,
                    a.estado, c.fecha_compra, c.id AS id_contrato, c.codigo AS contrato_codigo
             FROM animales a
             JOIN contratos_compra c ON c.id = a.id_contrato
             WHERE a.codigo IN ({$placeholders}) AND a.estado = 'activo'"
        );
        $stmt->execute($codigos);
        $animales = $stmt->fetchAll();
        if (empty($animales)) Response::error('No se encontraron animales activos con esos códigos.');

        // Otros gastos se prorratean entre todos los animales
        $cantAnimalesPrev    = count($animales);
        $otrosGastasPorAnim  = $cantAnimalesPrev > 0 ? round($otrosGastosPrev / $cantAnimalesPrev, 2) : 0;

        $preview = [];
        foreach ($animales as $a) {
            $dias          = diasEntre($a['fecha_compra'], $fechaVenta);
            $meses         = calcularMeses($dias);
            $tarifa        = getTarifaManutencion($fechaVenta);

            // Manutención: valor EXACTO sin redondear por animal
            // round() se aplica al TOTAL del lote para evitar acumulación de error
            // Ej: 145 dias → 4.767123 × 11518 = 54907.73 (sin round)
            // 3 animales: 54907.73 × 3 = 164723.19 → round = 164.723 ✓
            $costoManutSinRound = calcularCostoManutencion($dias, $tarifa);

            $costoCompra   = (float)$a['costo_compra_animal'];
            $costoFleteEnt = (float)$a['costo_flete_animal'];
            $costoFleteSal = $fleteSalidaPrev;
            $otrosGastosAn = $otrosGastasPorAnim;

            // costo_total exacto sin redondeos intermedios
            $costoTotalExacto = $costoCompra + $costoFleteEnt
                              + $costoManutSinRound
                              + $costoFleteSal + $otrosGastosAn;

            // Valor promedio $/kg del animal (compra + flete entrada) / peso_finca
            $pesoFinca = (float)$a['peso_finca_kg'];
            $valorPromedioKg = ($pesoFinca > 0)
                ? round(($costoCompra + $costoFleteEnt) / $pesoFinca, 4)
                : null;

            $preview[] = [
                'id_animal'           => $a['id'],
                'codigo'              => $a['codigo'],
                'id_contrato'         => $a['id_contrato'],
                'contrato_codigo'     => $a['contrato_codigo'],
                'fecha_compra'        => $a['fecha_compra'],
                'dias_manutencion'    => $dias,
                'meses_manutencion'   => round($meses, 2),   // 4.77 para mostrar
                'tarifa_manutencion'  => $tarifa,
                'costo_manutencion'   => round($costoManutSinRound, 2),  // para mostrar por animal
                'costo_compra'        => $costoCompra,
                'costo_flete_entrada' => $costoFleteEnt,
                'costo_flete_salida'  => $costoFleteSal,
                'otros_gastos'        => $otrosGastosAn,
                'costo_total'         => round($costoTotalExacto, 2),
                'peso_finca_kg'       => $pesoFinca,
                'valor_promedio_kg'   => $valorPromedioKg,
            ];
        }
        Response::success($preview);
    }

    // Detalle de una liquidación
    if ($id > 0) {
        $stmt = $pdo->prepare(
            'SELECT l.*, c.codigo AS contrato_codigo,
                    e.nombre AS empresa_factura, cl.nombre AS cliente
             FROM liquidaciones l
             JOIN contratos_compra c  ON c.id  = l.id_contrato
             LEFT JOIN empresas e     ON e.id  = l.id_empresa_factura
             LEFT JOIN clientes cl    ON cl.id = l.id_cliente
             WHERE l.id = ?'
        );
        $stmt->execute([$id]);
        $liq = $stmt->fetch();
        if (!$liq) Response::notFound();

        $stmt2 = $pdo->prepare(
            'SELECT la.*, a.codigo AS animal_codigo
             FROM liquidacion_animales la
             JOIN animales a ON a.id = la.id_animal
             WHERE la.id_liquidacion = ?'
        );
        $stmt2->execute([$id]);
        $liq['animales'] = $stmt2->fetchAll();
        Response::success($liq);
    }

    // Listado de liquidaciones por contrato
    $idContrato = (int)input('contrato', 0, 'GET');
    $where  = ['1=1'];
    $params = [];
    if ($idContrato > 0) {
        // Incluye cualquier liquidación que contenga al menos un animal de este contrato,
        // independientemente de cuál sea el id_contrato cabecera de la liquidación.
        $where[]  = 'l.id IN (SELECT DISTINCT la.id_liquidacion
                               FROM liquidacion_animales la
                               JOIN animales a ON a.id = la.id_animal
                               WHERE a.id_contrato = ?)';
        $params[] = $idContrato;
    }

    $stmt = $pdo->prepare(
        'SELECT l.id, l.id_contrato, l.numero_factura, l.fecha_venta, l.peso_total_kg,
                l.valor_total_venta, l.estado,
                c.codigo AS contrato_codigo,
                e.nombre AS empresa_factura,
                cl.nombre AS cliente,
                (SELECT COUNT(*) FROM liquidacion_animales la WHERE la.id_liquidacion = l.id) AS total_animales
         FROM liquidaciones l
         JOIN contratos_compra c  ON c.id  = l.id_contrato
         LEFT JOIN empresas e     ON e.id  = l.id_empresa_factura
         LEFT JOIN clientes cl    ON cl.id = l.id_cliente
         WHERE ' . implode(' AND ', $where) . ' ORDER BY l.fecha_venta DESC'
    );
    $stmt->execute($params);
    Response::success($stmt->fetchAll());
}

// ── POST ─────────────────────────────────────────────────────
function handlePost(): void {
    Auth::requirePermission('liquidaciones', 'crear');
    $data = jsonInput();

    $required = ['id_contrato', 'fecha_venta', 'animales'];

    foreach ($required as $field) {
        if (empty($data[$field]) && $data[$field] !== 0) {
            Response::error("El campo '{$field}' es obligatorio.");
        }
    }

    if (!is_array($data['animales']) || empty($data['animales'])) {
        Response::error('Debe incluir al menos un animal en la liquidación.');
    }

    // Determinar si todos los animales son muertes
    $soloMuertes = array_filter($data['animales'], fn($a) => ($a['tipo_salida'] ?? 'venta') === 'muerte');
    $esSoloMuertes = count($soloMuertes) === count($data['animales']);

    // Validar campos de venta solo cuando hay animales vendidos
    if (!$esSoloMuertes) {
        foreach (['id_empresa_factura', 'id_cliente', 'valor_venta_unitario_kg'] as $field) {
            if (empty($data[$field]) && $data[$field] !== 0) {
                Response::error("El campo '{$field}' es obligatorio.");
            }
        }
    }

    // ── Modo de peso: 'total' (un peso para todo el lote) o 'individual' (por animal)
    $modoPeso     = $data['modo_peso'] ?? 'total'; // 'total' | 'individual'
    $pesoTotalLote = (float)($data['peso_total_kg'] ?? 0);
    $cantAnimales = count($data['animales']);

    // Validar que haya peso según el modo (solo cuando hay ventas)
    if (!$esSoloMuertes && $modoPeso === 'total') {
        if ($pesoTotalLote <= 0) {
            Response::error('Ingrese el peso total de los animales a liquidar.');
        }
    } elseif (!$esSoloMuertes) {
        // modo individual: cada animal debe traer peso_salida_kg
        foreach ($data['animales'] as $idx => $item) {
            if (($item['tipo_salida'] ?? 'venta') === 'venta' && empty($item['peso_salida_kg'])) {
                Response::error("El animal #" . ($idx + 1) . " no tiene peso de salida.");
            }
        }
        // Calcular peso total sumando los individuales (solo ventas)
        $pesoTotalLote = array_sum(array_map(
            fn($a) => ($a['tipo_salida'] ?? 'venta') === 'venta' ? (float)($a['peso_salida_kg'] ?? 0) : 0,
            $data['animales']
        ));
    }

    $valorUnitKg  = (float)($data['valor_venta_unitario_kg'] ?? 0);
    $otrosGastos  = (float)($data['otros_gastos'] ?? 0);   // total otros gastos de la venta
    $pdo = getDB();
    $pdo->beginTransaction();

    try {
        // Flete de salida por animal
        $idFlete        = !empty($data['id_flete_salida']) ? (int)$data['id_flete_salida'] : null;
        $fletePorAnimal = 0;
        if ($idFlete) {
            $stmtFl = $pdo->prepare('SELECT valor_por_animal FROM fletes_salida WHERE id = ?');
            $stmtFl->execute([$idFlete]);
            $fl = $stmtFl->fetch();
            if ($fl) $fletePorAnimal = (float)$fl['valor_por_animal'];
        }

        // Otros gastos prorrrateados entre todos los animales
        $cantTotal          = count($data['animales']);
        $otrosGastasPorAnim = $cantTotal > 0 ? round($otrosGastos / $cantTotal, 2) : 0;

        // Peso distribuido en modo total
        $soloVentas = array_filter($data['animales'], fn($a) => ($a['tipo_salida'] ?? 'venta') === 'venta');
        $cantVentas = count($soloVentas);
        $pesoPorAnimalDistribuido = ($modoPeso === 'total' && $cantVentas > 0)
            ? round($pesoTotalLote / $cantVentas, 2)
            : 0;

        // Insertar encabezado liquidación
        $stmt = $pdo->prepare(
            'INSERT INTO liquidaciones
             (id_contrato, id_empresa_factura, id_cliente, id_flete_salida,
              numero_factura, fecha_venta, peso_total_kg,
              valor_venta_unitario_kg, otros_gastos, estado, observacion, creado_por)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?)'
        );
        $stmt->execute([
            $data['id_contrato'],
            !empty($data['id_empresa_factura']) ? $data['id_empresa_factura'] : null,
            !empty($data['id_cliente'])         ? $data['id_cliente']         : null,
            $idFlete,
            $data['numero_factura'] ?? null,
            $data['fecha_venta'],
            $pesoTotalLote,
            $valorUnitKg,
            $otrosGastos,
            'confirmada',
            $data['observacion'] ?? null,
            Auth::user()['id'],
        ]);
        $idLiq = (int)$pdo->lastInsertId();

        // Procesar animales
        $cacheFecha        = [];
        $contratosAfectados = [];   // todos los contratos con animales en esta liquidación
        $stmtAnimal    = $pdo->prepare('SELECT * FROM animales WHERE id = ?');
        $stmtUpdAnimal = $pdo->prepare('UPDATE animales SET estado = ? WHERE id = ?');
        $stmtLiqAn     = $pdo->prepare(
            'INSERT INTO liquidacion_animales
             (id_liquidacion, id_animal, dias_manutencion, meses_manutencion,
              tarifa_manutencion_dia, costo_manutencion, costo_flete_salida,
              costo_compra, costo_flete_entrada, otros_gastos, costo_total,
              peso_salida_kg, peso_canal_kg, valor_venta_kg, valor_venta,
              ganancia, tipo_salida, fecha_salida)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
        );

        foreach ($data['animales'] as $item) {
            $idAnimal   = (int)$item['id_animal'];
            $tipoSalida = $item['tipo_salida'] ?? 'venta';

            $stmtAnimal->execute([$idAnimal]);
            $a = $stmtAnimal->fetch();
            if (!$a || $a['estado'] !== 'activo') {
                throw new Exception("Animal ID {$idAnimal} no disponible o ya liquidado.");
            }

            // Registrar contrato real del animal
            $idCont = (int)$a['id_contrato'];
            $contratosAfectados[$idCont] = true;

            // Cache fecha compra por contrato
            if (!isset($cacheFecha[$idCont])) {
                $sf = $pdo->prepare('SELECT fecha_compra FROM contratos_compra WHERE id = ?');
                $sf->execute([$idCont]);
                $cacheFecha[$idCont] = $sf->fetchColumn();
            }

            // ── Cálculo de costos ─────────────────────────────
            $dias    = diasEntre($cacheFecha[$idCont], $data['fecha_venta']);
            $meses   = calcularMeses($dias);
            $tarifa  = getTarifaManutencion($data['fecha_venta']);

            // Manutención EXACTA sin round intermedio
            // Fórmula: dias/(365/12) × tarifa   — round solo al acumular
            // Ejemplo: 145 días → 4.767123 × 11518 = 54907.73 (exacto)
            // 3 animales × 54907.73 = 164723.19 → round = 164.723 ✓
            $costoManutExacto = calcularCostoManutencion($dias, $tarifa);

            $costoCompra   = (float)$a['costo_compra_animal'];
            $costoFleteEnt = (float)$a['costo_flete_animal'];
            $costoFleteSal = round($fletePorAnimal, 2);
            $otrosAnimal   = $otrosGastasPorAnim;

            // Costo total exacto (sin rounds intermedios)
            $costoTotalExacto = $costoCompra + $costoFleteEnt
                              + $costoManutExacto
                              + $costoFleteSal + $otrosAnimal;
            // Redondear solo al guardar
            $costoTotal = round($costoTotalExacto, 2);

            // Peso de salida
            if ($tipoSalida === 'muerte') {
                $pesoSalida = 0;
                $valorVenta = 0;
            } else {
                $pesoSalida = isset($item['peso_salida_kg']) && (float)$item['peso_salida_kg'] > 0
                    ? round((float)$item['peso_salida_kg'], 2)
                    : $pesoPorAnimalDistribuido;
                $valorVenta = round($pesoSalida * $valorUnitKg, 2);
            }

            $ganancia = $valorVenta - $costoTotal;

            $stmtLiqAn->execute([
                $idLiq, $idAnimal,
                $dias,
                round($meses, 2),
                $tarifa,
                round($costoManutExacto, 2),
                $costoFleteSal,
                $costoCompra,
                $costoFleteEnt,
                $otrosAnimal,
                $costoTotal,
                $pesoSalida,
                round((float)($item['peso_canal_kg'] ?? 0), 2), // estadístico
                $valorUnitKg,
                $valorVenta,
                $ganancia,
                $tipoSalida,
                $data['fecha_venta'],
            ]);

            $nuevoEstado = ($tipoSalida === 'muerte') ? 'muerto' : 'vendido';
            $stmtUpdAnimal->execute([$nuevoEstado, $idAnimal]);
        }

        // Verificar cierre para TODOS los contratos cuyos animales fueron liquidados
        $stmtCheck    = $pdo->prepare("SELECT COUNT(*) FROM animales WHERE id_contrato = ? AND estado = 'activo'");
        $stmtCerrar   = $pdo->prepare("UPDATE contratos_compra SET estado='cerrado' WHERE id=?");
        $contratosCerrados = [];

        foreach (array_keys($contratosAfectados) as $contId) {
            $stmtCheck->execute([$contId]);
            if ((int)$stmtCheck->fetchColumn() === 0) {
                generarCierre($contId, $pdo, $data['fecha_venta']);
                $stmtCerrar->execute([$contId]);
                $contratosCerrados[] = $contId;
            }
        }

        $pdo->commit();
        Logger::log('crear', 'liquidaciones', $idLiq);
        Response::success([
            'id'                => $idLiq,
            'peso_total_kg'     => $pesoTotalLote,
            'contrato_cerrado'  => in_array((int)$data['id_contrato'], $contratosCerrados),
            'contratos_cerrados'=> $contratosCerrados,
        ], 'Liquidación registrada correctamente.');

    } catch (Throwable $e) {
        $pdo->rollBack();
        error_log($e->getMessage());
        Response::serverError('No se pudo registrar la liquidación: ' . $e->getMessage());
    }
}

// ── Cierre automático del contrato ───────────────────────────
function generarCierre(int $idContrato, PDO $pdo, string $fechaCierre): void {
    // Totales desde liquidacion_animales usando el contrato REAL del animal,
    // no el id_contrato cabecera de la liquidación (que puede ser otro contrato).
    $stmt = $pdo->prepare(
        'SELECT
            COUNT(*)                  AS total,
            SUM(CASE WHEN tipo_salida="venta"  THEN 1 ELSE 0 END) AS vendidos,
            SUM(CASE WHEN tipo_salida="muerte" THEN 1 ELSE 0 END) AS muertos,
            SUM(costo_compra)         AS tot_compra,
            SUM(costo_flete_entrada)  AS tot_flete_ent,
            SUM(costo_manutencion)    AS tot_manutencion,
            SUM(costo_flete_salida)   AS tot_flete_sal,
            SUM(costo_total)          AS tot_costos,
            SUM(valor_venta)          AS tot_ventas,
            SUM(ganancia)             AS tot_ganancia
         FROM liquidacion_animales la
         JOIN animales a ON a.id = la.id_animal
         WHERE a.id_contrato = ?'
    );
    $stmt->execute([$idContrato]);
    $totales = $stmt->fetch();

    // Cantidad de socios
    $stmtSocios = $pdo->prepare(
        'SELECT COUNT(*) FROM contrato_socios WHERE id_contrato = ?'
    );
    $stmtSocios->execute([$idContrato]);
    $nSocios = max(1, (int)$stmtSocios->fetchColumn());

    $gananciaPorSocio = round((float)$totales['tot_ganancia'] / $nSocios, 2);

    // Insertar cierre
    $stmtCierre = $pdo->prepare(
        'INSERT INTO cierre_contrato
         (id_contrato, fecha_cierre, total_animales, animales_vendidos, animales_muertos,
          costo_total_compra, costo_total_flete_entrada, costo_total_manutencion,
          costo_total_flete_salida, costo_total, ingreso_total_ventas,
          ganancia_total, ganancia_por_socio)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)'
    );
    $stmtCierre->execute([
        $idContrato, $fechaCierre,
        $totales['total'], $totales['vendidos'], $totales['muertos'],
        $totales['tot_compra'], $totales['tot_flete_ent'],
        $totales['tot_manutencion'], $totales['tot_flete_sal'],
        $totales['tot_costos'], $totales['tot_ventas'],
        $totales['tot_ganancia'], $gananciaPorSocio,
    ]);
    $idCierre = (int)$pdo->lastInsertId();

    // Detalle por socio
    $stmtSociosList = $pdo->prepare(
        'SELECT id_socio, porcentaje FROM contrato_socios WHERE id_contrato = ?'
    );
    $stmtSociosList->execute([$idContrato]);
    $stmtDetSocio = $pdo->prepare(
        'INSERT INTO cierre_socios_detalle (id_cierre, id_socio, porcentaje, ganancia) VALUES (?,?,?,?)'
    );
    foreach ($stmtSociosList->fetchAll() as $socio) {
        $ganSocio = round((float)$totales['tot_ganancia'] * ((float)$socio['porcentaje'] / 100), 2);
        $stmtDetSocio->execute([$idCierre, $socio['id_socio'], $socio['porcentaje'], $ganSocio]);
    }
}
