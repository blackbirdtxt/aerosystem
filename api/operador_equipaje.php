<?php
// ============================================================
// operador_equipaje.php — AeroSystem Pro
// Panel y herramientas del Operador de Equipaje
// ============================================================

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/helpers.php';

if (session_status() === PHP_SESSION_NONE) session_start();

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { ok([]); exit; }

$action = $_GET['action'] ?? '';

switch ($action) {

    // ----------------------------------------------------------
    // GET ?action=dashboard  (requiere operador_equipaje)
    // ----------------------------------------------------------
    case 'dashboard':
        requireOp('operador_equipaje');
        $db = getDB();

        // Conteo de equipajes por estado (hoy, basado en vuelos del día)
        $st = $db->query(
            "SELECT e.estado, COUNT(*) AS total
             FROM equipaje e
             JOIN reserva    r  ON e.idreserva = r.idreserva
             JOIN vuelo      v  ON r.idvuelo   = v.idvuelo
             WHERE DATE(v.fecha_salida) = CURDATE()
             GROUP BY e.estado"
        );
        $por_estado = $st->fetchAll();

        // Incidencias abiertas
        $st = $db->query(
            "SELECT i.idincidencia, i.tipo, i.descripcion, i.fecha,
                    e.etiqueta_codigo, e.estado AS estado_equipaje,
                    p.nombre, p.apaterno,
                    v.numero_vuelo
             FROM incidencia_equipaje i
             JOIN equipaje e  ON i.idequipaje  = e.idequipaje
             JOIN pasajero p  ON e.idpasajero  = p.idpasajero
             JOIN reserva  r  ON e.idreserva   = r.idreserva
             JOIN vuelo    v  ON r.idvuelo     = v.idvuelo
             WHERE i.resuelto = 0
             ORDER BY i.fecha DESC"
        );
        $incidencias = $st->fetchAll();

        // Equipajes en cinta sin recoger (estado = En cinta)
        $st = $db->query(
            "SELECT e.idequipaje, e.etiqueta_codigo, e.color,
                    te.nombre AS tipo,
                    p.nombre, p.apaterno, p.telefono,
                    v.numero_vuelo,
                    ad.ciudad AS ciudad_destino
             FROM equipaje e
             JOIN tipo_equipaje te ON e.idtipo     = te.idtipo
             JOIN pasajero       p  ON e.idpasajero = p.idpasajero
             JOIN reserva        r  ON e.idreserva  = r.idreserva
             JOIN vuelo          v  ON r.idvuelo    = v.idvuelo
             JOIN aeropuerto     ad ON v.destino    = ad.codigo_iata
             WHERE e.estado = 'En cinta'
             ORDER BY v.fecha_llegada ASC"
        );
        $en_cinta = $st->fetchAll();

        ok([
            'equipajes_por_estado'  => $por_estado,
            'incidencias_abiertas'  => $incidencias,
            'en_cinta_sin_recoger'  => $en_cinta,
            'total_incidencias'     => count($incidencias),
            'total_en_cinta'        => count($en_cinta),
        ]);
        break;

    // ----------------------------------------------------------
    // GET ?action=equipajes_vuelo&idvuelo=X  (requiere operador_equipaje)
    // ----------------------------------------------------------
    case 'equipajes_vuelo':
        requireOp('operador_equipaje');
        $idvuelo = (int)($_GET['idvuelo'] ?? 0);
        if (!$idvuelo) err('idvuelo requerido.');

        $db = getDB();

        // Verificar que el vuelo existe
        $st = $db->prepare("SELECT numero_vuelo, estado FROM vuelo WHERE idvuelo = ?");
        $st->execute([$idvuelo]);
        $vuelo = $st->fetch();
        if (!$vuelo) err('Vuelo no encontrado.', 404);

        $st = $db->prepare(
            "SELECT e.idequipaje, e.etiqueta_codigo, e.estado, e.color,
                    e.peso_real, e.costo_extra, e.descripcion,
                    te.nombre AS tipo, te.peso_max_kg,
                    p.idpasajero, p.nombre, p.apaterno,
                    r.codigo AS codigo_reserva
             FROM equipaje e
             JOIN tipo_equipaje te ON e.idtipo     = te.idtipo
             JOIN pasajero       p  ON e.idpasajero = p.idpasajero
             JOIN reserva        r  ON e.idreserva  = r.idreserva
             WHERE r.idvuelo = ?
             ORDER BY e.estado, p.apaterno"
        );
        $st->execute([$idvuelo]);
        $equipajes = $st->fetchAll();

        // Resumen por estado
        $resumen = [];
        foreach ($equipajes as $eq) {
            $est = $eq['estado'];
            $resumen[$est] = ($resumen[$est] ?? 0) + 1;
        }

        ok([
            'vuelo'    => $vuelo,
            'equipajes' => $equipajes,
            'resumen'  => $resumen,
            'total'    => count($equipajes),
        ]);
        break;

    // ----------------------------------------------------------
    // GET ?action=buscar&codigo=AEROSYS-XXXX-9999  (requiere operador_equipaje)
    // ----------------------------------------------------------
    case 'buscar':
        requireOp('operador_equipaje');
        $codigo = trim($_GET['codigo'] ?? '');
        if (!$codigo) err('Código de etiqueta requerido.');

        $db = getDB();
        $st = $db->prepare(
            "SELECT e.idequipaje, e.etiqueta_codigo, e.estado, e.color,
                    e.peso_real, e.costo_extra, e.descripcion,
                    te.nombre AS tipo, te.peso_max_kg,
                    p.idpasajero, p.nombre, p.apaterno, p.telefono,
                    p.tipo_documento, p.num_documento,
                    v.numero_vuelo, v.idvuelo,
                    v.fecha_salida, v.estado AS estado_vuelo,
                    ao.ciudad AS ciudad_origen,
                    ad.ciudad AS ciudad_destino,
                    r.codigo AS codigo_reserva
             FROM equipaje e
             JOIN tipo_equipaje te ON e.idtipo     = te.idtipo
             JOIN pasajero       p  ON e.idpasajero = p.idpasajero
             JOIN reserva        r  ON e.idreserva  = r.idreserva
             JOIN vuelo          v  ON r.idvuelo    = v.idvuelo
             JOIN aeropuerto     ao ON v.origen     = ao.codigo_iata
             JOIN aeropuerto     ad ON v.destino    = ad.codigo_iata
             WHERE e.etiqueta_codigo = ?"
        );
        $st->execute([$codigo]);
        $eq = $st->fetch();
        if (!$eq) err('Equipaje no encontrado.', 404);

        // Incidencias del equipaje
        $st = $db->prepare(
            "SELECT i.idincidencia, i.tipo, i.descripcion, i.fecha, i.resuelto,
                    u.nombre AS reportado_por
             FROM incidencia_equipaje i
             JOIN usuario_operador u ON i.reportado_por = u.idusuario
             WHERE i.idequipaje = ?
             ORDER BY i.fecha DESC"
        );
        $st->execute([$eq['idequipaje']]);
        $incidencias = $st->fetchAll();

        ok([
            'equipaje'   => $eq,
            'incidencias' => $incidencias,
        ]);
        break;

    // ----------------------------------------------------------
    // POST ?action=cambiar_estado  (requiere operador_equipaje)
    // ----------------------------------------------------------
    case 'cambiar_estado':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') err('Método no permitido', 405);
        $idusuario = requireOp('operador_equipaje');

        $body       = getBody();
        $idequipaje = (int)($body['idequipaje'] ?? 0);
        $estado     = trim($body['estado']      ?? '');
        $desc_inc   = trim($body['descripcion_incidencia'] ?? '');

        $estadosValidos = [
            'Registrado','Check-in','En seguridad','En bodega',
            'En vuelo','En cinta','Entregado','Perdido','Dañado'
        ];

        if (!$idequipaje) err('idequipaje es obligatorio.');
        if (!in_array($estado, $estadosValidos)) err('Estado inválido.');

        $db = getDB();
        $st = $db->prepare(
            "SELECT e.idequipaje, e.estado, e.idpasajero, e.etiqueta_codigo
             FROM equipaje e WHERE e.idequipaje = ?"
        );
        $st->execute([$idequipaje]);
        $eq = $st->fetch();
        if (!$eq) err('Equipaje no encontrado.', 404);

        $db->beginTransaction();
        try {
            $st = $db->prepare("UPDATE equipaje SET estado = ? WHERE idequipaje = ?");
            $st->execute([$estado, $idequipaje]);

            if ($estado === 'Entregado') {
                notificar(
                    $eq['idpasajero'],
                    'equipaje_entregado',
                    "Tu equipaje ({$eq['etiqueta_codigo']}) ha sido entregado en la cinta. ¡Bienvenido!"
                );
            }

            if (in_array($estado, ['Perdido','Dañado'])) {
                $tipoInc = $estado === 'Perdido' ? 'Perdido' : 'Dañado';
                $st = $db->prepare(
                    "INSERT INTO incidencia_equipaje (idequipaje, tipo, descripcion, reportado_por)
                     VALUES (?, ?, ?, ?)"
                );
                $st->execute([
                    $idequipaje,
                    $tipoInc,
                    $desc_inc ?: "Equipaje marcado como $tipoInc por operador.",
                    $idusuario
                ]);

                notificar(
                    $eq['idpasajero'],
                    'incidencia_equipaje',
                    "Incidencia registrada con tu equipaje ({$eq['etiqueta_codigo']}): $tipoInc. " .
                    "Contacta al mostrador de equipaje."
                );
            }

            $db->commit();
            ok(['message' => 'Estado actualizado.', 'nuevo_estado' => $estado]);

        } catch (Exception $e) {
            $db->rollBack();
            err('Error al actualizar equipaje: ' . $e->getMessage(), 500);
        }
        break;

    // ----------------------------------------------------------
    // POST ?action=resolver_incidencia  (requiere operador_equipaje)
    // ----------------------------------------------------------
    case 'resolver_incidencia':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') err('Método no permitido', 405);
        requireOp('operador_equipaje');

        $body        = getBody();
        $idincidencia = (int)($body['idincidencia'] ?? 0);
        if (!$idincidencia) err('idincidencia es obligatorio.');

        $db = getDB();
        $st = $db->prepare("UPDATE incidencia_equipaje SET resuelto = 1 WHERE idincidencia = ?");
        $st->execute([$idincidencia]);
        ok(['message' => 'Incidencia marcada como resuelta.']);
        break;

    // ----------------------------------------------------------
    // GET ?action=vuelos_hoy  (requiere operador_equipaje)
    // Para el select de vuelos del panel
    // ----------------------------------------------------------
    case 'vuelos_hoy':
        requireOp('operador_equipaje');
        $db = getDB();
        $st = $db->query(
            "SELECT v.idvuelo, v.numero_vuelo, v.estado,
                    ao.ciudad AS ciudad_origen,
                    ad.ciudad AS ciudad_destino,
                    DATE_FORMAT(v.fecha_salida, '%H:%i') AS hora_salida,
                    al.nombre AS aerolinea
             FROM vuelo v
             JOIN aeropuerto ao ON v.origen     = ao.codigo_iata
             JOIN aeropuerto ad ON v.destino    = ad.codigo_iata
             JOIN avion      av ON v.idavion    = av.idavion
             JOIN aerolinea  al ON av.idaerolinea = al.idaerolinea
             WHERE DATE(v.fecha_salida) = CURDATE()
               AND v.estado NOT IN ('Cancelado')
             ORDER BY v.fecha_salida ASC"
        );
        ok($st->fetchAll());
        break;

    // ----------------------------------------------------------
    default:
        err('Acción no reconocida.', 404);
}