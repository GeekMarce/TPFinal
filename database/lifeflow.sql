-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: localhost
-- Tiempo de generación: 05-11-2026 a las 15:14:15
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
-- Base de datos: `lifeflow`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `actividades_rutina`
--

CREATE TABLE `actividades_rutina` (
  `id` int(10) UNSIGNED NOT NULL,
  `rutina_id` int(10) UNSIGNED NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `hora_inicio` time DEFAULT NULL,
  `hora_fin` time DEFAULT NULL,
  `color` varchar(7) NOT NULL
) ;

--
-- Volcado de datos para la tabla `actividades_rutina`
--

INSERT INTO `actividades_rutina` (`id`, `rutina_id`, `nombre`, `descripcion`, `hora_inicio`, `hora_fin`, `color`) VALUES
(1, 1, 'Desayunar', 'Preparar y tomar el desayuno', '07:00:00', '07:30:00', '#4A90E2'),
(2, 2, 'Estudiar matemática', 'Repasar contenidos de clase', '16:00:00', '17:00:00', '#7B61A8'),
(3, 3, 'Revisar pendientes', 'Organizar tareas laborales', '09:00:00', '09:30:00', '#E58A54'),
(4, 4, 'Salir a correr', 'Actividad física al aire libre', '08:00:00', '09:00:00', '#36A88A'),
(5, 5, 'Leer', 'Lectura antes de dormir', '22:00:00', '23:00:00', '#D66B91');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `categorias`
--

CREATE TABLE `categorias` (
  `id` int(10) UNSIGNED NOT NULL,
  `usuario_id` int(10) UNSIGNED NOT NULL,
  `categoria_predeterminada_id` int(10) UNSIGNED DEFAULT NULL,
  `nombre` varchar(60) NOT NULL,
  `color` varchar(7) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `categorias`
--

INSERT INTO `categorias` (`id`, `usuario_id`, `categoria_predeterminada_id`, `nombre`, `color`) VALUES
(1, 2, 1, 'Estudio', '#4A90E2'),
(2, 3, 1, 'Estudio', '#4A90E2'),
(3, 4, 1, 'Estudio', '#4A90E2'),
(4, 5, 1, 'Estudio', '#4A90E2'),
(5, 1, 1, 'Estudio', '#4A90E2'),
(6, 2, 2, 'Trabajo', '#7B61A8'),
(7, 3, 2, 'Trabajo', '#7B61A8'),
(8, 4, 2, 'Trabajo', '#7B61A8'),
(9, 5, 2, 'Trabajo', '#7B61A8'),
(10, 1, 2, 'Trabajo', '#7B61A8'),
(11, 2, 3, 'Personal', '#36A88A'),
(12, 3, 3, 'Personal', '#36A88A'),
(13, 4, 3, 'Personal', '#36A88A'),
(14, 5, 3, 'Personal', '#36A88A'),
(15, 1, 3, 'Personal', '#36A88A'),
(16, 2, 4, 'Salud', '#E58A54'),
(17, 3, 4, 'Salud', '#E58A54'),
(18, 4, 4, 'Salud', '#E58A54'),
(19, 5, 4, 'Salud', '#E58A54'),
(20, 1, 4, 'Salud', '#E58A54'),
(21, 2, 5, 'Ocio', '#D66B91'),
(22, 3, 5, 'Ocio', '#D66B91'),
(23, 4, 5, 'Ocio', '#D66B91'),
(24, 5, 5, 'Ocio', '#D66B91'),
(25, 1, 5, 'Ocio', '#D66B91');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `categorias_predeterminadas`
--

CREATE TABLE `categorias_predeterminadas` (
  `id` int(10) UNSIGNED NOT NULL,
  `nombre` varchar(60) NOT NULL,
  `color` varchar(7) NOT NULL COMMENT 'Formato hexadecimal, ej: #FF8800'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `categorias_predeterminadas`
--

INSERT INTO `categorias_predeterminadas` (`id`, `nombre`, `color`) VALUES
(1, 'Estudio', '#4A90E2'),
(2, 'Trabajo', '#7B61A8'),
(3, 'Personal', '#36A88A'),
(4, 'Salud', '#E58A54'),
(5, 'Ocio', '#D66B91');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `dias_actividad_rutina`
--

CREATE TABLE `dias_actividad_rutina` (
  `id` int(10) UNSIGNED NOT NULL,
  `actividad_rutina_id` int(10) UNSIGNED NOT NULL,
  `dia_semana` tinyint(3) UNSIGNED NOT NULL
) ;

--
-- Volcado de datos para la tabla `dias_actividad_rutina`
--

INSERT INTO `dias_actividad_rutina` (`id`, `actividad_rutina_id`, `dia_semana`) VALUES
(1, 1, 1),
(2, 2, 2),
(3, 3, 4),
(4, 4, 3),
(5, 5, 5);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `dias_habito`
--

CREATE TABLE `dias_habito` (
  `id` int(10) UNSIGNED NOT NULL,
  `habito_id` int(10) UNSIGNED NOT NULL,
  `dia_semana` tinyint(3) UNSIGNED NOT NULL
) ;

--
-- Volcado de datos para la tabla `dias_habito`
--

INSERT INTO `dias_habito` (`id`, `habito_id`, `dia_semana`) VALUES
(8, 1, 1),
(7, 1, 2),
(6, 1, 3),
(5, 1, 4),
(4, 1, 5),
(11, 2, 2),
(10, 2, 4),
(9, 2, 6),
(16, 3, 1),
(15, 3, 2),
(14, 3, 3),
(13, 3, 4),
(12, 3, 5),
(18, 4, 6),
(17, 4, 7),
(3, 5, 1),
(2, 5, 3),
(1, 5, 5);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `habitos`
--

CREATE TABLE `habitos` (
  `id` int(10) UNSIGNED NOT NULL,
  `usuario_id` int(10) UNSIGNED NOT NULL,
  `categoria_id` int(10) UNSIGNED DEFAULT NULL,
  `nombre` varchar(100) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `color` varchar(7) NOT NULL,
  `fecha_creacion` datetime NOT NULL DEFAULT current_timestamp(),
  `eliminado` tinyint(1) NOT NULL DEFAULT 0
) ;

--
-- Volcado de datos para la tabla `habitos`
--

INSERT INTO `habitos` (`id`, `usuario_id`, `categoria_id`, `nombre`, `descripcion`, `color`, `fecha_creacion`, `eliminado`) VALUES
(1, 2, 11, 'Estudiar diariamente', 'Repasar contenidos de clase', '#7B61A8', '2026-11-05 11:09:56', 0),
(2, 3, 12, 'Hacer ejercicio', 'Realizar actividad física', '#36A88A', '2026-11-05 11:09:56', 0),
(3, 4, 13, 'Beber agua', 'Mantener una buena hidratación', '#4A90E2', '2026-11-05 11:09:56', 0),
(4, 5, 14, 'Dormir temprano', 'Mantener un horario de descanso', '#D66B91', '2026-11-05 11:09:56', 0),
(5, 1, 15, 'Leer 20 minutos', 'Dedicar un momento diario a la lectura', '#4A90E2', '2026-11-05 11:09:56', 0);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `justificaciones_dia`
--

CREATE TABLE `justificaciones_dia` (
  `id` int(10) UNSIGNED NOT NULL,
  `usuario_id` int(10) UNSIGNED NOT NULL,
  `fecha` date NOT NULL,
  `justificacion` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `justificaciones_dia`
--

INSERT INTO `justificaciones_dia` (`id`, `usuario_id`, `fecha`, `justificacion`) VALUES
(1, 1, '2026-11-02', 'Día de descanso planificado'),
(2, 2, '2026-11-03', 'Día dedicado a actividades familiares'),
(3, 4, '2026-11-03', 'Día de trámites personales'),
(4, 3, '2026-11-05', 'Jornada de estudio intensivo'),
(5, 5, '2026-10-25', 'Día de descanso');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `registros_habitos`
--

CREATE TABLE `registros_habitos` (
  `id` int(10) UNSIGNED NOT NULL,
  `habito_id` int(10) UNSIGNED NOT NULL,
  `fecha` date NOT NULL,
  `estado` enum('pendiente','completada','cancelada','postergada_con_justificacion','postergada_sin_justificacion') NOT NULL DEFAULT 'pendiente',
  `justificacion` text DEFAULT NULL
) ;

--
-- Volcado de datos para la tabla `registros_habitos`
--

INSERT INTO `registros_habitos` (`id`, `habito_id`, `fecha`, `estado`, `justificacion`) VALUES
(1, 5, '2026-11-04', 'completada', NULL),
(2, 1, '2026-11-05', 'pendiente', NULL),
(3, 3, '2026-11-04', 'postergada_con_justificacion', 'Tuvo un imprevisto'),
(4, 2, '2026-11-03', 'cancelada', NULL),
(5, 4, '2026-11-01', 'postergada_sin_justificacion', NULL);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `roles`
--

CREATE TABLE `roles` (
  `id` tinyint(3) UNSIGNED NOT NULL,
  `nombre` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `roles`
--

INSERT INTO `roles` (`id`, `nombre`) VALUES
(2, 'Administrador'),
(1, 'Usuario');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `rutinas`
--

CREATE TABLE `rutinas` (
  `id` int(10) UNSIGNED NOT NULL,
  `usuario_id` int(10) UNSIGNED NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `fecha_creacion` datetime NOT NULL DEFAULT current_timestamp(),
  `eliminado` tinyint(1) NOT NULL DEFAULT 0
) ;

--
-- Volcado de datos para la tabla `rutinas`
--

INSERT INTO `rutinas` (`id`, `usuario_id`, `nombre`, `descripcion`, `fecha_creacion`, `eliminado`) VALUES
(1, 1, 'Rutina de mañana', 'Organización del comienzo del día', '2026-11-05 11:11:43', 0),
(2, 2, 'Rutina de estudio', 'Organización de las horas de estudio', '2026-11-05 11:11:43', 0),
(3, 4, 'Rutina laboral', 'Organización de la jornada laboral', '2026-11-05 11:11:43', 0),
(4, 3, 'Rutina de ejercicio', 'Actividades para mantenerse activa', '2026-11-05 11:11:43', 0),
(5, 5, 'Rutina nocturna', 'Preparación para el descanso', '2026-11-05 11:11:43', 0);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `seguidores`
--

CREATE TABLE `seguidores` (
  `id` int(10) UNSIGNED NOT NULL,
  `solicitante_id` int(10) UNSIGNED NOT NULL,
  `seguido_id` int(10) UNSIGNED NOT NULL,
  `estado` enum('pendiente','aceptada','rechazada') NOT NULL DEFAULT 'pendiente',
  `fecha` datetime NOT NULL DEFAULT current_timestamp()
) ;

--
-- Volcado de datos para la tabla `seguidores`
--

INSERT INTO `seguidores` (`id`, `solicitante_id`, `seguido_id`, `estado`, `fecha`) VALUES
(1, 2, 3, 'pendiente', '2026-11-05 11:12:08'),
(2, 3, 2, 'aceptada', '2026-11-05 11:12:08'),
(3, 4, 2, 'aceptada', '2026-11-05 11:12:08'),
(4, 5, 3, 'pendiente', '2026-11-05 11:12:08'),
(5, 2, 4, 'aceptada', '2026-11-05 11:12:08');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `tareas`
--

CREATE TABLE `tareas` (
  `id` int(10) UNSIGNED NOT NULL,
  `usuario_id` int(10) UNSIGNED NOT NULL,
  `categoria_id` int(10) UNSIGNED DEFAULT NULL,
  `titulo` varchar(150) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `fecha_limite` datetime DEFAULT NULL,
  `prioridad` enum('baja','media','alta') NOT NULL DEFAULT 'media',
  `estado` enum('pendiente','completada','cancelada','postergada') NOT NULL DEFAULT 'pendiente',
  `eliminado` tinyint(1) NOT NULL DEFAULT 0,
  `fecha_creacion` datetime NOT NULL DEFAULT current_timestamp()
) ;

--
-- Volcado de datos para la tabla `tareas`
--

INSERT INTO `tareas` (`id`, `usuario_id`, `categoria_id`, `titulo`, `descripcion`, `fecha_limite`, `prioridad`, `estado`, `eliminado`, `fecha_creacion`) VALUES
(1, 2, 1, 'Leer un capítulo', 'Avanzar con la lectura', '2026-11-10 20:00:00', 'baja', 'pendiente', 0, '2026-11-05 11:09:37'),
(2, 2, 1, 'Ordenar apuntes', 'Organizar los apuntes de clase', '2026-11-07 12:00:00', 'media', 'completada', 0, '2026-11-05 11:09:37'),
(3, 2, 1, 'Preparar examen de matemática', 'Repasar los temas de la unidad 3', '2026-11-08 18:00:00', 'alta', 'pendiente', 0, '2026-11-05 11:09:37'),
(4, 3, 7, 'Organizar escritorio', 'Ordenar el espacio de trabajo', '2026-11-12 10:00:00', 'baja', 'pendiente', 0, '2026-11-05 11:09:37'),
(5, 3, 7, 'Comprar materiales', 'Comprar útiles para el proyecto', '2026-11-11 17:00:00', 'media', 'completada', 0, '2026-11-05 11:09:37'),
(6, 3, 7, 'Entregar informe', 'Terminar el informe semanal', '2026-11-09 15:00:00', 'alta', 'pendiente', 0, '2026-11-05 11:09:37'),
(7, 4, 8, 'Responder correos', 'Contestar mensajes pendientes', '2026-11-06 12:00:00', 'media', 'completada', 0, '2026-11-05 11:09:37'),
(8, 4, 8, 'Actualizar currículum', 'Revisar experiencia y habilidades', '2026-11-13 18:00:00', 'alta', 'pendiente', 0, '2026-11-05 11:09:37'),
(9, 5, 14, 'Limpiar habitación', 'Ordenar y limpiar el dormitorio', '2026-11-10 11:00:00', 'baja', 'cancelada', 0, '2026-11-05 11:09:37'),
(10, 5, 14, 'Planificar la semana', 'Organizar actividades y horarios', '2026-11-09 09:00:00', 'media', 'pendiente', 0, '2026-11-05 11:09:37');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuarios`
--

CREATE TABLE `usuarios` (
  `id` int(10) UNSIGNED NOT NULL,
  `alias` varchar(50) NOT NULL,
  `email` varchar(190) NOT NULL,
  `password` varchar(255) NOT NULL COMMENT 'Hash generado desde PHP',
  `rol_id` tinyint(3) UNSIGNED NOT NULL,
  `foto` varchar(255) DEFAULT NULL,
  `privacidad` enum('publico','privado') NOT NULL DEFAULT 'publico',
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `fecha_registro` datetime NOT NULL DEFAULT current_timestamp()
) ;

--
-- Volcado de datos para la tabla `usuarios`
--

INSERT INTO `usuarios` (`id`, `alias`, `email`, `password`, `rol_id`, `foto`, `privacidad`, `activo`, `fecha_registro`) VALUES
(1, 'Admin Demo', 'admin@lifeflow.test', '$2y$10$jhQBIQIWGgOx6XQ0ANnK.e7FS9nl8fb6OFc0LChedzop0W/O1KZ6a', 2, NULL, 'publico', 1, '2026-11-05 11:06:10'),
(2, 'Alex Demo', 'alex@lifeflow.test', '$2y$10$jhQBIQIWGgOx6XQ0ANnK.e7FS9nl8fb6OFc0LChedzop0W/O1KZ6a', 1, NULL, 'publico', 1, '2026-11-05 11:06:10'),
(3, 'Sofia Demo', 'sofia@lifeflow.test', '$2y$10$jhQBIQIWGgOx6XQ0ANnK.e7FS9nl8fb6OFc0LChedzop0W/O1KZ6a', 1, NULL, 'privado', 1, '2026-11-05 11:06:10'),
(4, 'Lucas Demo', 'lucas@lifeflow.test', '$2y$10$jhQBIQIWGgOx6XQ0ANnK.e7FS9nl8fb6OFc0LChedzop0W/O1KZ6a', 1, NULL, 'publico', 1, '2026-11-05 11:06:10'),
(5, 'Valentina Demo', 'valentina@lifeflow.test', '$2y$10$jhQBIQIWGgOx6XQ0ANnK.e7FS9nl8fb6OFc0LChedzop0W/O1KZ6a', 1, NULL, 'privado', 1, '2026-11-05 11:06:10');

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `actividades_rutina`
--
ALTER TABLE `actividades_rutina`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_actividades_rutina` (`rutina_id`);

--
-- Indices de la tabla `categorias`
--
ALTER TABLE `categorias`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_categorias_usuario_nombre` (`usuario_id`,`nombre`),
  ADD UNIQUE KEY `uq_categorias_id_usuario` (`id`,`usuario_id`),
  ADD KEY `idx_categorias_predeterminada` (`categoria_predeterminada_id`);

--
-- Indices de la tabla `categorias_predeterminadas`
--
ALTER TABLE `categorias_predeterminadas`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_catpred_nombre` (`nombre`);

--
-- Indices de la tabla `dias_actividad_rutina`
--
ALTER TABLE `dias_actividad_rutina`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_dias_actividad_rutina` (`actividad_rutina_id`,`dia_semana`);

--
-- Indices de la tabla `dias_habito`
--
ALTER TABLE `dias_habito`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_dias_habito` (`habito_id`,`dia_semana`);

--
-- Indices de la tabla `habitos`
--
ALTER TABLE `habitos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_habitos_usuario` (`usuario_id`,`eliminado`),
  ADD KEY `idx_habitos_categoria` (`categoria_id`);

--
-- Indices de la tabla `justificaciones_dia`
--
ALTER TABLE `justificaciones_dia`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_justificaciones_usuario_fecha` (`usuario_id`,`fecha`);

--
-- Indices de la tabla `registros_habitos`
--
ALTER TABLE `registros_habitos`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_registros_habito_fecha` (`habito_id`,`fecha`),
  ADD KEY `idx_registros_fecha` (`fecha`),
  ADD KEY `idx_registros_estado` (`estado`);

--
-- Indices de la tabla `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_roles_nombre` (`nombre`);

--
-- Indices de la tabla `rutinas`
--
ALTER TABLE `rutinas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_rutinas_usuario` (`usuario_id`,`eliminado`);

--
-- Indices de la tabla `seguidores`
--
ALTER TABLE `seguidores`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_seguidores_par` (`solicitante_id`,`seguido_id`),
  ADD KEY `idx_seguidores_seguido` (`seguido_id`,`estado`);

--
-- Indices de la tabla `tareas`
--
ALTER TABLE `tareas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_tareas_usuario_estado` (`usuario_id`,`eliminado`,`estado`),
  ADD KEY `idx_tareas_fecha_limite` (`usuario_id`,`fecha_limite`),
  ADD KEY `idx_tareas_categoria` (`categoria_id`);

--
-- Indices de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_usuarios_email` (`email`),
  ADD KEY `idx_usuarios_rol` (`rol_id`),
  ADD KEY `idx_usuarios_alias` (`alias`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `actividades_rutina`
--
ALTER TABLE `actividades_rutina`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `categorias`
--
ALTER TABLE `categorias`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=32;

--
-- AUTO_INCREMENT de la tabla `categorias_predeterminadas`
--
ALTER TABLE `categorias_predeterminadas`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT de la tabla `dias_actividad_rutina`
--
ALTER TABLE `dias_actividad_rutina`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `dias_habito`
--
ALTER TABLE `dias_habito`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `habitos`
--
ALTER TABLE `habitos`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `justificaciones_dia`
--
ALTER TABLE `justificaciones_dia`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT de la tabla `registros_habitos`
--
ALTER TABLE `registros_habitos`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `roles`
--
ALTER TABLE `roles`
  MODIFY `id` tinyint(3) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de la tabla `rutinas`
--
ALTER TABLE `rutinas`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `seguidores`
--
ALTER TABLE `seguidores`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `tareas`
--
ALTER TABLE `tareas`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `actividades_rutina`
--
ALTER TABLE `actividades_rutina`
  ADD CONSTRAINT `fk_actividades_rutina` FOREIGN KEY (`rutina_id`) REFERENCES `rutinas` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `categorias`
--
ALTER TABLE `categorias`
  ADD CONSTRAINT `fk_categorias_predeterminada` FOREIGN KEY (`categoria_predeterminada_id`) REFERENCES `categorias_predeterminadas` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_categorias_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON UPDATE CASCADE;

--
-- Filtros para la tabla `dias_actividad_rutina`
--
ALTER TABLE `dias_actividad_rutina`
  ADD CONSTRAINT `fk_dias_actividad_actividad` FOREIGN KEY (`actividad_rutina_id`) REFERENCES `actividades_rutina` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `dias_habito`
--
ALTER TABLE `dias_habito`
  ADD CONSTRAINT `fk_dias_habito_habito` FOREIGN KEY (`habito_id`) REFERENCES `habitos` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `habitos`
--
ALTER TABLE `habitos`
  ADD CONSTRAINT `fk_habitos_categoria` FOREIGN KEY (`categoria_id`) REFERENCES `categorias` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_habitos_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON UPDATE CASCADE;

--
-- Filtros para la tabla `justificaciones_dia`
--
ALTER TABLE `justificaciones_dia`
  ADD CONSTRAINT `fk_justificaciones_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON UPDATE CASCADE;

--
-- Filtros para la tabla `registros_habitos`
--
ALTER TABLE `registros_habitos`
  ADD CONSTRAINT `fk_registros_habito` FOREIGN KEY (`habito_id`) REFERENCES `habitos` (`id`) ON UPDATE CASCADE;

--
-- Filtros para la tabla `rutinas`
--
ALTER TABLE `rutinas`
  ADD CONSTRAINT `fk_rutinas_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON UPDATE CASCADE;

--
-- Filtros para la tabla `seguidores`
--
ALTER TABLE `seguidores`
  ADD CONSTRAINT `fk_seguidores_seguido` FOREIGN KEY (`seguido_id`) REFERENCES `usuarios` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_seguidores_solicitante` FOREIGN KEY (`solicitante_id`) REFERENCES `usuarios` (`id`) ON UPDATE CASCADE;

--
-- Filtros para la tabla `tareas`
--
ALTER TABLE `tareas`
  ADD CONSTRAINT `fk_tareas_categoria` FOREIGN KEY (`categoria_id`) REFERENCES `categorias` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_tareas_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON UPDATE CASCADE;

--
-- Filtros para la tabla `usuarios`
--
ALTER TABLE `usuarios`
  ADD CONSTRAINT `fk_usuarios_rol` FOREIGN KEY (`rol_id`) REFERENCES `roles` (`id`) ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
