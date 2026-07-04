<!DOCTYPE html>
<html lang="<?php echo e(str_replace('_', '-', app()->getLocale())); ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?php echo e(csrf_token()); ?>">

    <title><?php echo $__env->yieldContent('page_title', 'Login'); ?> - <?php echo e(config('app.name')); ?></title>

    <link rel="stylesheet" href="/css/dashboard-builder.css">

    <?php echo $__env->yieldPushContent('styles'); ?>
</head>
<body class="page-login">
    <main class="login-page-main">
        <div class="login-page-wrapper">
            <?php echo $__env->yieldContent('content'); ?>
        </div>
    </main>

    <?php echo $__env->yieldPushContent('scripts'); ?>
</body>
</html>
<?php /**PATH C:\laragon\www\dashboard-builder\resources\views/layouts/guest.blade.php ENDPATH**/ ?>