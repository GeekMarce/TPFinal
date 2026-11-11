<?php
declare(strict_types=1);

/**
 * Racha.php — cálculo de la racha de hábitos (solo hábitos; las tareas no cuentan).
 *
 * Esta clase NO usa base de datos: recibe arrays y devuelve resultados, por eso se puede
 * probar sola (ver tests/RachaTest.php). La racha NUNCA se guarda: se calcula siempre
 * desde los registros, así que corregir un día pasado recalcula todo de forma coherente.
 *
 * Reglas decididas para LifeFlow:
 *  - Un día CUMPLE si los hábitos completados son al menos el 70 % del total del día,
 *    comparando sin redondear: completados * 100 >= 70 * total (7 de 10 cumple, 2 de 3 no).
 *  - El total del día son los hábitos programados ese día de la semana. Pendiente y
 *    postergada sin justificación quedan en el total pero no suman como hechos.
 *    Cancelada y postergada con justificación salen del total.
 *  - Un día sin hábitos en el total mantiene la racha (no suma ni corta).
 *  - Un día justificado (justificaciones_dia) no corta la racha y SÍ cuenta en ella.
 *  - Un día se evalúa cuando termina: hoy no corta la racha ni suma hasta mañana.
 *
 * Formato de entrada:
 *   $habitos     = [['id'=>1, 'creado'=>'2026-11-05', 'dias'=>[1,2,3]], ...]   (1 = lunes ... 7 = domingo)
 *   $estados     = [habito_id => ['2026-11-05' => 'completada', ...], ...]
 *   $justificadas = ['2026-11-02' => true, ...]
 */
final class Racha
{
    public const UMBRAL = 70;
    /** Estados que sacan al hábito del total del día. */
    public const FUERA_DEL_TOTAL = ['cancelada', 'postergada_con_justificacion'];

    /** ¿El hábito está programado en esa fecha? (día de la semana elegido y ya creado). */
    public static function programado(array $habito, string $fecha): bool
    {
        $dia = (int)date('N', strtotime($fecha));
        return $fecha >= $habito['creado'] && in_array($dia, $habito['dias'], true);
    }

    /** Estado de un hábito en una fecha ('pendiente' si no hay registro). */
    public static function estado(array $estados, int $habitoId, string $fecha): string
    {
        return $estados[$habitoId][$fecha] ?? 'pendiente';
    }

    /** Total y hechos de un día. Devuelve ['total'=>int, 'hechos'=>int]. */
    public static function evaluarDia(array $habitos, array $estados, string $fecha): array
    {
        $total = 0;
        $hechos = 0;
        foreach ($habitos as $h) {
            if (!self::programado($h, $fecha)) {
                continue;
            }
            $estado = self::estado($estados, (int)$h['id'], $fecha);
            if (in_array($estado, self::FUERA_DEL_TOTAL, true)) {
                continue;
            }
            $total++;
            if ($estado === 'completada') {
                $hechos++;
            }
        }
        return ['total' => $total, 'hechos' => $hechos];
    }

    /** ¿Alcanza el umbral? Sin redondeos. */
    public static function cumple(int $hechos, int $total): bool
    {
        return $total > 0 && $hechos * 100 >= self::UMBRAL * $total;
    }

    /** Cuántos hábitos más hay que completar para llegar al umbral (0 si ya cumple). */
    public static function faltan(int $hechos, int $total): int
    {
        if ($total === 0) {
            return 0;
        }
        $n = 0;
        while (!self::cumple($hechos + $n, $total)) {
            $n++;
        }
        return $n;
    }

    /** Tipo de un día cerrado: 'justificado', 'cumple', 'falla' o 'neutral' (sin hábitos). */
    public static function tipoDia(array $habitos, array $estados, array $justificadas, string $fecha): string
    {
        if (isset($justificadas[$fecha])) {
            return 'justificado';
        }
        $r = self::evaluarDia($habitos, $estados, $fecha);
        if ($r['total'] === 0) {
            return 'neutral';
        }
        return self::cumple($r['hechos'], $r['total']) ? 'cumple' : 'falla';
    }

    /**
     * Calcula la racha actual y la mejor racha histórica hasta AYER.
     * Devuelve ['actual'=>int, 'mejor'=>int, 'dias'=>[fecha => tipo]].
     */
    public static function calcular(array $habitos, array $estados, array $justificadas, string $hoy): array
    {
        $inicios = array_column($habitos, 'creado');
        foreach (array_keys($justificadas) as $f) {
            $inicios[] = $f;
        }
        $res = ['actual' => 0, 'mejor' => 0, 'dias' => []];
        if (!$inicios) {
            return $res;
        }
        $desde = min($inicios);
        $actual = 0;
        $mejor = 0;
        $fecha = new DateTimeImmutable($desde);
        $limite = new DateTimeImmutable($hoy);
        while ($fecha < $limite) { // solo días ya terminados
            $f = $fecha->format('Y-m-d');
            $tipo = self::tipoDia($habitos, $estados, $justificadas, $f);
            if ($tipo === 'cumple' || $tipo === 'justificado') {
                $actual++;
                $mejor = max($mejor, $actual);
            } elseif ($tipo === 'falla') {
                $actual = 0;
            }
            $res['dias'][$f] = $tipo;
            $fecha = $fecha->modify('+1 day');
        }
        $res['actual'] = $actual;
        $res['mejor'] = $mejor;
        return $res;
    }

    /**
     * Porcentaje de cumplimiento en los últimos $dias días (incluye hoy).
     * = hábitos completados / hábitos en el total, sumando todos esos días. null si no hubo hábitos.
     */
    public static function porcentaje(array $habitos, array $estados, string $hoy, int $dias = 30): ?int
    {
        $total = 0;
        $hechos = 0;
        $fecha = new DateTimeImmutable($hoy);
        for ($i = 0; $i < $dias; $i++) {
            $r = self::evaluarDia($habitos, $estados, $fecha->format('Y-m-d'));
            $total += $r['total'];
            $hechos += $r['hechos'];
            $fecha = $fecha->modify('-1 day');
        }
        return $total === 0 ? null : (int)round($hechos * 100 / $total);
    }
}
