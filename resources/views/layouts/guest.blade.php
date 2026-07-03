<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('page_title', 'Login') - {{ config('app.name') }}</title>

    <link rel="stylesheet" href="{{ asset('css/style.css') }}">
    <link rel="stylesheet" href="{{ asset('css/layouts/login.css') }}">

    @stack('styles')
</head>
<body class="page-login">
    <main class="login-page-main">
        <div class="login-page-wrapper">
            @yield('content')
        </div>
    </main>

    @stack('scripts')
</body>
</html>
