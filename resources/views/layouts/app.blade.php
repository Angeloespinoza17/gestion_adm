<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width,initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @include('partials.favicon')
    <title>CNSC Valdivia</title>
    <meta content="Skote is a fully featured premium Vuejs admin dashboard template built on top of awesome Bootstrap 5"
        name="description" />
    @if (request()->is('login'))
        @php
            $loginStylesheet = 'build/css/login.min.css';
            $viteManifestPath = public_path('build/manifest.json');

            if (is_file($viteManifestPath)) {
                $viteManifest = json_decode((string) file_get_contents($viteManifestPath), true);
                $loginEntry = is_array($viteManifest)
                    ? ($viteManifest['resources/js/views/account/login.vue'] ?? [])
                    : [];
                $manifestStylesheet = is_array($loginEntry)
                    ? ($loginEntry['css'][0] ?? null)
                    : null;

                if (is_string($manifestStylesheet) && $manifestStylesheet !== '') {
                    $loginStylesheet = 'build/'.ltrim($manifestStylesheet, '/');
                }
            }

            $loginStylesheetVersion = file_exists(public_path($loginStylesheet))
                ? filemtime(public_path($loginStylesheet))
                : '1';
        @endphp
        <link rel="stylesheet" href="{{ asset($loginStylesheet) }}?v={{ $loginStylesheetVersion }}">
        <link rel="preload" as="image" href="{{ asset('brand/logo-cnsc.png') }}" fetchpriority="high">
        <style>
            img[data-cnsc-auth-logo] {
                display: block;
                width: 44px;
                height: 44px;
                object-fit: contain;
            }

            @media (max-width: 991.98px) {
                img[data-cnsc-auth-logo] {
                    width: 38px;
                    height: 38px;
                }
            }
        </style>
    @endif
    <!-- vite css and js  -->
    @vite(['resources/sass/app.scss', 'resources/js/app.js'])

    @yield('css')
</head>

<body data-sidebar="dark" data-layout-mode="light">
    <div id="app">
        @yield('content')
    </div>
    <!-- built files will be auto injected -->
    @stack('scripts')
    @yield('js')
    
</body>
</html>
