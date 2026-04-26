<?php
// ============================================================
// auth_operador.php — AeroSystem Pro
// Autenticación de OPERADORES (vuelos y equipaje)
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

        $body  = getBody();
        $mail  = trim($body['mail']  ?? '');
        $clave = trim($body['clave'] ?? '');

        if (!$mail || !$clave) err('Mail y contraseña son obligatorios.');

        $db = getDB();
        $st = $db->prepare(
            "SELECT idusuario, nombre, mail, clave, rol, activo
             FROM usuario_operador WHERE mail = ?"
        );
        $st->execute([$mail]);
        $op = $st->fetch();

        if (!$op) err('Credenciales incorrectas.', 401);
        if (!$op['activo']) err('Cuenta desactivada.', 403);
        if (!password_verify($clave, $op['clave'])) err('Credenciales incorrectas.', 401);

        $_SESSION['idusuario'] = $op['idusuario'];
        $_SESSION['nombre']    = $op['nombre'];
        $_SESSION['mail']      = $op['mail'];
        $_SESSION['rol']       = $op['rol'];

        unset($op['clave'], $op['activo']);
        ok($op);
        break;

    // ----------------------------------------------------------
    case 'logout':
        $_SESSION = [];
        session_destroy();
        ok(['message' => 'Sesión cerrada.']);
        break;

    // ----------------------------------------------------------
    case 'check_session':
        if (empty($_SESSION['idusuario'])) {
            ok(['logged' => false]);
        } else {
            ok([
                'logged'    => true,
                'idusuario' => $_SESSION['idusuario'],
                'nombre'    => $_SESSION['nombre'],
                'mail'      => $_SESSION['mail'],
                'rol'       => $_SESSION['rol'],
            ]);
        }
        break;

    // ----------------------------------------------------------
    default:
        err('Acción no reconocida.', 404);
}