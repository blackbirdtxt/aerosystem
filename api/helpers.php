<?php
// ============================================================
// helpers.php — AeroSystem Pro
// Funciones globales reutilizables
// ============================================================

require_once __DIR__ . '/config.php';

function sendJSON($data, $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function ok($data = []): void {
    sendJSON(['success' => true, 'data' => $data]);
}

function err($msg, $code = 400): void {
    sendJSON(['success' => false, 'error' => $msg], $code);
}

function requireAuth(): string {
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (empty($_SESSION['idpasajero'])) err('No autorizado', 401);
    return $_SESSION['idpasajero'];
}

function requireOp(?string $rol = null): int {
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (empty($_SESSION['idusuario'])) err('No autorizado', 401);
    if ($rol && $_SESSION['rol'] !== $rol) err('Permiso denegado', 403);
    return (int)$_SESSION['idusuario'];
}

function getBody(): array {
    $raw = file_get_contents('php://input');
    return json_decode($raw, true) ?? [];
}

function generarIdPasajero(): string {
    $db = getDB();
    do {
        $id = 'PAS' . str_pad(rand(1, 999999), 6, '0', STR_PAD_LEFT);
        $st = $db->prepare("SELECT idpasajero FROM pasajero WHERE idpasajero = ?");
        $st->execute([$id]);
    } while ($st->fetch());
    return $id;
}

function generarCodReserva(): string {
    return 'AERO-' . date('Y') . '-' . strtoupper(substr(md5(uniqid('', true)), 0, 6));
}

function generarEtiqueta(string $apellido): string {
    $ap = strtoupper(substr(preg_replace('/[^a-zA-Z]/', '', $apellido), 0, 4));
    $ap = str_pad($ap, 4, 'X');
    return 'AEROSYS-' . $ap . '-' . rand(1000, 9999);
}

function calcularDuracion(string $salida, string $llegada): int {
    $s = new DateTime($salida);
    $l = new DateTime($llegada);
    return (int)(($l->getTimestamp() - $s->getTimestamp()) / 60);
}

function notificar(string $idpasajero, string $tipo, string $mensaje): void {
    $db = getDB();
    $st = $db->prepare(
        "INSERT INTO notificacion (idpasajero, tipo, mensaje) VALUES (?, ?, ?)"
    );
    $st->execute([$idpasajero, $tipo, $mensaje]);
}