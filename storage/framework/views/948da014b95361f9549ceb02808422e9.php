<?php $__env->startSection('title', $sequence->name.' contacts'); ?>
<?php $__env->startSection('page-title', 'Sequence leads'); ?>

<?php $__env->startSection('breadcrumb'); ?>
    <li class="breadcrumb-item"><a href="<?php echo e(route('outreach.sequences.index')); ?>">Sequences</a></li>
    <li class="breadcrumb-item"><a href="<?php echo e(route('outreach.sequences.show', $sequence)); ?>"><?php echo e($sequence->name); ?></a></li>
    <li class="breadcrumb-item active">Leads</li>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('content'); ?>
<?php echo $__env->make('sequencer._errors', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<div class="row">
<div class="col-xl-9">
<div class="card">
    <div class="card-header d-flex flex-wrap justify-content-between gap-2">
        <h4 class="card-title mb-0">Enrolled leads <small class="text-muted">(<?php echo e($enrollments->total()); ?>)</small> <?php echo $__env->make('sequencer._badge', ['status' => $sequence->status], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?></h4>
    </div>
    <div class="card-header d-block pb-2">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-12 col-md-5"><label class="form-label" for="q">Search</label><input class="form-control" id="q" name="q" value="<?php echo e($filters['q'] ?? ''); ?>" placeholder="Email, name, company"></div>
            <div class="col-6 col-md-3"><label class="form-label" for="status">Status</label>
                <select class="form-select" id="status" name="status"><option value="">All</option>
                    <?php $__currentLoopData = $statuses; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $s): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><option value="<?php echo e($s->value); ?>" <?php if(($filters['status'] ?? '') === $s->value): echo 'selected'; endif; ?>><?php echo e($s->label()); ?></option><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></select></div>
            <div class="col-6 col-md-4 d-flex gap-2"><button class="btn btn-primary">Filter</button><a href="<?php echo e(route('outreach.enrollments.index', $sequence)); ?>" class="btn btn-danger light">Clear</a></div>
        </form>
    </div>

    <?php if($enrollments->isEmpty()): ?>
        <div class="card-body"><div class="empty-state"><i class="bi bi-person-plus empty-state-icon"></i>No leads <?php echo e(array_filter($filters) ? 'match' : 'enrolled yet'); ?>. Use the panel on the right to enrol a category, or tick leads on the Leads page.</div></div>
    <?php else: ?>
    <form method="POST" action="<?php echo e(route('outreach.enrollments.bulk', $sequence)); ?>">
        <?php echo csrf_field(); ?>
        <div class="card-body border-bottom py-2 d-flex flex-wrap gap-2 align-items-center">
            <select name="action" class="form-select form-select-sm w-auto" required>
                <option value="">Bulk action…</option><option value="pause">Pause</option><option value="resume">Resume</option><option value="remove">Remove from sequence</option></select>
            <button class="btn btn-sm btn-primary" onclick="return confirm('Apply to the selected enrollments?')">Apply to selected</button>
            <div class="form-check ms-2"><input type="checkbox" class="form-check-input" id="all" name="all" value="1"><label class="form-check-label fs-13" for="all">…or to ALL <?php echo e($enrollments->total()); ?><?php echo e(array_filter($filters) ? ' (ignores filters)' : ''); ?> enrolments</label></div>
        </div>
        <div class="table-responsive"><table class="table mb-0">
            <thead class="table-light"><tr><th style="width:36px"><input type="checkbox" class="form-check-input" id="checkAll" aria-label="Select all"></th><th>Contact</th><th>Status</th><th>Step</th><th class="d-none d-md-table-cell">Next send</th><th class="d-none d-lg-table-cell">Account</th><th></th></tr></thead>
            <tbody>
            <?php $__currentLoopData = $enrollments; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $e): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <tr>
                    <td><input type="checkbox" class="form-check-input row-check" name="ids[]" value="<?php echo e($e->id); ?>" aria-label="Select"></td>
                    <td><a href="<?php echo e(route('outreach.activity.index', ['lead' => $e->lead_id])); ?>"><?php echo e($e->lead?->displayName()); ?></a><div class="fs-13 text-muted"><?php echo e($e->lead?->email); ?></div></td>
                    <td><?php echo $__env->make('sequencer._badge', ['status' => $e->status], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php if($e->stop_reason): ?><div class="fs-12 text-muted"><?php echo e(str_replace('_', ' ', $e->stop_reason)); ?></div><?php endif; ?></td>
                    <td><?php echo e($e->current_step); ?></td>
                    <td class="d-none d-md-table-cell fs-13"><?php echo e(\App\Sequencer\Support\Tz::format($e->next_action_at)); ?></td>
                    <td class="d-none d-lg-table-cell fs-13"><?php echo e($e->mailSetting?->name); ?></td>
                    <td class="text-end text-nowrap">
                        <?php if(in_array($e->status->value, ['active', 'pending'])): ?><button class="btn btn-sm btn-light" formaction="<?php echo e(route('outreach.enrollments.pause', $e)); ?>" formnovalidate title="Pause"><i class="bi bi-pause"></i></button><?php endif; ?>
                        <?php if($e->status->value === 'paused'): ?><button class="btn btn-sm btn-light" formaction="<?php echo e(route('outreach.enrollments.resume', $e)); ?>" formnovalidate title="Resume"><i class="bi bi-play"></i></button><?php endif; ?>
                        <?php if($e->status->value === 'failed'): ?><button class="btn btn-sm btn-light" formaction="<?php echo e(route('outreach.enrollments.retry', $e)); ?>" formnovalidate title="Retry"><i class="bi bi-arrow-repeat"></i></button><?php endif; ?>
                        <?php if($e->status->isOpen()): ?><button class="btn btn-sm btn-danger light" formaction="<?php echo e(route('outreach.enrollments.remove', $e)); ?>" formnovalidate title="Remove" onclick="return confirm('Remove this lead from the sequence?')"><i class="bi bi-x-lg"></i></button><?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </tbody></table></div>
    </form>
    <div class="card-footer"><?php echo e($enrollments->links()); ?></div>
    <?php endif; ?>
</div>
</div>

<div class="col-xl-3">
    <div class="card"><div class="card-header"><h5 class="card-title mb-0">Enrol leads</h5></div>
        <form method="POST" action="<?php echo e(route('outreach.enrollments.store', $sequence)); ?>" class="card-body"><?php echo csrf_field(); ?>
            <div class="mb-3"><label class="form-label" for="category_id">Every lead in a category</label>
                <select class="form-select" id="category_id" name="category_id" required><option value="">— choose category —</option>
                    <?php $__currentLoopData = $categories; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $c): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><option value="<?php echo e($c->id); ?>"><?php echo e($c->name); ?> (<?php echo e($c->leads_count); ?>)</option><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></select></div>
            <div class="mb-3"><label class="form-label" for="mail_setting_id">Send from</label>
                <select class="form-select" id="mail_setting_id" name="mail_setting_id"><option value="">Sequence default</option>
                    <?php $__currentLoopData = $accounts; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $a): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><option value="<?php echo e($a->id); ?>"><?php echo e($a->name); ?></option><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></select></div>
            <button class="btn btn-primary w-100">Enrol category</button>
            <div class="form-text mt-2">To enrol individual leads, tick them on the <a href="<?php echo e(route('leads.index')); ?>">Leads</a> page and choose "Enroll in sequence". Enrolling runs in the background; unsubscribed, bounced and already-enrolled leads are skipped.</div>
        </form></div>
</div>
</div>
<?php $__env->stopSection(); ?>

<?php $__env->startPush('scripts'); ?>
<script>document.getElementById('checkAll')?.addEventListener('change', e => document.querySelectorAll('.row-check').forEach(c => c.checked = e.target.checked));</script>
<?php $__env->stopPush(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /var/www/html/resources/views/sequencer/enrollments/index.blade.php ENDPATH**/ ?>