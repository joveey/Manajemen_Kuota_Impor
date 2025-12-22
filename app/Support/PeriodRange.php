<?php

namespace App\Support;

use Illuminate\Support\Carbon;

class PeriodRange
{
    /**
     * Build an inclusive start / exclusive end range for a specific month & year.
     *
     * @return array{Carbon,Carbon}
     */
    public static function monthYear(?int $month, ?int $year): array
    {
        $now = Carbon::now();
        $month = (int) ($month ?: $now->month);
        $year = (int) ($year ?: $now->year);

        if ($month < 1 || $month > 12) {
            $month = $now->month;
        }

        if ($year < 2000 || $year > 2100) {
            $year = $now->year;
        }

        $start = Carbon::create($year, $month, 1, 0, 0, 0, $now->timezone);
        $end = $start->copy()->addMonth();

        return [$start, $end];
    }

    public static function periodKey(Carbon $start): string
    {
        return $start->format('Y-m');
    }
}
