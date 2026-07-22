<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width,initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="icon" href="{{ url('favicon.ico') }}">
    <title>CNSC Gestión</title>
    <meta content="Skote is a fully featured premium Vuejs admin dashboard template built on top of awesome Bootstrap 5"
        name="description" />
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
