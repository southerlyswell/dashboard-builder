<?php $__env->startSection('page_title', 'Login'); ?>

<?php $__env->startSection('content'); ?>
<div class="login-container mt-4">
    <div class="login-header">
        <h1>Dashboard Builder</h1>
        <p>AI-Powered Dashboard Platform</p>
    </div>

    <div class="login-form">
        <?php if($errors->any()): ?>
            <div class="alert alert-danger">
                <?php echo e($errors->first()); ?>

            </div>
        <?php endif; ?>

        <form method="POST" action="<?php echo e(route('login')); ?>">
            <?php echo csrf_field(); ?>
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" value="<?php echo e(old('email')); ?>" required autofocus>
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required>
            </div>
            <div class="form-group">
                <label>
                    <input type="checkbox" name="remember"> Remember me
                </label>
            </div>
            <button type="submit" class="btn-login" style="width:100%">Login</button>
        </form>
        <div style="text-align:center;margin-top:16px;">
            <a href="<?php echo e(route('register')); ?>" style="color:#FB923C;text-decoration:none;font-size:13px;">Don't have an account? Register</a>
        </div>
    </div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.guest', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\laragon\www\dashboard-builder\resources\views/auth/login.blade.php ENDPATH**/ ?>