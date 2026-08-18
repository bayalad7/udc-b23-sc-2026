<?php

require_once __DIR__ . '/rutas.php';

// Conexión PDO compartida a MariaDB. No contiene credenciales reales:
// - En Docker (desarrollo local), las lee de variables de entorno (ver docker-compose.yml).
// - Fuera de Docker (ej. VPS), las toma de db-credenciales.php (no se sube al repositorio — copiar desde db-credenciales.example.php).

if (getenv('DB_HOST') === false) {
    $credenciales = __DIR__ . '/db-credenciales.php';
    if (!file_exists($credenciales)) {
        throw new RuntimeException(
            'Faltan las credenciales de base de datos: define variables de entorno ' .
            'DB_HOST/DB_NAME/DB_USER/DB_PASSWORD o crea config/db-credenciales.php ' .
            '(ver config/db-credenciales.example.php).'
        );
    }
    require $credenciales;
} else {
    define('DB_HOST', getenv('DB_HOST'));
    define('DB_PORT', getenv('DB_PORT') ?: '3306');
    define('DB_NAME', getenv('DB_NAME'));
    define('DB_USER', getenv('DB_USER'));
    define('DB_PASSWORD', getenv('DB_PASSWORD'));
}

$dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', DB_HOST, DB_PORT, DB_NAME);

$pdo = new PDO($dsn, DB_USER, DB_PASSWORD, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
]);

return $pdo;
