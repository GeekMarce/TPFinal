<?php
declare(strict_types=1);

/**
 * restablecer_clave_demo.php — pone una contraseña NUEVA a las cuentas de demostración.
 *
 * Solo se usa desde la consola, nunca desde el navegador:
 *   php tools/restablecer_clave_demo.php NUEVA_CLAVE                -> todas las cuentas @lifeflow.test
 *   php tools/restablecer_clave_demo.php NUEVA_CLAVE alex@lifeflow.test  -> solo esas cuentas
 *
 * Por qué existe: en la base las contraseñas son hashes (bcrypt) y no se pueden leer.
 * Este script NO descubre la clave vieja: guarda un hash nuevo con password_hash().
 * Solo toca cuentas @lifeflow.test; nunca cuentas reales. Hay que ejecutarlo a propósito.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Este script solo se ejecuta desde la consola.\n");
}
$clave = $argv[1] ?? '';
if (strlen($clave) < 8 || strlen($clave) > 72) {
    exit("Uso: php tools/restablecer_clave_demo.php NUEVA_CLAVE [correo ...]\nLa clave debe tener entre 8 y 72 caracteres.\n");
}
$config = require dirname(__DIR__) . '/config.php';
$db = new PDO(sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $config['host'], $config['port'], $config['database']),
    $config['user'], $config['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

$correos = array_slice($argv, 2);
if (!$correos) {
    $correos = $db->query("SELECT email FROM usuarios WHERE email LIKE '%@lifeflow.test'")->fetchAll(PDO::FETCH_COLUMN);
}
$hash = password_hash($clave, PASSWORD_DEFAULT);
$stmt = $db->prepare("UPDATE usuarios SET password = ? WHERE email = ? AND email LIKE '%@lifeflow.test'");
foreach ($correos as $correo) {
    $stmt->execute([$hash, $correo]);
    echo ($stmt->rowCount() ? 'Actualizada: ' : 'Sin cambios (no existe o no es @lifeflow.test): ') . $correo . "\n";
}
echo "Listo. Ingresá con el correo y la clave nueva.\n";
