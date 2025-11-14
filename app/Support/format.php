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
            'users' => 'Users',
            'roles' => 'Roles',
            'permissions' => 'Permissions',
            'analytics' => 'Analytics',
            'reports' => 'Reports',
            'mapping' => 'Mapping',
            'mapped' => 'Mapped',
            'unmapped' => 'Unmapped',
            'hs_pk' => 'HS PK',
            'hs-pk' => 'HS PK',
            'po_progress' => 'PO Progress',
            'purchase_order' => 'Purchase Order',
            'purchase_orders' => 'Purchase Order',
            'master-data' => 'Master Data',
            'admins' => 'Admin',
            'audit-logs' => 'Audit Log',
        ];

        $actionMap = [
            'index' => 'List',
            'create' => 'Create',
            'store' => 'Store',
            'edit' => 'Edit',
            'update' => 'Update',
            'destroy' => 'Destroy',
            'delete' => 'Delete',
            'show' => 'Show',
            'upload' => 'Upload',
            'preview' => 'Preview',
            'publish' => 'Publish',
            'form' => 'Form',
            'page' => 'Page',
            'export' => 'Export',
            'import' => 'Import',
            'bulk' => 'Bulk Update',
            'move' => 'Move Split Quota',
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

        return $module.' - '.$action;
    }
}

if (! function_exists('audit_activity_label')) {
    /**
     * Build an informative feature/action label from route + payload.
     */
    function audit_activity_label(?string $route, $method, $description): string
    {
        $base = audit_route_label($route);

        // Normalize description to array
        $desc = [];
        if (is_array($description)) {
            $desc = $description;
        } elseif (is_string($description)) {
            $trim = trim($description);
            if ($trim !== '') {
                $decoded = json_decode($trim, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    $desc = $decoded;
                }
            }
        }

        $extra = null;
        $tokens = array_values(array_filter(explode('.', (string) $route)));
        $lower = array_map('strtolower', $tokens);
        $set = array_flip($lower);

        // Helper to find uploaded filename in payload
        $findUploaded = function (array $d): ?string {
            foreach ($d as $k => $v) {
                if (is_string($v) && str_starts_with($v, 'uploaded_file:')) {
                    return substr($v, strlen('uploaded_file:'));
                }
                if (is_array($v)) {
                    foreach ($v as $vv) {
                        if (is_string($vv) && str_starts_with($vv, 'uploaded_file:')) {
                            return substr($vv, strlen('uploaded_file:'));
                        }
                    }
                }
            }
            return null;
        };

        // HS PK Manual create/store
        if ((isset($set['hs_pk']) || isset($set['hs-pk'])) && (isset($set['manual']) || isset($set['manuals']))) {
            $parts = [];
            if (!empty($desc['product_model'])) {
                $parts[] = 'Model '.(string) $desc['product_model'];
            }
            if (isset($desc['quantity'])) {
                $qty = is_numeric($desc['quantity']) ? (int) $desc['quantity'] : (string) $desc['quantity'];
                $parts[] = $qty.' unit';
            }
            if (isset($desc['unit_price']) && $desc['unit_price'] !== '') {
                $parts[] = 'Harga @ '.fmt_qty($desc['unit_price']);
            }
            if ($parts) {
                $extra = implode(' • ', $parts);
            }
        }

        // Import GR upload
        if (isset($set['imports']) && isset($set['gr']) && (isset($set['upload']) || isset($set['import']))) {
            $parts = [];
            if ($fname = $findUploaded($desc)) { $parts[] = 'File: '.$fname; }
            foreach (['inserted' => 'Inserted', 'updated' => 'Updated', 'skipped' => 'Skipped', 'errors' => 'Errors'] as $k => $label) {
                if (isset($desc[$k]) && is_numeric($desc[$k])) { $parts[] = $label.': '.(int)$desc[$k]; }
            }
            if ($parts) { $extra = implode(' • ', $parts); }
        }

        // Import GR publish
        if (isset($set['imports']) && isset($set['gr']) && isset($set['publish'])) {
            $parts = [];
            foreach (['inserted' => 'Inserted', 'updated' => 'Updated', 'skipped' => 'Skipped', 'errors' => 'Errors', 'count' => 'Total'] as $k => $label) {
                if (isset($desc[$k]) && is_numeric($desc[$k])) { $parts[] = $label.': '.(int)$desc[$k]; }
            }
            if ($parts) { $extra = implode(' • ', $parts); }
        }

        // Open PO import
        if ((isset($set['openpo']) || isset($set['open-po'])) && (isset($set['import']) || isset($set['upload']))) {
            $parts = [];
            if ($fname = $findUploaded($desc)) { $parts[] = 'File: '.$fname; }
            foreach (['created' => 'Created', 'updated' => 'Updated', 'duplicated' => 'Duplicated', 'invalid' => 'Invalid'] as $k => $label) {
                if (isset($desc[$k]) && is_numeric($desc[$k])) { $parts[] = $label.': '.(int)$desc[$k]; }
            }
            if ($parts) { $extra = implode(' • ', $parts); }
        }

        // Purchase Orders Voyage Bulk/Move: add concise counts if available
        if (in_array('purchase-orders', $lower, true) && in_array('voyage', $lower, true)) {
            // Try to infer from payload keys
            $parts = [];
            if (isset($desc['saved_rows']) && is_numeric($desc['saved_rows'])) {
                $parts[] = 'Lines updated: '.(int)$desc['saved_rows'];
            } elseif (isset($desc['rows']) && is_array($desc['rows'])) {
                $parts[] = 'Lines submitted: '.count($desc['rows']);
            }
            if (isset($desc['splits']) && is_array($desc['splits'])) {
                $ins = $upd = $del = 0;
                foreach ($desc['splits'] as $sp) {
                    $sid = (int)($sp['id'] ?? 0);
                    $isDel = (bool)($sp['delete'] ?? false);
                    if ($sid > 0 && $isDel) { $del++; }
                    elseif ($sid > 0) { $upd++; }
                    elseif (!empty($sp)) { $ins++; }
                }
                $parts[] = 'Splits: add '.$ins.' upd '.$upd.' del '.$del;
            }
            if ($parts) { $extra = implode(' • ', $parts); }
        }

        // Users
        if (isset($set['users'])) {
            if (!empty($desc['name'])) {
                $extra = 'Pengguna: '.(string) $desc['name'];
            } elseif (!empty($desc['email'])) {
                $extra = 'Email: '.(string) $desc['email'];
            }
        }

        return $extra ? ($base.' — '.$extra) : $base;
    }
}



if (! function_exists('audit_page_label')) {
    function audit_page_label(?string $route, ?string $path): string {
        if ($route) {
            $tokens = array_values(array_filter(explode('.', $route), function ($t) {
                return $t !== '' && !in_array($t, ['admin','api','web'], true);
            }));
            $actionTokens = ['index','create','store','edit','update','destroy','delete','show','upload','preview','publish','form','page','export','import','bulk','move'];
            $lower = array_map('strtolower', $tokens);
            $set = array_flip($lower);
            if ((isset($set['hs_pk']) || isset($set['hs-pk'])) && isset($set['manual'])) {
                return 'Input HS PK';
            }
            if (isset($set['imports']) && isset($set['gr']) && isset($set['upload'])) {
                return 'Upload GR';
            }
            if (isset($set['imports']) && isset($set['gr']) && isset($set['publish'])) {
                return 'Publish GR';
            }
            $moduleTokens = array_values(array_filter($tokens, function ($tk) use ($actionTokens) {
                return !in_array(strtolower($tk), $actionTokens, true);
            }));
            if (!empty($moduleTokens)) {
                $parts = [];
                foreach ($moduleTokens as $tk) {
                    $parts[] = ucwords(str_replace(['_','-'], ' ', $tk));
                }
                return implode(' ', $parts);
            }
        }
        $p = trim((string) $path);
        if ($p === '') return '-';
        $p = trim($p, '/');
        if ($p === '') return '/';
        $seg = explode('/', $p);
        $slice = array_slice($seg, -2);
        $title = ucwords(str_replace(['-','_'], ' ', implode(' ', $slice)));
        return $title !== '' ? $title : (string) $path;
    }
}
