<?php
declare(strict_types=1);

/**
 * Prueba unitaria de la racha. Se ejecuta desde la consola:  php tests/RachaTest.php
 * No necesita base de datos ni servidor web.
 */
require dirname(__DIR__) . '/src/Racha.php';
date_default_timezone_set('America/Argentina/Cordoba');

$fallos = 0;
function verificar(string $nombre, $obtenido, $esperado): void
{
    global $fallos;
    $ok = $obtenido === $esperado;
    if (!$ok) {
        $fallos++;
    }
    printf("[%s] %s%s\n", $ok ? 'OK ' : 'FALLÓ', $nombre,
        $ok ? '' : ' (esperado ' . json_encode($esperado) . ', obtenido ' . json_encode($obtenido) . ')');
}

// Hábito que se hace todos los días, creado el lunes 2026-11-02. Hoy = 2026-11-09 (lunes).
$todos = [1, 2, 3, 4, 5, 6, 7];
$h1 = ['id' => 1, 'creado' => '2026-11-02', 'dias' => $todos];
$hoy = '2026-11-09';
$dias = ['2026-11-02', '2026-11-03', '2026-11-04', '2026-11-05', '2026-11-06', '2026-11-07', '2026-11-08'];
$completos = [1 => array_fill_keys($dias, 'completada')];

// 1. Siete días consecutivos cumplidos
$r = Racha::calcular([$h1], $completos, [], $hoy);
verificar('7 días consecutivos: racha actual 7', $r['actual'], 7);
verificar('7 días consecutivos: mejor racha 7', $r['mejor'], 7);

// 2. Un día incumplido corta la racha pero se conserva la mejor
$e = $completos;
$e[1]['2026-11-06'] = 'pendiente';
$r = Racha::calcular([$h1], $e, [], $hoy);
verificar('día incumplido: racha actual 2 (7 y 8)', $r['actual'], 2);
verificar('día incumplido: mejor racha 4 (2 al 5)', $r['mejor'], 4);

// 3. Día justificado: no corta y cuenta (el día siguiente muestra 7)
$r = Racha::calcular([$h1], $e, ['2026-11-06' => true], $hoy);
verificar('día justificado: racha actual 7', $r['actual'], 7);
verificar('día justificado: se marca como justificado', $r['dias']['2026-11-06'], 'justificado');

// 4. Hoy no corta ni suma: se evalúa cuando termina
$r = Racha::calcular([$h1], $completos, [], '2026-11-08');
verificar('hoy no cuenta todavía (hasta ayer son 6)', $r['actual'], 6);

// 5. Límite del 70 %: 7 de 10 cumple, 2 de 3 no, 3 de 4 sí
verificar('7 de 10 cumple', Racha::cumple(7, 10), true);
verificar('2 de 3 no cumple (66,6 %)', Racha::cumple(2, 3), false);
verificar('3 de 4 cumple', Racha::cumple(3, 4), true);
verificar('1 de 10 no cumple (10 %)', Racha::cumple(1, 10), false);
verificar('0 de 0 no cumple (no hay hábitos)', Racha::cumple(0, 0), false);
verificar('faltan 3 para pasar de 4 a 7 de 10', Racha::faltan(4, 10), 3);

// 6. Cancelada y postergada con justificación salen del total; pendiente y sin justificación quedan
$hs = [];
for ($i = 1; $i <= 5; $i++) {
    $hs[] = ['id' => $i, 'creado' => '2026-11-02', 'dias' => $todos];
}
$est = [
    1 => ['2026-11-02' => 'completada'], 2 => ['2026-11-02' => 'completada'],
    3 => ['2026-11-02' => 'cancelada'], 4 => ['2026-11-02' => 'postergada_con_justificacion'],
    5 => ['2026-11-02' => 'pendiente'],
];
verificar('cancelada y postergada justificada salen del total', Racha::evaluarDia($hs, $est, '2026-11-02'), ['total' => 3, 'hechos' => 2]);
$est[5]['2026-11-02'] = 'postergada_sin_justificacion';
verificar('postergada sin justificar sigue en el total', Racha::evaluarDia($hs, $est, '2026-11-02')['total'], 3);

// 7. Un día sin hábitos programados mantiene la racha (no suma ni corta)
$lunes = ['id' => 1, 'creado' => '2026-11-02', 'dias' => [1]];
$est = [1 => ['2026-11-02' => 'completada', '2026-11-09' => 'completada']];
$r = Racha::calcular([$lunes], $est, [], '2026-11-10');
verificar('día sin hábitos mantiene la racha (lunes, 6 días libres, lunes)', $r['actual'], 2);

// 8. Corregir un registro pasado recalcula la racha
$e = $completos;
$e[1]['2026-11-05'] = 'pendiente';
$antes = Racha::calcular([$h1], $e, [], $hoy)['actual'];
$e[1]['2026-11-05'] = 'completada';
$despues = Racha::calcular([$h1], $e, [], $hoy)['actual'];
verificar('corregir el día 5: la racha pasa de 3 a 7', [$antes, $despues], [3, 7]);

// 9. Un hábito no cuenta antes de su fecha de creación
$tarde = ['id' => 1, 'creado' => '2026-11-07', 'dias' => $todos];
$r = Racha::calcular([$tarde], [1 => ['2026-11-07' => 'completada', '2026-11-08' => 'completada']], [], $hoy);
verificar('hábito creado el 7: racha 2', $r['actual'], 2);

echo $fallos === 0 ? "\nTodas las pruebas pasaron.\n" : "\nHubo $fallos prueba(s) con error.\n";
exit($fallos === 0 ? 0 : 1);
