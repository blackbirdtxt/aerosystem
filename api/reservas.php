<?php
// ============================================================
// reservas.php — AeroSystem Pro
// Gestión de reservas de pasajeros
// ============================================================

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/helpers.php';

if (session_status() === PHP_SESSION_NONE) session_start();

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { ok([]); exit; }

$action = $_GET['action'] ?? '';

switch ($action) {

    // ----------------------------------------------------------
    // POST ?action=crear  (requiere sesión pasajero)
    // ----------------------------------------------------------
    case 'crear':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') err('Método no permitido', 405);
        $idpasajeroSesion = requireAuth();

        $body     = getBody();
        $idvuelo  = (int)($body['idvuelo']   ?? 0);
        $idpromo  = !empty($body['idpromo']) ? (int)$body['idpromo'] : null;
        $pasajeros = $body['pasajeros'] ?? [];
        $equipajes = $body['equipaje']  ?? [];

        if (!$idvuelo)         err('idvuelo es obligatorio.');
        if (empty($pasajeros)) err('Se requiere al menos un pasajero.');

        $db = getDB();

        // Verificar vuelo activo
        $st = $db->prepare(
            "SELECT v.idvuelo, v.estado, v.tipo, v.fecha_salida,
                    v.idtarifa_eco, v.idtarifa_eje, v.idtarifa_pri
             FROM vuelo v WHERE v.idvuelo = ?"
        );
        $st->execute([$idvuelo]);
        $vuelo = $st->fetch();
        if (!$vuelo) err('Vuelo no encontrado.', 404);
        if (in_array($vuelo['estado'], ['Cancelado','Finalizado'])) {
            err('No se puede reservar en un vuelo cancelado o finalizado.');
        }

        // Verificar asientos disponibles
        $idsAsientos = array_column($pasajeros, 'idasiento_vuelo');
        if (count($idsAsientos) !== count(array_unique($idsAsientos))) {
            err('Hay asientos duplicados en la solicitud.');
        }

        foreach ($idsAsientos as $ida) {
            $st = $db->prepare(
                "SELECT estado FROM asiento_vuelo WHERE idasiento_vuelo = ? AND idvuelo = ?"
            );
            $st->execute([$ida, $idvuelo]);
            $asiento = $st->fetch();
            if (!$asiento) err("Asiento #$ida no pertenece a este vuelo.");
            if ($asiento['estado'] !== 'Disponible') err("El asiento #$ida ya no está disponible.");
        }

        // Calcular costo_base (suma de tarifas de cada pasajero)
        $costo_base = 0.0;
        foreach ($pasajeros as $p) {
            $idtarifa = (int)($p['idtarifa'] ?? 0);
            if (!$idtarifa) err('Cada pasajero debe tener idtarifa.');
            $st = $db->prepare("SELECT precio FROM tarifa WHERE idtarifa = ?");
            $st->execute([$idtarifa]);
            $t = $st->fetch();
            if (!$t) err("Tarifa #$idtarifa no encontrada.");
            $costo_base += (float)$t['precio'];
        }

        // Calcular costo_equipaje (solo precio_extra de los tipos que cuestan extra)
        $costo_equipaje = 0.0;
        foreach ($equipajes as $eq) {
            $idtipo = (int)($eq['idtipo'] ?? 0);
            if (!$idtipo) continue;
            $st = $db->prepare("SELECT precio_extra FROM tipo_equipaje WHERE idtipo = ?");
            $st->execute([$idtipo]);
            $te = $st->fetch();
            if ($te) $costo_equipaje += (float)$te['precio_extra'];
        }

        // Aplicar promoción si hay
        $descuento = 0.0;
        if ($idpromo) {
            $st = $db->prepare(
                "SELECT idpromo, descuento, usos_max, usos_actual
                 FROM promocion
                 WHERE idpromo = ? AND activa = 1
                   AND fecha_inicio <= CURDATE() AND fecha_fin >= CURDATE()"
            );
            $st->execute([$idpromo]);
            $promo = $st->fetch();
            if (!$promo) err('Promoción inválida o vencida.');
            if ($promo['usos_actual'] >= $promo['usos_max']) err('La promoción ya alcanzó su límite de usos.');
            $descuento = round(($costo_base + $costo_equipaje) * $promo['descuento'] / 100, 2);
        }

        // Calcular impuesto y total (18% sobre subtotal)
        $subtotal   = $costo_base + $costo_equipaje - $descuento;
        $impuesto   = round($subtotal * 0.18, 2);
        $costo_total = round($subtotal + $impuesto, 2);

        $codigo = generarCodReserva();

        $db->beginTransaction();
        try {
            // a. INSERT reserva
            $st = $db->prepare(
                "INSERT INTO reserva
                 (codigo, idvuelo, costo_base, costo_equipaje, descuento,
                  impuesto, costo_total, tipo_vuelo, idpromo)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
            );
            $st->execute([
                $codigo, $idvuelo,
                $costo_base, $costo_equipaje, $descuento,
                $impuesto, $costo_total,
                $vuelo['tipo'],
                $idpromo
            ]);
            $idreserva = (int)$db->lastInsertId();

            // b. INSERT reserva_pasajero + c. UPDATE asientos
            foreach ($pasajeros as $p) {
                $idp   = $p['idpasajero']      ?? '';
                $idav  = (int)($p['idasiento_vuelo'] ?? 0);
                $idtar = (int)($p['idtarifa']   ?? 0);
                $esTit = !empty($p['es_titular']) ? 1 : 0;

                if (!$idp || !$idav || !$idtar) err('Datos de pasajero incompletos.');

                // Verificar que el pasajero exista
                $st = $db->prepare("SELECT idpasajero FROM pasajero WHERE idpasajero = ?");
                $st->execute([$idp]);
                if (!$st->fetch()) err("Pasajero $idp no encontrado.");

                $st = $db->prepare(
                    "INSERT INTO reserva_pasajero (idreserva, idpasajero, idasiento_vuelo, idtarifa, es_titular)
                     VALUES (?, ?, ?, ?, ?)"
                );
                $st->execute([$idreserva, $idp, $idav, $idtar, $esTit]);

                // Marcar asiento como Reservado
                $st = $db->prepare(
                    "UPDATE asiento_vuelo SET estado = 'Reservado' WHERE idasiento_vuelo = ?"
                );
                $st->execute([$idav]);
            }

            // d. INSERT equipaje
            $etiquetasGeneradas = [];
            foreach ($equipajes as $eq) {
                $idp     = $eq['idpasajero'] ?? '';
                $idtipo  = (int)($eq['idtipo']       ?? 0);
                $color   = trim($eq['color']      ?? '');
                $desc    = trim($eq['descripcion'] ?? '');
                if (!$idp || !$idtipo) continue;

                // Obtener apellido para etiqueta
                $st = $db->prepare("SELECT apaterno FROM pasajero WHERE idpasajero = ?");
                $st->execute([$idp]);
                $pas = $st->fetch();
                $etiqueta = generarEtiqueta($pas ? $pas['apaterno'] : 'PAS');

                // Precio extra del tipo
                $st = $db->prepare("SELECT precio_extra FROM tipo_equipaje WHERE idtipo = ?");
                $st->execute([$idtipo]);
                $te = $st->fetch();
                $precio_extra = $te ? (float)$te['precio_extra'] : 0.0;

                $st = $db->prepare(
                    "INSERT INTO equipaje
                     (idreserva, idpasajero, idtipo, color, descripcion, etiqueta_codigo, costo_extra)
                     VALUES (?, ?, ?, ?, ?, ?, ?)"
                );
                $st->execute([
                    $idreserva, $idp, $idtipo,
                    $color ?: null, $desc ?: null,
                    $etiqueta, $precio_extra
                ]);
                $etiquetasGeneradas[] = $etiqueta;
            }

            // e. Actualizar usos de promoción
            if ($idpromo) {
                $st = $db->prepare(
                    "UPDATE promocion SET usos_actual = usos_actual + 1 WHERE idpromo = ?"
                );
                $st->execute([$idpromo]);
            }

            $db->commit();
            ok([
                'idreserva'   => $idreserva,
                'codigo'      => $codigo,
                'costo_total' => $costo_total,
                'etiquetas'   => $etiquetasGeneradas,
            ]);

        } catch (Exception $e) {
            $db->rollBack();
            err('Error al crear la reserva: ' . $e->getMessage(), 500);
        }
        break;

    // ----------------------------------------------------------
    // GET ?action=mis_reservas  (requiere sesión pasajero)
    // ----------------------------------------------------------
    case 'mis_reservas':
        $idpasajero = requireAuth();
        $db = getDB();
        $st = $db->prepare(
            "SELECT * FROM vw_reservas_completas
             WHERE idpasajero = ?
             ORDER BY fecha DESC"
        );
        $st->execute([$idpasajero]);
        ok($st->fetchAll());
        break;

    // ----------------------------------------------------------
    // GET ?action=detalle&id=X  (requiere sesión pasajero)
    // ----------------------------------------------------------
    case 'detalle':
        $idpasajero = requireAuth();
        $id = (int)($_GET['id'] ?? 0);
        if (!$id) err('ID de reserva requerido.');

        $db = getDB();

        // Verificar que el pasajero sea parte de la reserva
        $st = $db->prepare(
            "SELECT 1 FROM reserva_pasajero WHERE idreserva = ? AND idpasajero = ?"
        );
        $st->execute([$id, $idpasajero]);
        if (!$st->fetch()) err('Reserva no encontrada o sin permisos.', 403);

        // Datos base de la reserva + vuelo
        $st = $db->prepare(
            "SELECT r.*, v.numero_vuelo, v.fecha_salida, v.fecha_llegada,
                    v.origen, v.destino, v.estado AS estado_vuelo, v.puerta,
                    ao.ciudad AS ciudad_origen, ad.ciudad AS ciudad_destino,
                    al.nombre AS aerolinea
             FROM reserva r
             JOIN vuelo      v  ON r.idvuelo   = v.idvuelo
             JOIN aeropuerto ao ON v.origen    = ao.codigo_iata
             JOIN aeropuerto ad ON v.destino   = ad.codigo_iata
             JOIN avion      av ON v.idavion   = av.idavion
             JOIN aerolinea  al ON av.idaerolinea = al.idaerolinea
             WHERE r.idreserva = ?"
        );
        $st->execute([$id]);
        $reserva = $st->fetch();

        // Pasajeros de la reserva con asiento
        $st = $db->prepare(
            "SELECT p.idpasajero, p.nombre, p.apaterno, p.amaterno,
                    p.tipo_documento, p.num_documento,
                    av.fila, av.letra, av.clase, rp.es_titular,
                    t.precio, t.clase AS clase_tarifa
             FROM reserva_pasajero rp
             JOIN pasajero      p  ON rp.idpasajero      = p.idpasajero
             JOIN asiento_vuelo av ON rp.idasiento_vuelo = av.idasiento_vuelo
             JOIN tarifa        t  ON rp.idtarifa        = t.idtarifa
             WHERE rp.idreserva = ?"
        );
        $st->execute([$id]);
        $pasajerosList = $st->fetchAll();

        // Equipajes
        $st = $db->prepare(
            "SELECT e.idequipaje, e.etiqueta_codigo, e.estado, e.color,
                    e.peso_real, e.costo_extra, te.nombre AS tipo, te.peso_max_kg,
                    p.nombre, p.apaterno
             FROM equipaje e
             JOIN tipo_equipaje te ON e.idtipo     = te.idtipo
             JOIN pasajero       p ON e.idpasajero = p.idpasajero
             WHERE e.idreserva = ?"
        );
        $st->execute([$id]);
        $equipajesList = $st->fetchAll();

        // Pagos
        $st = $db->prepare(
            "SELECT idpago, fecha, monto, tipo_documento, numcomprobante, estado
             FROM pago WHERE idreserva = ?"
        );
        $st->execute([$id]);
        $pagosList = $st->fetchAll();

        // Check-in
        $st = $db->prepare(
            "SELECT c.idcheckin, c.idpasajero, c.fecha_checkin, c.puerta_embarque, c.estado,
                    p.nombre, p.apaterno
             FROM checkin c
             JOIN pasajero p ON c.idpasajero = p.idpasajero
             WHERE c.idreserva = ?"
        );
        $st->execute([$id]);
        $checkinList = $st->fetchAll();

        ok([
            'reserva'   => $reserva,
            'pasajeros' => $pasajerosList,
            'equipaje'  => $equipajesList,
            'pagos'     => $pagosList,
            'checkin'   => $checkinList,
        ]);
        break;

    // ----------------------------------------------------------
    // POST ?action=cancelar  (requiere sesión pasajero)
    // ----------------------------------------------------------
    case 'cancelar':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') err('Método no permitido', 405);
        $idpasajero = requireAuth();

        $body      = getBody();
        $idreserva = (int)($body['idreserva'] ?? 0);
        if (!$idreserva) err('idreserva es obligatorio.');

        $db = getDB();

        // Verificar que el pasajero sea titular
        $st = $db->prepare(
            "SELECT 1 FROM reserva_pasajero WHERE idreserva = ? AND idpasajero = ? AND es_titular = 1"
        );
        $st->execute([$idreserva, $idpasajero]);
        if (!$st->fetch()) err('Solo el titular puede cancelar la reserva.', 403);

        // Obtener datos de la reserva
        $st = $db->prepare(
            "SELECT r.idreserva, r.estado, r.costo_total,
                    v.fecha_salida
             FROM reserva r
             JOIN vuelo v ON r.idvuelo = v.idvuelo
             WHERE r.idreserva = ?"
        );
        $st->execute([$idreserva]);
        $reserva = $st->fetch();
        if (!$reserva) err('Reserva no encontrada.', 404);
        if (!in_array($reserva['estado'], ['Pendiente','Pagada'])) {
            err('La reserva no puede cancelarse en estado: ' . $reserva['estado'] . '.');
        }

        // Calcular horas hasta el vuelo
        $ahora       = new DateTime();
        $fechaVuelo  = new DateTime($reserva['fecha_salida']);
        $diffHoras   = ($fechaVuelo->getTimestamp() - $ahora->getTimestamp()) / 3600;

        if ($diffHoras > 24) {
            $reembolso_pct = 100;
        } elseif ($diffHoras >= 2) {
            $reembolso_pct = 50;
        } else {
            $reembolso_pct = 0;
        }

        $monto_reembolso = round($reserva['costo_total'] * $reembolso_pct / 100, 2);

        $db->beginTransaction();
        try {
            // Cancelar reserva
            $st = $db->prepare("UPDATE reserva SET estado = 'Cancelada' WHERE idreserva = ?");
            $st->execute([$idreserva]);

            // Liberar asientos
            $st = $db->prepare(
                "UPDATE asiento_vuelo av
                 JOIN reserva_pasajero rp ON av.idasiento_vuelo = rp.idasiento_vuelo
                 SET av.estado = 'Disponible'
                 WHERE rp.idreserva = ?"
            );
            $st->execute([$idreserva]);

            // Actualizar pago si existe y hay reembolso
            if ($reembolso_pct > 0) {
                $st = $db->prepare(
                    "UPDATE pago SET estado = 'Reembolsado' WHERE idreserva = ? AND estado = 'Procesado'"
                );
                $st->execute([$idreserva]);
            }

            // Notificar al pasajero
            notificar(
                $idpasajero,
                'reserva_cancelada',
                "Tu reserva ha sido cancelada. " .
                ($reembolso_pct > 0
                    ? "Se procesará un reembolso del {$reembolso_pct}% (S/ {$monto_reembolso})."
                    : "No aplica reembolso por política de cancelación tardía.")
            );

            $db->commit();
            ok([
                'message'            => 'Reserva cancelada.',
                'reembolso_porcentaje' => $reembolso_pct,
                'monto_reembolso'    => $monto_reembolso,
            ]);

        } catch (Exception $e) {
            $db->rollBack();
            err('Error al cancelar la reserva: ' . $e->getMessage(), 500);
        }
        break;

    // ----------------------------------------------------------
    default:
        err('Acción no reconocida.', 404);
}