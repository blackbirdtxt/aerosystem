<?php
// ============================================================
// aeropuertos.php — AeroSystem
// Endpoints públicos de aeropuertos y países
// ============================================================

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/helpers.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { ok([]); exit; }

$action = $_GET['action'] ?? '';

switch ($action) {

    // ----------------------------------------------------------
    // PÚBLICO: listar aeropuertos activos
    // GET ?action=listar
    // ----------------------------------------------------------
    case 'listar':
        $db = getDB();
        $st = $db->query(
            "SELECT a.codigo_iata, a.nombre, a.ciudad, a.es_internacional,
                    p.nombre AS pais, p.idpais
             FROM aeropuerto a
             JOIN pais p ON a.idpais = p.idpais
             WHERE a.activo = 1
             ORDER BY p.nombre, a.ciudad"
        );
        ok($st->fetchAll());
        break;

    // ----------------------------------------------------------
    // PÚBLICO: listar países
    // GET ?action=paises
    // ----------------------------------------------------------
    case 'paises':
        $db = getDB();
        $st = $db->query(
            "SELECT idpais, nombre, codigo_telefono FROM pais ORDER BY nombre"
        );
        ok($st->fetchAll());
        break;

    // ----------------------------------------------------------
    // PÚBLICO: detalle de un aeropuerto
    // GET ?action=detalle&iata=LIM
    // ----------------------------------------------------------
    case 'detalle':
        $iata = strtoupper(trim($_GET['iata'] ?? ''));
        if (!$iata) err('Código IATA requerido.');

        $db = getDB();
        $st = $db->prepare(
            "SELECT a.codigo_iata, a.nombre, a.ciudad, a.es_internacional,
                    p.nombre AS pais, p.codigo_telefono
             FROM aeropuerto a
             JOIN pais p ON a.idpais = p.idpais
             WHERE a.codigo_iata = ? AND a.activo = 1"
        );
        $st->execute([$iata]);
        $ap = $st->fetch();
        if (!$ap) err('Aeropuerto no encontrado.', 404);
        ok($ap);
        break;

    // ----------------------------------------------------------
    // OPERADOR: crear aeropuerto (requiere operador_vuelos)
    // POST ?action=crear
    // ----------------------------------------------------------
    case 'crear':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') err('Método no permitido', 405);
        if (session_status() === PHP_SESSION_NONE) session_start();
        requireOp('operador_vuelos');

        $body = getBody();
        $requeridos = ['codigo_iata','nombre','ciudad','idpais'];
        foreach ($requeridos as $c) {
            if (empty($body[$c])) err("El campo '$c' es obligatorio.");
        }

        $iata           = strtoupper(trim($body['codigo_iata']));
        $nombre         = trim($body['nombre']);
        $ciudad         = trim($body['ciudad']);
        $idpais         = trim($body['idpais']);
        $es_internacional = isset($body['es_internacional']) ? (int)(bool)$body['es_internacional'] : 1;

        if (strlen($iata) !== 3) err('El código IATA debe tener exactamente 3 caracteres.');

        $db = getDB();

        $st = $db->prepare("SELECT codigo_iata FROM aeropuerto WHERE codigo_iata = ?");
        $st->execute([$iata]);
        if ($st->fetch()) err('Ya existe un aeropuerto con ese código IATA.');

        $st = $db->prepare(
            "INSERT INTO aeropuerto (codigo_iata, nombre, ciudad, idpais, es_internacional)
             VALUES (?, ?, ?, ?, ?)"
        );
        $st->execute([$iata, $nombre, $ciudad, $idpais, $es_internacional]);
        ok(['codigo_iata' => $iata]);
        break;

    // ----------------------------------------------------------
    // OPERADOR: editar aeropuerto
    // POST ?action=editar
    // ----------------------------------------------------------
    case 'editar':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') err('Método no permitido', 405);
        if (session_status() === PHP_SESSION_NONE) session_start();
        requireOp('operador_vuelos');

        $body = getBody();
        $iata = strtoupper(trim($body['codigo_iata'] ?? ''));
        if (!$iata) err('codigo_iata es obligatorio.');

        $editables = ['nombre','ciudad','idpais','es_internacional','activo'];
        $campos    = [];
        $valores   = [];
        foreach ($editables as $c) {
            if (isset($body[$c])) {
                $campos[]  = "$c = ?";
                $valores[] = $body[$c];
            }
        }
        if (empty($campos)) err('No hay campos para actualizar.');

        $valores[] = $iata;
        $db = getDB();
        $st = $db->prepare(
            "UPDATE aeropuerto SET " . implode(', ', $campos) . " WHERE codigo_iata = ?"
        );
        $st->execute($valores);
        ok(['message' => 'Aeropuerto actualizado.']);
        break;

    // ----------------------------------------------------------
    default:
        err('Acción no reconocida.', 404);
}