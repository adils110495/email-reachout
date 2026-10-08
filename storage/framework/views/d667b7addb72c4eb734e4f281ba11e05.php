<?php $__env->startSection('title', 'Email Templates - Settings'); ?>
<?php $__env->startSection('page-title', 'Email Templates'); ?>

<?php $__env->startSection('breadcrumb'); ?>
    <li class="breadcrumb-item">Settings</li>
    <li class="breadcrumb-item active" aria-current="page">Email Templates</li>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('page-title-action'); ?>
    <a class="text-primary fs-13" href="<?php echo e(route('templates.create')); ?>">+ New Template</a>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('content'); ?>

<div class="row">
    <div class="col-xl-12">
        
        <div class="card" data-ajax-root>

            <div class="card-header py-3 d-sm-flex d-block align-items-center justify-content-between">
                <div class="clearfix">
                    <h4 class="card-title"><i class="bi bi-envelope-paper me-2 text-primary"></i>Email Templates</h4>
                    <p class="mb-0 fs-13">Create reusable templates to load into the compose window.</p>
                </div>
                <div class="clearfix">
                    <a href="<?php echo e(route('templates.export')); ?>" class="btn btn-light btn-sm m-1">
                        <i class="bi bi-download me-1"></i>Export CSV
                    </a>
                    <a href="<?php echo e(route('templates.create')); ?>" class="btn btn-primary btn-sm m-1">
                        <i class="bi bi-plus-lg me-1"></i>New Template
                    </a>
                </div>
            </div>

            
            <div class="card-header d-block pb-2">
                <div class="row filter-bar align-items-start">

                    
                    <div class="col-12 col-md-6 col-xl-4 mb-3">
                        <label class="form-label" for="templateSearch">Search</label>
                        <input type="text" id="templateSearch" class="form-control" data-live-filter
                               placeholder="Search by name or subject…" autocomplete="off">
                    </div>

                    
                    <div class="col-6 col-md-4 col-xl-3 mb-3">
                        <label class="form-label" for="templateStatusFilter">Status</label>
                        
                        <select id="templateStatusFilter" class="form-select select2" data-param="status" data-placeholder="All Statuses">
                            <option value="">All Statuses</option>
                            <?php $__currentLoopData = $statusOptions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $value => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <option value="<?php echo e($value); ?>" <?php echo e($activeStatus === $value ? 'selected' : ''); ?>>
                                    <?php echo e($label); ?>

                                </option>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        </select>
                    </div>

                    
                    <div class="col-6 col-md-2 col-xl-2 mb-3">
                        
                        <label class="form-label" aria-hidden="true">&nbsp;</label>
                        <a href="<?php echo e(route('templates.index')); ?>" class="btn btn-danger light" title="Clear all filters">
                            <i class="bi bi-x-circle me-1"></i>Clear
                        </a>
                    </div>
                </div>
            </div>

            
            <div class="ajax-region">
                <?php echo $__env->make('templates._table', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
            </div>

        </div>
    </div>
</div>

<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /var/www/html/resources/views/templates/index.blade.php ENDPATH**/ ?>