<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('page_title', 'Dashboard') - {{ config('app.name') }}</title>

    <!-- Design System -->
    <link rel="stylesheet" href="{{ asset('css/style.css') }}?v={{ filemtime(public_path('css/style.css')) }}">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

    @stack('styles')
</head>
<body class="page-@yield('page_slug', 'dashboard')">
    <div class="app-layout">
        <!-- Sidebar Navigation -->
        <aside class="sidebar" id="sidebar">
            <!-- Logo area -->
            <div class="logo-area">
                <img src="{{ asset('icons/acfs-round-01.svg') }}" alt="Logo" width="40" height="40">
                <div>
                    <h2 class="logo-title">Dashboard Builder</h2>
                    <p class="logo-subtitle">AI-Powered Dashboards</p>
                </div>
            </div>

            <nav class="nav-sidebar">
                <a href="{{ route('dashbuilder.projects') }}" class="nav-item {{ request()->routeIs('dashbuilder.*') ? 'active' : '' }}">
                    <span class="nav-icon">🎯</span>
                    Dashboard Builder
                </a>
                <div class="nav-divider"></div>
                <a href="{{ route('logout') }}" class="nav-item"
                   onclick="event.preventDefault(); document.getElementById('logout-form').submit();">
                    <span class="nav-icon">🚪</span>
                    Logout
                </a>
                <form id="logout-form" method="POST" action="{{ route('logout') }}" class="hidden">
                    @csrf
                </form>
            </nav>
        </aside>

        <!-- Mobile menu toggle -->
        <button id="menu-toggle" onclick="document.getElementById('sidebar').classList.toggle('open'); document.getElementById('sidebar-overlay').classList.toggle('open');">
            ☰
        </button>

        <!-- Sidebar overlay for mobile -->
        <div id="sidebar-overlay" onclick="document.getElementById('sidebar').classList.remove('open'); this.classList.remove('open');"></div>

        <!-- Main Content Area -->
        <main class="main-content">
            <div class="content-wrapper">
                <!-- Page Header -->
                <div class="page-header">
                    <h1>@yield('page_title', 'Dashboard')</h1>
                    @auth
                    <div class="user-info">
                        <span class="current-user-label">Admin:</span>
                        <span class="user-name">{{ Auth::user()->name }}</span>
                    </div>
                    @endauth
                </div>

                @yield('content')

                <!-- Footer -->
                <footer class="app-footer">
                    <p class="text-sm text-muted">Dashboard Builder v1.0</p>
                </footer>
            </div>
        </main>
    </div>

    <script>
        function updateMenuToggle() {
            const menuToggle = document.getElementById('menu-toggle');
            const overlay = document.getElementById('sidebar-overlay');
            if (menuToggle) {
                menuToggle.style.display = window.innerWidth <= 1024 ? 'block' : 'none';
            }
            if (overlay) {
                overlay.style.display = 'none';
            }
        }
        updateMenuToggle();
        window.addEventListener('resize', updateMenuToggle);
    </script>

    @stack('scripts')
</body>
</html>
