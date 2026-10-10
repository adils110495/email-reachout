<?php $__env->startSection('title', 'Sequences'); ?>
<?php $__env->startSection('page-title', 'Sequences'); ?>

<?php $__env->startSection('breadcrumb'); ?>
    <li class="breadcrumb-item active">Sequences</li>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('content'); ?>
<?php echo $__env->make('sequencer._errors', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<div class="card">
    <div class="card-header d-flex flex-wrap justify-content-between gap-2">
        <h4 class="card-title mb-0"><i class="bi bi-diagram-3 me-2 text-primary"></i>Sequences</h4>
        <a href="<?php echo e(route('outreach.sequences.create')); ?>" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i>New sequence</a>
    </div>
    <div class="card-header d-block pb-2">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-12 col-md-5"><label class="form-label" for="q">Search</label><input class="form-control" id="q" name="q" value="<?php echo e($filters['q'] ?? ''); ?>"></div>
            <div class="col-6 col-md-3"><label class="form-label" for="status">Status</label>
                <select class="form-select" id="status" name="status"><option value="">All</option>
                    <?php $__currentLoopData = $statuses; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $s): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><option value="<?php echo e($s->value); ?>" <?php if(($filters['status'] ?? '') === $s->value): echo 'selected'; endif; ?>><?php echo e($s->label()); ?></option><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></select></div>
            <div class="col-6 col-md-4 d-flex gap-2"><button class="btn btn-primary">Filter</button><a href="<?php echo e(route('outreach.sequences.index')); ?>" class="btn btn-danger light">Clear</a></div>
        </form>
    </div>
    <?php if($sequences->isEmpty()): ?>
        <div class="card-body"><div class="empty-state"><i class="bi bi-diagram-3 empty-state-icon"></i>No sequences found. <a href="<?php echo e(route('outreach.sequences.create')); ?>">Create your first sequence</a>.</div></div>
    <?php else: ?>
    <div class="table-responsive"><table class="table mb-0">
        <thead class="table-light"><tr><th>Name</th><th>Status</th><th>Steps</th><th>Enrolled</th><th class="d-none d-md-table-cell">Window</th><th class="d-none d-md-table-cell">Daily limit</th></tr></thead>
        <tbody>
        <?php $__currentLoopData = $sequences; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $s): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <tr><td><a href="<?php echo e(route('outreach.sequences.show', $s)); ?>"><strong><?php echo e($s->name); ?></strong></a></td>
                <td><?php echo $__env->make('sequencer._badge', ['status' => $s->status], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?></td>
                <td><?php echo e($s->steps_count); ?></td><td><?php echo e($s->enrollments_count); ?></td>
                <td class="d-none d-md-table-cell fs-13"><?php echo e($s->startTime()); ?>–<?php echo e($s->endTime()); ?> <?php echo e($s->effectiveTimezone()); ?></td>
                <td class="d-none d-md-table-cell"><?php echo e($s->daily_limit); ?></td></tr>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </tbody></table></div>
    <div class="card-footer"><?php echo e($sequences->links()); ?></div>
    <?php endif; ?>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /var/www/html/resources/views/sequencer/sequences/index.blade.php ENDPATH**/ ?>