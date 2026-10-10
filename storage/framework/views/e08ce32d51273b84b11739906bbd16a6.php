<?php
    // Uploaded under Settings > Branding (falls back to the shipped logo).
    $brandLogo = \App\Models\AppSetting::adminLogoUrl();
    $brandIcon = \App\Models\AppSetting::adminIconUrl();
    $hasIcon   = \App\Models\AppSetting::isCustom(\App\Models\AppSetting::ADMIN_ICON);
?>


<div class="nav-header">
    <a href="<?php echo e(route('dashboard')); ?>" class="brand-logo" aria-label="SabRight">
        
        <img class="logo-abbr <?php echo e($hasIcon ? 'is-icon' : ''); ?>" src="<?php echo e($brandIcon); ?>" alt="">
        <img class="brand-title" src="<?php echo e($brandLogo); ?>" alt="SabRight">
    </a>
    <div class="nav-control">
        <div class="hamburger">
            <span class="line"></span>
            <span class="line"></span>
            <span class="line"></span>
        </div>
    </div>
</div>

<?php /**PATH /var/www/html/resources/views/layouts/partials/nav-header.blade.php ENDPATH**/ ?>