<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo e(csrf_token()); ?>">
    <title><?php echo $__env->yieldContent('title', 'AI Client Finder'); ?></title>
    <link rel="icon" type="image/png" sizes="32x32" href="<?php echo e(asset('hes-favicon.png?v=4')); ?>">
    <link rel="shortcut icon" type="image/png" href="<?php echo e(asset('hes-favicon.png?v=4')); ?>">

    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">

    <style>
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
        }
        .navbar-brand {
            font-weight: 700;
            font-size: 1.4rem;
        }
        .badge-new      { background-color: #6c757d; }
        .badge-sent     { background-color: #0d6efd; }
        .badge-failed   { background-color: #dc3545; }
        .badge-replied  { background-color: #198754; }
        .table > :not(caption) > * > * {
            vertical-align: middle;
        }
        .search-card {
            border: none;
            box-shadow: 0 2px 12px rgba(0,0,0,.08);
            border-radius: 12px;
        }
        .btn-find {
            min-width: 140px;
        }
    </style>

    <?php echo $__env->yieldPushContent('styles'); ?>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-4">
    <div class="container">
        <a class="navbar-brand" href="<?php echo e(route('leads.index')); ?>">
            <i class="bi bi-robot me-2"></i>AI Client Finder
        </a>
        <div class="ms-auto d-flex align-items-center gap-2">
            <a href="<?php echo e(route('leads.export')); ?>" class="btn btn-sm btn-outline-light">
                <i class="bi bi-download me-1"></i>Export CSV
            </a>

            
            <div class="dropdown">
                <button class="btn btn-sm btn-outline-light dropdown-toggle" data-bs-toggle="dropdown">
                    <i class="bi bi-gear me-1"></i>Settings
                </button>
                <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                    <li>
                        <a class="dropdown-item" href="<?php echo e(route('templates.index')); ?>">
                            <i class="bi bi-envelope-paper me-2 text-primary"></i>Email Templates
                        </a>
                    </li>
                    <li>
                        <a class="dropdown-item" href="<?php echo e(route('platforms.index')); ?>">
                            <i class="bi bi-grid me-2 text-success"></i>Platforms
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</nav>

<div class="container pb-5">

    
    <?php if(session('success')): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i><?php echo e(session('success')); ?>

            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if(session('error')): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i><?php echo e(session('error')); ?>

            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php echo $__env->yieldContent('content'); ?>
</div>

<!-- Bootstrap 5 JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<?php echo $__env->yieldPushContent('scripts'); ?>
</body>
</html>
<?php /**PATH /var/www/html/resources/views/layouts/app.blade.php ENDPATH**/ ?>