<?php
// ============================================================
// checkin.php — AeroSystem Pro
// Proceso de check-in online para pasajeros
// ============================================================

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/helpers.php';

if (session_status() === PHP_SESSION_NONE) session_start();

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { ok([]); exit; }

$action = $_GET['action'] ?? '';

switch ($action) {

    // ----------------------------------------------------------
    // POST ?action=hacer  (requiere sesión pasajero)
    // ----------------------------------------------------------
    case 'hacer':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') err('Método no permitido', 405);
        $idpasajero = requireAuth();

        $body      = getBody();
        $idreserva = (int)($body['idreserva'] ?? 0);
        if (!$idreserva) err('idreserva es obligatorio.');

        $db = getDB();

        // Verificar que el pasajero tenga parte en la reserva (titular o pasajero adicional)
        $st = $db->prepare(
            "SELECT 1 FROM reserva_pasajero WHERE idreserva = ? AND idpasajero = ?"
        );
        $st->execute([$idreserva, $idpasajero]);
        if (!$st->fetch()) err('No perteneces a esta reserva.', 403);

        // Obtener datos de la reserva y vuelo
        $st = $db->prepare(
            "SELECT r.idreserva, r.estado, v.fecha_salida, v.puerta
             FROM reserva r
             JOIN vuelo v ON r.idvuelo = v.idvuelo
             WHERE r.idreserva = ?"
        );
        $st->execute([$idreserva]);
        $reserva = $st->fetch();

        if (!$reserva) err('Reserva no encontrada.', 404);
        if ($reserva['estado'] !== 'Pagada') {
            err('Solo se puede hacer check-in en reservas pagadas (estado actual: ' . $reserva['estado'] . ').');
        }

        // Verificar ventana de check-in: entre 24h y 1h antes del vuelo
        $ahora      = new DateTime();
        $fechaVuelo = new DateTime($reserva['fecha_salida']);
        $diffSeg    = $fechaVuelo->getTimestamp() - $ahora->getTimestamp();
        $diffHoras  = $diffSeg / 3600;

        if ($diffHoras > 24) err('El check-in solo está disponible dentro de las 24 horas previas al vuelo.');
        if ($diffHoras < 1)  err('El check-in cierra 1 hora antes de la salida del vuelo.');

        // Obtener todos los pasajeros de la reserva
        $st = $db->prepare(
            "SELECT rp.idpasajero, p.nombre, p.apaterno,
                    av.fila, av.letra, av.clase, t.clase AS clase_tarifa
             FROM reserva_pasajero rp
             JOIN pasajero      p  ON rp.idpasajero      = p.idpasajero
             JOIN asiento_vuelo av ON rp.idasiento_vuelo = av.idasiento_vuelo
             JOIN tarifa        t  ON rp.idtarifa        = t.idtarifa
             WHERE rp.idreserva = ?"
        );
        $st->execute([$idreserva]);
        $pasajerosList = $st->fetchAll();

        $puerta = $reserva['puerta'] ?? 'Por asignar';
        $pases  = [];

        $db->beginTransaction();
        try {
            foreach ($pasajerosList as $p) {
                // Verificar si ya hizo check-in
                $st = $db->prepare(
                    "SELECT idcheckin FROM checkin WHERE idreserva = ? AND idpasajero = ?"
                );
                $st->execute([$idreserva, $p['idpasajero']]);
                if ($st->fetch()) continue; // ya hecho, saltar

                $st = $db->prepare(
                    "INSERT INTO checkin (idreserva, idpasajero, puerta_embarque)
                     VALUES (?, ?, ?)"
                );
                $st->execute([$idreserva, $p['idpasajero'], $puerta]);

                $pases[] = [
                    'idpasajero'    => $p['idpasajero'],
                    'nombre'        => $p['nombre'] . ' ' . $p['apaterno'],
                    'asiento'       => $p['fila'] . $p['letra'],
                    'clase'         => $p['clase'],
                    'puerta'        => $puerta,
                    'fecha_vuelo'   => $reserva['fecha_salida'],
                ];

                // Notificar
                notificar(
                    $p['idpasajero'],
                    'check-in_disponible',
                    "Check-in completado. Tu asiento es {$p['fila']}{$p['letra']} ({$p['clase']}). Puerta: $puerta."
                );
            }

            // Actualizar estado de la reserva a Check-in
            $st = $db->prepare("UPDATE reserva SET estado = 'Check-in' WHERE idreserva = ?");
            $st->execute([$idreserva]);

            $db->commit();
            ok([
                'message' => 'Check-in completado.',
                'pases_abordaje' => $pases,
            ]);

        } catch (Exception $e) {
            $db->rollBack();
            err('Error al realizar el check-in: ' . $e->getMessage(), 500);
        }
        break;

    // ----------------------------------------------------------
    // GET ?action=estado&idreserva=X  (requiere sesión pasajero)
    // ----------------------------------------------------------
    case 'estado':
        $idpasajero = requireAuth();
        $idreserva  = (int)($_GET['idreserva'] ?? 0);
        if (!$idreserva) err('idreserva es obligatorio.');

        $db = getDB();
        $st = $db->prepare(
            "SELECT c.idcheckin, c.fecha_checkin, c.puerta_embarque, c.estado,
                    p.nombre, p.apaterno,
                    av.fila, av.letra, av.clase
             FROM checkin c
             JOIN pasajero      p  ON c.idpasajero      = p.idpasajero
             JOIN reserva_pasajero rp ON rp.idreserva   = c.idreserva
                                     AND rp.idpasajero  = c.idpasajero
             JOIN asiento_vuelo av ON rp.idasiento_vuelo = av.idasiento_vuelo
             WHERE c.idreserva = ?"
        );
        $st->execute([$idreserva]);
        ok($st->fetchAll());
        break;

    // ----------------------------------------------------------
    default:
        err('Acción no reconocida.', 404);
}