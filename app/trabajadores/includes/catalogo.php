<?php
declare(strict_types=1);

// Valores válidos del registro de personal — un solo lugar para que el
// formulario público (app/trabajadores), el guardado y el CRUD del panel
// (app/admin/public/trabajador.php) no se desincronicen entre sí ni con el
// ENUM de la tabla `trabajadores` en database/schema.sql.
//
// Incluir SIEMPRE con require_once: son constantes de archivo y volver a
// incluirlo sería un error fatal de redeclaración.

/** Valores del ENUM trabajadores.tipo. */
const TRABAJADOR_TIPOS = ['Administrativo', 'Docente'];

/**
 * Cortes de camisa que se ofrecen hoy. El ENUM de la tabla admite
 * Hombre/Mujer/Unisex (igual que alumnos.camisa_corte), pero solo se manda
 * hacer el corte Unisex — para ofrecer los otros basta agregarlos aquí.
 */
const TRABAJADOR_CAMISA_CORTES = ['Unisex'];

/** Tallas del ENUM trabajadores.camisa_talla, con su etiqueta para la gente. */
const TRABAJADOR_CAMISA_TALLAS = [
    'XS' => 'Extra Chica',
    'S' => 'Chica',
    'M' => 'Mediana',
    'L' => 'Grande',
    'XL' => 'Extra Grande',
    '2XL' => 'Doble Extra Grande',
];
