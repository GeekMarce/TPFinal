<?php
declare(strict_types=1);

/**
 * bootstrap.php — arranque común de la API.
 *
 * Qué hace: carga config.php, abre la sesión con cookie segura, crea la conexión PDO y
 * ofrece funciones de ayuda (respuestas JSON, consultas preparadas y validaciones).
 * Parámetros: ninguno. Permisos: no decide permisos; eso lo hace public/api.php.
 * Importante: TODA consulta usa sentencias preparadas (prepare + execute con parámetros).
 */

$config = require dirname(__DIR__) . '/config.php';
date_default_timezone_set($config['timezone']);
ini_set('display_errors', '0');
ini_set('session.use_strict_mode', '1');
session_name('lifeflow_session');
session_set_cookie_params([
    'httponly' => true,
    'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'samesite' => 'Strict',
    'path' => '/',
]);
session_start();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

/** Envía una respuesta JSON y termina. */
function respond(array $data, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

/** Error controlado para el usuario (validación, permisos, no encontrado). */
function fail(string $message, int $status = 422): void
{
    respond(['error' => $message], $status);
}

/** Conexión PDO única. Se crea la primera vez que se usa. */
function db(): PDO
{
    global $config;
    static $pdo = null;
    if ($pdo === null) {
        $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
            $config['host'], $config['port'], $config['database']);
        $pdo = new PDO($dsn, $config['user'], $config['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    }
    return $pdo;
}

/** Ejecuta una consulta preparada y devuelve el statement. */
function q(string $sql, array $params = []): PDOStatement
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt;
}
function filas(string $sql, array $params = []): array
{
    return q($sql, $params)->fetchAll();
}
function fila(string $sql, array $params = []): ?array
{
    $r = q($sql, $params)->fetch();
    return $r === false ? null : $r;
}
function valor(string $sql, array $params = [])
{
    $r = q($sql, $params)->fetchColumn();
    return $r === false ? null : $r;
}

function hoy(): string
{
    return date('Y-m-d');
}

/* ---------- Validaciones (se repiten en el navegador, pero acá son las que valen) ---------- */

/** Cantidad de caracteres (no bytes); funciona aunque la extensión mbstring no esté activa. */
function largo(string $v): int
{
    return function_exists('mb_strlen') ? mb_strlen($v) : (int)preg_match_all('/./us', $v);
}

function texto(array $in, string $clave, string $etiqueta, int $min, int $max): string
{
    $v = $in[$clave] ?? null;
    if (!is_string($v)) {
        fail("$etiqueta no es válido.");
    }
    $v = trim($v);
    $n = largo($v);
    if ($n < $min) {
        fail($min === 1 ? "$etiqueta es obligatorio." : "$etiqueta debe tener al menos $min caracteres.");
    }
    if ($n > $max) {
        fail("$etiqueta admite hasta $max caracteres.");
    }
    return $v;
}

function textoOpcional(array $in, string $clave, string $etiqueta, int $max): ?string
{
    $v = $in[$clave] ?? null;
    if ($v === null || (is_string($v) && trim($v) === '')) {
        return null;
    }
    return texto($in, $clave, $etiqueta, 1, $max);
}

function enumerado(array $in, string $clave, string $etiqueta, array $permitidos): string
{
    $v = $in[$clave] ?? null;
    if (!is_string($v) || !in_array($v, $permitidos, true)) {
        fail("$etiqueta no es válido.");
    }
    return $v;
}

function entero(array $in, string $clave, string $etiqueta): int
{
    $v = $in[$clave] ?? null;
    if (is_string($v) && ctype_digit($v)) {
        $v = (int)$v;
    }
    if (!is_int($v) || $v < 1) {
        fail("$etiqueta no es válido.");
    }
    return $v;
}

function enteroOpcional(array $in, string $clave, string $etiqueta): ?int
{
    $v = $in[$clave] ?? null;
    return ($v === null || $v === '') ? null : entero($in, $clave, $etiqueta);
}

function fechaValida($v, string $etiqueta = 'La fecha'): string
{
    if (!is_string($v) || !preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $v, $m) || !checkdate((int)$m[2], (int)$m[3], (int)$m[1])) {
        fail("$etiqueta no es válida.");
    }
    return $v;
}

function colorValido(array $in): string
{
    $v = $in['color'] ?? null;
    if (!is_string($v) || !preg_match('/^#[0-9a-fA-F]{6}$/', $v)) {
        fail('El color no es válido.');
    }
    return strtoupper($v);
}

/** Hora HH:MM → 'HH:MM:00', o null si viene vacía. */
function horaOpcional(array $in, string $clave, string $etiqueta): ?string
{
    $v = $in[$clave] ?? null;
    if ($v === null || $v === '') {
        return null;
    }
    if (!is_string($v) || !preg_match('/^([01]\d|2[0-3]):([0-5]\d)(:[0-5]\d)?$/', $v)) {
        fail("$etiqueta no es válida.");
    }
    return substr($v, 0, 5) . ':00';
}

/** Lista de días de la semana (1 = lunes ... 7 = domingo), sin repetir y al menos uno. */
function diasValidos(array $in): array
{
    $v = $in['dias'] ?? null;
    if (!is_array($v)) {
        fail('Elegí al menos un día de la semana.');
    }
    $dias = [];
    foreach ($v as $d) {
        if (is_string($d) && ctype_digit($d)) {
            $d = (int)$d;
        }
        if (!is_int($d) || $d < 1 || $d > 7) {
            fail('Los días de la semana no son válidos.');
        }
        $dias[$d] = $d;
    }
    if (!$dias) {
        fail('Elegí al menos un día de la semana.');
    }
    sort($dias);
    return array_values($dias);
}

/** Convierte 'HH:MM:SS' en 'HH:MM' (o null). */
function hhmm(?string $t): ?string
{
    return $t === null ? null : substr($t, 0, 5);
}
