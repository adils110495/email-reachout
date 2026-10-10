<?php $__env->startSection('title', 'Preview step '.$step->step_number); ?>
<?php $__env->startSection('page-title', 'Preview'); ?>

<?php $__env->startSection('breadcrumb'); ?>
    <li class="breadcrumb-item"><a href="<?php echo e(route('outreach.sequences.show', $step->sequence_id)); ?>"><?php echo e($step->sequence->name); ?></a></li>
    <li class="breadcrumb-item active">Preview step <?php echo e($step->step_number); ?></li>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('content'); ?>
<div class="card">
    <div class="card-header d-flex justify-content-between flex-wrap gap-2">
        <div><div class="text-muted fs-13">Subject</div><h4 class="mb-0"><?php echo e($preview['subject']); ?></h4></div>
        <div class="fs-13 text-muted align-self-center"><?php echo e($lead ? 'Rendered for '.$lead->email : 'Rendered with sample data'); ?></div>
    </div>
    <?php if($preview['unknown']): ?>
        <div class="alert alert-warning m-3 mb-0">Unknown variable(s) will render empty: <?php $__currentLoopData = $preview['unknown']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $u): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><code><?php echo e('{'.'{'.$u.'}'.'}'); ?></code> <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></div>
    <?php endif; ?>
    <div class="card-body">
        
        <iframe sandbox title="Email preview" style="width:100%;height:420px;border:1px solid var(--bs-border-color,#ddd);border-radius:6px;background:#fff" srcdoc="<?php echo e($preview['html']); ?>"></iframe>
        <a href="<?php echo e(route('outreach.steps.edit', $step)); ?>" class="btn btn-light mt-3">Back to editor</a>
    </div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /var/www/html/resources/views/sequencer/steps/preview.blade.php ENDPATH**/ ?>