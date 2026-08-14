<?php
declare(strict_types=1);

// Catálogo cerrado de colores de camisa para equipos de torneos deportivos
// (ver UNIQUE uq_equipos_color en schema.sql) — un color no se repite dentro
// del mismo torneo. Placeholder acordado mientras no exista un catálogo
// institucional distinto (ver pendiente en torneos-deportivos.md).
// Compartido entre public/deportivo.php (selección) e
// includes/crear-equipo-deportivo.php (validación) para que ambos lados
// vean siempre la misma lista.
const COLORES_CAMISA = [ 'Blanco', 'Rojo', 'Azul Marino', 'Azul Rey', 'Amarillo','Verde', 'Negro', 'Gris', 'Naranja', 'Celeste', 'Tinto', 'Rosa', 'Morado', 'Verde Lima', 'Beige', 'Cafe'];
