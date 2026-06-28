<?php
declare(strict_types=1);

namespace Lebytek\Framework\Application\Reporte;

/**
 * Convierte un preset de periodo en un rango concreto [from, to] (Y-m-d H:i:s) y una
 * etiqueta legible. Puro: el `now` es inyectable para pruebas deterministas. Los
 * presets relativos se recalculan en cada generación.
 */
final class PeriodoResolver
{
    private const FMT = 'Y-m-d H:i:s';

    /** @return array{from:string,to:string,label:string} */
    public function resolve(string $preset, ?\DateTimeImmutable $now = null): array
    {
        $now = $now ?? new \DateTimeImmutable('now');

        switch ($preset) {
            case 'hoy':
                return $this->range($now, $now, 'Hoy');

            case 'ayer':
                $y = $now->modify('-1 day');
                return $this->range($y, $y, 'Ayer');

            case 'semana':
                $dow = (int) $now->format('N');
                $start = $now->modify('-' . ($dow - 1) . ' days');
                $end = $start->modify('+6 days');
                return $this->range($start, $end, 'Esta semana');

            case 'mes':
                return $this->range(
                    $now->modify('first day of this month'),
                    $now->modify('last day of this month'),
                    'Este mes'
                );

            case 'mes_pasado':
                return $this->range(
                    $now->modify('first day of last month'),
                    $now->modify('last day of last month'),
                    'Mes pasado'
                );

            case 'anio':
                $y = (int) $now->format('Y');
                return $this->range($now->setDate($y, 1, 1), $now->setDate($y, 12, 31), 'Este año');

            case 'anio_pasado':
                $y = (int) $now->format('Y') - 1;
                return $this->range($now->setDate($y, 1, 1), $now->setDate($y, 12, 31), 'Año pasado');

            case 'todo':
            default:
                return [
                    'from'  => '1970-01-01 00:00:00',
                    'to'    => '2999-12-31 23:59:59',
                    'label' => 'Todo',
                ];
        }
    }

    /** @return array{from:string,to:string,label:string} */
    private function range(\DateTimeImmutable $from, \DateTimeImmutable $to, string $label): array
    {
        return [
            'from'  => $from->setTime(0, 0, 0)->format(self::FMT),
            'to'    => $to->setTime(23, 59, 59)->format(self::FMT),
            'label' => $label,
        ];
    }
}
