<?php
declare(strict_types=1);

// Datos de conexión y entorno. Ajustar según tu instalación.
// Este archivo NO debe subirse a GitHub con claves reales (ver .gitignore).
// El entorno se detecta solo: localhost = 'local', cualquier otro dominio = 'produccion'.
$servidor = $_SERVER['SERVER_NAME'] ?? 'localhost';
$esLocal = PHP_SAPI === 'cli' || in_array($servidor, ['localhost', '127.0.0.1', '::1'], true);

return [
    'entorno' => $esLocal ? 'local' : 'produccion',
    'debug' => $esLocal, // solo en local: la API agrega el detalle técnico de los errores
    'timezone' => getenv('LF_TIMEZONE') ?: 'America/Argentina/Cordoba',
    'host' => getenv('DB_HOST') ?: '127.0.0.1',
    'port' => getenv('DB_PORT') ?: '3306',
    'database' => getenv('DB_NAME') ?: 'lifeflow',
    'user' => getenv('DB_USER') ?: 'root',
    'password' => getenv('DB_PASSWORD') !== false ? getenv('DB_PASSWORD') : '',
];
