<?php
// ============================================================
// operador_vuelos.php — AeroSystem Pro
// Panel y herramientas del Operador de Vuelos
// ============================================================

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/helpers.php';

if (session_status() === PHP_SESSION_NONE) session_start();

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { ok([]); exit; }

$action = $_GET['action'] ?? '';

switch ($action) {

    // ----------------------------------------------------------
    // GET ?action=dashboard  (requiere operador_vuelos)
    // ----------------------------------------------------------
    case 'dashboard':
        requireOp('operador_vuelos');
        $db = getDB();

        // Vuelos de hoy
        $st = $db->query(
            "SELECT
                COUNT(*) AS total_vuelos,
                SUM(estado = 'Programado')    AS programados,
                SUM(estado = 'Abordando')     AS abordando,
                SUM(estado = 'En vuelo')      AS en_vuelo,
                SUM(estado = 'Aterrizado')    AS aterrizado,
                SUM(estado = 'Desembarcando') AS desembarcando,
                SUM(estado = 'Finalizado')    AS finalizados,
                SUM(estado = 'Retrasado')     AS retrasados,
                SUM(estado = 'Cancelado')     AS cancelados
             FROM vuelo
             WHERE DATE(fecha_salida) = CURDATE()"
        );
        $metricas = $st->fetch();

        // Pasajeros embarcados hoy (check-in completados hoy)
        $st = $db->query(
            "SELECT COUNT(*) AS pasajeros_hoy
             FROM checkin c
             JOIN reserva r ON c.idreserva = r.idreserva
             JOIN vuelo   v ON r.idvuelo   = v.idvuelo
             WHERE DATE(c.fecha_checkin) = CURDATE()
               AND c.estado = 'Completado'"
        );
        $pasajeros = $st->fetch();

        // Vuelos retrasados con motivo
        $st = $db->query(
            "SELECT v.idvuelo, v.numero_vuelo, v.motivo_retraso,
                    v.fecha_salida, ao.ciudad AS origen, ad.ciudad AS destino
             FROM vuelo v
             JOIN aeropuerto ao ON v.origen  = ao.codigo_iata
             JOIN aeropuerto ad ON v.destino = ad.codigo_iata
             WHERE v.estado = 'Retrasado'
               AND DATE(v.fecha_salida) = CURDATE()"
        );
        $retrasados = $st->fetchAll();

        ok([
            'metricas'         => $metricas,
            'pasajeros_hoy'    => (int)$pasajeros['pasajeros_hoy'],
            'vuelos_retrasados' => $retrasados,
        ]);
        break;

    // ----------------------------------------------------------
    // GET ?action=manifiesto&idvuelo=X  (requiere operador_vuelos)
    // ----------------------------------------------------------
    case 'manifiesto':
        requireOp('operador_vuelos');
        $idvuelo = (int)($_GET['idvuelo'] ?? 0);
        if (!$idvuelo) err('idvuelo requerido.');

        $db = getDB();
        $st = $db->prepare(
            "SELECT * FROM vw_manifiesto_vuelo WHERE idvuelo = ?"
        );
        $st->execute([$idvuelo]);
        $manifiesto = $st->fetchAll();

        // Datos del vuelo
        $st = $db->prepare(
            "SELECT v.numero_vuelo, v.estado, v.fecha_salida, v.fecha_llegada, v.puerta,
                    ao.ciudad AS ciudad_origen, ao.codigo_iata AS cod_origen,
                    ad.ciudad AS ciudad_destino, ad.codigo_iata AS cod_destino,
                    al.nombre AS aerolinea
             FROM vuelo v
             JOIN aeropuerto ao ON v.origen     = ao.codigo_iata
             JOIN aeropuerto ad ON v.destino    = ad.codigo_iata
             JOIN avion      av ON v.idavion    = av.idavion
             JOIN aerolinea  al ON av.idaerolinea = al.idaerolinea
             WHERE v.idvuelo = ?"
        );
        $st->execute([$idvuelo]);
        $vuelo = $st->fetch();

        // Resumen
        $total       = count($manifiesto);
        $conCheckin  = count(array_filter($manifiesto, fn($r) => !empty($r['fecha_checkin'])));

        ok([
            'vuelo'      => $vuelo,
            'manifiesto' => $manifiesto,
            'resumen'    => [
                'total_pasajeros'    => $total,
                'con_checkin'        => $conCheckin,
                'sin_checkin'        => $total - $conCheckin,
            ]
        ]);
        break;

    // ----------------------------------------------------------
    // GET ?action=lista_vuelos  (requiere operador_vuelos)
    // Filtros opcionales: ?fecha=YYYY-MM-DD  ?estado=X  ?tipo=X
    // ----------------------------------------------------------
    case 'lista_vuelos':
        requireOp('operador_vuelos');

        $fecha  = trim($_GET['fecha']  ?? '');
        $estado = trim($_GET['estado'] ?? '');
        $tipo   = trim($_GET['tipo']   ?? '');

        $where  = [];
        $params = [];

        if ($fecha) {
            $where[]  = "DATE(v.fecha_salida) = ?";
            $params[] = $fecha;
        }
        if ($estado) {
            $where[]  = "v.estado = ?";
            $params[] = $estado;
        }
        if ($tipo) {
            $where[]  = "v.tipo = ?";
            $params[] = $tipo;
        }

        $sql = "SELECT v.idvuelo, v.numero_vuelo, v.tipo, v.estado, v.puerta,
                       v.fecha_salida, v.fecha_llegada, v.duracion_min,
                       v.motivo_retraso,
                       ao.ciudad AS ciudad_origen, ao.codigo_iata AS cod_origen,
                       ad.ciudad AS ciudad_destino, ad.codigo_iata AS cod_destino,
                       al.nombre AS aerolinea, av.modelo AS avion,
                       (SELECT COUNT(*) FROM reserva_pasajero rp
                        JOIN reserva r ON rp.idreserva = r.idreserva
                        WHERE r.idvuelo = v.idvuelo
                          AND r.estado NOT IN ('Cancelada')) AS total_pasajeros
                FROM vuelo v
                JOIN aeropuerto ao ON v.origen     = ao.codigo_iata
                JOIN aeropuerto ad ON v.destino    = ad.codigo_iata
                JOIN avion      av ON v.idavion    = av.idavion
                JOIN aerolinea  al ON av.idaerolinea = al.idaerolinea";

        if ($where) {
            $sql .= " WHERE " . implode(' AND ', $where);
        }
        $sql .= " ORDER BY v.fecha_salida ASC";

        $db = getDB();
        $st = $db->prepare($sql);
        $st->execute($params);
        ok($st->fetchAll());
        break;

    // ----------------------------------------------------------
    // POST ?action=asignar_puerta  (requiere operador_vuelos)
    // ----------------------------------------------------------
    case 'asignar_puerta':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') err('Método no permitido', 405);
        requireOp('operador_vuelos');

        $body    = getBody();
        $idvuelo = (int)($body['idvuelo'] ?? 0);
        $puerta  = trim($body['puerta']   ?? '');

        if (!$idvuelo) err('idvuelo es obligatorio.');
        if (!$puerta)  err('puerta es obligatoria.');

        $db = getDB();
        $st = $db->prepare("UPDATE vuelo SET puerta = ? WHERE idvuelo = ?");
        $st->execute([$puerta, $idvuelo]);

        // Notificar a pasajeros
        $st = $db->prepare(
            "SELECT DISTINCT rp.idpasajero FROM reserva_pasajero rp
             JOIN reserva r ON rp.idreserva = r.idreserva
             WHERE r.idvuelo = ? AND r.estado NOT IN ('Cancelada','Finalizada')"
        );
        $st->execute([$idvuelo]);
        $pasajeros = $st->fetchAll();

        foreach ($pasajeros as $p) {
            notificar(
                $p['idpasajero'],
                'cambio_puerta',
                "Tu vuelo ha sido asignado a la puerta de embarque: $puerta."
            );
        }

        ok(['message' => "Puerta $puerta asignada al vuelo."]);
        break;

    // ----------------------------------------------------------
    // GET ?action=aerolineas  (requiere operador_vuelos)
    // ----------------------------------------------------------
    case 'aerolineas':
        requireOp('operador_vuelos');
        $db = getDB();
        $st = $db->query(
            "SELECT idaerolinea, ruc, nombre, logo_url, activa FROM aerolinea ORDER BY nombre"
        );
        ok($st->fetchAll());
        break;

    // ----------------------------------------------------------
    // GET ?action=aviones  (requiere operador_vuelos)
    // Filtro opcional: ?idaerolinea=X
    // ----------------------------------------------------------
    case 'aviones':
        requireOp('operador_vuelos');
        $idaerolinea = (int)($_GET['idaerolinea'] ?? 0);

        $db = getDB();
        if ($idaerolinea) {
            $st = $db->prepare(
                "SELECT av.idavion, av.fabricante, av.modelo,
                        av.capacidad_eco, av.capacidad_eje, av.capacidad_pri,
                        al.nombre AS aerolinea
                 FROM avion av
                 JOIN aerolinea al ON av.idaerolinea = al.idaerolinea
                 WHERE av.idaerolinea = ? AND av.activo = 1
                 ORDER BY av.idavion"
            );
            $st->execute([$idaerolinea]);
        } else {
            $st = $db->query(
                "SELECT av.idavion, av.fabricante, av.modelo,
                        av.capacidad_eco, av.capacidad_eje, av.capacidad_pri,
                        al.nombre AS aerolinea
                 FROM avion av
                 JOIN aerolinea al ON av.idaerolinea = al.idaerolinea
                 WHERE av.activo = 1
                 ORDER BY al.nombre, av.idavion"
            );
        }
        ok($st->fetchAll());
        break;

    // ----------------------------------------------------------
    // GET ?action=tarifas  (requiere operador_vuelos)
    // ----------------------------------------------------------
    case 'tarifas':
        requireOp('operador_vuelos');
        $db = getDB();
        $st = $db->query(
            "SELECT idtarifa, clase, precio, impuesto, equipaje_incluido
             FROM tarifa ORDER BY clase, precio"
        );
        ok($st->fetchAll());
        break;

    // ----------------------------------------------------------
    // POST ?action=crear_tarifa  (requiere operador_vuelos)
    // ----------------------------------------------------------
    case 'crear_tarifa':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') err('Método no permitido', 405);
        requireOp('operador_vuelos');

        $body = getBody();
        $clase    = trim($body['clase']    ?? '');
        $precio   = (float)($body['precio'] ?? 0);
        $impuesto = (float)($body['impuesto'] ?? 0.18);
        $eq_inc   = (int)($body['equipaje_incluido'] ?? 1);

        $clasesValidas = ['Económica','Ejecutiva','Primera Clase'];
        if (!in_array($clase, $clasesValidas)) err('Clase inválida.');
        if ($precio <= 0) err('El precio debe ser mayor a 0.');

        $db = getDB();
        $st = $db->prepare(
            "INSERT INTO tarifa (clase, precio, impuesto, equipaje_incluido) VALUES (?, ?, ?, ?)"
        );
        $st->execute([$clase, $precio, $impuesto, $eq_inc]);
        ok(['idtarifa' => (int)$db->lastInsertId()]);
        break;

    // ----------------------------------------------------------
    // GET ?action=promociones  (requiere operador_vuelos)
    // ----------------------------------------------------------
    case 'promociones':
        requireOp('operador_vuelos');
        $db = getDB();
        $st = $db->query(
            "SELECT idpromo, codigo, descripcion, descuento,
                    fecha_inicio, fecha_fin, activa, usos_max, usos_actual
             FROM promocion ORDER BY fecha_fin DESC"
        );
        ok($st->fetchAll());
        break;

    // ----------------------------------------------------------
    // POST ?action=validar_promo  (PÚBLICO - para el formulario de reserva)
    // ----------------------------------------------------------
    case 'validar_promo':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') err('Método no permitido', 405);
        $body   = getBody();
        $codigo = strtoupper(trim($body['codigo'] ?? ''));
        if (!$codigo) err('Código de promoción requerido.');

        $db = getDB();
        $st = $db->prepare(
            "SELECT idpromo, descripcion, descuento, usos_max, usos_actual
             FROM promocion
             WHERE codigo = ? AND activa = 1
               AND fecha_inicio <= CURDATE() AND fecha_fin >= CURDATE()"
        );
        $st->execute([$codigo]);
        $promo = $st->fetch();

        if (!$promo) err('Código de promoción inválido o vencido.');
        if ($promo['usos_actual'] >= $promo['usos_max']) err('Esta promoción ya alcanzó su límite de usos.');

        ok([
            'idpromo'    => $promo['idpromo'],
            'descripcion' => $promo['descripcion'],
            'descuento'  => $promo['descuento'],
        ]);
        break;

    // ----------------------------------------------------------
    default:
        err('Acción no reconocida.', 404);
}