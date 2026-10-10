<?php $__env->startSection('title', $sequence->name); ?>
<?php $__env->startSection('page-title', 'Sequence'); ?>

<?php $__env->startSection('breadcrumb'); ?>
    <li class="breadcrumb-item"><a href="<?php echo e(route('outreach.sequences.index')); ?>">Sequences</a></li>
    <li class="breadcrumb-item active"><?php echo e($sequence->name); ?></li>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('content'); ?>
<?php echo $__env->make('sequencer._errors', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<?php use \App\Sequencer\Enums\SequenceStatus as SS; ?>
<?php $a = $analytics; ?>

<div class="card">
    <div class="card-body d-flex flex-wrap justify-content-between align-items-start gap-3">
        <div>
            <h3 class="mb-1"><?php echo e($sequence->name); ?> <?php echo $__env->make('sequencer._badge', ['status' => $sequence->status], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?></h3>
            <div class="text-muted fs-13"><?php echo e($sequence->description); ?></div>
            <div class="fs-13 mt-2">
                <i class="bi bi-clock me-1"></i><?php echo e($sequence->startTime()); ?>–<?php echo e($sequence->endTime()); ?> <?php echo e($sequence->effectiveTimezone()); ?>,
                <?php echo e(collect($sequence->sending_days)->map(fn ($d) => [1=>'Mon',2=>'Tue',3=>'Wed',4=>'Thu',5=>'Fri',6=>'Sat',7=>'Sun'][$d] ?? '')->implode(' ')); ?>

                · <i class="bi bi-speedometer2 mx-1"></i><?php echo e($sequence->daily_limit); ?>/day
                · <i class="bi bi-at mx-1"></i><?php echo e($sequence->mailSetting?->senderEmail() ?? 'default account'); ?>

            </div>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <?php if(in_array($sequence->status, [SS::Draft])): ?>
                <form method="POST" action="<?php echo e(route('outreach.sequences.activate', $sequence)); ?>"><?php echo csrf_field(); ?> <button class="btn btn-success"><i class="bi bi-play-fill me-1"></i>Activate</button></form>
            <?php elseif($sequence->status === SS::Active): ?>
                <form method="POST" action="<?php echo e(route('outreach.sequences.pause', $sequence)); ?>"><?php echo csrf_field(); ?> <button class="btn btn-warning"><i class="bi bi-pause-fill me-1"></i>Pause</button></form>
            <?php elseif($sequence->status === SS::Paused): ?>
                <form method="POST" action="<?php echo e(route('outreach.sequences.resume', $sequence)); ?>"><?php echo csrf_field(); ?> <button class="btn btn-success"><i class="bi bi-play-fill me-1"></i>Resume</button></form>
            <?php endif; ?>
            <a href="<?php echo e(route('outreach.enrollments.index', $sequence)); ?>" class="btn btn-primary"><i class="bi bi-person-plus me-1"></i>Leads (<?php echo e($a['enrollments']['total']); ?>)</a>
            <a href="<?php echo e(route('outreach.sequences.analytics', $sequence)); ?>" class="btn btn-light"><i class="bi bi-graph-up me-1"></i>Analytics</a>
            <div class="dropdown"><button class="btn btn-light" data-bs-toggle="dropdown" aria-label="More"><i class="bi bi-three-dots-vertical"></i></button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><a class="dropdown-item" href="<?php echo e(route('outreach.sequences.edit', $sequence)); ?>"><i class="bi bi-pencil me-2"></i>Edit settings</a></li>
                    <li><form method="POST" action="<?php echo e(route('outreach.sequences.duplicate', $sequence)); ?>"><?php echo csrf_field(); ?> <button class="dropdown-item"><i class="bi bi-copy me-2"></i>Duplicate</button></form></li>
                    <?php if($sequence->status !== SS::Archived): ?><li><form method="POST" action="<?php echo e(route('outreach.sequences.archive', $sequence)); ?>"><?php echo csrf_field(); ?> <button class="dropdown-item"><i class="bi bi-archive me-2"></i>Archive</button></form></li><?php endif; ?>
                    <li><form method="POST" action="<?php echo e(route('outreach.sequences.destroy', $sequence)); ?>" onsubmit="return confirm('Delete this sequence and its enrollments?')"><?php echo csrf_field(); ?> <?php echo method_field('DELETE'); ?> <button class="dropdown-item text-danger"><i class="bi bi-trash me-2"></i>Delete</button></form></li>
                </ul></div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h4 class="card-title mb-0">Steps</h4>
        <a href="<?php echo e(route('outreach.steps.create', $sequence)); ?>" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i>Add step</a>
    </div>
    <?php if($sequence->steps->isEmpty()): ?>
        <div class="card-body"><div class="empty-state"><i class="bi bi-envelope empty-state-icon"></i>No steps yet. <a href="<?php echo e(route('outreach.steps.create', $sequence)); ?>">Add the first email</a> to this sequence.</div></div>
    <?php else: ?>
    <ul class="list-group list-group-flush">
        <?php $__currentLoopData = $sequence->steps; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $step): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <li class="list-group-item d-flex flex-wrap gap-3 align-items-center">
                <span class="badge badge-primary light fs-14"><?php echo e($step->step_number); ?></span>
                <div class="flex-grow-1" style="min-width:200px">
                    <div class="fw-semibold"><?php echo e($step->subject); ?></div>
                    <div class="fs-13 text-muted"><?php echo e($loop->first && $step->delayInMinutes() === 0 ? 'Sent right after enrolment' : 'Wait '.$step->delayLabel().' after the previous step'); ?> <?php if($step->status->value !== 'active'): ?><span class="badge badge-secondary light">inactive</span><?php endif; ?></div>
                </div>
                <div class="text-nowrap">
                    <?php if(! $loop->first): ?><form method="POST" action="<?php echo e(route('outreach.steps.move', $step)); ?>" class="d-inline"><?php echo csrf_field(); ?> <input type="hidden" name="direction" value="up"><button class="btn btn-sm btn-light" aria-label="Move up"><i class="bi bi-arrow-up"></i></button></form><?php endif; ?>
                    <?php if(! $loop->last): ?><form method="POST" action="<?php echo e(route('outreach.steps.move', $step)); ?>" class="d-inline"><?php echo csrf_field(); ?> <input type="hidden" name="direction" value="down"><button class="btn btn-sm btn-light" aria-label="Move down"><i class="bi bi-arrow-down"></i></button></form><?php endif; ?>
                    <a class="btn btn-sm btn-light" href="<?php echo e(route('outreach.steps.preview', $step)); ?>" aria-label="Preview"><i class="bi bi-eye"></i></a>
                    <a class="btn btn-sm btn-light" href="<?php echo e(route('outreach.steps.edit', $step)); ?>" aria-label="Edit"><i class="bi bi-pencil"></i></a>
                    <form method="POST" action="<?php echo e(route('outreach.steps.duplicate', $step)); ?>" class="d-inline"><?php echo csrf_field(); ?> <button class="btn btn-sm btn-light" aria-label="Duplicate"><i class="bi bi-copy"></i></button></form>
                    <form method="POST" action="<?php echo e(route('outreach.steps.destroy', $step)); ?>" class="d-inline" onsubmit="return confirm('Delete this step?')"><?php echo csrf_field(); ?> <?php echo method_field('DELETE'); ?> <button class="btn btn-sm btn-danger light" aria-label="Delete"><i class="bi bi-trash"></i></button></form>
                </div>
            </li>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </ul>
    <?php endif; ?>
</div>

<div class="row">
    <?php $__currentLoopData = ['active' => 'Active', 'completed' => 'Completed', 'replied' => 'Replied', 'bounced' => 'Bounced', 'unsubscribed' => 'Unsubscribed', 'paused' => 'Paused']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $k => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <div class="col-6 col-md-2 mb-3"><div class="card h-100 mb-0"><div class="card-body py-3"><div class="text-muted fs-13"><?php echo e($label); ?></div><div class="fs-3 fw-semibold"><?php echo e($a['enrollments'][$k]); ?></div></div></div></div>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /var/www/html/resources/views/sequencer/sequences/show.blade.php ENDPATH**/ ?>