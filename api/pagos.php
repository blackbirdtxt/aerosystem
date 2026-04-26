<?php
// ============================================================
// pagos.php — AeroSystem Pro
// Procesamiento de pagos de reservas
// ============================================================

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/helpers.php';

if (session_status() === PHP_SESSION_NONE) session_start();

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { ok([]); exit; }

$action = $_GET['action'] ?? '';

switch ($action) {

    // ----------------------------------------------------------
    // POST ?action=procesar  (requiere sesión pasajero)
    // ----------------------------------------------------------
    case 'procesar':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') err('Método no permitido', 405);
        $idpasajero = requireAuth();

        $body          = getBody();
        $idreserva     = (int)($body['idreserva']     ?? 0);
        $tipo_documento = trim($body['tipo_documento'] ?? '');
        $numcomprobante = trim($body['numcomprobante'] ?? '');

        if (!$idreserva)     err('idreserva es obligatorio.');
        if (!$tipo_documento) err('tipo_documento es obligatorio.');

        $tiposValidos = ['Boleta','Factura','Recibo'];
        if (!in_array($tipo_documento, $tiposValidos)) err('tipo_documento inválido.');

        $db = getDB();

        // Verificar que la reserva esté Pendiente y que el pasajero sea titular
        $st = $db->prepare(
            "SELECT r.idreserva, r.estado, r.costo_total, rp.idpasajero
             FROM reserva r
             JOIN reserva_pasajero rp ON r.idreserva = rp.idreserva AND rp.es_titular = 1
             WHERE r.idreserva = ?"
        );
        $st->execute([$idreserva]);
        $reserva = $st->fetch();

        if (!$reserva) err('Reserva no encontrada.', 404);
        if ($reserva['idpasajero'] !== $idpasajero) err('Solo el titular puede realizar el pago.', 403);
        if ($reserva['estado'] !== 'Pendiente') {
            err('La reserva no está en estado Pendiente (estado actual: ' . $reserva['estado'] . ').');
        }

        $db->beginTransaction();
        try {
            // INSERT pago
            $st = $db->prepare(
                "INSERT INTO pago (idreserva, idpasajero, monto, tipo_documento, numcomprobante)
                 VALUES (?, ?, ?, ?, ?)"
            );
            $st->execute([
                $idreserva,
                $idpasajero,
                $reserva['costo_total'],
                $tipo_documento,
                $numcomprobante ?: null,
            ]);
            $idpago = (int)$db->lastInsertId();

            // UPDATE reserva -> Pagada
            $st = $db->prepare("UPDATE reserva SET estado = 'Pagada' WHERE idreserva = ?");
            $st->execute([$idreserva]);

            // Notificar al titular
            notificar(
                $idpasajero,
                'reserva_confirmada',
                "¡Pago confirmado! Tu reserva ha sido registrada. Monto: S/ {$reserva['costo_total']}."
            );

            $db->commit();
            ok([
                'idpago'         => $idpago,
                'numcomprobante' => $numcomprobante,
                'monto'          => $reserva['costo_total'],
                'tipo_documento' => $tipo_documento,
            ]);

        } catch (Exception $e) {
            $db->rollBack();
            err('Error al procesar el pago: ' . $e->getMessage(), 500);
        }
        break;

    // ----------------------------------------------------------
    // GET ?action=historial  (requiere sesión pasajero)
    // ----------------------------------------------------------
    case 'historial':
        $idpasajero = requireAuth();
        $db = getDB();
        $st = $db->prepare(
            "SELECT p.idpago, p.fecha, p.monto, p.tipo_documento,
                    p.numcomprobante, p.estado,
                    r.codigo AS codigo_reserva,
                    v.numero_vuelo,
                    ao.ciudad AS ciudad_origen,
                    ad.ciudad AS ciudad_destino
             FROM pago p
             JOIN reserva    r  ON p.idreserva = r.idreserva
             JOIN vuelo      v  ON r.idvuelo   = v.idvuelo
             JOIN aeropuerto ao ON v.origen    = ao.codigo_iata
             JOIN aeropuerto ad ON v.destino   = ad.codigo_iata
             WHERE p.idpasajero = ?
             ORDER BY p.fecha DESC"
        );
        $st->execute([$idpasajero]);
        ok($st->fetchAll());
        break;

    // ----------------------------------------------------------
    default:
        err('Acción no reconocida.', 404);
}