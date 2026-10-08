<?php
    // filemtime busts the browser cache whenever the logo file is replaced.
    $brandLogo = asset('images/sabright-logo.png').'?v='.filemtime(public_path('images/sabright-logo.png'));
?>


<div class="nav-header">
    <a href="<?php echo e(route('dashboard')); ?>" class="brand-logo" aria-label="SabRight">
        
        <img class="logo-abbr" src="<?php echo e($brandLogo); ?>" alt="">
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