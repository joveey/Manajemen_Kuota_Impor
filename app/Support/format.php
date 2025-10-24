<?php

if (! function_exists('fmt_qty')) {
    function fmt_qty($n, $dec = 2) {
        if ($n === null) return '0';
        $s = number_format((float)$n, $dec, '.', ',');
        $s = rtrim($s, '0');
        $s = rtrim($s, '.');
        return $s === '' ? '0' : $s;
    }
}

