<?php

if (! function_exists('num')) {
    /** Format a number, trimming useless trailing zeros (95.50 → 95.5, 100.00 → 100). */
    function num($value, int $decimals = 2): string
    {
        if ($value === null || $value === '') {
            return '—';
        }
        $formatted = number_format((float) $value, $decimals);

        return str_contains($formatted, '.') ? rtrim(rtrim($formatted, '0'), '.') : $formatted;
    }
}

if (! function_exists('weight_class')) {
    function weight_class(float $total): string
    {
        return abs($total - 100) < 0.01 ? 'weight-ok' : 'weight-warn';
    }
}
