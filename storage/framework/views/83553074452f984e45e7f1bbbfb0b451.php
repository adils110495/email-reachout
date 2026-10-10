<?php $__env->startSection('title', 'Activity'); ?>
<?php $__env->startSection('page-title', 'Activity'); ?>

<?php $__env->startSection('breadcrumb'); ?>
    <li class="breadcrumb-item"><a href="<?php echo e(route('outreach.sequences.index')); ?>">Sequences</a></li>
    <li class="breadcrumb-item active">Activity</li>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('content'); ?>
<div class="card">
    <div class="card-header d-flex flex-wrap justify-content-between gap-2"><h4 class="card-title mb-0"><i class="bi bi-activity me-2 text-primary"></i>Activity timeline <?php if($lead): ?><small class="text-muted">· <?php echo e($lead->displayName()); ?> (<?php echo e($lead->email); ?>)</small><?php endif; ?></h4>
        <?php if($lead): ?><a href="<?php echo e(route('leads.index')); ?>" class="btn btn-light btn-sm"><i class="bi bi-people me-1"></i>Back to Leads</a><?php endif; ?></div>
    <div class="card-header d-block pb-2">
        <form method="GET" class="row g-2 align-items-end"><?php if($lead): ?><input type="hidden" name="lead" value="<?php echo e($lead->id); ?>"><?php endif; ?>
            <div class="col-6 col-md-3"><label class="form-label" for="type">Event</label>
                <select class="form-select" id="type" name="type"><option value="">All events</option>
                    <?php $__currentLoopData = $types; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $t): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><option value="<?php echo e($t->value); ?>" <?php if(($filters['type'] ?? '') === $t->value): echo 'selected'; endif; ?>><?php echo e($t->label()); ?></option><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></select></div>
            <div class="col-6 col-md-4"><label class="form-label" for="sequence">Sequence</label>
                <select class="form-select" id="sequence" name="sequence"><option value="">All</option>
                    <?php $__currentLoopData = $sequences; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $s): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><option value="<?php echo e($s->id); ?>" <?php if((int) ($filters['sequence'] ?? 0) === $s->id): echo 'selected'; endif; ?>><?php echo e($s->name); ?></option><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></select></div>
            <div class="col-12 col-md-3 d-flex gap-2"><button class="btn btn-primary">Filter</button><a href="<?php echo e(route('outreach.activity.index')); ?>" class="btn btn-danger light">Clear</a></div>
        </form>
    </div>
    <div class="card-body">
        <?php $groups = $events->getCollection()->groupBy(fn ($e) => \App\Sequencer\Support\Tz::format($e->occurred_at, 'd M Y')); ?>
        <?php $__empty_1 = true; $__currentLoopData = $groups; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $day => $items): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
            <div class="fw-semibold text-muted fs-13 mt-3 mb-1"><?php echo e($day); ?></div>
            <?php $__currentLoopData = $items; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $event): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <div class="d-flex gap-2 py-1">
                    <i class="bi <?php echo e($event->type->icon()); ?> text-<?php echo e($event->type->badge()); ?> mt-1"></i>
                    <div><span class="fw-medium"><?php echo e($event->type->label()); ?></span> <span class="text-muted fs-13">· <?php echo e(\App\Sequencer\Support\Tz::format($event->occurred_at, 'H:i')); ?>
                        <?php if($event->lead): ?> · <a href="<?php echo e(route('outreach.activity.index', ['lead' => $event->lead_id])); ?>"><?php echo e($event->lead->displayName()); ?></a><?php endif; ?>
                        <?php if($event->sequence): ?> · <?php echo e($event->sequence->name); ?><?php endif; ?></span>
                        <div class="fs-13 text-muted"><?php echo e($event->description); ?></div></div>
                </div>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
            <div class="empty-state"><i class="bi bi-activity empty-state-icon"></i>No activity yet.</div>
        <?php endif; ?>
    </div>
    <div class="card-footer"><?php echo e($events->links()); ?></div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /var/www/html/resources/views/sequencer/activity/index.blade.php ENDPATH**/ ?>