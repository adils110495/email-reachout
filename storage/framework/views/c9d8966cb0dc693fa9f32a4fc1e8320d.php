<?php $__env->startSection('title', 'Email Activity'); ?>
<?php $__env->startSection('page-title', 'Email Activity'); ?>

<?php $__env->startSection('breadcrumb'); ?>
    <li class="breadcrumb-item active" aria-current="page">Email Activity</li>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('content'); ?>

<div class="row">
    <div class="col-xl-12">
        
        <div class="card" data-ajax-root>

            <div class="card-header py-3 d-sm-flex d-block align-items-center justify-content-between">
                <div class="clearfix">
                    <h4 class="card-title"><i class="bi bi-activity me-2 text-primary"></i>Email Activity</h4>
                    <p class="mb-0 fs-13">What happened to every email you sent: opened, replied, or not opened yet.</p>
                </div>
                <div class="clearfix">
                    <form method="POST" action="<?php echo e(route('email-activity.check-replies')); ?>" class="d-inline">
                        <?php echo csrf_field(); ?>
                        <button type="submit" class="btn btn-primary btn-sm m-1">
                            <i class="bi bi-arrow-repeat me-1"></i>Check Replies
                        </button>
                    </form>
                </div>
            </div>

            
            <div class="card-header d-block pb-2">
                <div class="row filter-bar align-items-start">

                    
                    <div class="col-12 col-md-6 col-xl-4 mb-3">
                        <label class="form-label" for="activitySearch">Search</label>
                        <input type="text" id="activitySearch" class="form-control" data-search-param="q"
                               value="<?php echo e($search); ?>" placeholder="Company, email or subject…" autocomplete="off">
                    </div>

                    
                    <div class="col-6 col-md-4 col-xl-3 mb-3">
                        <label class="form-label" for="activityFilter">Activity</label>
                        <select id="activityFilter" class="form-select select2" data-param="activity" data-placeholder="All Activity">
                            <option value="">All Activity (<?php echo e($counts['total']); ?>)</option>
                            <?php $__currentLoopData = $activityOptions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $value => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <option value="<?php echo e($value); ?>" <?php echo e($activity === $value ? 'selected' : ''); ?>>
                                    <?php echo e($label); ?> (<?php echo e($counts[$value]); ?>)
                                </option>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        </select>
                    </div>

                    
                    <div class="col-6 col-md-2 col-xl-2 mb-3">
                        
                        <label class="form-label" aria-hidden="true">&nbsp;</label>
                        <a href="<?php echo e(route('email-activity.index')); ?>" class="btn btn-danger light" title="Clear all filters">
                            <i class="bi bi-x-circle me-1"></i>Clear
                        </a>
                    </div>
                </div>
            </div>

            
            <div class="ajax-region">
                <?php echo $__env->make('email-activity._table', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
            </div>

        </div>
    </div>
</div>

<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /var/www/html/resources/views/email-activity/index.blade.php ENDPATH**/ ?>