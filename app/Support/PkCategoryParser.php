<?php
namespace App\Support;

class PkCategoryParser
{
    /**
     * @return array{min_pk:?float, max_pk:?float, min_incl:bool, max_incl:bool}
     */
    public static function parse(string $label): array
    {
        $s = strtoupper(trim($label));
        $s = str_replace(' ', '', $s);
        $s = str_replace('PK', '', $s);

        // 8-10
        if (strpos($s, '-') !== false) {
            [$l, $r] = explode('-', $s, 2);
            return [
                'min_pk' => (float)$l,
                'max_pk' => (float)$r,
                'min_incl' => true,
                'max_incl' => true,
            ];
        }
        // <8
        if (str_starts_with($s, '<')) {
            $v = (float)substr($s, 1);
            return ['min_pk'=>null,'max_pk'=>$v,'min_incl'=>true,'max_incl'=>false];
        }
        // >10
        if (str_starts_with($s, '>')) {
            $v = (float)substr($s, 1);
            return ['min_pk'=>$v,'max_pk'=>null,'min_incl'=>false,'max_incl'=>true];
        }
        // angka tunggal
        if (is_numeric($s)) {
            $v = (float)$s;
            return ['min_pk'=>$v,'max_pk'=>$v,'min_incl'=>true,'max_incl'=>true];
        }
        // fallback
        return ['min_pk'=>null,'max_pk'=>null,'min_incl'=>true,'max_incl'=>true];
    }
}

