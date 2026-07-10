<?php $codeRev = 7; ?>
<!DOCTYPE html>
<html lang="<?php echo e(str_replace('_', '-', app()->getLocale())); ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?php echo e(csrf_token()); ?>">

    <title><?php echo $__env->yieldContent('page_title', 'Dashboard'); ?> - <?php echo e(config('app.name')); ?></title>
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">

    <link rel="stylesheet" href="/css/dashboard-builder.css">

    <?php echo $__env->yieldPushContent('styles'); ?>
</head>
<body>
    <div class="app-layout">
        <aside class="nav-sidebar-w" id="sidebar">
            <div class="logo-area">
                <span style="font-size:24px;">&#9660;</span>
                <div>
                    <h2 class="logo-title">Dashboard Builder <span style="color:#64748b;font-size:11px;font-weight:400;">[rev <?php echo e($codeRev); ?>]</span></h2>
                    <p class="logo-subtitle">AI-Powered Dashboards</p>
                </div>
            </div>
            <nav class="nav-sidebar">
                <a href="<?php echo e(route('dashbuilder.projects')); ?>" class="nav-item <?php echo e(request()->routeIs('dashbuilder.*') ? 'active' : ''); ?>">
                    <span class="nav-icon">&#9679;</span> Dashboard Builder
                </a>
                <div class="nav-divider"></div>
                <a href="<?php echo e(route('logout')); ?>" class="nav-item"
                   onclick="event.preventDefault(); document.getElementById('logout-form').submit();">
                    <span class="nav-icon">&#9660;</span> Logout
                </a>
                <form id="logout-form" method="POST" action="<?php echo e(route('logout')); ?>" class="hidden">
                    <?php echo csrf_field(); ?>
                </form>
            </nav>
        </aside>

        <div class="main-area">
            <div class="page-header">
                <h1><?php echo $__env->yieldContent('page_title', 'Dashboard'); ?></h1>
                <?php if(auth()->guard()->check()): ?>
                <div class="user-info">
                    <span class="dot"></span> <?php echo e(Auth::user()->name); ?>

                </div>
                <?php endif; ?>
            </div>

            <div class="content-area">
                <?php echo $__env->yieldContent('content'); ?>
            </div>

            <footer class="app-footer">
                Dashboard Builder v1.0
            </footer>
        </div>
    </div>

    <script>
        (function(){
            var btn = document.getElementById('menu-toggle');
            var sidebar = document.getElementById('sidebar');
            var overlay = document.getElementById('sidebar-overlay');
            if (btn) btn.style.display = window.innerWidth <= 768 ? 'block' : 'none';
            window.addEventListener('resize', function(){
                if (btn) btn.style.display = window.innerWidth <= 768 ? 'block' : 'none';
            });
            if (overlay) {
                overlay.addEventListener('click', function(){
                    sidebar.classList.remove('open');
                    overlay.classList.remove('open');
                });
            }
        })();
    </script>

    <?php echo $__env->yieldPushContent('scripts'); ?>
</body>
</html>
<?php /**PATH C:\laragon\www\dashboard-builder\resources\views/layouts/app.blade.php ENDPATH**/ ?>