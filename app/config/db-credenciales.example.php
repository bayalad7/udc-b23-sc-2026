<?php

// Copia este archivo a db-credenciales.php (no se sube al repositorio) para
// despliegues fuera de Docker (ej. el VPS de producción), donde no se usan
// variables de entorno. En desarrollo local con Docker no hace falta: las
// credenciales llegan por variables de entorno definidas en docker-compose.yml.

define('DB_HOST', 'localhost');
define('DB_PORT', '3306');
define('DB_NAME', 'b23_semana_cultural');
define('DB_USER', 'b23_app');
define('DB_PASSWORD', 'cambia_esta_clave');
