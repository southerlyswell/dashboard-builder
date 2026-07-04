<!DOCTYPE html>
<html lang="<?php echo e(str_replace('_', '-', app()->getLocale())); ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?php echo e(csrf_token()); ?>">

    <title><?php echo $__env->yieldContent('page_title', 'Dashboard'); ?> - <?php echo e(config('app.name')); ?></title>

    <!-- Design System -->
    <link rel="stylesheet" href="<?php echo e(asset('css/style.css')); ?>?v=<?php echo e(filemtime(public_path('css/style.css'))); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

    <?php echo $__env->yieldPushContent('styles'); ?>
</head>
<body class="page-<?php echo $__env->yieldContent('page_slug', 'dashboard'); ?>" style="background: #0f172a; color: #e2e8f0;">
    <div class="app-layout">
        <!-- Sidebar Navigation -->
        <aside class="sidebar" id="sidebar">
            <!-- Logo area -->
            <div class="logo-area">
                <img src="<?php echo e(asset('icons/acfs-round-01.svg')); ?>" alt="Logo" width="40" height="40">
                <div>
                    <h2 class="logo-title">Dashboard Builder</h2>
                    <p class="logo-subtitle">AI-Powered Dashboards</p>
                </div>
            </div>

            <nav class="nav-sidebar">
                <a href="<?php echo e(route('dashbuilder.projects')); ?>" class="nav-item <?php echo e(request()->routeIs('dashbuilder.*') ? 'active' : ''); ?>">
                    <span class="nav-icon">🎯</span>
                    Dashboard Builder
                </a>
                <div class="nav-divider"></div>
                <a href="<?php echo e(route('logout')); ?>" class="nav-item"
                   onclick="event.preventDefault(); document.getElementById('logout-form').submit();">
                    <span class="nav-icon">🚪</span>
                    Logout
                </a>
                <form id="logout-form" method="POST" action="<?php echo e(route('logout')); ?>" class="hidden">
                    <?php echo csrf_field(); ?>
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
                    <h1><?php echo $__env->yieldContent('page_title', 'Dashboard'); ?></h1>
                    <?php if(auth()->guard()->check()): ?>
                    <div class="user-info">
                        <span class="current-user-label">Admin:</span>
                        <span class="user-name"><?php echo e(Auth::user()->name); ?></span>
                    </div>
                    <?php endif; ?>
                </div>

                <?php echo $__env->yieldContent('content'); ?>

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

    <?php echo $__env->yieldPushContent('scripts'); ?>
</body>
</html>
<?php /**PATH C:\laragon\www\dashboard-builder\resources\views/layouts/app.blade.php ENDPATH**/ ?>