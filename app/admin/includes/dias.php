<?php
declare(strict_types=1);

// Etiquetas de los 3 días del evento, en un solo lugar: las usan tanto las
// pantallas del panel como los reportes que se descargan, y dos listas
// distintas acabarían diciendo "Día Académico" en una y "academico" en la otra.
// El ENUM de `dia` vive en app/database/schema.sql (eventos solo admite
// academico/cultural; competiciones y asistencias_generales, los tres).

const DIAS_EVENTO_LABEL = [
    'academico' => 'Día Académico',
    'cultural' => 'Día Cultural',
    'deportivo' => 'Día Deportivo',
];

/** Etiqueta larga del día del evento; devuelve la clave tal cual si no la conoce. */
function diaEventoLabel(string $dia): string
{
    return DIAS_EVENTO_LABEL[$dia] ?? $dia;
}
