<?php
// ============================================================
// equipaje.php — AeroSystem Pro
// Gestión y tracking de equipaje
// ============================================================

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/helpers.php';

if (session_status() === PHP_SESSION_NONE) session_start();

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { ok([]); exit; }

$action = $_GET['action'] ?? '';

switch ($action) {

    // ----------------------------------------------------------
    // PÚBLICO: listar tipos de equipaje
    // GET ?action=tipos
    // ----------------------------------------------------------
    case 'tipos':
        $db = getDB();
        $st = $db->query(
            "SELECT idtipo, nombre, peso_max_kg, precio_extra,
                    descripcion, requiere_reserva_anticipada
             FROM tipo_equipaje
             ORDER BY precio_extra ASC"
        );
        ok($st->fetchAll());
        break;

    // ----------------------------------------------------------
    // PÚBLICO: tracking por código de etiqueta
    // GET ?action=tracking&codigo=AEROSYS-GARC-1234
    // ----------------------------------------------------------
    case 'tracking':
        $codigo = trim($_GET['codigo'] ?? '');
        if (!$codigo) err('Código de etiqueta requerido.');

        $db = getDB();
        $st = $db->prepare(
            "SELECT * FROM vw_equipaje_tracking WHERE etiqueta_codigo = ?"
        );
        $st->execute([$codigo]);
        $eq = $st->fetch();
        if (!$eq) err('Equipaje no encontrado.', 404);
        ok($eq);
        break;

    // ----------------------------------------------------------
    // PASAJERO: mis equipajes
    // GET ?action=mis_equipajes  (requiere sesión pasajero)
    // ----------------------------------------------------------
    case 'mis_equipajes':
        $idpasajero = requireAuth();
        $db = getDB();
        $st = $db->prepare(
            "SELECT e.idequipaje, e.etiqueta_codigo, e.estado,
                    e.color, e.peso_real, e.costo_extra,
                    te.nombre AS tipo, te.peso_max_kg,
                    r.codigo AS codigo_reserva,
                    v.numero_vuelo,
                    ao.ciudad AS ciudad_origen,
                    ad.ciudad AS ciudad_destino,
                    v.fecha_salida
             FROM equipaje e
             JOIN tipo_equipaje te ON e.idtipo     = te.idtipo
             JOIN reserva        r  ON e.idreserva  = r.idreserva
             JOIN vuelo          v  ON r.idvuelo    = v.idvuelo
             JOIN aeropuerto     ao ON v.origen     = ao.codigo_iata
             JOIN aeropuerto     ad ON v.destino    = ad.codigo_iata
             WHERE e.idpasajero = ?
             ORDER BY v.fecha_salida DESC"
        );
        $st->execute([$idpasajero]);
        ok($st->fetchAll());
        break;

    // ----------------------------------------------------------
    // OPERADOR EQUIPAJE: cambiar estado de un equipaje
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

        // Obtener datos del equipaje
        $st = $db->prepare(
            "SELECT e.idequipaje, e.estado, e.idpasajero, e.etiqueta_codigo
             FROM equipaje e WHERE e.idequipaje = ?"
        );
        $st->execute([$idequipaje]);
        $eq = $st->fetch();
        if (!$eq) err('Equipaje no encontrado.', 404);

        $db->beginTransaction();
        try {
            // Actualizar estado
            $st = $db->prepare("UPDATE equipaje SET estado = ? WHERE idequipaje = ?");
            $st->execute([$estado, $idequipaje]);

            // Si se entrega: notificar al pasajero
            if ($estado === 'Entregado') {
                notificar(
                    $eq['idpasajero'],
                    'equipaje_entregado',
                    "Tu equipaje ({$eq['etiqueta_codigo']}) ha sido entregado. ¡Que disfrutes tu viaje!"
                );
            }

            // Si se pierde o daña: crear incidencia
            if (in_array($estado, ['Perdido','Dañado'])) {
                $tipoInc = $estado === 'Perdido' ? 'Perdido' : 'Dañado';
                $st = $db->prepare(
                    "INSERT INTO incidencia_equipaje (idequipaje, tipo, descripcion, reportado_por)
                     VALUES (?, ?, ?, ?)"
                );
                $st->execute([
                    $idequipaje,
                    $tipoInc,
                    $desc_inc ?: "Equipaje marcado como $estado por operador.",
                    $idusuario
                ]);

                notificar(
                    $eq['idpasajero'],
                    'incidencia_equipaje',
                    "Se ha reportado una incidencia con tu equipaje ({$eq['etiqueta_codigo']}): $tipoInc. " .
                    "Por favor contacta al aeropuerto."
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
    default:
        err('Acción no reconocida.', 404);
}