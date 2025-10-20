<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'Import Control') }} - @yield('title', 'Halaman Error')</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">

    <style>
        :root {
            --primary: #2563eb;
            --surface: #f5f7fb;
            --text: #0f172a;
            --muted: #6b7280;
        }

        * {
            box-sizing: border-box;
        }

        html, body {
            margin: 0;
            font-family: 'Inter', sans-serif;
            background: var(--surface);
            color: var(--text);
        }

        .error-shell {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        .app-bar {
            min-height: 88px;
            background: #ffffff;
            border-bottom: 1px solid rgba(148, 163, 184, 0.18);
            display: flex;
            flex-direction: column;
            gap: 18px;
            padding: 20px 32px;
            backdrop-filter: blur(14px);
            box-shadow: 0 18px 36px -30px rgba(15, 23, 42, 0.4);
        }

        .app-bar__masthead {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 18px;
            width: 100%;
        }

        .app-bar-brand {
            display: inline-flex;
            align-items: center;
        }

        .app-bar-brand img {
            width: 140px;
            height: auto;
            display: block;
        }

        .user-pill {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            background: rgba(37, 99, 235, 0.1);
            border: 1px solid rgba(37, 99, 235, 0.15);
            padding: 6px 12px;
            border-radius: 999px;
            font-size: 13px;
            color: var(--muted);
        }

        .user-pill__avatar {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: var(--primary);
            color: #fff;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
        }

        .user-pill__name {
            font-weight: 600;
            color: var(--text);
        }

        .user-pill__email {
            font-size: 11px;
        }

        .bar-left {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .bar-left h1 {
            font-size: 24px;
            font-weight: 700;
            margin: 0;
            color: var(--text);
            letter-spacing: -0.01em;
        }

        .bar-meta {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }

        .meta-pill {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 6px 12px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 600;
            color: #1d4ed8;
            background: rgba(37, 99, 235, 0.12);
            border: 1px solid rgba(37, 99, 235, 0.18);
        }

        .meta-pill svg {
            width: 16px;
            height: 16px;
        }

        .meta-pill.neutral {
            color: #475569;
            background: rgba(148, 163, 184, 0.1);
            border-color: rgba(148, 163, 184, 0.2);
        }

        .error-main {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 64px 16px;
        }

        .empty-state {
            max-width: 560px;
            width: 100%;
            background: #fff;
            border-radius: 20px;
            padding: 48px;
            box-shadow: 0 24px 60px -38px rgba(15, 23, 42, 0.25);
            text-align: center;
        }

        .empty-state__icon {
            width: 64px;
            height: 64px;
            border-radius: 16px;
            background: rgba(37, 99, 235, 0.12);
            color: var(--primary);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            margin-bottom: 18px;
        }

        .empty-state__title {
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 12px;
        }

        .empty-state__subtitle {
            margin: 0 0 28px;
            color: var(--muted);
        }

        .empty-state__actions {
            display: inline-flex;
            gap: 12px;
            flex-wrap: wrap;
            justify-content: center;
        }
    </style>
</head>
<body>
    @php
        $displayDate = now()->format('l, d F Y');
        $user = auth()->user();
        $initials = $user ? mb_strtoupper(mb_substr($user->name, 0, 2)) : null;
    @endphp

    <div class="error-shell">
        <header class="app-bar">
            <div class="app-bar__masthead">
                <span class="app-bar-brand">
                    <img src="{{ asset('images/panasonic-logo.svg') }}" alt="Panasonic logo">
                </span>
                @if($user)
                    <div class="user-pill">
                        <span class="user-pill__avatar">{{ $initials }}</span>
                        <div>
                            <div class="user-pill__name">{{ $user->name }}</div>
                            <div class="user-pill__email">{{ $user->email }}</div>
                        </div>
                    </div>
                @else
                    <a href="{{ route('login') }}" class="btn btn-sm btn-outline-primary">Masuk</a>
                @endif
            </div>
            <div class="bar-left">
                <h1>@yield('title', 'Halaman Error')</h1>
                <div class="bar-meta">
                    <span class="meta-pill neutral">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        {{ $displayDate }}
                    </span>
                    @if($user)
                        <span class="meta-pill">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14c-4 0-7 2-7 4v2h14v-2c0-2-3-4-7-4z" />
                            </svg>
                            {{ $user->name }}
                        </span>
                    @endif
                </div>
            </div>
        </header>

        <main class="error-main">
            @yield('content')
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
