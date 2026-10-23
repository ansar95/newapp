<?php

namespace App\Support;

use App\Enums\SCurveGranularity;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

final class SCurveItemPlan
{
    /**
     * Spread each item's value weight evenly across the days from its start through its finish,
     * then total those days into weekly buckets. The weights must already be shares of 100.
     *
     * @param  list<array{start: CarbonInterface, finish: CarbonInterface, weight: float}>  $items
     * @return list<array{period_start: string, period_end: string, planned_percent: float}>
     */
    public static function weekly(array $items): array
    {
        $rangeStart = collect($items)->min(fn (array $item) => $item['start']->copy()->startOfDay());
        $rangeEnd = collect($items)->max(fn (array $item) => $item['finish']->copy()->startOfDay());
        $weeks = SCurvePeriods::between($rangeStart, $rangeEnd, SCurveGranularity::Week);
        $amounts = array_fill(0, count($weeks), 0.0);
        $weekStarts = array_map(
            fn (array $week) => Carbon::parse($week['period_start'])->startOfDay(),
            $weeks,
        );
        $weekEnds = array_map(
            fn (array $week) => Carbon::parse($week['period_end'])->startOfDay(),
            $weeks,
        );

        foreach ($items as $item) {
            $start = $item['start']->copy()->startOfDay();
            $finish = $item['finish']->copy()->startOfDay();
            $days = 0;
            $cursor = $start->copy();

            while ($cursor->lte($finish)) {
                $days++;
                $cursor->addDay();
            }

            if ($days === 0 || $item['weight'] <= 0) {
                continue;
            }

            $daily = $item['weight'] / $days;
            $cursor = $start->copy();

            while ($cursor->lte($finish)) {
                $index = self::weekIndex($weekStarts, $weekEnds, $cursor);
                $amounts[$index] += $daily;
                $cursor->addDay();
            }
        }

        $rounded = array_map(fn (float $amount) => round($amount, 4), $amounts);
        $difference = round(100 - array_sum($rounded), 4);
        $last = count($rounded) - 1;
        $rounded[$last] = round($rounded[$last] + $difference, 4);

        $periods = [];

        foreach ($weeks as $index => $week) {
            $periods[] = [
                'period_start' => $week['period_start'],
                'period_end' => $week['period_end'],
                'planned_percent' => $rounded[$index],
            ];
        }

        return $periods;
    }

    /**
     * @param  list<CarbonInterface>  $starts
     * @param  list<CarbonInterface>  $ends
     */
    private static function weekIndex(array $starts, array $ends, CarbonInterface $day): int
    {
        foreach ($starts as $index => $start) {
            if ($day->gte($start) && $day->lte($ends[$index])) {
                return $index;
            }
        }

        return count($starts) - 1;
    }
}
