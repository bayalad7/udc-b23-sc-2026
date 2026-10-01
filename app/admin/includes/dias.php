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

// Fecha de cada día, en el formato de la convención del proyecto (ver
// CLAUDE.md). Son fijas y no viven en la base: el esquema guarda la hora de
// cada actividad, no el calendario del evento. Las usa lo que se imprime y se
// pega en una pared, donde "Día Académico" por sí solo no dice cuándo.
const DIAS_EVENTO_FECHA = [
    'academico' => 'Jueves 1 de Octubre',
    'cultural' => 'Viernes 2 de Octubre',
    'deportivo' => 'Sábado 3 de Octubre',
];

/** Fecha del día del evento; cadena vacía si no la conoce, para no inventarla. */
function diaEventoFecha(string $dia): string
{
    return DIAS_EVENTO_FECHA[$dia] ?? '';
}
