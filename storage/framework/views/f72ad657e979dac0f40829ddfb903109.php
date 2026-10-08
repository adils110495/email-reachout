<!DOCTYPE html>
<html lang="<?php echo e(str_replace('_', '-', app()->getLocale())); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">

    <title><?php echo $__env->yieldContent('title', 'Sign in'); ?> — AI Client Finder</title>

    <?php $logo = asset('images/sabright-logo.png').'?v='.filemtime(public_path('images/sabright-logo.png')); ?>
    <link rel="icon" type="image/png" href="<?php echo e($logo); ?>">

    <link class="main-plugins" href="<?php echo e(asset('assets/css/plugins.css')); ?>" rel="stylesheet">
    <link class="main-css" href="<?php echo e(asset('assets/css/style.css')); ?>" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="<?php echo e(asset('assets/css/app-custom.css')); ?>?v=<?php echo e(filemtime(public_path('assets/css/app-custom.css'))); ?>" rel="stylesheet">

    <style>
        body { background: var(--bs-light, #f5f6fa); }
        .auth-wrap { min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 1.5rem 1rem; }
        .auth-card { width: 100%; max-width: 26rem; }
        .auth-logo { display: block; margin: 0 auto 1.25rem; max-width: 13rem; height: auto; }
    </style>
</head>
<body>
<div class="auth-wrap">
    <div class="auth-card">
        <img class="auth-logo" src="<?php echo e($logo); ?>" alt="SabRight">

        <div class="card">
            <div class="card-body p-4">
                <h4 class="mb-1"><?php echo $__env->yieldContent('heading'); ?></h4>
                <p class="text-muted fs-13 mb-4"><?php echo $__env->yieldContent('subheading'); ?></p>

                <?php if(session('success')): ?>
                    <div class="alert alert-success py-2 fs-13"><?php echo e(session('success')); ?></div>
                <?php endif; ?>

                <?php echo $__env->yieldContent('content'); ?>
            </div>
        </div>

        <p class="text-center text-muted fs-13 mt-3 mb-0">&copy; <?php echo e(date('Y')); ?> AI Client Finder</p>
    </div>
</div>
</body>
</html>
<?php /**PATH /var/www/html/resources/views/layouts/auth.blade.php ENDPATH**/ ?>