<?php $editing = $step->exists; ?>
<?php $__env->startSection('title', $editing ? 'Edit step' : 'Add step'); ?>
<?php $__env->startSection('page-title', $editing ? 'Edit step '.$step->step_number : 'Add step'); ?>

<?php $__env->startSection('breadcrumb'); ?>
    <li class="breadcrumb-item"><a href="<?php echo e(route('outreach.sequences.index')); ?>">Sequences</a></li>
    <li class="breadcrumb-item"><a href="<?php echo e(route('outreach.sequences.show', $sequence)); ?>"><?php echo e($sequence->name); ?></a></li>
    <li class="breadcrumb-item active"><?php echo e($editing ? 'Edit step' : 'Add step'); ?></li>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('content'); ?>
<?php echo $__env->make('sequencer._errors', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<form method="POST" action="<?php echo e($editing ? route('outreach.steps.update', $step) : route('outreach.steps.store', $sequence)); ?>">
    <?php echo csrf_field(); ?> <?php if($editing): ?> <?php echo method_field('PUT'); ?> <?php endif; ?>
    <div class="row">
        <div class="col-lg-8">
            <div class="card"><div class="card-body">
                <?php if(! $editing && $templates->isNotEmpty()): ?>
                <div class="mb-3"><label class="form-label" for="template_id">Start from an <a href="<?php echo e(route('templates.index')); ?>">Email Template</a> <small class="text-muted">(fills blank subject/body)</small></label>
                    <select class="form-select" id="template_id" name="template_id"><option value="">— none —</option>
                        <?php $__currentLoopData = $templates; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $t): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><option value="<?php echo e($t->id); ?>" <?php if((int) old('template_id') === $t->id): echo 'selected'; endif; ?>><?php echo e($t->name); ?></option><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></select></div>
                <?php endif; ?>
                <div class="mb-3"><label class="form-label" for="subject">Subject</label><input class="form-control" id="subject" name="subject" value="<?php echo e(old('subject', $step->subject)); ?>" maxlength="998"></div>
                <div class="mb-3"><label class="form-label" for="body">Body <small class="text-muted">plain text or HTML</small></label>
                    <textarea class="form-control font-monospace" id="body" name="body" rows="14"><?php echo e(old('body', $step->body)); ?></textarea></div>
                <fieldset class="mb-3"><legend class="form-label fs-6">Wait before sending <?php echo e(($editing ? $step->step_number : 2) > 1 ? 'this step (after the previous one)' : 'this step (after enrolment)'); ?></legend>
                    <div class="row g-2">
                        <div class="col-4"><div class="input-group"><input type="number" min="0" max="365" class="form-control" name="delay_days" value="<?php echo e(old('delay_days', $step->delay_days ?? 0)); ?>" aria-label="Days"><span class="input-group-text">days</span></div></div>
                        <div class="col-4"><div class="input-group"><input type="number" min="0" max="23" class="form-control" name="delay_hours" value="<?php echo e(old('delay_hours', $step->delay_hours ?? 0)); ?>" aria-label="Hours"><span class="input-group-text">hours</span></div></div>
                        <div class="col-4"><div class="input-group"><input type="number" min="0" max="59" class="form-control" name="delay_minutes" value="<?php echo e(old('delay_minutes', $step->delay_minutes ?? 0)); ?>" aria-label="Minutes"><span class="input-group-text">min</span></div></div>
                    </div></fieldset>
                <?php if($editing): ?>
                <div class="mb-3"><label class="form-label" for="status">Status</label>
                    <select class="form-select w-auto" id="status" name="status"><option value="active" <?php if($step->status->value === 'active'): echo 'selected'; endif; ?>>Active</option><option value="inactive" <?php if($step->status->value === 'inactive'): echo 'selected'; endif; ?>>Inactive (skipped)</option></select></div>
                <?php endif; ?>
                <button class="btn btn-primary">Save step</button> <a href="<?php echo e(route('outreach.sequences.show', $sequence)); ?>" class="btn btn-link">Cancel</a>
                <?php if($editing): ?><a href="<?php echo e(route('outreach.steps.preview', $step)); ?>" class="btn btn-light float-end"><i class="bi bi-eye me-1"></i>Preview</a><?php endif; ?>
            </div></div>
        </div>
        <div class="col-lg-4"><?php echo $__env->make('sequencer._variables', ['variables' => $variables], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?></div>
    </div>
</form>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /var/www/html/resources/views/sequencer/steps/form.blade.php ENDPATH**/ ?>