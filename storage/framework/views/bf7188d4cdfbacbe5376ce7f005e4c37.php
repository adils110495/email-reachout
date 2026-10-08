
<div class="deznav">
    <div class="deznav-scroll">
        <ul class="metismenu" id="menu">

            <?php $__currentLoopData = $navSidebar; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $group): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>

                <?php if(! empty($group['title'])): ?>
                    <li class="menu-title"><?php echo e(Str::upper($group['title'])); ?></li>
                <?php endif; ?>

                <?php $__currentLoopData = $group['items']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <?php echo $__env->make('layouts.partials.sidebar-item', ['item' => $item, 'depth' => 0], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>

            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>

        </ul>
    </div>
</div>

<?php /**PATH /var/www/html/resources/views/layouts/partials/sidebar.blade.php ENDPATH**/ ?>