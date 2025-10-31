{{-- resources/views/layouts/auth.blade.php --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'Import Control') }} - @yield('title', 'Login')</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
        /* Keep tokens and component looks consistent with admin layout */
        html { font-size: 14px; }
        :root {
            --surface: #f5f7fb;         /* light surface (used for cards) */
            --card: #ffffff;
            --stroke: #e4e8f1;
            --text: #0f172a;
            --auth-bg: #2563eb;         /* primary blue */
            --auth-bg-2: #1d4ed8;       /* deeper blue for gradient */
            --brand-on-blue: #f5f7fb;   /* text color = previous surface */
        }

        body {
            margin: 0;
            font-family: 'Inter', sans-serif;
            background: linear-gradient(180deg, var(--auth-bg) 0%, var(--auth-bg-2) 100%);
            color: var(--text);
        }

        .auth-shell {
            min-height: 100vh;
            display: grid;
            grid-template-rows: auto 1fr auto;
            align-items: start;
        }

        .auth-brand {
            display: flex;
            justify-content: center;
            align-items: center;
            padding-top: 56px;
        }

        .auth-brand .brand-text {
            color: var(--brand-on-blue);
            font-weight: 800;
            font-size: 34px;
            letter-spacing: -.01em;
            text-shadow: 0 6px 18px rgba(0,0,0,.15);
        }

        .auth-main {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
        }

        .card {
            border-radius: 16px !important;
            border: 1px solid var(--stroke) !important;
            background: var(--card) !important;
            box-shadow: 0 12px 24px -18px rgba(15, 23, 42, 0.25);
        }

        .card-header { background: transparent; border-bottom: 1px solid var(--stroke); border-radius: 16px 16px 0 0; }

        .form-label { font-weight: 600; color: #334155; }
        .form-control { padding: .6rem .85rem; border-radius: 10px; border-color: var(--stroke); }
        .form-check-label { color: #475569; }

        .auth-footer { text-align: center; color: rgba(255,255,255,.8); padding-bottom: 24px; font-size: 12px; }
    </style>
    @stack('styles')
    @yield('head')
</head>
<body>
    <div class="auth-shell">
        <div class="auth-brand">
            <div class="brand-text">Panasonic</div>
        </div>

        <main class="auth-main">
            @yield('content')
        </main>

        <div class="auth-footer">
            &copy; {{ date('Y') }} {{ config('app.name', 'Import Control') }}
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    @stack('scripts')
</body>
</html>
