<?php
// ============================================================
// vuelos.php — AeroSystem Pro
// Gestión y búsqueda de vuelos
// ============================================================

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/helpers.php';

if (session_status() === PHP_SESSION_NONE) session_start();

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { ok([]); exit; }

$action = $_GET['action'] ?? '';

switch ($action) {

    // ----------------------------------------------------------
    // PÚBLICO: buscar vuelos disponibles
    // GET ?action=buscar&origen=LIM&destino=BOG&fecha=2026-04-26&pasajeros=2
    // ----------------------------------------------------------
    case 'buscar':
        $origen    = strtoupper(trim($_GET['origen']    ?? ''));
        $destino   = strtoupper(trim($_GET['destino']   ?? ''));
        $fecha     = trim($_GET['fecha']     ?? '');
        $pasajeros = (int)($_GET['pasajeros'] ?? 1);

        if (!$origen || !$destino || !$fecha) err('origen, destino y fecha son obligatorios.');
        if ($pasajeros < 1) $pasajeros = 1;

        $db = getDB();
        $st = $db->prepare(
            "SELECT * FROM vw_vuelos_disponibles
             WHERE cod_origen  = ?
               AND cod_destino = ?
               AND DATE(fecha_salida) = ?
               AND asientos_disponibles >= ?
             ORDER BY fecha_salida ASC"
        );
        $st->execute([$origen, $destino, $fecha, $pasajeros]);
        ok($st->fetchAll());
        break;

    // ----------------------------------------------------------
    // PÚBLICO: detalle de un vuelo + mapa de asientos
    // GET ?action=detalle&id=X
    // ----------------------------------------------------------
    case 'detalle':
        $id = (int)($_GET['id'] ?? 0);
        if (!$id) err('ID de vuelo requerido.');

        $db = getDB();

        $st = $db->prepare("SELECT * FROM vw_vuelos_disponibles WHERE idvuelo = ?");
        $st->execute([$id]);
        $vuelo = $st->fetch();
        if (!$vuelo) {
            // Intentar en todos los vuelos (para operador)
            $st = $db->prepare(
                "SELECT v.*, ao.ciudad AS ciudad_origen, ad.ciudad AS ciudad_destino,
                        al.nombre AS aerolinea, av.modelo AS tipo_avion
                 FROM vuelo v
                 JOIN aeropuerto ao ON v.origen  = ao.codigo_iata
                 JOIN aeropuerto ad ON v.destino = ad.codigo_iata
                 JOIN avion      av ON v.idavion = av.idavion
                 JOIN aerolinea  al ON av.idaerolinea = al.idaerolinea
                 WHERE v.idvuelo = ?"
            );
            $st->execute([$id]);
            $vuelo = $st->fetch();
            if (!$vuelo) err('Vuelo no encontrado.', 404);
        }

        // Asientos agrupados por clase
        $st = $db->prepare(
            "SELECT idasiento_vuelo, fila, letra, clase, estado
             FROM asiento_vuelo WHERE idvuelo = ?
             ORDER BY clase, fila, letra"
        );
        $st->execute([$id]);
        $asientos = $st->fetchAll();

        $resumen = [];
        foreach ($asientos as $a) {
            $c = $a['clase'];
            if (!isset($resumen[$c])) $resumen[$c] = ['total' => 0, 'disponibles' => 0];
            $resumen[$c]['total']++;
            if ($a['estado'] === 'Disponible') $resumen[$c]['disponibles']++;
        }

        ok([
            'vuelo'   => $vuelo,
            'asientos' => $asientos,
            'resumen_asientos' => $resumen,
        ]);
        break;

    // ----------------------------------------------------------
    // PÚBLICO: asientos de un vuelo filtrados por clase
    // GET ?action=asientos&id=X&clase=Económica
    // ----------------------------------------------------------
    case 'asientos':
        $id    = (int)($_GET['id']    ?? 0);
        $clase = trim($_GET['clase'] ?? '');
        if (!$id) err('ID de vuelo requerido.');

        $db = getDB();
        if ($clase) {
            $st = $db->prepare(
                "SELECT idasiento_vuelo, fila, letra, clase, estado
                 FROM asiento_vuelo WHERE idvuelo = ? AND clase = ?
                 ORDER BY fila, letra"
            );
            $st->execute([$id, $clase]);
        } else {
            $st = $db->prepare(
                "SELECT idasiento_vuelo, fila, letra, clase, estado
                 FROM asiento_vuelo WHERE idvuelo = ?
                 ORDER BY clase, fila, letra"
            );
            $st->execute([$id]);
        }
        ok($st->fetchAll());
        break;

    // ----------------------------------------------------------
    // OPERADOR: crear vuelo
    // POST ?action=crear  (requiere operador_vuelos)
    // ----------------------------------------------------------
    case 'crear':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') err('Método no permitido', 405);
        $idusuario = requireOp('operador_vuelos');

        $body = getBody();
        $requeridos = [
            'numero_vuelo','idavion','origen','destino','tipo',
            'fecha_salida','fecha_llegada',
            'idtarifa_eco','idtarifa_eje','idtarifa_pri'
        ];
        foreach ($requeridos as $campo) {
            if (empty($body[$campo])) err("El campo '$campo' es obligatorio.");
        }

        $numero_vuelo  = strtoupper(trim($body['numero_vuelo']));
        $idavion       = trim($body['idavion']);
        $origen        = strtoupper(trim($body['origen']));
        $destino       = strtoupper(trim($body['destino']));
        $tipo          = $body['tipo'];
        $fecha_salida  = $body['fecha_salida'];
        $fecha_llegada = $body['fecha_llegada'];
        $puerta        = trim($body['puerta'] ?? '');
        $idtarifa_eco  = (int)$body['idtarifa_eco'];
        $idtarifa_eje  = (int)$body['idtarifa_eje'];
        $idtarifa_pri  = (int)$body['idtarifa_pri'];

        if ($origen === $destino) err('Origen y destino no pueden ser iguales.');

        $duracion = calcularDuracion($fecha_salida, $fecha_llegada);
        if ($duracion <= 0) err('La fecha de llegada debe ser posterior a la de salida.');

        $db = getDB();

        // Verificar que numero_vuelo no exista
        $st = $db->prepare("SELECT idvuelo FROM vuelo WHERE numero_vuelo = ?");
        $st->execute([$numero_vuelo]);
        if ($st->fetch()) err('El número de vuelo ya existe.');

        // Obtener capacidades del avión
        $st = $db->prepare(
            "SELECT capacidad_eco, capacidad_eje, capacidad_pri FROM avion WHERE idavion = ? AND activo = 1"
        );
        $st->execute([$idavion]);
        $avion = $st->fetch();
        if (!$avion) err('Avión no encontrado o inactivo.');

        $db->beginTransaction();
        try {
            // Insertar vuelo
            $st = $db->prepare(
                "INSERT INTO vuelo
                 (numero_vuelo, idavion, origen, destino, tipo,
                  fecha_salida, fecha_llegada, duracion_min, puerta,
                  idtarifa_eco, idtarifa_eje, idtarifa_pri, creado_por)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
            );
            $st->execute([
                $numero_vuelo, $idavion, $origen, $destino, $tipo,
                $fecha_salida, $fecha_llegada, $duracion,
                $puerta ?: null,
                $idtarifa_eco, $idtarifa_eje, $idtarifa_pri, $idusuario
            ]);
            $idvuelo = (int)$db->lastInsertId();

            // Generar asientos
            _generarAsientos($db, $idvuelo, $avion);

            $db->commit();
            ok(['idvuelo' => $idvuelo, 'numero_vuelo' => $numero_vuelo]);

        } catch (Exception $e) {
            $db->rollBack();
            err('Error al crear el vuelo: ' . $e->getMessage(), 500);
        }
        break;

    // ----------------------------------------------------------
    // OPERADOR: cambiar estado de un vuelo
    // POST ?action=cambiar_estado  (requiere operador_vuelos)
    // ----------------------------------------------------------
    case 'cambiar_estado':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') err('Método no permitido', 405);
        requireOp('operador_vuelos');

        $body    = getBody();
        $idvuelo = (int)($body['idvuelo'] ?? 0);
        $estado  = trim($body['estado']   ?? '');
        $motivo  = trim($body['motivo_retraso'] ?? '');
        $puerta  = trim($body['puerta']   ?? '');

        $estadosValidos = [
            'Programado','Retrasado','Abordando',
            'En vuelo','Aterrizado','Desembarcando',
            'Finalizado','Cancelado'
        ];
        if (!$idvuelo) err('ID de vuelo requerido.');
        if (!in_array($estado, $estadosValidos)) err('Estado inválido.');

        $db = getDB();

        // Verificar que el vuelo existe
        $st = $db->prepare("SELECT idvuelo, estado FROM vuelo WHERE idvuelo = ?");
        $st->execute([$idvuelo]);
        $vuelo = $st->fetch();
        if (!$vuelo) err('Vuelo no encontrado.', 404);

        $db->beginTransaction();
        try {
            // Actualizar estado
            $campos  = ["estado = ?"];
            $valores = [$estado];

            if ($estado === 'Retrasado' && $motivo) {
                $campos[]  = "motivo_retraso = ?";
                $valores[] = $motivo;
            }
            if ($puerta) {
                $campos[]  = "puerta = ?";
                $valores[] = $puerta;
            }

            $valores[] = $idvuelo;
            $st = $db->prepare(
                "UPDATE vuelo SET " . implode(', ', $campos) . " WHERE idvuelo = ?"
            );
            $st->execute($valores);

            // Si se cancela: cancelar reservas Pendientes/Pagadas
            if ($estado === 'Cancelado') {
                $st = $db->prepare(
                    "UPDATE reserva SET estado = 'Cancelada'
                     WHERE idvuelo = ? AND estado IN ('Pendiente','Pagada')"
                );
                $st->execute([$idvuelo]);

                // Liberar asientos
                $st = $db->prepare(
                    "UPDATE asiento_vuelo SET estado = 'Disponible' WHERE idvuelo = ?"
                );
                $st->execute([$idvuelo]);
            }

            // Notificar a pasajeros con reservas activas
            $tipoNotif = match($estado) {
                'Retrasado'    => 'vuelo_retrasado',
                'Cancelado'    => 'vuelo_cancelado',
                'Abordando'    => 'embarque_iniciado',
                default        => 'cambio_estado_vuelo',
            };

            $mensaje = match($estado) {
                'Retrasado'    => "Tu vuelo ha sido retrasado. " . ($motivo ? "Motivo: $motivo" : ''),
                'Cancelado'    => "Tu vuelo ha sido cancelado. Tu reserva ha sido actualizada.",
                'Abordando'    => "¡Embarque iniciado! Dirígete a la puerta " . ($puerta ?: 'asignada') . ".",
                'En vuelo'     => "Tu vuelo está en el aire. ¡Buen viaje!",
                'Aterrizado'   => "Tu vuelo ha aterrizado. Bienvenido.",
                default        => "El estado de tu vuelo cambió a: $estado.",
            };

            $st = $db->prepare(
                "SELECT DISTINCT rp.idpasajero FROM reserva_pasajero rp
                 JOIN reserva r ON rp.idreserva = r.idreserva
                 WHERE r.idvuelo = ? AND r.estado NOT IN ('Cancelada','Finalizada')"
            );
            $st->execute([$idvuelo]);
            $pasajeros = $st->fetchAll();

            foreach ($pasajeros as $p) {
                notificar($p['idpasajero'], $tipoNotif, $mensaje);
            }

            $db->commit();
            ok(['message' => 'Estado actualizado.', 'nuevo_estado' => $estado]);

        } catch (Exception $e) {
            $db->rollBack();
            err('Error al cambiar estado: ' . $e->getMessage(), 500);
        }
        break;

    // ----------------------------------------------------------
    // OPERADOR: editar datos del vuelo (solo si estado=Programado)
    // POST ?action=editar  (requiere operador_vuelos)
    // ----------------------------------------------------------
    case 'editar':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') err('Método no permitido', 405);
        requireOp('operador_vuelos');

        $body    = getBody();
        $idvuelo = (int)($body['idvuelo'] ?? 0);
        if (!$idvuelo) err('ID de vuelo requerido.');

        $db = getDB();
        $st = $db->prepare("SELECT estado FROM vuelo WHERE idvuelo = ?");
        $st->execute([$idvuelo]);
        $vuelo = $st->fetch();
        if (!$vuelo) err('Vuelo no encontrado.', 404);
        if ($vuelo['estado'] !== 'Programado') err('Solo se pueden editar vuelos en estado Programado.');

        $editables = ['puerta','fecha_salida','fecha_llegada','motivo_retraso'];
        $campos    = [];
        $valores   = [];

        foreach ($editables as $c) {
            if (isset($body[$c])) {
                $campos[]  = "$c = ?";
                $valores[] = $body[$c];
            }
        }

        // Recalcular duración si cambian fechas
        if (isset($body['fecha_salida']) && isset($body['fecha_llegada'])) {
            $dur = calcularDuracion($body['fecha_salida'], $body['fecha_llegada']);
            if ($dur > 0) {
                $campos[]  = "duracion_min = ?";
                $valores[] = $dur;
            }
        }

        if (empty($campos)) err('No hay campos para actualizar.');

        $valores[] = $idvuelo;
        $st = $db->prepare(
            "UPDATE vuelo SET " . implode(', ', $campos) . " WHERE idvuelo = ?"
        );
        $st->execute($valores);
        ok(['message' => 'Vuelo actualizado.']);
        break;

    // ----------------------------------------------------------
    default:
        err('Acción no reconocida.', 404);
}

// ============================================================
// Función auxiliar: genera asientos para un vuelo nuevo
// ============================================================
function _generarAsientos(PDO $db, int $idvuelo, array $avion): void {
    $inserts = [];

    // Primera Clase: filas 1..N, letras A-D (4 por fila)
    $filasPri = (int)ceil($avion['capacidad_pri'] / 4);
    for ($fila = 1; $fila <= $filasPri; $fila++) {
        foreach (['A','B','C','D'] as $letra) {
            $inserts[] = [$idvuelo, $fila, $letra, 'Primera Clase'];
        }
    }

    // Ejecutiva: filas siguientes, letras A-F (6 por fila)
    $filasEje = (int)ceil($avion['capacidad_eje'] / 6);
    $inicioEje = $filasPri + 1;
    for ($fila = $inicioEje; $fila < $inicioEje + $filasEje; $fila++) {
        foreach (['A','B','C','D','E','F'] as $letra) {
            $inserts[] = [$idvuelo, $fila, $letra, 'Ejecutiva'];
        }
    }

    // Económica: filas restantes, letras A-F
    $filasEco = (int)ceil($avion['capacidad_eco'] / 6);
    $inicioEco = $inicioEje + $filasEje;
    for ($fila = $inicioEco; $fila < $inicioEco + $filasEco; $fila++) {
        foreach (['A','B','C','D','E','F'] as $letra) {
            $inserts[] = [$idvuelo, $fila, $letra, 'Económica'];
        }
    }

    $st = $db->prepare(
        "INSERT INTO asiento_vuelo (idvuelo, fila, letra, clase) VALUES (?, ?, ?, ?)"
    );
    foreach ($inserts as $row) {
        $st->execute($row);
    }
}