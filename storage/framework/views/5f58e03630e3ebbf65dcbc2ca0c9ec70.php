<?php $__env->startSection('title', 'Edit Template'); ?>
<?php $__env->startSection('page-title', 'Edit Template'); ?>

<?php $__env->startSection('breadcrumb'); ?>
    <li class="breadcrumb-item">Settings</li>
    <li class="breadcrumb-item"><a href="<?php echo e(route('templates.index')); ?>">Email Templates</a></li>
    <li class="breadcrumb-item active" aria-current="page">Edit</li>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('page-title-action'); ?>
    <a class="text-primary fs-13" href="<?php echo e(route('templates.index')); ?>">&larr; Back to Templates</a>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('content'); ?>

<div class="row">
    <div class="col-xl-12">
        <div class="card">

            <div class="card-header py-3 d-sm-flex d-block align-items-center justify-content-between">
                <div class="clearfix">
                    <h4 class="card-title"><i class="bi bi-pencil-square me-2 text-warning"></i>Edit Template</h4>
                    <p class="mb-0 fs-13"><?php echo e($template->name); ?></p>
                </div>
                <a href="<?php echo e(route('templates.index')); ?>" class="btn btn-light btn-sm m-1">
                    <i class="bi bi-arrow-left me-1"></i>Back
                </a>
            </div>

            <div class="card-body">
                <form method="POST" action="<?php echo e(route('templates.update', $template->id)); ?>" id="templateForm" enctype="multipart/form-data">
                    <?php echo csrf_field(); ?> <?php echo method_field('PUT'); ?>
                    <?php echo $__env->make('templates._form', ['template' => $template], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
                </form>
            </div>

            <div class="card-footer border-top d-flex gap-2 py-3">
                <button type="submit" form="templateForm" class="btn btn-primary px-4">
                    <i class="bi bi-save me-1"></i>Update Template
                </button>
                <a href="<?php echo e(route('templates.index')); ?>" class="btn btn-light">Cancel</a>
            </div>

        </div>
    </div>
</div>

<?php $__env->stopSection(); ?>

<?php $__env->startPush('scripts'); ?>
<?php echo $__env->make('templates._quill-init', ['existingBody' => $template->body], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<?php $__env->stopPush(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /var/www/html/resources/views/templates/edit.blade.php ENDPATH**/ ?>