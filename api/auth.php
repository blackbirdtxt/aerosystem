<?php
// ============================================================
// auth.php — AeroSystem Pro
// Autenticación de PASAJEROS
// ============================================================

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/helpers.php';

if (session_status() === PHP_SESSION_NONE) session_start();

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { ok([]); exit; }

$action = $_GET['action'] ?? '';

switch ($action) {

    // ----------------------------------------------------------
    case 'login':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') err('Método no permitido', 405);

        $body = getBody();
        $mail  = trim($body['mail']  ?? '');
        $clave = trim($body['clave'] ?? '');

        if (!$mail || !$clave) err('Mail y contraseña son obligatorios.');

        $db = getDB();
        $st = $db->prepare(
            "SELECT idpasajero, nombre, apaterno, mail, clave, activo
             FROM pasajero WHERE mail = ?"
        );
        $st->execute([$mail]);
        $pasajero = $st->fetch();

        if (!$pasajero) err('Credenciales incorrectas.', 401);
        if (!$pasajero['activo']) err('Cuenta desactivada.', 403);
        if (!password_verify($clave, $pasajero['clave'])) err('Credenciales incorrectas.', 401);

        $_SESSION['idpasajero'] = $pasajero['idpasajero'];
        $_SESSION['nombre']     = $pasajero['nombre'];
        $_SESSION['apaterno']   = $pasajero['apaterno'];
        $_SESSION['mail']       = $pasajero['mail'];
        $_SESSION['rol']        = 'pasajero';

        unset($pasajero['clave'], $pasajero['activo']);
        ok($pasajero);
        break;

    // ----------------------------------------------------------
    case 'registro':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') err('Método no permitido', 405);

        $body = getBody();
        $requeridos = ['nombre', 'apaterno', 'mail', 'clave', 'tipo_documento', 'num_documento', 'idpais'];
        foreach ($requeridos as $campo) {
            if (empty($body[$campo])) err("El campo '$campo' es obligatorio.");
        }

        $nombre         = trim($body['nombre']);
        $apaterno       = trim($body['apaterno']);
        $amaterno       = trim($body['amaterno']  ?? '');
        $telefono       = trim($body['telefono']  ?? '');
        $mail           = trim($body['mail']);
        $clave          = $body['clave'];
        $fecha_nac      = $body['fecha_nac']      ?? null;
        $tipo_documento = $body['tipo_documento'];
        $num_documento  = trim($body['num_documento']);
        $idpais         = $body['idpais'];
        $tipo_vuelo_pref = $body['tipo_vuelo_pref'] ?? 'Ambos';

        if (!filter_var($mail, FILTER_VALIDATE_EMAIL)) err('Email inválido.');
        if (strlen($clave) < 6) err('La contraseña debe tener al menos 6 caracteres.');

        $db = getDB();

        $st = $db->prepare("SELECT idpasajero FROM pasajero WHERE mail = ?");
        $st->execute([$mail]);
        if ($st->fetch()) err('El email ya está registrado.');

        $idpasajero = generarIdPasajero();
        $hash       = password_hash($clave, PASSWORD_BCRYPT);

        $st = $db->prepare(
            "INSERT INTO pasajero
             (idpasajero, nombre, apaterno, amaterno, telefono, mail, clave,
              fecha_nac, tipo_documento, num_documento, idpais, tipo_vuelo_pref)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $st->execute([
            $idpasajero, $nombre, $apaterno,
            $amaterno ?: null, $telefono ?: null,
            $mail, $hash,
            $fecha_nac ?: null, $tipo_documento, $num_documento,
            $idpais, $tipo_vuelo_pref
        ]);

        ok(['idpasajero' => $idpasajero]);
        break;

    // ----------------------------------------------------------
    case 'logout':
        $_SESSION = [];
        session_destroy();
        ok(['message' => 'Sesión cerrada.']);
        break;

    // ----------------------------------------------------------
    case 'check_session':
        if (empty($_SESSION['idpasajero'])) {
            ok(['logged' => false]);
        } else {
            ok([
                'logged'      => true,
                'idpasajero'  => $_SESSION['idpasajero'],
                'nombre'      => $_SESSION['nombre'],
                'apaterno'    => $_SESSION['apaterno'],
                'mail'        => $_SESSION['mail'],
                'rol'         => $_SESSION['rol'],
            ]);
        }
        break;

    // ----------------------------------------------------------
    case 'perfil':
        $idpasajero = requireAuth();
        $db = getDB();
        $st = $db->prepare(
            "SELECT idpasajero, nombre, apaterno, amaterno, telefono, mail,
                    fecha_nac, tipo_documento, num_documento, idpais, tipo_vuelo_pref, creado_en
             FROM pasajero WHERE idpasajero = ?"
        );
        $st->execute([$idpasajero]);
        $p = $st->fetch();
        if (!$p) err('Pasajero no encontrado.', 404);
        ok($p);
        break;

    // ----------------------------------------------------------
    case 'actualizar_perfil':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') err('Método no permitido', 405);
        $idpasajero = requireAuth();
        $body = getBody();

        $campos   = [];
        $valores  = [];
        $editables = ['nombre','apaterno','amaterno','telefono','fecha_nac','tipo_vuelo_pref'];
        foreach ($editables as $c) {
            if (isset($body[$c])) {
                $campos[]  = "$c = ?";
                $valores[] = $body[$c];
            }
        }
        if (empty($campos)) err('No hay campos para actualizar.');

        $valores[] = $idpasajero;
        $db = getDB();
        $st = $db->prepare("UPDATE pasajero SET " . implode(', ', $campos) . " WHERE idpasajero = ?");
        $st->execute($valores);
        ok(['message' => 'Perfil actualizado.']);
        break;

    // ----------------------------------------------------------
    default:
        err('Acción no reconocida.', 404);
}