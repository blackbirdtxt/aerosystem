<?php
// ============================================================
// panel.php — AeroSystem
// Panel público del aeropuerto (sin autenticación)
// ============================================================

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/helpers.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { ok([]); exit; }

$action = $_GET['action'] ?? '';

switch ($action) {

    // ----------------------------------------------------------
    // PÚBLICO: vuelos próximas 24 horas
    // GET ?action=vuelos24h
    // ----------------------------------------------------------
    case 'vuelos24h':
        $db = getDB();
        $st = $db->query("SELECT * FROM vw_panel_aeropuerto");
        ok($st->fetchAll());
        break;

    // ----------------------------------------------------------
    // PÚBLICO: vuelos por estado
    // GET ?action=por_estado&estado=Abordando
    // ----------------------------------------------------------
    case 'por_estado':
        $estado = trim($_GET['estado'] ?? '');
        if (!$estado) err('Estado requerido.');

        $db = getDB();
        $st = $db->prepare("SELECT * FROM vw_panel_aeropuerto WHERE estado = ?");
        $st->execute([$estado]);
        ok($st->fetchAll());
        break;

    // ----------------------------------------------------------
    // PÚBLICO: resumen de estados (para ticker/dashboard)
    // GET ?action=resumen_estados
    // ----------------------------------------------------------
    case 'resumen_estados':
        $db = getDB();
        $st = $db->query(
            "SELECT estado, COUNT(*) AS total
             FROM vuelo
             WHERE fecha_salida BETWEEN DATE_SUB(NOW(), INTERVAL 2 HOUR)
                               AND DATE_ADD(NOW(), INTERVAL 24 HOUR)
             GROUP BY estado"
        );
        ok($st->fetchAll());
        break;

    // ----------------------------------------------------------
    // PÚBLICO: hora actual del servidor (para ticker)
    // GET ?action=hora
    // ----------------------------------------------------------
    case 'hora':
        ok(['hora' => date('H:i:s'), 'fecha' => date('Y-m-d'), 'timestamp' => time()]);
        break;

    // ----------------------------------------------------------
    default:
        err('Acción no reconocida.', 404);
}