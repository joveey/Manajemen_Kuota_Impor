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

if (! function_exists('audit_route_label')) {
    function audit_route_label(?string $route): string {
        if (!$route) return '-';
        $tokens = array_values(array_filter(explode('.', $route), function ($t) {
            return $t !== '' && !in_array($t, ['admin','api','web'], true);
        }));

        if (empty($tokens)) return '-';

        $map = [
            'imports' => 'Import',
            'gr' => 'GR',
            'invoices' => 'Invoice',
            'quotas' => 'Quota',
            'openpo' => 'Open PO',
            'open-po' => 'Open PO',
            'users' => 'Pengguna',
            'roles' => 'Peran',
            'permissions' => 'Izin',
            'analytics' => 'Analitik',
            'reports' => 'Laporan',
            'mapping' => 'Pemetaan',
            'mapped' => 'Sudah Pemetaan',
            'unmapped' => 'Belum Pemetaan',
            'hs_pk' => 'HS → PK',
            'hs-pk' => 'HS → PK',
            'po_progress' => 'Progres PO',
            'purchase_order' => 'Purchase Order',
            'purchase_orders' => 'Purchase Order',
            'master-data' => 'Master Data',
            'admins' => 'Admin',
            'audit-logs' => 'Audit Log',
        ];

        $actionMap = [
            'index' => 'Daftar',
            'create' => 'Buat',
            'store' => 'Simpan',
            'edit' => 'Ubah',
            'update' => 'Ubah',
            'destroy' => 'Hapus',
            'delete' => 'Hapus',
            'show' => 'Lihat',
            'upload' => 'Unggah',
            'preview' => 'Pratinjau',
            'publish' => 'Terbitkan',
            'form' => 'Form',
            'page' => 'Halaman',
            'export' => 'Ekspor',
            'import' => 'Impor',
        ];

        // Pick action from right-most known token
        $rev = array_reverse($tokens);
        $action = null;
        foreach ($rev as $tk) {
            if (isset($actionMap[$tk])) { $action = $actionMap[$tk]; break; }
        }
        if (!$action) { $last = $tokens[count($tokens)-1]; $action = ucwords(str_replace(['_','-'], ' ', $last)); }

        // Build module label from remaining tokens (excluding action candidates like 'index','page')
        $moduleTokens = array_values(array_filter($tokens, function ($tk) use ($actionMap) {
            return !isset($actionMap[$tk]);
        }));
        if (empty($moduleTokens)) return $action;

        $parts = [];
        foreach ($moduleTokens as $tk) {
            $parts[] = $map[$tk] ?? ucwords(str_replace(['_','-'], ' ', $tk));
        }
        $module = trim(implode(' ', $parts));

        return $module.' · '.$action;
    }
}
