<?php
declare(strict_types=1);

/**
 * plantillas.php — plantillas iniciales de rutina (BORRADOR: el contenido hay que revisarlo).
 *
 * Una plantilla no es una tabla: es una lista que se COPIA como rutina propia del usuario
 * al elegirla; después cada persona la modifica a su gusto. Para cambiar, agregar o quitar
 * plantillas solo hay que editar este archivo.
 * Días: 1 = lunes ... 7 = domingo. Horas en formato HH:MM (null = actividad sin hora).
 */
return [
    'manana' => [
        'nombre' => 'Rutina de mañana',
        'descripcion' => 'Organización del comienzo del día',
        'actividades' => [
            ['nombre' => 'Levantarse y ordenar', 'inicio' => '07:00', 'fin' => '07:15', 'color' => '#1F5C3F', 'dias' => [1, 2, 3, 4, 5]],
            ['nombre' => 'Desayunar', 'inicio' => '07:15', 'fin' => '07:45', 'color' => '#1F5C3F', 'dias' => [1, 2, 3, 4, 5]],
            ['nombre' => 'Repasar el plan del día', 'inicio' => '07:45', 'fin' => '08:00', 'color' => '#1F5C3F', 'dias' => [1, 2, 3, 4, 5]],
        ],
    ],
    'estudio' => [
        'nombre' => 'Rutina de estudio',
        'descripcion' => 'Organización de las horas de estudio',
        'actividades' => [
            ['nombre' => 'Repasar apuntes', 'inicio' => '16:00', 'fin' => '17:00', 'color' => '#1F5C3F', 'dias' => [1, 2, 3, 4, 5]],
            ['nombre' => 'Hacer ejercicios', 'inicio' => '17:00', 'fin' => '18:00', 'color' => '#1F5C3F', 'dias' => [1, 2, 3, 4, 5]],
        ],
    ],
    'dormir' => [
        'nombre' => 'Preparación para dormir',
        'descripcion' => 'Cierre tranquilo del día',
        'actividades' => [
            ['nombre' => 'Dejar las pantallas', 'inicio' => '22:00', 'fin' => '22:30', 'color' => '#1F5C3F', 'dias' => [1, 2, 3, 4, 5, 6, 7]],
            ['nombre' => 'Leer', 'inicio' => '22:30', 'fin' => '23:00', 'color' => '#1F5C3F', 'dias' => [1, 2, 3, 4, 5, 6, 7]],
        ],
    ],
];
