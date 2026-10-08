<?php
    /**
     * Renders one metisMenu entry and recurses into its children, so a nav
     * item can nest to any depth. $depth 0 = top level (gets the icon).
     */
    $depth       = $depth ?? 0;
    $hasChildren = ! empty($item['children']);
    $isActive    = request()->routeIs($item['active'] ?? []);
    $href        = $hasChildren || empty($item['route'])
        ? 'javascript:void(0);'
        : route($item['route']);
?>

<li class="<?php echo e($isActive ? 'mm-active' : ''); ?>">
    <a class="<?php echo e($hasChildren ? 'has-arrow' : ''); ?> <?php echo e($isActive && ! $hasChildren ? 'mm-active' : ''); ?>"
       href="<?php echo e($href); ?>"
       aria-expanded="<?php echo e($isActive && $hasChildren ? 'true' : 'false'); ?>">
        <?php if($depth === 0): ?>
            <div class="menu-icon">
                <i class="bi <?php echo e($item['icon'] ?? 'bi-dot'); ?>"></i>
            </div>
            <span class="nav-text"><?php echo e($item['label']); ?></span>
        <?php else: ?>
            <?php echo e($item['label']); ?>

        <?php endif; ?>
    </a>

    <?php if($hasChildren): ?>
        <ul class="<?php echo e($isActive ? 'mm-show' : ''); ?>" aria-expanded="<?php echo e($isActive ? 'true' : 'false'); ?>">
            <?php $__currentLoopData = $item['children']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $child): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <?php echo $__env->make('layouts.partials.sidebar-item', ['item' => $child, 'depth' => $depth + 1], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </ul>
    <?php endif; ?>
</li>
<?php /**PATH /var/www/html/resources/views/layouts/partials/sidebar-item.blade.php ENDPATH**/ ?>