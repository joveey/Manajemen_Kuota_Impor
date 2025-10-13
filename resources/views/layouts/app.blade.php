<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Import Control') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @php($__viteV = app()->environment('production') ? '' : ('?v=' . time()))
        @vite(["resources/css/app.css$__viteV", "resources/js/app.js$__viteV"])
        <style>
            /* Reduce base font size across app pages */
            html { font-size: 14px; }
        </style>
    </head>
    <body class="font-sans antialiased">
        <div class="min-h-screen bg-gray-100">
            @include('layouts.navigation')

            <!-- Page Heading -->
            @if (View::hasSection('header') || isset($header))
                <header class="bg-white shadow">
                    <div class="max-w-[1400px] mx-auto py-6 px-4 md:px-6">
                        @hasSection('header')
                            @yield('header')
                        @else
                            {{ $header ?? '' }}
                        @endif
                    </div>
                </header>
            @endif

            <!-- Page Content -->
            <main>
                <div class="max-w-[1400px] mx-auto px-4 md:px-6 py-6">
                    @hasSection('content')
                        @yield('content')
                    @else
                        {{ $slot ?? '' }}
                    @endif
                </div>
            </main>
        </div>
        @stack('scripts')
    </body>
</html>
