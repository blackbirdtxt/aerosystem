-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 26-04-2026 a las 06:22:27
-- Versión del servidor: 10.4.32-MariaDB
-- Versión de PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `aerosystem_db`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `aerolinea`
--

CREATE TABLE `aerolinea` (
  `idaerolinea` int(11) NOT NULL,
  `ruc` varchar(15) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `logo_url` varchar(255) DEFAULT NULL,
  `activa` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `aerolinea`
--

INSERT INTO `aerolinea` (`idaerolinea`, `ruc`, `nombre`, `logo_url`, `activa`) VALUES
(1, '20123456789', 'LATAM Airlines', 'https://logo.clearbit.com/latam.com', 1),
(2, '20987654321', 'Avianca', 'https://logo.clearbit.com/avianca.com', 1),
(3, '20111222333', 'Sky Airline', 'https://logo.clearbit.com/skyairline.com', 1),
(4, '20444555666', 'Copa Airlines', 'https://logo.clearbit.com/copaair.com', 1);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `aeropuerto`
--

CREATE TABLE `aeropuerto` (
  `codigo_iata` char(3) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `ciudad` varchar(50) NOT NULL,
  `idpais` char(8) NOT NULL,
  `es_internacional` tinyint(1) NOT NULL DEFAULT 1,
  `activo` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `aeropuerto`
--

INSERT INTO `aeropuerto` (`codigo_iata`, `nombre`, `ciudad`, `idpais`, `es_internacional`, `activo`) VALUES
('AQP', 'Aeropuerto Rodríguez Ballón', 'Arequipa', 'PE', 0, 1),
('BOG', 'Aeropuerto El Dorado', 'Bogotá', 'CO', 1, 1),
('CUZ', 'Aeropuerto Alejandro Velasco Astete', 'Cusco', 'PE', 0, 1),
('EZE', 'Aeropuerto Internacional Ezeiza', 'Buenos Aires', 'AR', 1, 1),
('LIM', 'Aeropuerto Jorge Chávez', 'Lima', 'PE', 1, 1),
('MDE', 'Aeropuerto José María Córdova', 'Medellín', 'CO', 1, 1),
('MEX', 'Aeropuerto Benito Juárez', 'Ciudad de México', 'MX', 1, 1),
('SCL', 'Aeropuerto Arturo Merino Benítez', 'Santiago', 'CL', 1, 1),
('TRU', 'Aeropuerto Carlos Martínez de Pinillos', 'Trujillo', 'PE', 0, 1),
('UIO', 'Aeropuerto Mariscal Sucre', 'Quito', 'EC', 1, 1);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `asiento_vuelo`
--

CREATE TABLE `asiento_vuelo` (
  `idasiento_vuelo` int(11) NOT NULL,
  `idvuelo` int(11) NOT NULL,
  `fila` int(11) NOT NULL,
  `letra` char(1) NOT NULL,
  `clase` enum('Económica','Ejecutiva','Primera Clase') NOT NULL,
  `estado` enum('Disponible','Reservado','Bloqueado') NOT NULL DEFAULT 'Disponible'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `avion`
--

CREATE TABLE `avion` (
  `idavion` varchar(10) NOT NULL,
  `idaerolinea` int(11) NOT NULL,
  `fabricante` varchar(50) DEFAULT NULL,
  `modelo` varchar(50) DEFAULT NULL,
  `capacidad_eco` int(11) NOT NULL DEFAULT 150,
  `capacidad_eje` int(11) NOT NULL DEFAULT 20,
  `capacidad_pri` int(11) NOT NULL DEFAULT 10,
  `activo` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `avion`
--

INSERT INTO `avion` (`idavion`, `idaerolinea`, `fabricante`, `modelo`, `capacidad_eco`, `capacidad_eje`, `capacidad_pri`, `activo`) VALUES
('AV001', 1, 'Boeing', '737-800', 150, 24, 12, 1),
('AV002', 1, 'Airbus', 'A320', 150, 24, 0, 1),
('AV003', 2, 'Airbus', 'A319', 120, 18, 6, 1),
('AV004', 2, 'Boeing', '787-9', 250, 42, 18, 1),
('AV005', 3, 'Boeing', '737 MAX', 162, 18, 0, 1),
('AV006', 4, 'Boeing', '737-700', 120, 12, 8, 1);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `checkin`
--

CREATE TABLE `checkin` (
  `idcheckin` int(11) NOT NULL,
  `idreserva` int(11) NOT NULL,
  `idpasajero` varchar(10) NOT NULL,
  `fecha_checkin` datetime NOT NULL DEFAULT current_timestamp(),
  `puerta_embarque` varchar(10) DEFAULT NULL,
  `estado` enum('Completado','No presentado') NOT NULL DEFAULT 'Completado'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `equipaje`
--

CREATE TABLE `equipaje` (
  `idequipaje` int(11) NOT NULL,
  `idreserva` int(11) NOT NULL,
  `idpasajero` varchar(10) NOT NULL,
  `idtipo` int(11) NOT NULL,
  `peso_real` decimal(5,2) DEFAULT NULL,
  `color` varchar(30) DEFAULT NULL,
  `descripcion` varchar(200) DEFAULT NULL,
  `estado` enum('Registrado','Check-in','En seguridad','En bodega','En vuelo','En cinta','Entregado','Perdido','Dañado') NOT NULL DEFAULT 'Registrado',
  `etiqueta_codigo` varchar(20) NOT NULL,
  `costo_extra` decimal(10,2) NOT NULL DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `incidencia_equipaje`
--

CREATE TABLE `incidencia_equipaje` (
  `idincidencia` int(11) NOT NULL,
  `idequipaje` int(11) NOT NULL,
  `tipo` enum('Perdido','Dañado','Retrasado','Otro') NOT NULL,
  `descripcion` varchar(500) NOT NULL,
  `fecha` datetime NOT NULL DEFAULT current_timestamp(),
  `reportado_por` int(11) NOT NULL,
  `resuelto` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `notificacion`
--

CREATE TABLE `notificacion` (
  `idnotif` int(11) NOT NULL,
  `idpasajero` varchar(10) NOT NULL,
  `tipo` varchar(50) NOT NULL,
  `mensaje` varchar(500) NOT NULL,
  `fecha` datetime NOT NULL DEFAULT current_timestamp(),
  `leida` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `pago`
--

CREATE TABLE `pago` (
  `idpago` int(11) NOT NULL,
  `idreserva` int(11) NOT NULL,
  `idpasajero` varchar(10) NOT NULL,
  `fecha` datetime NOT NULL DEFAULT current_timestamp(),
  `monto` decimal(10,2) NOT NULL,
  `tipo_documento` enum('Boleta','Factura','Recibo') NOT NULL,
  `numcomprobante` varchar(20) DEFAULT NULL,
  `estado` enum('Procesado','Anulado','Reembolsado') NOT NULL DEFAULT 'Procesado'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `pais`
--

CREATE TABLE `pais` (
  `idpais` char(8) NOT NULL,
  `nombre` varchar(50) NOT NULL,
  `codigo_telefono` varchar(5) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `pais`
--

INSERT INTO `pais` (`idpais`, `nombre`, `codigo_telefono`) VALUES
('AR', 'Argentina', '+54'),
('BO', 'Bolivia', '+591'),
('BR', 'Brasil', '+55'),
('CL', 'Chile', '+56'),
('CO', 'Colombia', '+57'),
('EC', 'Ecuador', '+593'),
('MX', 'México', '+52'),
('PE', 'Perú', '+51'),
('US', 'Estados Unidos', '+1');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `pasajero`
--

CREATE TABLE `pasajero` (
  `idpasajero` varchar(10) NOT NULL,
  `nombre` varchar(50) NOT NULL,
  `apaterno` varchar(50) NOT NULL,
  `amaterno` varchar(50) DEFAULT NULL,
  `telefono` varchar(20) DEFAULT NULL,
  `mail` varchar(100) NOT NULL,
  `clave` varchar(255) NOT NULL,
  `fecha_nac` date DEFAULT NULL,
  `tipo_documento` enum('DNI','Pasaporte','CE','RUC') NOT NULL,
  `num_documento` varchar(20) NOT NULL,
  `idpais` char(8) NOT NULL,
  `tipo_vuelo_pref` enum('Nacional','Internacional','Ambos') DEFAULT 'Ambos',
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `pasajero`
--

INSERT INTO `pasajero` (`idpasajero`, `nombre`, `apaterno`, `amaterno`, `telefono`, `mail`, `clave`, `fecha_nac`, `tipo_documento`, `num_documento`, `idpais`, `tipo_vuelo_pref`, `activo`, `creado_en`) VALUES
('PAS000001', 'Ana', 'García', 'López', '999888777', 'demo@aerosystem.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '1995-03-15', 'DNI', '12345678', 'PE', 'Ambos', 1, '2026-04-25 18:16:53');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `promocion`
--

CREATE TABLE `promocion` (
  `idpromo` int(11) NOT NULL,
  `codigo` varchar(20) NOT NULL,
  `descripcion` varchar(200) DEFAULT NULL,
  `descuento` decimal(5,2) NOT NULL,
  `fecha_inicio` date NOT NULL,
  `fecha_fin` date NOT NULL,
  `activa` tinyint(1) NOT NULL DEFAULT 1,
  `usos_max` int(11) NOT NULL DEFAULT 100,
  `usos_actual` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `promocion`
--

INSERT INTO `promocion` (`idpromo`, `codigo`, `descripcion`, `descuento`, `fecha_inicio`, `fecha_fin`, `activa`, `usos_max`, `usos_actual`) VALUES
(1, 'AERO10', '10% de descuento en tu primer vuelo', 10.00, '2026-01-01', '2026-12-31', 1, 500, 0),
(2, 'VERANO20', '20% de descuento en vuelos de verano', 20.00, '2026-04-01', '2026-06-30', 1, 200, 0);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `reserva`
--

CREATE TABLE `reserva` (
  `idreserva` int(11) NOT NULL,
  `codigo` varchar(20) NOT NULL,
  `idvuelo` int(11) NOT NULL,
  `costo_base` decimal(10,2) NOT NULL,
  `costo_equipaje` decimal(10,2) NOT NULL DEFAULT 0.00,
  `descuento` decimal(10,2) NOT NULL DEFAULT 0.00,
  `impuesto` decimal(10,2) NOT NULL,
  `costo_total` decimal(10,2) NOT NULL,
  `fecha` datetime NOT NULL DEFAULT current_timestamp(),
  `estado` enum('Pendiente','Pagada','Cancelada','Check-in','Embarcando','Finalizada') NOT NULL DEFAULT 'Pendiente',
  `tipo_vuelo` enum('Nacional','Internacional') NOT NULL,
  `idpromo` int(11) DEFAULT NULL,
  `observacion` varchar(300) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `reserva_pasajero`
--

CREATE TABLE `reserva_pasajero` (
  `id` int(11) NOT NULL,
  `idreserva` int(11) NOT NULL,
  `idpasajero` varchar(10) NOT NULL,
  `idasiento_vuelo` int(11) NOT NULL,
  `idtarifa` int(11) NOT NULL,
  `es_titular` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `tarifa`
--

CREATE TABLE `tarifa` (
  `idtarifa` int(11) NOT NULL,
  `clase` enum('Económica','Ejecutiva','Primera Clase') NOT NULL,
  `precio` decimal(10,2) NOT NULL,
  `impuesto` decimal(5,4) NOT NULL DEFAULT 0.1800,
  `equipaje_incluido` int(11) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `tarifa`
--

INSERT INTO `tarifa` (`idtarifa`, `clase`, `precio`, `impuesto`, `equipaje_incluido`) VALUES
(1, 'Económica', 180.00, 0.1800, 1),
(2, 'Económica', 220.00, 0.1800, 1),
(3, 'Ejecutiva', 450.00, 0.1800, 2),
(4, 'Ejecutiva', 580.00, 0.1800, 2),
(5, 'Primera Clase', 1200.00, 0.1800, 3);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `tipo_equipaje`
--

CREATE TABLE `tipo_equipaje` (
  `idtipo` int(11) NOT NULL,
  `nombre` varchar(80) NOT NULL,
  `peso_max_kg` decimal(5,2) NOT NULL,
  `precio_extra` decimal(10,2) NOT NULL DEFAULT 0.00,
  `descripcion` varchar(200) DEFAULT NULL,
  `requiere_reserva_anticipada` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `tipo_equipaje`
--

INSERT INTO `tipo_equipaje` (`idtipo`, `nombre`, `peso_max_kg`, `precio_extra`, `descripcion`, `requiere_reserva_anticipada`) VALUES
(1, 'Equipaje de mano', 7.00, 0.00, 'Incluido. Máx 55x40x20cm', 0),
(2, 'Maleta bodega 23kg', 23.00, 45.00, 'Maleta estándar en bodega', 0),
(3, 'Maleta bodega 32kg', 32.00, 75.00, 'Maleta grande en bodega', 0),
(4, 'Bicicleta', 25.00, 100.00, 'Embalada en caja o funda rígida', 1),
(5, 'Instrumento musical', 15.00, 60.00, 'En estuche rígido homologado', 1),
(6, 'Silla de ruedas', 30.00, 0.00, 'Servicio sin costo adicional', 0),
(7, 'Mascota en cabina', 8.00, 80.00, 'En transportín homologado', 1),
(8, 'Equipo de esquí', 25.00, 100.00, 'Esquís, botas y bastones', 1);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuario_operador`
--

CREATE TABLE `usuario_operador` (
  `idusuario` int(11) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `mail` varchar(100) NOT NULL,
  `clave` varchar(255) NOT NULL,
  `rol` enum('operador_vuelos','operador_equipaje') NOT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `creado_en` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `usuario_operador`
--

INSERT INTO `usuario_operador` (`idusuario`, `nombre`, `mail`, `clave`, `rol`, `activo`, `creado_en`) VALUES
(1, 'Carlos Mendoza', 'operador.vuelos@aerosystem.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'operador_vuelos', 1, '2026-04-25 18:16:53'),
(2, 'María Torres', 'operador.equipaje@aerosystem.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'operador_equipaje', 1, '2026-04-25 18:16:53');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `vuelo`
--

CREATE TABLE `vuelo` (
  `idvuelo` int(11) NOT NULL,
  `numero_vuelo` varchar(10) NOT NULL,
  `idavion` varchar(10) NOT NULL,
  `origen` char(3) NOT NULL,
  `destino` char(3) NOT NULL,
  `tipo` enum('Nacional','Internacional') NOT NULL,
  `fecha_salida` datetime NOT NULL,
  `fecha_llegada` datetime NOT NULL,
  `duracion_min` int(11) NOT NULL,
  `estado` enum('Programado','Retrasado','Abordando','En vuelo','Aterrizado','Desembarcando','Finalizado','Cancelado') NOT NULL DEFAULT 'Programado',
  `puerta` varchar(10) DEFAULT NULL,
  `motivo_retraso` varchar(200) DEFAULT NULL,
  `idtarifa_eco` int(11) NOT NULL,
  `idtarifa_eje` int(11) NOT NULL,
  `idtarifa_pri` int(11) NOT NULL,
  `creado_por` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `vuelo`
--

INSERT INTO `vuelo` (`idvuelo`, `numero_vuelo`, `idavion`, `origen`, `destino`, `tipo`, `fecha_salida`, `fecha_llegada`, `duracion_min`, `estado`, `puerta`, `motivo_retraso`, `idtarifa_eco`, `idtarifa_eje`, `idtarifa_pri`, `creado_por`) VALUES
(1, 'LA2341', 'AV001', 'LIM', 'BOG', 'Internacional', '2026-05-03 06:00:00', '2026-05-03 10:30:00', 270, 'Programado', 'A3', NULL, 1, 3, 5, 1),
(2, 'LA2890', 'AV002', 'LIM', 'SCL', 'Internacional', '2026-05-03 08:30:00', '2026-05-03 13:15:00', 285, 'Programado', 'B1', NULL, 2, 3, 5, 1),
(3, 'AV9021', 'AV003', 'LIM', 'EZE', 'Internacional', '2026-05-03 14:00:00', '2026-05-03 20:45:00', 405, 'Programado', 'C2', NULL, 2, 4, 5, 1),
(4, 'LA2342', 'AV001', 'BOG', 'LIM', 'Internacional', '2026-05-03 11:00:00', '2026-05-03 13:30:00', 150, 'Abordando', 'A4', NULL, 1, 3, 5, 1),
(5, 'SK2199', 'AV005', 'LIM', 'CUZ', 'Nacional', '2026-05-03 07:00:00', '2026-05-03 08:05:00', 65, 'En vuelo', 'D1', NULL, 1, 3, 5, 1),
(6, 'AV3341', 'AV004', 'LIM', 'MDE', 'Internacional', '2026-05-03 16:30:00', '2026-05-03 21:00:00', 270, 'Programado', 'B3', NULL, 2, 4, 5, 1),
(7, 'LA9910', 'AV002', 'SCL', 'LIM', 'Internacional', '2026-05-03 09:00:00', '2026-05-03 13:00:00', 240, 'Retrasado', 'B2', NULL, 1, 3, 5, 1),
(8, 'SK1100', 'AV005', 'LIM', 'AQP', 'Nacional', '2026-05-04 06:30:00', '2026-05-04 07:45:00', 75, 'Programado', 'A1', NULL, 1, 3, 5, 1),
(9, 'CP0421', 'AV006', 'LIM', 'BOG', 'Internacional', '2026-05-04 10:00:00', '2026-05-04 14:30:00', 270, 'Programado', 'C1', NULL, 2, 4, 5, 1),
(10, 'SK3300', 'AV005', 'LIM', 'TRU', 'Nacional', '2026-05-04 08:00:00', '2026-05-04 09:00:00', 60, 'Programado', 'A2', NULL, 1, 3, 5, 1);

-- --------------------------------------------------------

--
-- Estructura Stand-in para la vista `vw_equipaje_tracking`
-- (Véase abajo para la vista actual)
--
CREATE TABLE `vw_equipaje_tracking` (
`idequipaje` int(11)
,`etiqueta_codigo` varchar(20)
,`estado` enum('Registrado','Check-in','En seguridad','En bodega','En vuelo','En cinta','Entregado','Perdido','Dañado')
,`color` varchar(30)
,`peso_real` decimal(5,2)
,`costo_extra` decimal(10,2)
,`tipo_equipaje` varchar(80)
,`peso_max_kg` decimal(5,2)
,`nombre` varchar(50)
,`apaterno` varchar(50)
,`numero_vuelo` varchar(10)
,`ciudad_origen` varchar(50)
,`ciudad_destino` varchar(50)
,`fecha_salida` datetime
,`estado_vuelo` enum('Programado','Retrasado','Abordando','En vuelo','Aterrizado','Desembarcando','Finalizado','Cancelado')
);

-- --------------------------------------------------------

--
-- Estructura Stand-in para la vista `vw_manifiesto_vuelo`
-- (Véase abajo para la vista actual)
--
CREATE TABLE `vw_manifiesto_vuelo` (
`idvuelo` int(11)
,`numero_vuelo` varchar(10)
,`idpasajero` varchar(10)
,`nombre` varchar(50)
,`apaterno` varchar(50)
,`amaterno` varchar(50)
,`tipo_documento` enum('DNI','Pasaporte','CE','RUC')
,`num_documento` varchar(20)
,`fila` int(11)
,`letra` char(1)
,`clase` enum('Económica','Ejecutiva','Primera Clase')
,`clase_tarifa` enum('Económica','Ejecutiva','Primera Clase')
,`precio` decimal(10,2)
,`codigo_reserva` varchar(20)
,`estado_reserva` enum('Pendiente','Pagada','Cancelada','Check-in','Embarcando','Finalizada')
,`fecha_checkin` datetime
,`estado_checkin` enum('Completado','No presentado')
,`cant_equipaje` bigint(21)
);

-- --------------------------------------------------------

--
-- Estructura Stand-in para la vista `vw_panel_aeropuerto`
-- (Véase abajo para la vista actual)
--
CREATE TABLE `vw_panel_aeropuerto` (
`idvuelo` int(11)
,`numero_vuelo` varchar(10)
,`tipo` enum('Nacional','Internacional')
,`estado` enum('Programado','Retrasado','Abordando','En vuelo','Aterrizado','Desembarcando','Finalizado','Cancelado')
,`puerta` varchar(10)
,`motivo_retraso` varchar(200)
,`cod_origen` char(3)
,`ciudad_origen` varchar(50)
,`cod_destino` char(3)
,`ciudad_destino` varchar(50)
,`hora_salida` varchar(10)
,`hora_llegada` varchar(10)
,`aerolinea` varchar(100)
,`logo_url` varchar(255)
);

-- --------------------------------------------------------

--
-- Estructura Stand-in para la vista `vw_reservas_completas`
-- (Véase abajo para la vista actual)
--
CREATE TABLE `vw_reservas_completas` (
`idreserva` int(11)
,`codigo` varchar(20)
,`costo_total` decimal(10,2)
,`fecha` datetime
,`estado` enum('Pendiente','Pagada','Cancelada','Check-in','Embarcando','Finalizada')
,`tipo_vuelo` enum('Nacional','Internacional')
,`idpasajero` varchar(10)
,`nombre` varchar(50)
,`apaterno` varchar(50)
,`mail` varchar(100)
,`numero_vuelo` varchar(10)
,`tipo_vuelo_vuelo` enum('Nacional','Internacional')
,`estado_vuelo` enum('Programado','Retrasado','Abordando','En vuelo','Aterrizado','Desembarcando','Finalizado','Cancelado')
,`ciudad_origen` varchar(50)
,`ciudad_destino` varchar(50)
,`fecha_salida` datetime
,`puerta` varchar(10)
,`aerolinea` varchar(100)
);

-- --------------------------------------------------------

--
-- Estructura Stand-in para la vista `vw_vuelos_disponibles`
-- (Véase abajo para la vista actual)
--
CREATE TABLE `vw_vuelos_disponibles` (
`idvuelo` int(11)
,`numero_vuelo` varchar(10)
,`tipo` enum('Nacional','Internacional')
,`estado` enum('Programado','Retrasado','Abordando','En vuelo','Aterrizado','Desembarcando','Finalizado','Cancelado')
,`puerta` varchar(10)
,`fecha_salida` datetime
,`fecha_llegada` datetime
,`duracion_min` int(11)
,`cod_origen` char(3)
,`ciudad_origen` varchar(50)
,`nombre_origen` varchar(100)
,`cod_destino` char(3)
,`ciudad_destino` varchar(50)
,`nombre_destino` varchar(100)
,`aerolinea` varchar(100)
,`logo_url` varchar(255)
,`tipo_avion` varchar(50)
,`capacidad_total` bigint(13)
,`precio_eco` decimal(10,2)
,`precio_eje` decimal(10,2)
,`precio_pri` decimal(10,2)
,`impuesto` decimal(5,4)
,`eq_incluido_eco` int(11)
,`eq_incluido_eje` int(11)
,`eq_incluido_pri` int(11)
,`asientos_disponibles` bigint(21)
);

-- --------------------------------------------------------

--
-- Estructura para la vista `vw_equipaje_tracking`
--
DROP TABLE IF EXISTS `vw_equipaje_tracking`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vw_equipaje_tracking`  AS SELECT `e`.`idequipaje` AS `idequipaje`, `e`.`etiqueta_codigo` AS `etiqueta_codigo`, `e`.`estado` AS `estado`, `e`.`color` AS `color`, `e`.`peso_real` AS `peso_real`, `e`.`costo_extra` AS `costo_extra`, `te`.`nombre` AS `tipo_equipaje`, `te`.`peso_max_kg` AS `peso_max_kg`, `p`.`nombre` AS `nombre`, `p`.`apaterno` AS `apaterno`, `v`.`numero_vuelo` AS `numero_vuelo`, `ao`.`ciudad` AS `ciudad_origen`, `ad`.`ciudad` AS `ciudad_destino`, `v`.`fecha_salida` AS `fecha_salida`, `v`.`estado` AS `estado_vuelo` FROM ((((((`equipaje` `e` join `tipo_equipaje` `te` on(`e`.`idtipo` = `te`.`idtipo`)) join `pasajero` `p` on(`e`.`idpasajero` = `p`.`idpasajero`)) join `reserva` `r` on(`e`.`idreserva` = `r`.`idreserva`)) join `vuelo` `v` on(`r`.`idvuelo` = `v`.`idvuelo`)) join `aeropuerto` `ao` on(`v`.`origen` = `ao`.`codigo_iata`)) join `aeropuerto` `ad` on(`v`.`destino` = `ad`.`codigo_iata`)) ;

-- --------------------------------------------------------

--
-- Estructura para la vista `vw_manifiesto_vuelo`
--
DROP TABLE IF EXISTS `vw_manifiesto_vuelo`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vw_manifiesto_vuelo`  AS SELECT `v`.`idvuelo` AS `idvuelo`, `v`.`numero_vuelo` AS `numero_vuelo`, `p`.`idpasajero` AS `idpasajero`, `p`.`nombre` AS `nombre`, `p`.`apaterno` AS `apaterno`, `p`.`amaterno` AS `amaterno`, `p`.`tipo_documento` AS `tipo_documento`, `p`.`num_documento` AS `num_documento`, `av_s`.`fila` AS `fila`, `av_s`.`letra` AS `letra`, `av_s`.`clase` AS `clase`, `t`.`clase` AS `clase_tarifa`, `t`.`precio` AS `precio`, `r`.`codigo` AS `codigo_reserva`, `r`.`estado` AS `estado_reserva`, `c`.`fecha_checkin` AS `fecha_checkin`, `c`.`estado` AS `estado_checkin`, (select count(0) from `equipaje` `eq` where `eq`.`idreserva` = `r`.`idreserva` and `eq`.`idpasajero` = `p`.`idpasajero`) AS `cant_equipaje` FROM ((((((`vuelo` `v` join `reserva` `r` on(`r`.`idvuelo` = `v`.`idvuelo`)) join `reserva_pasajero` `rp` on(`rp`.`idreserva` = `r`.`idreserva`)) join `pasajero` `p` on(`rp`.`idpasajero` = `p`.`idpasajero`)) join `asiento_vuelo` `av_s` on(`rp`.`idasiento_vuelo` = `av_s`.`idasiento_vuelo`)) join `tarifa` `t` on(`rp`.`idtarifa` = `t`.`idtarifa`)) left join `checkin` `c` on(`c`.`idreserva` = `r`.`idreserva` and `c`.`idpasajero` = `p`.`idpasajero`)) ORDER BY `av_s`.`clase` ASC, `av_s`.`fila` ASC, `av_s`.`letra` ASC ;

-- --------------------------------------------------------

--
-- Estructura para la vista `vw_panel_aeropuerto`
--
DROP TABLE IF EXISTS `vw_panel_aeropuerto`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vw_panel_aeropuerto`  AS SELECT `v`.`idvuelo` AS `idvuelo`, `v`.`numero_vuelo` AS `numero_vuelo`, `v`.`tipo` AS `tipo`, `v`.`estado` AS `estado`, `v`.`puerta` AS `puerta`, `v`.`motivo_retraso` AS `motivo_retraso`, `ao`.`codigo_iata` AS `cod_origen`, `ao`.`ciudad` AS `ciudad_origen`, `ad`.`codigo_iata` AS `cod_destino`, `ad`.`ciudad` AS `ciudad_destino`, date_format(`v`.`fecha_salida`,'%H:%i') AS `hora_salida`, date_format(`v`.`fecha_llegada`,'%H:%i') AS `hora_llegada`, `al`.`nombre` AS `aerolinea`, `al`.`logo_url` AS `logo_url` FROM ((((`vuelo` `v` join `aeropuerto` `ao` on(`v`.`origen` = `ao`.`codigo_iata`)) join `aeropuerto` `ad` on(`v`.`destino` = `ad`.`codigo_iata`)) join `avion` `av` on(`v`.`idavion` = `av`.`idavion`)) join `aerolinea` `al` on(`av`.`idaerolinea` = `al`.`idaerolinea`)) WHERE `v`.`fecha_salida` between current_timestamp() - interval 2 hour and current_timestamp() + interval 24 hour ORDER BY `v`.`fecha_salida` ASC ;

-- --------------------------------------------------------

--
-- Estructura para la vista `vw_reservas_completas`
--
DROP TABLE IF EXISTS `vw_reservas_completas`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vw_reservas_completas`  AS SELECT `r`.`idreserva` AS `idreserva`, `r`.`codigo` AS `codigo`, `r`.`costo_total` AS `costo_total`, `r`.`fecha` AS `fecha`, `r`.`estado` AS `estado`, `r`.`tipo_vuelo` AS `tipo_vuelo`, `p`.`idpasajero` AS `idpasajero`, `p`.`nombre` AS `nombre`, `p`.`apaterno` AS `apaterno`, `p`.`mail` AS `mail`, `v`.`numero_vuelo` AS `numero_vuelo`, `v`.`tipo` AS `tipo_vuelo_vuelo`, `v`.`estado` AS `estado_vuelo`, `ao`.`ciudad` AS `ciudad_origen`, `ad`.`ciudad` AS `ciudad_destino`, `v`.`fecha_salida` AS `fecha_salida`, `v`.`puerta` AS `puerta`, `al`.`nombre` AS `aerolinea` FROM (((((((`reserva` `r` join `reserva_pasajero` `rp` on(`r`.`idreserva` = `rp`.`idreserva` and `rp`.`es_titular` = 1)) join `pasajero` `p` on(`rp`.`idpasajero` = `p`.`idpasajero`)) join `vuelo` `v` on(`r`.`idvuelo` = `v`.`idvuelo`)) join `aeropuerto` `ao` on(`v`.`origen` = `ao`.`codigo_iata`)) join `aeropuerto` `ad` on(`v`.`destino` = `ad`.`codigo_iata`)) join `avion` `av` on(`v`.`idavion` = `av`.`idavion`)) join `aerolinea` `al` on(`av`.`idaerolinea` = `al`.`idaerolinea`)) ;

-- --------------------------------------------------------

--
-- Estructura para la vista `vw_vuelos_disponibles`
--
DROP TABLE IF EXISTS `vw_vuelos_disponibles`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vw_vuelos_disponibles`  AS SELECT `v`.`idvuelo` AS `idvuelo`, `v`.`numero_vuelo` AS `numero_vuelo`, `v`.`tipo` AS `tipo`, `v`.`estado` AS `estado`, `v`.`puerta` AS `puerta`, `v`.`fecha_salida` AS `fecha_salida`, `v`.`fecha_llegada` AS `fecha_llegada`, `v`.`duracion_min` AS `duracion_min`, `ao`.`codigo_iata` AS `cod_origen`, `ao`.`ciudad` AS `ciudad_origen`, `ao`.`nombre` AS `nombre_origen`, `ad`.`codigo_iata` AS `cod_destino`, `ad`.`ciudad` AS `ciudad_destino`, `ad`.`nombre` AS `nombre_destino`, `al`.`nombre` AS `aerolinea`, `al`.`logo_url` AS `logo_url`, `av`.`modelo` AS `tipo_avion`, `av`.`capacidad_eco`+ `av`.`capacidad_eje` + `av`.`capacidad_pri` AS `capacidad_total`, `te`.`precio` AS `precio_eco`, `tj`.`precio` AS `precio_eje`, `tp`.`precio` AS `precio_pri`, `te`.`impuesto` AS `impuesto`, `te`.`equipaje_incluido` AS `eq_incluido_eco`, `tj`.`equipaje_incluido` AS `eq_incluido_eje`, `tp`.`equipaje_incluido` AS `eq_incluido_pri`, (select count(0) from `asiento_vuelo` `av2` where `av2`.`idvuelo` = `v`.`idvuelo` and `av2`.`estado` = 'Disponible') AS `asientos_disponibles` FROM (((((((`vuelo` `v` join `avion` `av` on(`v`.`idavion` = `av`.`idavion`)) join `aerolinea` `al` on(`av`.`idaerolinea` = `al`.`idaerolinea`)) join `aeropuerto` `ao` on(`v`.`origen` = `ao`.`codigo_iata`)) join `aeropuerto` `ad` on(`v`.`destino` = `ad`.`codigo_iata`)) join `tarifa` `te` on(`v`.`idtarifa_eco` = `te`.`idtarifa`)) join `tarifa` `tj` on(`v`.`idtarifa_eje` = `tj`.`idtarifa`)) join `tarifa` `tp` on(`v`.`idtarifa_pri` = `tp`.`idtarifa`)) WHERE `v`.`estado` not in ('Finalizado','Cancelado') ;

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `aerolinea`
--
ALTER TABLE `aerolinea`
  ADD PRIMARY KEY (`idaerolinea`);

--
-- Indices de la tabla `aeropuerto`
--
ALTER TABLE `aeropuerto`
  ADD PRIMARY KEY (`codigo_iata`),
  ADD KEY `fk_aeropuerto_pais` (`idpais`);

--
-- Indices de la tabla `asiento_vuelo`
--
ALTER TABLE `asiento_vuelo`
  ADD PRIMARY KEY (`idasiento_vuelo`),
  ADD UNIQUE KEY `uq_asiento` (`idvuelo`,`fila`,`letra`);

--
-- Indices de la tabla `avion`
--
ALTER TABLE `avion`
  ADD PRIMARY KEY (`idavion`),
  ADD KEY `fk_avion_aerolinea` (`idaerolinea`);

--
-- Indices de la tabla `checkin`
--
ALTER TABLE `checkin`
  ADD PRIMARY KEY (`idcheckin`),
  ADD UNIQUE KEY `uq_checkin` (`idreserva`,`idpasajero`),
  ADD KEY `fk_checkin_pasajero` (`idpasajero`);

--
-- Indices de la tabla `equipaje`
--
ALTER TABLE `equipaje`
  ADD PRIMARY KEY (`idequipaje`),
  ADD UNIQUE KEY `uq_equipaje_etiqueta` (`etiqueta_codigo`),
  ADD KEY `fk_equipaje_reserva` (`idreserva`),
  ADD KEY `fk_equipaje_pasajero` (`idpasajero`),
  ADD KEY `fk_equipaje_tipo` (`idtipo`);

--
-- Indices de la tabla `incidencia_equipaje`
--
ALTER TABLE `incidencia_equipaje`
  ADD PRIMARY KEY (`idincidencia`),
  ADD KEY `fk_incidencia_equipaje` (`idequipaje`),
  ADD KEY `fk_incidencia_reportado` (`reportado_por`);

--
-- Indices de la tabla `notificacion`
--
ALTER TABLE `notificacion`
  ADD PRIMARY KEY (`idnotif`),
  ADD KEY `fk_notif_pasajero` (`idpasajero`);

--
-- Indices de la tabla `pago`
--
ALTER TABLE `pago`
  ADD PRIMARY KEY (`idpago`),
  ADD KEY `fk_pago_reserva` (`idreserva`),
  ADD KEY `fk_pago_pasajero` (`idpasajero`);

--
-- Indices de la tabla `pais`
--
ALTER TABLE `pais`
  ADD PRIMARY KEY (`idpais`);

--
-- Indices de la tabla `pasajero`
--
ALTER TABLE `pasajero`
  ADD PRIMARY KEY (`idpasajero`),
  ADD UNIQUE KEY `uq_pasajero_mail` (`mail`),
  ADD KEY `fk_pasajero_pais` (`idpais`);

--
-- Indices de la tabla `promocion`
--
ALTER TABLE `promocion`
  ADD PRIMARY KEY (`idpromo`),
  ADD UNIQUE KEY `uq_promo_codigo` (`codigo`);

--
-- Indices de la tabla `reserva`
--
ALTER TABLE `reserva`
  ADD PRIMARY KEY (`idreserva`),
  ADD UNIQUE KEY `uq_reserva_codigo` (`codigo`),
  ADD KEY `fk_reserva_vuelo` (`idvuelo`),
  ADD KEY `fk_reserva_promo` (`idpromo`);

--
-- Indices de la tabla `reserva_pasajero`
--
ALTER TABLE `reserva_pasajero`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_asiento_reserva` (`idasiento_vuelo`),
  ADD KEY `fk_rp_reserva` (`idreserva`),
  ADD KEY `fk_rp_pasajero` (`idpasajero`),
  ADD KEY `fk_rp_tarifa` (`idtarifa`);

--
-- Indices de la tabla `tarifa`
--
ALTER TABLE `tarifa`
  ADD PRIMARY KEY (`idtarifa`);

--
-- Indices de la tabla `tipo_equipaje`
--
ALTER TABLE `tipo_equipaje`
  ADD PRIMARY KEY (`idtipo`);

--
-- Indices de la tabla `usuario_operador`
--
ALTER TABLE `usuario_operador`
  ADD PRIMARY KEY (`idusuario`),
  ADD UNIQUE KEY `uq_operador_mail` (`mail`);

--
-- Indices de la tabla `vuelo`
--
ALTER TABLE `vuelo`
  ADD PRIMARY KEY (`idvuelo`),
  ADD UNIQUE KEY `uq_vuelo_numero` (`numero_vuelo`),
  ADD KEY `fk_vuelo_avion` (`idavion`),
  ADD KEY `fk_vuelo_origen` (`origen`),
  ADD KEY `fk_vuelo_destino` (`destino`),
  ADD KEY `fk_vuelo_tarifa_eco` (`idtarifa_eco`),
  ADD KEY `fk_vuelo_tarifa_eje` (`idtarifa_eje`),
  ADD KEY `fk_vuelo_tarifa_pri` (`idtarifa_pri`),
  ADD KEY `fk_vuelo_creado_por` (`creado_por`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `aerolinea`
--
ALTER TABLE `aerolinea`
  MODIFY `idaerolinea` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de la tabla `asiento_vuelo`
--
ALTER TABLE `asiento_vuelo`
  MODIFY `idasiento_vuelo` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `checkin`
--
ALTER TABLE `checkin`
  MODIFY `idcheckin` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `equipaje`
--
ALTER TABLE `equipaje`
  MODIFY `idequipaje` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `incidencia_equipaje`
--
ALTER TABLE `incidencia_equipaje`
  MODIFY `idincidencia` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `notificacion`
--
ALTER TABLE `notificacion`
  MODIFY `idnotif` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `pago`
--
ALTER TABLE `pago`
  MODIFY `idpago` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `promocion`
--
ALTER TABLE `promocion`
  MODIFY `idpromo` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de la tabla `reserva`
--
ALTER TABLE `reserva`
  MODIFY `idreserva` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `reserva_pasajero`
--
ALTER TABLE `reserva_pasajero`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `tarifa`
--
ALTER TABLE `tarifa`
  MODIFY `idtarifa` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT de la tabla `tipo_equipaje`
--
ALTER TABLE `tipo_equipaje`
  MODIFY `idtipo` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT de la tabla `usuario_operador`
--
ALTER TABLE `usuario_operador`
  MODIFY `idusuario` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de la tabla `vuelo`
--
ALTER TABLE `vuelo`
  MODIFY `idvuelo` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `aeropuerto`
--
ALTER TABLE `aeropuerto`
  ADD CONSTRAINT `fk_aeropuerto_pais` FOREIGN KEY (`idpais`) REFERENCES `pais` (`idpais`);

--
-- Filtros para la tabla `asiento_vuelo`
--
ALTER TABLE `asiento_vuelo`
  ADD CONSTRAINT `fk_asiento_vuelo` FOREIGN KEY (`idvuelo`) REFERENCES `vuelo` (`idvuelo`);

--
-- Filtros para la tabla `avion`
--
ALTER TABLE `avion`
  ADD CONSTRAINT `fk_avion_aerolinea` FOREIGN KEY (`idaerolinea`) REFERENCES `aerolinea` (`idaerolinea`);

--
-- Filtros para la tabla `checkin`
--
ALTER TABLE `checkin`
  ADD CONSTRAINT `fk_checkin_pasajero` FOREIGN KEY (`idpasajero`) REFERENCES `pasajero` (`idpasajero`),
  ADD CONSTRAINT `fk_checkin_reserva` FOREIGN KEY (`idreserva`) REFERENCES `reserva` (`idreserva`);

--
-- Filtros para la tabla `equipaje`
--
ALTER TABLE `equipaje`
  ADD CONSTRAINT `fk_equipaje_pasajero` FOREIGN KEY (`idpasajero`) REFERENCES `pasajero` (`idpasajero`),
  ADD CONSTRAINT `fk_equipaje_reserva` FOREIGN KEY (`idreserva`) REFERENCES `reserva` (`idreserva`),
  ADD CONSTRAINT `fk_equipaje_tipo` FOREIGN KEY (`idtipo`) REFERENCES `tipo_equipaje` (`idtipo`);

--
-- Filtros para la tabla `incidencia_equipaje`
--
ALTER TABLE `incidencia_equipaje`
  ADD CONSTRAINT `fk_incidencia_equipaje` FOREIGN KEY (`idequipaje`) REFERENCES `equipaje` (`idequipaje`),
  ADD CONSTRAINT `fk_incidencia_reportado` FOREIGN KEY (`reportado_por`) REFERENCES `usuario_operador` (`idusuario`);

--
-- Filtros para la tabla `notificacion`
--
ALTER TABLE `notificacion`
  ADD CONSTRAINT `fk_notif_pasajero` FOREIGN KEY (`idpasajero`) REFERENCES `pasajero` (`idpasajero`);

--
-- Filtros para la tabla `pago`
--
ALTER TABLE `pago`
  ADD CONSTRAINT `fk_pago_pasajero` FOREIGN KEY (`idpasajero`) REFERENCES `pasajero` (`idpasajero`),
  ADD CONSTRAINT `fk_pago_reserva` FOREIGN KEY (`idreserva`) REFERENCES `reserva` (`idreserva`);

--
-- Filtros para la tabla `pasajero`
--
ALTER TABLE `pasajero`
  ADD CONSTRAINT `fk_pasajero_pais` FOREIGN KEY (`idpais`) REFERENCES `pais` (`idpais`);

--
-- Filtros para la tabla `reserva`
--
ALTER TABLE `reserva`
  ADD CONSTRAINT `fk_reserva_promo` FOREIGN KEY (`idpromo`) REFERENCES `promocion` (`idpromo`),
  ADD CONSTRAINT `fk_reserva_vuelo` FOREIGN KEY (`idvuelo`) REFERENCES `vuelo` (`idvuelo`);

--
-- Filtros para la tabla `reserva_pasajero`
--
ALTER TABLE `reserva_pasajero`
  ADD CONSTRAINT `fk_rp_asiento` FOREIGN KEY (`idasiento_vuelo`) REFERENCES `asiento_vuelo` (`idasiento_vuelo`),
  ADD CONSTRAINT `fk_rp_pasajero` FOREIGN KEY (`idpasajero`) REFERENCES `pasajero` (`idpasajero`),
  ADD CONSTRAINT `fk_rp_reserva` FOREIGN KEY (`idreserva`) REFERENCES `reserva` (`idreserva`),
  ADD CONSTRAINT `fk_rp_tarifa` FOREIGN KEY (`idtarifa`) REFERENCES `tarifa` (`idtarifa`);

--
-- Filtros para la tabla `vuelo`
--
ALTER TABLE `vuelo`
  ADD CONSTRAINT `fk_vuelo_avion` FOREIGN KEY (`idavion`) REFERENCES `avion` (`idavion`),
  ADD CONSTRAINT `fk_vuelo_creado_por` FOREIGN KEY (`creado_por`) REFERENCES `usuario_operador` (`idusuario`),
  ADD CONSTRAINT `fk_vuelo_destino` FOREIGN KEY (`destino`) REFERENCES `aeropuerto` (`codigo_iata`),
  ADD CONSTRAINT `fk_vuelo_origen` FOREIGN KEY (`origen`) REFERENCES `aeropuerto` (`codigo_iata`),
  ADD CONSTRAINT `fk_vuelo_tarifa_eco` FOREIGN KEY (`idtarifa_eco`) REFERENCES `tarifa` (`idtarifa`),
  ADD CONSTRAINT `fk_vuelo_tarifa_eje` FOREIGN KEY (`idtarifa_eje`) REFERENCES `tarifa` (`idtarifa`),
  ADD CONSTRAINT `fk_vuelo_tarifa_pri` FOREIGN KEY (`idtarifa_pri`) REFERENCES `tarifa` (`idtarifa`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
