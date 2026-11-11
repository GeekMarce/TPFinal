<?php
declare(strict_types=1);

/**
 * api.php — API JSON de LifeFlow.
 *
 * Todas las acciones se llaman como api.php?action=NOMBRE (GET para leer, POST para modificar).
 * Cada acción tiene un NIVEL de acceso, que el servidor comprueba siempre (no alcanza con
 * ocultar botones):
 *   pub   = cualquiera, incluso visitantes sin cuenta (rol Observador: solo lectura del perfil público)
 *   user  = cuenta iniciada (Usuario o Administrador): crear, editar y eliminar SOLO lo propio
 *   admin = rol Administrador: categorías por defecto y cuentas
 * Además del rol, cada consulta filtra por usuario_id de la sesión: nadie puede leer ni tocar
 * registros de otra cuenta, ni siquiera un Administrador.
 */

require dirname(__DIR__) . '/src/bootstrap.php';
require dirname(__DIR__) . '/src/Racha.php';
$PLANTILLAS = require dirname(__DIR__) . '/src/plantillas.php';

const ESTADOS_HABITO = ['pendiente', 'completada', 'cancelada', 'postergada_con_justificacion', 'postergada_sin_justificacion'];
const ESTADOS_TAREA = ['pendiente', 'completada', 'cancelada', 'postergada'];
const PRIORIDADES = ['baja', 'media', 'alta'];

// acción => [método, nivel]
$rutas = [
    'session' => ['GET', 'pub'], 'login' => ['POST', 'pub'], 'register' => ['POST', 'pub'], 'logout' => ['POST', 'pub'],
    'perfiles' => ['GET', 'pub'], 'perfil' => ['GET', 'pub'],
    'data' => ['GET', 'user'], 'dia' => ['GET', 'user'], 'semana' => ['GET', 'user'], 'estadisticas' => ['GET', 'user'],
    'tarea_guardar' => ['POST', 'user'], 'tarea_estado' => ['POST', 'user'], 'tarea_eliminar' => ['POST', 'user'],
    'categoria_guardar' => ['POST', 'user'], 'categoria_eliminar' => ['POST', 'user'],
    'habito_guardar' => ['POST', 'user'], 'habito_eliminar' => ['POST', 'user'], 'registro_guardar' => ['POST', 'user'],
    'justificacion_guardar' => ['POST', 'user'], 'justificacion_eliminar' => ['POST', 'user'],
    'rutina_guardar' => ['POST', 'user'], 'rutina_eliminar' => ['POST', 'user'], 'rutina_plantilla' => ['POST', 'user'],
    'actividad_guardar' => ['POST', 'user'], 'actividad_eliminar' => ['POST', 'user'],
    'excepcion_guardar' => ['POST', 'user'], 'excepcion_eliminar' => ['POST', 'user'],
    'perfil_guardar' => ['POST', 'user'],
    'admin_usuarios' => ['GET', 'admin'], 'admin_usuario' => ['POST', 'admin'],
    'admin_catpred_guardar' => ['POST', 'admin'], 'admin_catpred_eliminar' => ['POST', 'admin'],
];

/* ====================== Sesión y usuario ====================== */

function cargarSesion(): ?array
{
    if (!isset($_SESSION['user_id'])) {
        return null;
    }
    if (time() - ($_SESSION['last_seen'] ?? 0) > 1800) { // 30 minutos sin actividad
        unset($_SESSION['user_id']);
        return null;
    }
    $u = fila('SELECT u.id, u.alias, u.email, u.privacidad, r.nombre AS rol
        FROM usuarios u JOIN roles r ON r.id = u.rol_id WHERE u.id = ? AND u.activo = 1', [(int)$_SESSION['user_id']]);
    if (!$u) {
        unset($_SESSION['user_id']);
        return null;
    }
    $_SESSION['last_seen'] = time();
    $u['id'] = (int)$u['id'];
    return $u;
}

function iniciarSesion(int $id): void
{
    session_regenerate_id(true);
    $_SESSION['user_id'] = $id;
    $_SESSION['last_seen'] = time();
    $_SESSION['attempts'] = 0;
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

function limitarIntentos(): void
{
    $ahora = time();
    if ($ahora - ($_SESSION['attempt_start'] ?? 0) > 300) {
        $_SESSION['attempt_start'] = $ahora;
        $_SESSION['attempts'] = 0;
    }
    if (($_SESSION['attempts'] ?? 0) >= 10) {
        fail('Demasiados intentos. Esperá cinco minutos.', 429);
    }
    $_SESSION['attempts'] = ($_SESSION['attempts'] ?? 0) + 1;
}

function correoValido(array $in): string
{
    $email = texto($in, 'email', 'El correo', 1, 190);
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        fail('Ingresá un correo válido.');
    }
    return $email;
}

function a_session(array $in, ?array $yo): void
{
    respond(['user' => $yo, 'csrf' => $_SESSION['csrf']]);
}

function a_login(array $in, ?array $yo): void
{
    if (!is_string($in['email'] ?? null) || !is_string($in['password'] ?? null)
        || strlen($in['email']) > 190 || strlen($in['password']) > 4096) {
        fail('Ingresá un correo y una contraseña válidos.', 400);
    }
    limitarIntentos();
    $u = fila('SELECT id, password, activo FROM usuarios WHERE email = ? LIMIT 1', [trim($in['email'])]);
    // Si el correo no existe se verifica igual contra un hash falso: así tarda lo mismo y no se filtra qué correos existen.
    $falso = '$2y$10$jhQBIQIWGgOx6XQ0ANnK.e7FS9nl8fb6OFc0LChedzop0W/O1KZ6a';
    $ok = password_verify($in['password'], $u ? $u['password'] : $falso);
    if (!$u || !$ok || (int)$u['activo'] !== 1) {
        fail('Correo o contraseña incorrectos.', 401);
    }
    iniciarSesion((int)$u['id']);
    a_session([], cargarSesion());
}

function a_register(array $in, ?array $yo): void
{
    limitarIntentos();
    $alias = texto($in, 'alias', 'El alias', 2, 50);
    $email = correoValido($in);
    $clave = $in['password'] ?? null;
    if (!is_string($clave) || strlen($clave) < 8) {
        fail('La contraseña debe tener al menos 8 caracteres.');
    }
    if (strlen($clave) > 72) {
        fail('La contraseña admite hasta 72 caracteres.');
    }
    if (fila('SELECT id FROM usuarios WHERE email = ?', [$email])) {
        fail('Ese correo ya está registrado.', 409);
    }
    if (fila('SELECT id FROM usuarios WHERE alias = ?', [$alias])) {
        fail('Ese alias ya está en uso. Elegí otro.', 409);
    }
    $rol = valor("SELECT id FROM roles WHERE nombre = 'Usuario'");
    if (!$rol) {
        fail('No se pudo crear la cuenta: falta el rol Usuario.', 500);
    }
    $pdo = db();
    $pdo->beginTransaction();
    try {
        // Siempre entra como Usuario y con perfil público; el formulario no puede elegir rol.
        q('INSERT INTO usuarios (alias, email, password, rol_id, privacidad, activo) VALUES (?, ?, ?, ?, ?, 1)',
            [$alias, $email, password_hash($clave, PASSWORD_DEFAULT), (int)$rol, 'publico']);
        $id = (int)$pdo->lastInsertId();
        // La cuenta nueva recibe una copia de las categorías por defecto.
        q('INSERT INTO categorias (usuario_id, categoria_predeterminada_id, nombre, color)
           SELECT ?, id, nombre, color FROM categorias_predeterminadas', [$id]);
        $pdo->commit();
    } catch (PDOException $e) {
        $pdo->rollBack();
        if ($e->getCode() === '23000') {
            fail('Ese correo o alias ya está registrado.', 409);
        }
        throw $e;
    }
    iniciarSesion($id);
    a_session([], cargarSesion());
}

function a_logout(array $in, ?array $yo): void
{
    $_SESSION = [];
    $p = session_get_cookie_params();
    setcookie(session_name(), '', ['expires' => time() - 3600, 'path' => $p['path'],
        'secure' => $p['secure'], 'httponly' => true, 'samesite' => 'Strict']);
    session_destroy();
    respond(['ok' => true]);
}

/* ====================== Carga de datos del usuario ====================== */

/** Hábitos, registros y justificaciones de UNA cuenta, en el formato que usa Racha. */
function contextoHabitos(int $uid): array
{
    $habitos = [];
    foreach (filas('SELECT id, nombre, descripcion, color, categoria_id, DATE(fecha_creacion) AS creado
        FROM habitos WHERE usuario_id = ? AND eliminado = 0 ORDER BY id', [$uid]) as $h) {
        $habitos[(int)$h['id']] = [
            'id' => (int)$h['id'], 'nombre' => $h['nombre'], 'descripcion' => $h['descripcion'],
            'color' => $h['color'], 'categoria_id' => $h['categoria_id'] === null ? null : (int)$h['categoria_id'],
            'creado' => $h['creado'], 'dias' => [],
        ];
    }
    foreach (filas('SELECT d.habito_id, d.dia_semana FROM dias_habito d
        JOIN habitos h ON h.id = d.habito_id WHERE h.usuario_id = ? AND h.eliminado = 0 ORDER BY d.dia_semana', [$uid]) as $d) {
        $habitos[(int)$d['habito_id']]['dias'][] = (int)$d['dia_semana'];
    }
    $estados = [];
    $textos = [];
    foreach (filas('SELECT r.habito_id, r.fecha, r.estado, r.justificacion FROM registros_habitos r
        JOIN habitos h ON h.id = r.habito_id WHERE h.usuario_id = ? AND h.eliminado = 0', [$uid]) as $r) {
        $estados[(int)$r['habito_id']][$r['fecha']] = $r['estado'];
        $textos[(int)$r['habito_id']][$r['fecha']] = $r['justificacion'];
    }
    $justificadas = [];
    foreach (filas('SELECT fecha, justificacion FROM justificaciones_dia WHERE usuario_id = ?', [$uid]) as $j) {
        $justificadas[$j['fecha']] = $j['justificacion'];
    }
    return ['habitos' => $habitos, 'estados' => $estados, 'textos' => $textos, 'justificadas' => $justificadas];
}

function rachaDe(array $ctx): array
{
    return Racha::calcular(array_values($ctx['habitos']), $ctx['estados'], $ctx['justificadas'], hoy());
}

function tareasUsuario(int $uid): array
{
    $t = filas('SELECT t.id, t.titulo, t.descripcion, t.fecha_limite, t.prioridad, t.estado, t.categoria_id,
        c.nombre AS categoria, c.color AS categoria_color
        FROM tareas t LEFT JOIN categorias c ON c.id = t.categoria_id
        WHERE t.usuario_id = ? AND t.eliminado = 0
        ORDER BY t.fecha_limite IS NULL, t.fecha_limite, t.id', [$uid]);
    foreach ($t as &$x) {
        $x['id'] = (int)$x['id'];
        $x['categoria_id'] = $x['categoria_id'] === null ? null : (int)$x['categoria_id'];
    }
    return $t;
}

function actividadesUsuario(int $uid): array
{
    $acts = [];
    foreach (filas('SELECT a.id, a.rutina_id, r.nombre AS rutina, a.nombre, a.descripcion, a.hora_inicio, a.hora_fin, a.color
        FROM actividades_rutina a JOIN rutinas r ON r.id = a.rutina_id
        WHERE r.usuario_id = ? AND r.eliminado = 0
        ORDER BY r.id, a.hora_inicio IS NULL, a.hora_inicio, a.id', [$uid]) as $a) {
        $acts[(int)$a['id']] = [
            'id' => (int)$a['id'], 'rutina_id' => (int)$a['rutina_id'], 'rutina' => $a['rutina'],
            'nombre' => $a['nombre'], 'descripcion' => $a['descripcion'],
            'inicio' => hhmm($a['hora_inicio']), 'fin' => hhmm($a['hora_fin']), 'color' => $a['color'], 'dias' => [],
        ];
        $acts[(int)$a['id']]['cruza'] = $a['hora_inicio'] !== null && $a['hora_fin'] !== null && $a['hora_fin'] < $a['hora_inicio'];
    }
    foreach (filas('SELECT d.actividad_rutina_id, d.dia_semana FROM dias_actividad_rutina d
        JOIN actividades_rutina a ON a.id = d.actividad_rutina_id JOIN rutinas r ON r.id = a.rutina_id
        WHERE r.usuario_id = ? AND r.eliminado = 0 ORDER BY d.dia_semana', [$uid]) as $d) {
        $acts[(int)$d['actividad_rutina_id']]['dias'][] = (int)$d['dia_semana'];
    }
    return $acts;
}

/** ¿Existe la tabla opcional de excepciones? (database/extra_excepciones_rutina.sql) */
function tablaExcepciones(): bool
{
    static $existe = null;
    if ($existe === null) {
        $existe = (bool)valor("SELECT 1 FROM information_schema.tables
            WHERE table_schema = DATABASE() AND table_name = 'excepciones_actividad_rutina'");
    }
    return $existe;
}

function excepcionesRango(int $uid, string $desde, string $hasta): array
{
    if (!tablaExcepciones()) {
        return [];
    }
    $m = [];
    foreach (filas('SELECT e.actividad_rutina_id, e.fecha, e.cancelada, e.hora_inicio, e.hora_fin
        FROM excepciones_actividad_rutina e
        JOIN actividades_rutina a ON a.id = e.actividad_rutina_id JOIN rutinas r ON r.id = a.rutina_id
        WHERE r.usuario_id = ? AND e.fecha BETWEEN ? AND ?', [$uid, $desde, $hasta]) as $e) {
        $m[$e['actividad_rutina_id'] . '|' . $e['fecha']] = $e;
    }
    return $m;
}

function diaAnterior(string $fecha): string
{
    return date('Y-m-d', strtotime($fecha . ' -1 day'));
}

/** Arma todo lo que corresponde a una fecha: hábitos, progreso, tareas, actividades y justificación. */
function armarDia(array $ctx, array $tareas, array $acts, array $exc, string $fecha): array
{
    $dow = (int)date('N', strtotime($fecha));
    $habitos = [];
    foreach ($ctx['habitos'] as $h) {
        if (!Racha::programado($h, $fecha)) {
            continue;
        }
        $habitos[] = [
            'id' => $h['id'], 'nombre' => $h['nombre'], 'color' => $h['color'],
            'estado' => Racha::estado($ctx['estados'], $h['id'], $fecha),
            'justificacion' => $ctx['textos'][$h['id']][$fecha] ?? null,
        ];
    }
    $ev = Racha::evaluarDia(array_values($ctx['habitos']), $ctx['estados'], $fecha);
    $hoy = hoy();

    $delDia = [];
    $vencidas = [];
    foreach ($tareas as $t) {
        if ($t['fecha_limite'] === null) {
            continue;
        }
        $f = substr($t['fecha_limite'], 0, 10);
        if ($f === $fecha) {
            $delDia[] = $t;
        } elseif ($fecha === $hoy && $f < $hoy && in_array($t['estado'], ['pendiente', 'postergada'], true)) {
            $vencidas[] = $t;
        }
    }

    $actividades = [];
    $ayer = diaAnterior($fecha);
    $diaAyer = (int)date('N', strtotime($ayer));
    foreach ($acts as $a) {
        // Actividad que empieza este día de la semana (con la excepción de esta fecha, si hay)
        if (in_array($dow, $a['dias'], true)) {
            $actividades[] = aplicarExcepcion($a, $exc[$a['id'] . '|' . $fecha] ?? null, false);
        }
        // Actividad que empezó ayer y termina pasada la medianoche (ej. 23:00 a 01:00)
        if ($a['cruza'] && in_array($diaAyer, $a['dias'], true)) {
            $item = aplicarExcepcion($a, $exc[$a['id'] . '|' . $ayer] ?? null, true);
            if (!$item['cancelada'] && $item['cruza']) {
                $actividades[] = $item;
            }
        }
    }
    usort($actividades, fn($x, $y) => [$x['inicio'] === null, $x['continua'] ? '00:00' : ($x['inicio'] ?? '')]
        <=> [$y['inicio'] === null, $y['continua'] ? '00:00' : ($y['inicio'] ?? '')]);

    return [
        'fecha' => $fecha,
        'dia_semana' => $dow,
        'es_hoy' => $fecha === $hoy,
        'es_futuro' => $fecha > $hoy,
        'habitos' => $habitos,
        'total' => $ev['total'],
        'hechos' => $ev['hechos'],
        'porcentaje' => $ev['total'] ? (int)floor($ev['hechos'] * 100 / $ev['total']) : null,
        'cumple' => Racha::cumple($ev['hechos'], $ev['total']),
        'faltan' => Racha::faltan($ev['hechos'], $ev['total']),
        'justificacion' => $ctx['justificadas'][$fecha] ?? null,
        'tareas' => $delDia,
        'vencidas' => $vencidas,
        'actividades' => $actividades,
    ];
}

function aplicarExcepcion(array $a, ?array $e, bool $continua): array
{
    $item = [
        'id' => $a['id'], 'nombre' => $a['nombre'], 'rutina' => $a['rutina'], 'color' => $a['color'],
        'inicio' => $a['inicio'], 'fin' => $a['fin'], 'cruza' => $a['cruza'], 'continua' => $continua,
        'cancelada' => false, 'excepcion' => false,
    ];
    if ($e) {
        $item['excepcion'] = true;
        $item['cancelada'] = (int)$e['cancelada'] === 1;
        if (!$item['cancelada']) {
            $item['inicio_original'] = $a['inicio'];
            $item['fin_original'] = $a['fin'];
            $item['inicio'] = hhmm($e['hora_inicio']);
            $item['fin'] = hhmm($e['hora_fin']);
            $item['cruza'] = $item['inicio'] !== null && $item['fin'] !== null && $item['fin'] < $item['inicio'];
        }
    }
    return $item;
}

/* ====================== Lectura ====================== */

function a_data(array $in, array $yo): void
{
    global $PLANTILLAS;
    $uid = $yo['id'];
    $ctx = contextoHabitos($uid);
    $acts = actividadesUsuario($uid);
    $rutinas = filas('SELECT id, nombre, descripcion FROM rutinas WHERE usuario_id = ? AND eliminado = 0 ORDER BY id', [$uid]);
    foreach ($rutinas as &$r) {
        $r['id'] = (int)$r['id'];
        $r['actividades'] = array_values(array_filter($acts, fn($a) => $a['rutina_id'] === $r['id']));
    }
    $plantillas = [];
    foreach ($PLANTILLAS as $clave => $p) {
        $plantillas[] = ['clave' => $clave, 'nombre' => $p['nombre'], 'descripcion' => $p['descripcion'], 'actividades' => count($p['actividades'])];
    }
    respond([
        'hoy' => hoy(),
        'user' => $yo,
        'excepciones' => tablaExcepciones(),
        'catpred' => filas('SELECT id, nombre, color FROM categorias_predeterminadas ORDER BY nombre'),
        'categorias' => filas('SELECT id, nombre, color, categoria_predeterminada_id FROM categorias WHERE usuario_id = ? ORDER BY nombre', [$uid]),
        'tareas' => tareasUsuario($uid),
        'habitos' => array_values($ctx['habitos']),
        'rutinas' => $rutinas,
        'plantillas' => $plantillas,
    ]);
}

function a_dia(array $in, array $yo): void
{
    $fecha = isset($_GET['fecha']) ? fechaValida($_GET['fecha']) : hoy();
    $uid = $yo['id'];
    $ctx = contextoHabitos($uid);
    $exc = excepcionesRango($uid, diaAnterior($fecha), $fecha);
    $dia = armarDia($ctx, tareasUsuario($uid), actividadesUsuario($uid), $exc, $fecha);
    $racha = rachaDe($ctx);
    $dia['racha'] = ['actual' => $racha['actual'], 'mejor' => $racha['mejor']];
    $dia['tipo'] = $racha['dias'][$fecha] ?? null;
    respond($dia);
}

function a_semana(array $in, array $yo): void
{
    $fecha = isset($_GET['fecha']) ? fechaValida($_GET['fecha']) : hoy();
    $lunes = date('Y-m-d', strtotime($fecha . ' -' . ((int)date('N', strtotime($fecha)) - 1) . ' days'));
    $domingo = date('Y-m-d', strtotime($lunes . ' +6 days'));
    $uid = $yo['id'];
    $ctx = contextoHabitos($uid);
    $tareas = tareasUsuario($uid);
    $acts = actividadesUsuario($uid);
    $exc = excepcionesRango($uid, diaAnterior($lunes), $domingo);
    $racha = rachaDe($ctx);
    $dias = [];
    for ($i = 0; $i < 7; $i++) {
        $f = date('Y-m-d', strtotime($lunes . " +$i days"));
        $d = armarDia($ctx, $tareas, $acts, $exc, $f);
        $d['tipo'] = $racha['dias'][$f] ?? null;
        unset($d['vencidas']);
        $dias[] = $d;
    }
    respond(['lunes' => $lunes, 'domingo' => $domingo, 'hoy' => hoy(), 'dias' => $dias]);
}

function a_estadisticas(array $in, array $yo): void
{
    $uid = $yo['id'];
    $ctx = contextoHabitos($uid);
    $racha = rachaDe($ctx);
    $ultimos = [];
    foreach (array_slice($racha['dias'], -14, 14, true) as $f => $tipo) {
        $ultimos[] = ['fecha' => $f, 'tipo' => $tipo];
    }
    respond([
        'racha_actual' => $racha['actual'],
        'mejor_racha' => $racha['mejor'],
        'porcentaje_30' => Racha::porcentaje(array_values($ctx['habitos']), $ctx['estados'], hoy(), 30),
        'tareas_completadas' => (int)valor("SELECT COUNT(*) FROM tareas WHERE usuario_id = ? AND eliminado = 0 AND estado = 'completada'", [$uid]),
        'tareas_pendientes' => (int)valor("SELECT COUNT(*) FROM tareas WHERE usuario_id = ? AND eliminado = 0 AND estado IN ('pendiente','postergada')", [$uid]),
        'dias_justificados' => count($ctx['justificadas']),
        'ultimos' => $ultimos,
    ]);
}

/* ====================== Perfil público (visitantes, rol Observador) ====================== */

function a_perfiles(array $in, ?array $yo): void
{
    // Solo cuentas activas y públicas, y solo el alias: nada privado.
    respond(['perfiles' => filas("SELECT id, alias FROM usuarios WHERE privacidad = 'publico' AND activo = 1 ORDER BY alias")]);
}

function a_perfil(array $in, ?array $yo): void
{
    $id = isset($_GET['id']) && ctype_digit((string)$_GET['id']) ? (int)$_GET['id'] : 0;
    $u = $id ? fila('SELECT id, alias, privacidad, DATE(fecha_registro) AS alta FROM usuarios WHERE id = ? AND activo = 1', [$id]) : null;
    if (!$u) {
        fail('No encontramos ese perfil.', 404);
    }
    if ($u['privacidad'] !== 'publico') {
        respond(['privado' => true]); // cuenta candado: ni siquiera se muestra el alias
    }
    $ctx = contextoHabitos((int)$u['id']);
    $racha = rachaDe($ctx);
    respond([
        'privado' => false,
        'alias' => $u['alias'],
        'alta' => $u['alta'],
        'racha_actual' => $racha['actual'],
        'mejor_racha' => $racha['mejor'],
        'porcentaje_30' => Racha::porcentaje(array_values($ctx['habitos']), $ctx['estados'], hoy(), 30),
        'tareas_completadas' => (int)valor("SELECT COUNT(*) FROM tareas WHERE usuario_id = ? AND eliminado = 0 AND estado = 'completada'", [(int)$u['id']]),
    ]);
}

function a_perfil_guardar(array $in, array $yo): void
{
    $alias = texto($in, 'alias', 'El alias', 2, 50);
    $priv = enumerado($in, 'privacidad', 'La privacidad', ['publico', 'privado']);
    if (fila('SELECT id FROM usuarios WHERE alias = ? AND id <> ?', [$alias, $yo['id']])) {
        fail('Ese alias ya está en uso. Elegí otro.', 409);
    }
    q('UPDATE usuarios SET alias = ?, privacidad = ? WHERE id = ?', [$alias, $priv, $yo['id']]);
    respond(['ok' => true]);
}

/* ====================== Comprobación de propiedad ====================== */

function propio(string $sql, int $id, int $uid, string $nombre): array
{
    $r = fila($sql, [$id, $uid]);
    if (!$r) {
        fail("$nombre no existe o no te pertenece.", 404); // mismo mensaje: no se revela si existe en otra cuenta
    }
    return $r;
}
function categoriaPropia(?int $id, int $uid): ?int
{
    if ($id === null) {
        return null;
    }
    propio('SELECT id FROM categorias WHERE id = ? AND usuario_id = ?', $id, $uid, 'La categoría');
    return $id;
}
function habitoPropio(int $id, int $uid): array
{
    return propio('SELECT id, DATE(fecha_creacion) AS creado FROM habitos WHERE id = ? AND usuario_id = ? AND eliminado = 0', $id, $uid, 'El hábito');
}
function rutinaPropia(int $id, int $uid): array
{
    return propio('SELECT id FROM rutinas WHERE id = ? AND usuario_id = ? AND eliminado = 0', $id, $uid, 'La rutina');
}
function actividadPropia(int $id, int $uid): array
{
    return propio('SELECT a.id FROM actividades_rutina a JOIN rutinas r ON r.id = a.rutina_id
        WHERE a.id = ? AND r.usuario_id = ? AND r.eliminado = 0', $id, $uid, 'La actividad');
}

/* ====================== Tareas y categorías ====================== */

function a_tarea_guardar(array $in, array $yo): void
{
    $uid = $yo['id'];
    $id = enteroOpcional($in, 'id', 'La tarea');
    $titulo = texto($in, 'titulo', 'El título', 1, 150);
    $desc = textoOpcional($in, 'descripcion', 'La descripción', 2000);
    $prio = enumerado($in, 'prioridad', 'La prioridad', PRIORIDADES);
    $estado = enumerado($in, 'estado', 'El estado', ESTADOS_TAREA);
    $cat = categoriaPropia(enteroOpcional($in, 'categoria_id', 'La categoría'), $uid);
    $limite = null;
    if (!empty($in['fecha'])) {
        $limite = fechaValida($in['fecha'], 'La fecha límite') . ' ' . (horaOpcional($in, 'hora', 'La hora') ?? '00:00:00'); // 00:00 = sin hora
    }
    if ($id) {
        propio('SELECT id FROM tareas WHERE id = ? AND usuario_id = ? AND eliminado = 0', $id, $uid, 'La tarea');
        q('UPDATE tareas SET titulo = ?, descripcion = ?, fecha_limite = ?, prioridad = ?, estado = ?, categoria_id = ?
           WHERE id = ? AND usuario_id = ?', [$titulo, $desc, $limite, $prio, $estado, $cat, $id, $uid]);
    } else {
        q('INSERT INTO tareas (usuario_id, categoria_id, titulo, descripcion, fecha_limite, prioridad, estado) VALUES (?, ?, ?, ?, ?, ?, ?)',
            [$uid, $cat, $titulo, $desc, $limite, $prio, $estado]);
    }
    respond(['ok' => true]);
}

function a_tarea_estado(array $in, array $yo): void
{
    $id = entero($in, 'id', 'La tarea');
    $estado = enumerado($in, 'estado', 'El estado', ESTADOS_TAREA);
    propio('SELECT id FROM tareas WHERE id = ? AND usuario_id = ? AND eliminado = 0', $id, $yo['id'], 'La tarea');
    q('UPDATE tareas SET estado = ? WHERE id = ? AND usuario_id = ?', [$estado, $id, $yo['id']]);
    respond(['ok' => true]);
}

function a_tarea_eliminar(array $in, array $yo): void
{
    $id = entero($in, 'id', 'La tarea');
    propio('SELECT id FROM tareas WHERE id = ? AND usuario_id = ? AND eliminado = 0', $id, $yo['id'], 'La tarea');
    q('UPDATE tareas SET eliminado = 1 WHERE id = ? AND usuario_id = ?', [$id, $yo['id']]); // eliminación lógica
    respond(['ok' => true]);
}

function a_categoria_guardar(array $in, array $yo): void
{
    $uid = $yo['id'];
    $id = enteroOpcional($in, 'id', 'La categoría');
    $nombre = texto($in, 'nombre', 'El nombre', 1, 60);
    $color = colorValido($in);
    if (fila('SELECT id FROM categorias WHERE usuario_id = ? AND nombre = ? AND id <> ?', [$uid, $nombre, $id ?? 0])) {
        fail('Ya tenés una categoría con ese nombre.', 409);
    }
    if ($id) {
        categoriaPropia($id, $uid);
        q('UPDATE categorias SET nombre = ?, color = ? WHERE id = ? AND usuario_id = ?', [$nombre, $color, $id, $uid]);
    } else {
        q('INSERT INTO categorias (usuario_id, nombre, color) VALUES (?, ?, ?)', [$uid, $nombre, $color]);
    }
    respond(['ok' => true]);
}

function a_categoria_eliminar(array $in, array $yo): void
{
    $id = entero($in, 'id', 'La categoría');
    categoriaPropia($id, $yo['id']);
    // Las tareas y hábitos que la usaban quedan sin categoría (lo hace la clave foránea ON DELETE SET NULL).
    q('DELETE FROM categorias WHERE id = ? AND usuario_id = ?', [$id, $yo['id']]);
    respond(['ok' => true]);
}

/* ====================== Hábitos, registros y justificaciones ====================== */

function a_habito_guardar(array $in, array $yo): void
{
    $uid = $yo['id'];
    $id = enteroOpcional($in, 'id', 'El hábito');
    $nombre = texto($in, 'nombre', 'El nombre', 1, 100);
    $desc = textoOpcional($in, 'descripcion', 'La descripción', 2000);
    $color = colorValido($in);
    $cat = categoriaPropia(enteroOpcional($in, 'categoria_id', 'La categoría'), $uid);
    $dias = diasValidos($in);
    if ($id) {
        habitoPropio($id, $uid);
    }
    $pdo = db();
    $pdo->beginTransaction();
    try {
        if ($id) {
            q('UPDATE habitos SET nombre = ?, descripcion = ?, color = ?, categoria_id = ? WHERE id = ? AND usuario_id = ?',
                [$nombre, $desc, $color, $cat, $id, $uid]);
            q('DELETE FROM dias_habito WHERE habito_id = ?', [$id]);
        } else {
            q('INSERT INTO habitos (usuario_id, categoria_id, nombre, descripcion, color) VALUES (?, ?, ?, ?, ?)',
                [$uid, $cat, $nombre, $desc, $color]);
            $id = (int)$pdo->lastInsertId();
        }
        foreach ($dias as $d) {
            q('INSERT INTO dias_habito (habito_id, dia_semana) VALUES (?, ?)', [$id, $d]);
        }
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
    respond(['ok' => true]);
}

function a_habito_eliminar(array $in, array $yo): void
{
    $id = entero($in, 'id', 'El hábito');
    habitoPropio($id, $yo['id']);
    q('UPDATE habitos SET eliminado = 1 WHERE id = ? AND usuario_id = ?', [$id, $yo['id']]); // los registros se conservan
    respond(['ok' => true]);
}

function a_registro_guardar(array $in, array $yo): void
{
    $uid = $yo['id'];
    $id = entero($in, 'habito_id', 'El hábito');
    $fecha = fechaValida($in['fecha'] ?? null);
    $estado = enumerado($in, 'estado', 'El estado', ESTADOS_HABITO);
    habitoPropio($id, $uid);
    $dias = array_map('intval', array_column(filas('SELECT dia_semana FROM dias_habito WHERE habito_id = ?', [$id]), 'dia_semana'));
    if (!in_array((int)date('N', strtotime($fecha)), $dias, true)) {
        fail('Ese hábito no está programado para ese día de la semana.');
    }
    if ($estado === 'completada' && $fecha > hoy()) {
        fail('No se puede marcar como completada una fecha futura.');
    }
    $just = null;
    if ($estado === 'postergada_con_justificacion') {
        $just = texto($in, 'justificacion', 'La justificación', 1, 500);
    }
    // Un único registro por hábito y fecha: marcar de nuevo corrige el anterior (la clave única lo garantiza).
    q('INSERT INTO registros_habitos (habito_id, fecha, estado, justificacion) VALUES (?, ?, ?, ?)
       ON DUPLICATE KEY UPDATE estado = VALUES(estado), justificacion = VALUES(justificacion)', [$id, $fecha, $estado, $just]);
    respond(['ok' => true]);
}

function a_justificacion_guardar(array $in, array $yo): void
{
    $fecha = fechaValida($in['fecha'] ?? null);
    if ($fecha > hoy()) {
        fail('Solo se puede justificar el día de hoy o uno anterior.');
    }
    $texto = texto($in, 'justificacion', 'El motivo', 1, 500);
    q('INSERT INTO justificaciones_dia (usuario_id, fecha, justificacion) VALUES (?, ?, ?)
       ON DUPLICATE KEY UPDATE justificacion = VALUES(justificacion)', [$yo['id'], $fecha, $texto]);
    respond(['ok' => true]);
}

function a_justificacion_eliminar(array $in, array $yo): void
{
    q('DELETE FROM justificaciones_dia WHERE usuario_id = ? AND fecha = ?', [$yo['id'], fechaValida($in['fecha'] ?? null)]);
    respond(['ok' => true]);
}

/* ====================== Rutinas ====================== */

function a_rutina_guardar(array $in, array $yo): void
{
    $uid = $yo['id'];
    $id = enteroOpcional($in, 'id', 'La rutina');
    $nombre = texto($in, 'nombre', 'El nombre', 1, 100);
    $desc = textoOpcional($in, 'descripcion', 'La descripción', 2000);
    if ($id) {
        rutinaPropia($id, $uid);
        q('UPDATE rutinas SET nombre = ?, descripcion = ? WHERE id = ? AND usuario_id = ?', [$nombre, $desc, $id, $uid]);
    } else {
        q('INSERT INTO rutinas (usuario_id, nombre, descripcion) VALUES (?, ?, ?)', [$uid, $nombre, $desc]);
    }
    respond(['ok' => true]);
}

function a_rutina_eliminar(array $in, array $yo): void
{
    $id = entero($in, 'id', 'La rutina');
    rutinaPropia($id, $yo['id']);
    q('UPDATE rutinas SET eliminado = 1 WHERE id = ? AND usuario_id = ?', [$id, $yo['id']]);
    respond(['ok' => true]);
}

function a_rutina_plantilla(array $in, array $yo): void
{
    global $PLANTILLAS;
    $clave = $in['plantilla'] ?? null;
    if (!is_string($clave) || !isset($PLANTILLAS[$clave])) {
        fail('La plantilla no existe.');
    }
    $p = $PLANTILLAS[$clave];
    $pdo = db();
    $pdo->beginTransaction();
    try {
        // Se COPIA: la rutina resultante es propia y se puede cambiar sin tocar la plantilla.
        q('INSERT INTO rutinas (usuario_id, nombre, descripcion) VALUES (?, ?, ?)', [$yo['id'], $p['nombre'], $p['descripcion']]);
        $rid = (int)$pdo->lastInsertId();
        foreach ($p['actividades'] as $a) {
            q('INSERT INTO actividades_rutina (rutina_id, nombre, hora_inicio, hora_fin, color) VALUES (?, ?, ?, ?, ?)',
                [$rid, $a['nombre'], $a['inicio'] ? $a['inicio'] . ':00' : null, $a['fin'] ? $a['fin'] . ':00' : null, $a['color']]);
            $aid = (int)$pdo->lastInsertId();
            foreach ($a['dias'] as $d) {
                q('INSERT INTO dias_actividad_rutina (actividad_rutina_id, dia_semana) VALUES (?, ?)', [$aid, $d]);
            }
        }
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
    respond(['ok' => true]);
}

function a_actividad_guardar(array $in, array $yo): void
{
    $uid = $yo['id'];
    $id = enteroOpcional($in, 'id', 'La actividad');
    $rutina = entero($in, 'rutina_id', 'La rutina');
    rutinaPropia($rutina, $uid);
    $nombre = texto($in, 'nombre', 'El nombre', 1, 100);
    $desc = textoOpcional($in, 'descripcion', 'La descripción', 2000);
    $ini = horaOpcional($in, 'inicio', 'La hora de inicio');
    $fin = horaOpcional($in, 'fin', 'La hora de fin');
    if (($ini === null) !== ($fin === null)) {
        fail('Completá la hora de inicio y la de fin, o dejá las dos vacías.');
    }
    if ($ini !== null && $ini === $fin) {
        fail('La hora de fin no puede ser igual a la de inicio.');
    } // si el fin es menor que el inicio, la actividad cruza la medianoche y es válida
    $color = colorValido($in);
    $dias = diasValidos($in);
    if ($id) {
        actividadPropia($id, $uid);
    }
    $pdo = db();
    $pdo->beginTransaction();
    try {
        if ($id) {
            q('UPDATE actividades_rutina SET rutina_id = ?, nombre = ?, descripcion = ?, hora_inicio = ?, hora_fin = ?, color = ? WHERE id = ?',
                [$rutina, $nombre, $desc, $ini, $fin, $color, $id]);
            q('DELETE FROM dias_actividad_rutina WHERE actividad_rutina_id = ?', [$id]);
        } else {
            q('INSERT INTO actividades_rutina (rutina_id, nombre, descripcion, hora_inicio, hora_fin, color) VALUES (?, ?, ?, ?, ?, ?)',
                [$rutina, $nombre, $desc, $ini, $fin, $color]);
            $id = (int)$pdo->lastInsertId();
        }
        foreach ($dias as $d) {
            q('INSERT INTO dias_actividad_rutina (actividad_rutina_id, dia_semana) VALUES (?, ?)', [$id, $d]);
        }
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
    respond(['ok' => true]);
}

function a_actividad_eliminar(array $in, array $yo): void
{
    $id = entero($in, 'id', 'La actividad');
    actividadPropia($id, $yo['id']);
    q('DELETE FROM actividades_rutina WHERE id = ?', [$id]); // sus días se borran en cascada
    respond(['ok' => true]);
}

/** Excepción: cambia o cancela UNA fecha sin tocar la rutina estándar ni las demás repeticiones. */
function a_excepcion_guardar(array $in, array $yo): void
{
    if (!tablaExcepciones()) {
        fail('Las excepciones no están activadas. Importá database/extra_excepciones_rutina.sql en phpMyAdmin.', 409);
    }
    $aid = entero($in, 'actividad_id', 'La actividad');
    actividadPropia($aid, $yo['id']);
    $fecha = fechaValida($in['fecha'] ?? null);
    $cancelada = !empty($in['cancelada']);
    $ini = $fin = null;
    if (!$cancelada) {
        $ini = horaOpcional($in, 'inicio', 'La hora de inicio');
        $fin = horaOpcional($in, 'fin', 'La hora de fin');
        if ($ini === null || $fin === null || $ini === $fin) {
            fail('Indicá una hora de inicio y una de fin distintas para ese día, o cancelá la actividad.');
        }
    }
    $dias = array_map('intval', array_column(filas('SELECT dia_semana FROM dias_actividad_rutina WHERE actividad_rutina_id = ?', [$aid]), 'dia_semana'));
    if (!in_array((int)date('N', strtotime($fecha)), $dias, true)) {
        fail('La actividad no se repite ese día de la semana.');
    }
    q('INSERT INTO excepciones_actividad_rutina (actividad_rutina_id, fecha, cancelada, hora_inicio, hora_fin) VALUES (?, ?, ?, ?, ?)
       ON DUPLICATE KEY UPDATE cancelada = VALUES(cancelada), hora_inicio = VALUES(hora_inicio), hora_fin = VALUES(hora_fin)',
        [$aid, $fecha, $cancelada ? 1 : 0, $ini, $fin]);
    respond(['ok' => true]);
}

function a_excepcion_eliminar(array $in, array $yo): void
{
    if (!tablaExcepciones()) {
        fail('Las excepciones no están activadas.', 409);
    }
    $aid = entero($in, 'actividad_id', 'La actividad');
    actividadPropia($aid, $yo['id']);
    q('DELETE FROM excepciones_actividad_rutina WHERE actividad_rutina_id = ? AND fecha = ?', [$aid, fechaValida($in['fecha'] ?? null)]);
    respond(['ok' => true]);
}

/* ====================== Administración (solo rol Administrador) ====================== */

function a_admin_usuarios(array $in, array $yo): void
{
    // El Administrador ve datos de la cuenta, nunca hábitos, tareas ni justificaciones de otras personas.
    respond([
        'usuarios' => filas('SELECT u.id, u.alias, u.email, r.nombre AS rol, u.activo, u.privacidad, DATE(u.fecha_registro) AS alta
            FROM usuarios u JOIN roles r ON r.id = u.rol_id ORDER BY u.id'),
        'roles' => array_column(filas('SELECT nombre FROM roles ORDER BY id'), 'nombre'),
    ]);
}

function a_admin_usuario(array $in, array $yo): void
{
    $id = entero($in, 'id', 'La cuenta');
    if ($id === $yo['id']) {
        fail('No podés cambiar tu propio rol ni desactivar tu propia cuenta.');
    }
    if (!fila('SELECT id FROM usuarios WHERE id = ?', [$id])) {
        fail('La cuenta no existe.', 404);
    }
    $rol = valor('SELECT id FROM roles WHERE nombre = ?', [$in['rol'] ?? '']);
    if (!$rol) {
        fail('El rol no es válido.');
    }
    $activo = !empty($in['activo']) ? 1 : 0;
    q('UPDATE usuarios SET rol_id = ?, activo = ? WHERE id = ?', [(int)$rol, $activo, $id]);
    respond(['ok' => true]);
}

function a_admin_catpred_guardar(array $in, array $yo): void
{
    $id = enteroOpcional($in, 'id', 'La categoría');
    $nombre = texto($in, 'nombre', 'El nombre', 1, 60);
    $color = colorValido($in);
    if (fila('SELECT id FROM categorias_predeterminadas WHERE nombre = ? AND id <> ?', [$nombre, $id ?? 0])) {
        fail('Ya existe una categoría por defecto con ese nombre.', 409);
    }
    if ($id) {
        if (!fila('SELECT id FROM categorias_predeterminadas WHERE id = ?', [$id])) {
            fail('La categoría no existe.', 404);
        }
        // Editar solo cambia la lista por defecto (y las cuentas nuevas); las copias ya entregadas no se tocan.
        q('UPDATE categorias_predeterminadas SET nombre = ?, color = ? WHERE id = ?', [$nombre, $color, $id]);
    } else {
        $pdo = db();
        $pdo->beginTransaction();
        try {
            q('INSERT INTO categorias_predeterminadas (nombre, color) VALUES (?, ?)', [$nombre, $color]);
            $nuevo = (int)$pdo->lastInsertId();
            // Las cuentas existentes reciben una copia (si ya tenían una con ese nombre, se respeta la suya).
            q('INSERT IGNORE INTO categorias (usuario_id, categoria_predeterminada_id, nombre, color)
               SELECT id, ?, ?, ? FROM usuarios', [$nuevo, $nombre, $color]);
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }
    respond(['ok' => true]);
}

function a_admin_catpred_eliminar(array $in, array $yo): void
{
    $id = entero($in, 'id', 'La categoría');
    if (!fila('SELECT id FROM categorias_predeterminadas WHERE id = ?', [$id])) {
        fail('La categoría no existe.', 404);
    }
    // Las copias de los usuarios se conservan (la clave foránea las desvincula con SET NULL).
    q('DELETE FROM categorias_predeterminadas WHERE id = ?', [$id]);
    respond(['ok' => true]);
}

/* ====================== Entrada: validación común y despacho ====================== */

$action = $_GET['action'] ?? 'session';
if (!is_string($action) || !isset($rutas[$action])) {
    fail('Solicitud no válida.', 404);
}
[$metodo, $nivel] = $rutas[$action];
if ($_SERVER['REQUEST_METHOD'] !== $metodo) {
    fail('Método no permitido.', 405);
}
if (!isset($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}
$in = [];
if ($metodo === 'POST') {
    if (!hash_equals($_SESSION['csrf'], $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')) {
        fail('La sesión venció. Recargá la página.', 403);
    }
    $crudo = file_get_contents('php://input');
    $in = $crudo === '' ? [] : json_decode($crudo, true);
    if (!is_array($in)) {
        fail('Solicitud no válida.', 400);
    }
}

try {
    $yo = cargarSesion();
    if ($nivel !== 'pub' && !$yo) {
        fail('Iniciá sesión para continuar.', 401);
    }
    if ($nivel === 'admin' && $yo['rol'] !== 'Administrador') {
        fail('No tenés permiso para esta acción.', 403);
    }
    ('a_' . $action)($in, $yo);
} catch (Throwable $error) {
    error_log('LifeFlow: ' . $error->getMessage());
    $respuesta = ['error' => 'No se pudo completar la solicitud. Revisá que MySQL esté iniciado, la base importada y config.php configurado.'];
    if (!empty($config['debug'])) {
        $respuesta['detalle'] = $error->getMessage(); // solo en local; en producción nunca se muestra
    }
    respond($respuesta, 503);
}
