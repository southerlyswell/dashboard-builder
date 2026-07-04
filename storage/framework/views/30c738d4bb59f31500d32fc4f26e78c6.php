<?php $__env->startSection('page_title', $client->name); ?>

<?php $__env->startSection('content'); ?>
<div style="max-width:1200px;margin:0 auto;">

    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
        <a href="<?php echo e(route('dashbuilder.projects')); ?>" class="btn-ghost">&#8592; All Clients</a>
        <a href="/dashboard-builder?client=<?php echo e($client->id); ?>" class="btn-orange">&#10011; New Dashboard</a>
    </div>

    <div class="client-info">
        <div class="client-info-top">
            <div>
                <div class="client-info-name"><?php echo e($client->name); ?></div>
                <span class="client-info-cat"><?php echo e($client->industry ?? 'General'); ?></span>
            </div>
            <span class="client-info-status"><?php echo e($client->is_active ? 'Active' : 'Inactive'); ?></span>
        </div>
        <div class="client-info-grid">
            <?php if($client->contact_name): ?><div class="info-item"><div class="lbl">Contact</div><div class="val"><?php echo e($client->contact_name); ?></div></div><?php endif; ?>
            <?php if($client->contact_email): ?><div class="info-item"><div class="lbl">Email</div><div class="val"><?php echo e($client->contact_email); ?></div></div><?php endif; ?>
            <?php if($client->contact_phone): ?><div class="info-item"><div class="lbl">Phone</div><div class="val"><?php echo e($client->contact_phone); ?></div></div><?php endif; ?>
            <?php if($client->city): ?><div class="info-item"><div class="lbl">Location</div><div class="val"><?php echo e($client->city); ?><?php echo e($client->province ? ', ' . $client->province : ''); ?></div></div><?php endif; ?>
        </div>
    </div>

    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
        <h2 style="font-size:16px;color:#f1f5f9;font-weight:600;">Dashboards</h2>
        <span style="font-size:12px;color:#64748b;"><?php echo e($dashboards->count()); ?> dashboard<?php echo e($dashboards->count() !== 1 ? 's' : ''); ?></span>
    </div>

    <?php if($dashboards->count() > 0): ?>
    <div class="dashboards-grid">
        <?php $__currentLoopData = $dashboards; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $dashboard): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <div class="db-card">
            <div class="db-card-title"><?php echo e($dashboard->name); ?></div>
            <div class="db-card-meta">
                <span>&#9679; <?php echo e($dashboard->card_count); ?> cards</span>
                <span>&#9679; <?php echo e($dashboard->updated_at?->format('Y-m-d')); ?></span>
                <?php if($dashboard->is_published): ?><span style="color:#4ade80;">&#9679; Embedded</span><?php endif; ?>
            </div>
            <div class="db-card-actions">
                <a href="/dashboard-builder?project=<?php echo e($dashboard->id); ?>">&#9998; Load &amp; Edit</a>
                <a href="/embed/<?php echo e($dashboard->public_id); ?>" target="_blank">&#9679; View</a>
            </div>
        </div>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </div>
    <?php else: ?>
    <div class="empty-state">
        <h3>No dashboards yet</h3>
        <a href="/dashboard-builder?client=<?php echo e($client->id); ?>" style="color:#FB923C;text-decoration:none;">Create one with AI &#8594;</a>
    </div>
    <?php endif; ?>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\laragon\www\dashboard-builder\resources\views/dashboard-builder/client-detail.blade.php ENDPATH**/ ?>