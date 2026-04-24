<?php $__env->startSection('title', 'Edit Template'); ?>

<?php $__env->startSection('content'); ?>

<div class="d-flex align-items-center gap-3 mb-4">
    <a href="<?php echo e(route('templates.index')); ?>" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left"></i>
    </a>
    <h4 class="mb-0 fw-bold"><i class="bi bi-pencil-square me-2 text-warning"></i>Edit Template</h4>
</div>

<div class="card border-0 shadow-sm rounded-3 d-flex flex-column" style="height:calc(100vh - 160px);">
    <div class="card-body p-4 overflow-auto flex-grow-1">
        <form method="POST" action="<?php echo e(route('templates.update', $template->id)); ?>" id="templateForm" enctype="multipart/form-data">
            <?php echo csrf_field(); ?> <?php echo method_field('PUT'); ?>
            <?php echo $__env->make('templates._form', ['template' => $template], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
        </form>
    </div>
    <div class="card-footer bg-white border-top d-flex gap-2 py-3 px-4 flex-shrink-0">
        <button type="submit" form="templateForm" class="btn btn-primary px-4">
            <i class="bi bi-save me-1"></i>Update Template
        </button>
        <a href="<?php echo e(route('templates.index')); ?>" class="btn btn-outline-secondary">Cancel</a>
    </div>
</div>

<?php $__env->stopSection(); ?>

<?php $__env->startPush('scripts'); ?>
<?php echo $__env->make('templates._quill-init', ['existingBody' => $template->body], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<?php $__env->stopPush(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /var/www/html/resources/views/templates/edit.blade.php ENDPATH**/ ?>