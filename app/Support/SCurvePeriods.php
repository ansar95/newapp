<?php

namespace App\Support;

use App\Enums\SCurveGranularity;
use App\Exceptions\BusinessRuleException;
use Carbon\CarbonInterface;

final class SCurvePeriods
{
    public const SUM_TOLERANCE = 0.05;

    public const MAX_WEEKS = 260;

    public const MAX_MONTHS = 60;

    public static function sumIsComplete(float $sum): bool
    {
        return abs($sum - 100) <= self::SUM_TOLERANCE;
    }

    /**
     * Inclusive buckets from the project start through the target end.
     * Weekly buckets are 7 days from the start date. Monthly buckets follow calendar months.
     *
     * @return list<array{period_start: string, period_end: string}>
     */
    public static function between(CarbonInterface $start, CarbonInterface $end, SCurveGranularity $granularity): array
    {
        $cursor = $start->copy()->startOfDay();
        $last = $end->copy()->startOfDay();

        if ($last->lt($cursor)) {
            throw BusinessRuleException::make('Tanggal selesai proyek harus sama atau setelah tanggal mulai.');
        }

        $limit = $granularity === SCurveGranularity::Week ? self::MAX_WEEKS : self::MAX_MONTHS;
        $periods = [];

        while ($cursor->lte($last)) {
            $periodEnd = $granularity === SCurveGranularity::Week
                ? $cursor->copy()->addDays(6)
                : $cursor->copy()->endOfMonth()->startOfDay();

            if ($periodEnd->gt($last)) {
                $periodEnd = $last->copy();
            }

            $periods[] = [
                'period_start' => $cursor->toDateString(),
                'period_end' => $periodEnd->toDateString(),
            ];

            if (count($periods) > $limit) {
                throw BusinessRuleException::make(
                    $granularity === SCurveGranularity::Week
                        ? 'Rentang proyek terlalu panjang untuk rencana mingguan. Gunakan periode bulanan.'
                        : 'Rentang proyek terlalu panjang untuk rencana bulanan.'
                );
            }

            $cursor = $periodEnd->copy()->addDay();
        }

        return $periods;
    }
}
