<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

class DbExpression
{
    /**
     * Normalize a line number column to integer across drivers.
     */
    public static function lineNoInt(string $qualifiedColumn): string
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'sqlsrv') {
            return "TRY_CONVERT(int, NULLIF($qualifiedColumn, ''))";
        }

        return "CAST(regexp_replace(COALESCE($qualifiedColumn,''), '[^0-9]', '', 'g') AS int)";
    }

    /**
     * Strip leading zeros for equality comparisons across drivers.
     */
    public static function lineNoTrimmed(string $qualifiedColumn): string
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'sqlsrv') {
            return "CONVERT(varchar(50), TRY_CONVERT(int, NULLIF($qualifiedColumn, '')))";
        }

        return "regexp_replace($qualifiedColumn::text, '^0+', '')";
    }

    /**
     * Month bucket for date/datetime columns across drivers.
     */
    public static function monthBucket(string $qualifiedColumn): string
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'sqlsrv') {
            return "DATEFROMPARTS(YEAR($qualifiedColumn), MONTH($qualifiedColumn), 1)";
        }

        return "DATE_TRUNC('month', $qualifiedColumn)";
    }

    /**
     * Cross-driver string aggregation.
     */
    public static function stringAgg(string $expression, string $separator = ', ', bool $distinct = false, ?string $orderBy = null): string
    {
        $driver = DB::connection()->getDriverName();
        $order = $orderBy ?: $expression;

        if ($driver === 'sqlsrv') {
            // SQL Server requires WITHIN GROUP; DISTINCT is not supported directly
            return "STRING_AGG($expression, '$separator') WITHIN GROUP (ORDER BY $order)";
        }

        $distinctSql = $distinct ? 'DISTINCT ' : '';

        return "STRING_AGG($distinctSql$expression, '$separator')";
    }
}
