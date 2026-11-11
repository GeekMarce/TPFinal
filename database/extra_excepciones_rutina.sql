-- OPCIONAL — Excepciones de rutina por fecha.
-- Tu base original no tiene dónde guardar "este martes cambia, los demás no".
-- Importá este archivo en phpMyAdmin (base lifeflow) para activar esa función.
-- No modifica ninguna tabla existente. Si no lo importás, la página funciona igual
-- y solo avisa que las excepciones no están activadas.

CREATE TABLE IF NOT EXISTS `excepciones_actividad_rutina` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `actividad_rutina_id` int(10) UNSIGNED NOT NULL,
  `fecha` date NOT NULL,
  `cancelada` tinyint(1) NOT NULL DEFAULT 0,
  `hora_inicio` time DEFAULT NULL,
  `hora_fin` time DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_excepcion_actividad_fecha` (`actividad_rutina_id`,`fecha`),
  CONSTRAINT `fk_excepcion_actividad` FOREIGN KEY (`actividad_rutina_id`)
    REFERENCES `actividades_rutina` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
