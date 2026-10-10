<?php $__env->startSection('title', $sequence->name.' analytics'); ?>
<?php $__env->startSection('page-title', 'Sequence analytics'); ?>

<?php $__env->startSection('breadcrumb'); ?>
    <li class="breadcrumb-item"><a href="<?php echo e(route('outreach.sequences.index')); ?>">Sequences</a></li>
    <li class="breadcrumb-item"><a href="<?php echo e(route('outreach.sequences.show', $sequence)); ?>"><?php echo e($sequence->name); ?></a></li>
    <li class="breadcrumb-item active">Analytics</li>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('content'); ?>
<?php $e = $analytics['enrollments']; $m = $analytics['emails']; $r = $analytics['rates']; ?>
<div class="row">
    <?php $__currentLoopData = ['Total enrolled' => $e['total'], 'Active' => $e['active'], 'Completed' => $e['completed'], 'Replied' => $e['replied'], 'Bounced' => $e['bounced'], 'Unsubscribed' => $e['unsubscribed'], 'Removed' => $e['removed']]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $label => $v): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <div class="col-6 col-md-3 col-xl mb-3"><div class="card h-100 mb-0"><div class="card-body py-3"><div class="text-muted fs-13"><?php echo e($label); ?></div><div class="fs-3 fw-semibold"><?php echo e($v); ?></div></div></div></div>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
</div>

<div class="row">
    <?php $__currentLoopData = ['Open rate' => ['open', $m['opened']], 'Click rate' => ['click', $m['clicked']], 'Reply rate' => ['reply', $m['replied']], 'Bounce rate' => ['bounce', $m['bounced']]]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $label => [$key, $count]): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <div class="col-6 col-lg-3 mb-3"><div class="card h-100 mb-0"><div class="card-body">
            <div class="text-muted fs-13"><?php echo e($label); ?></div><div class="fs-2 fw-semibold"><?php echo e($r[$key]); ?>%</div>
            <div class="fs-13 text-muted"><?php echo e($count); ?> of <?php echo e($m['attempts']); ?> emails</div>
        </div></div></div>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
</div>

<div class="card">
    <div class="card-header"><h4 class="card-title mb-0">Per step</h4></div>
    <div class="table-responsive"><table class="table mb-0">
        <thead class="table-light"><tr><th>Step</th><th>Emails</th><th>Opened</th><th>Clicked</th><th>Replied</th><th>Bounced</th></tr></thead>
        <tbody>
        <?php $__empty_1 = true; $__currentLoopData = $analytics['steps']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $row): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
            <?php $n = $row['attempts']; $pct = fn ($v) => $n > 0 ? round($v / $n * 100, 1) : 0; ?>
            <tr><td><?php echo e($row['step']->step_number); ?>. <?php echo e(\Illuminate\Support\Str::limit($row['step']->subject, 50)); ?></td>
                <td><?php echo e($n); ?></td><td><?php echo e($row['opened']); ?> <small class="text-muted">(<?php echo e($pct($row['opened'])); ?>%)</small></td>
                <td><?php echo e($row['clicked']); ?> <small class="text-muted">(<?php echo e($pct($row['clicked'])); ?>%)</small></td>
                <td><?php echo e($row['replied']); ?> <small class="text-muted">(<?php echo e($pct($row['replied'])); ?>%)</small></td>
                <td><?php echo e($row['bounced']); ?> <small class="text-muted">(<?php echo e($pct($row['bounced'])); ?>%)</small></td></tr>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><tr><td colspan="6" class="text-muted">No steps.</td></tr><?php endif; ?>
        </tbody></table></div>
</div>
<p class="fs-13 text-muted">Rates use emails that left our servers as the denominator; a rate is 0% (never an error) while nothing has been sent. Open tracking is approximate: some mail clients block or pre-load images.</p>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /var/www/html/resources/views/sequencer/sequences/analytics.blade.php ENDPATH**/ ?>