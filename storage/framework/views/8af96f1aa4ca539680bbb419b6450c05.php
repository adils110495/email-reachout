<!DOCTYPE html>
<html lang="<?php echo e(str_replace('_', '-', app()->getLocale())); ?>">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?php echo e(csrf_token()); ?>">
    <meta name="robots" content="noindex, nofollow">

    <title><?php echo $__env->yieldContent('title', 'AI Client Finder'); ?></title>

    
    <?php $favicon = \App\Models\AppSetting::adminIconUrl(); ?>
    <link rel="icon" type="image/png" href="<?php echo e($favicon); ?>">
    <link rel="shortcut icon" type="image/png" href="<?php echo e($favicon); ?>">
    <link rel="apple-touch-icon" href="<?php echo e($favicon); ?>">

    
    <link href="<?php echo e(asset('assets/vendor/metismenu/dist/metisMenu.min.css')); ?>" rel="stylesheet">
    <link href="<?php echo e(asset('assets/vendor/bootstrap-select/dist/css/bootstrap-select.min.css')); ?>" rel="stylesheet">
    <link class="main-switcher" href="<?php echo e(asset('assets/css/switcher.css')); ?>" rel="stylesheet">

    
    <link class="main-plugins" href="<?php echo e(asset('assets/css/plugins.css')); ?>" rel="stylesheet">
    <link class="main-css" href="<?php echo e(asset('assets/css/style.css')); ?>" rel="stylesheet">

    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">

    
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">

    
    <link href="<?php echo e(asset('assets/css/app-custom.css')); ?>?v=<?php echo e(filemtime(public_path('assets/css/app-custom.css'))); ?>" rel="stylesheet">

    <?php echo $__env->yieldPushContent('styles'); ?>
</head>
<body>


<div id="preloader">
    <div class="lds-ripple">
        <div></div>
        <div></div>
    </div>
</div>



<div id="main-wrapper">

    <?php echo $__env->make('layouts.partials.nav-header', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

    <?php echo $__env->make('layouts.partials.header', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

    <?php echo $__env->make('layouts.partials.sidebar', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

    
    <main class="content-body">

        <?php echo $__env->make('layouts.partials.page-title', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

        <div class="container-fluid">

            
            <?php if(session('success')): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="bi bi-check-circle-fill me-2"></i><?php echo e(session('success')); ?>

                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <?php if(session('error')): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i><?php echo e(session('error')); ?>

                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <?php echo $__env->yieldContent('content'); ?>
        </div>
    </main>
    

    <?php echo $__env->make('layouts.partials.footer', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

</div>



<script>
    // Consumed by deznav-init.js so theme stylesheets resolve from any URL depth.
    window.THEME_ASSET_BASE = "<?php echo e(asset('assets')); ?>/";
</script>
<script src="<?php echo e(asset('assets/vendor/jquery/dist/jquery.min.js')); ?>"></script>
<script src="<?php echo e(asset('assets/vendor/bootstrap/dist/js/bootstrap.bundle.min.js')); ?>"></script>
<script src="<?php echo e(asset('assets/vendor/bootstrap-select/dist/js/bootstrap-select.min.js')); ?>"></script>
<script src="<?php echo e(asset('assets/vendor/metismenu/dist/metisMenu.min.js')); ?>"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="<?php echo e(asset('assets/vendor/i18n/i18n.js')); ?>"></script>
<script src="<?php echo e(asset('assets/js/translator.js')); ?>"></script>
<script src="<?php echo e(asset('assets/js/deznav-init.js')); ?>"></script>
<script>
    // deznav-init.js defaults the header bar to color_12 (#2c2c2c); run it light
    // so the hamburger lines render dark. The nav header's white (the logo's
    // dark "SA" and tagline need a light background) is owned by
    // app-custom.css (.nav-header).
    // Mutating the shared options object keeps this applied on the theme's resize re-init.
    Object.assign(dzSettingsOptions, { headerBg: 'color_1' });
    new dzSettings(dzSettingsOptions);
</script>
<script src="<?php echo e(asset('assets/js/custom.js')); ?>"></script>

<script src="<?php echo e(asset('assets/js/mobile-nav.js')); ?>?v=<?php echo e(filemtime(public_path('assets/js/mobile-nav.js'))); ?>"></script>
<script src="<?php echo e(asset('assets/js/ajax-filters.js')); ?>"></script>


<script>
    document.addEventListener('click', function (e) {
        const toggle = e.target.closest('.table-responsive [data-bs-toggle="dropdown"]');
        if (! toggle || bootstrap.Dropdown.getInstance(toggle)) return;

        bootstrap.Dropdown.getOrCreateInstance(toggle, { popperConfig: { strategy: 'fixed' } });
    }, true);
</script>

<?php echo $__env->make('layouts.partials.notifications', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

<?php echo $__env->yieldPushContent('scripts'); ?>

</body>
</html>
<?php /**PATH /var/www/html/resources/views/layouts/app.blade.php ENDPATH**/ ?>