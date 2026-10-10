<?php
    $editing = $sequence->exists;
    $days = old('sending_days', $sequence->sending_days ?? [1,2,3,4,5]);
    $dayNames = [1 => 'Mon', 2 => 'Tue', 3 => 'Wed', 4 => 'Thu', 5 => 'Fri', 6 => 'Sat', 7 => 'Sun'];
?>
<?php $__env->startSection('title', $editing ? 'Edit sequence' : 'New sequence'); ?>
<?php $__env->startSection('page-title', $editing ? 'Edit sequence' : 'New sequence'); ?>

<?php $__env->startSection('breadcrumb'); ?>
    <li class="breadcrumb-item"><a href="<?php echo e(route('outreach.sequences.index')); ?>">Sequences</a></li>
    <li class="breadcrumb-item active"><?php echo e($editing ? 'Edit' : 'New'); ?></li>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('content'); ?>
<?php echo $__env->make('sequencer._errors', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<form method="POST" action="<?php echo e($editing ? route('outreach.sequences.update', $sequence) : route('outreach.sequences.store')); ?>">
    <?php echo csrf_field(); ?> <?php if($editing): ?> <?php echo method_field('PUT'); ?> <?php endif; ?>
    <div class="card"><div class="card-header"><h4 class="card-title mb-0">Details</h4></div><div class="card-body row g-3">
        <div class="col-md-6"><label class="form-label" for="name">Name</label><input class="form-control" id="name" name="name" value="<?php echo e(old('name', $sequence->name)); ?>" required></div>
        <div class="col-md-6"><label class="form-label" for="mail_setting_id">Send from <a href="<?php echo e(route('mail-settings.index')); ?>" class="fs-13 ms-1">(Mail Settings)</a></label>
            <select class="form-select" id="mail_setting_id" name="mail_setting_id"><option value="">Default account</option>
                <?php $__currentLoopData = $accounts; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $a): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><option value="<?php echo e($a->id); ?>" <?php if((int) old('mail_setting_id', $sequence->mail_setting_id) === $a->id): echo 'selected'; endif; ?>><?php echo e($a->label()); ?></option><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></select></div>
        <div class="col-12"><label class="form-label" for="description">Description</label><textarea class="form-control" id="description" name="description" rows="2"><?php echo e(old('description', $sequence->description)); ?></textarea></div>
    </div></div>

    <div class="card"><div class="card-header"><h4 class="card-title mb-0">Sending schedule</h4></div><div class="card-body row g-3">
        <div class="col-md-4"><label class="form-label" for="timezone">Timezone</label>
            <select class="form-select" id="timezone" name="timezone"><?php $__currentLoopData = $timezones; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $tz): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><option value="<?php echo e($tz); ?>" <?php if(old('timezone', $sequence->timezone) === $tz): echo 'selected'; endif; ?>><?php echo e($tz); ?></option><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></select></div>
        <div class="col-6 col-md-2"><label class="form-label" for="sending_start_time">From</label><input type="time" class="form-control" id="sending_start_time" name="sending_start_time" value="<?php echo e(old('sending_start_time', substr((string) $sequence->sending_start_time, 0, 5))); ?>" required></div>
        <div class="col-6 col-md-2"><label class="form-label" for="sending_end_time">Until</label><input type="time" class="form-control" id="sending_end_time" name="sending_end_time" value="<?php echo e(old('sending_end_time', substr((string) $sequence->sending_end_time, 0, 5))); ?>" required></div>
        <div class="col-md-4"><label class="form-label" for="daily_limit">Daily limit (emails/day)</label><input type="number" min="1" class="form-control" id="daily_limit" name="daily_limit" value="<?php echo e(old('daily_limit', $sequence->daily_limit)); ?>" required></div>
        <div class="col-12"><span class="form-label d-block">Sending days</span>
            <?php $__currentLoopData = $dayNames; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $n => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <div class="form-check form-check-inline"><input type="checkbox" class="form-check-input" id="d<?php echo e($n); ?>" name="sending_days[]" value="<?php echo e($n); ?>" <?php if(in_array($n, $days)): echo 'checked'; endif; ?>><label class="form-check-label" for="d<?php echo e($n); ?>"><?php echo e($label); ?></label></div>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            <div class="form-text">Emails that fall due outside these days and hours wait for the next allowed time.</div></div>
        <div class="col-12">
            <div class="form-check form-check-inline"><input type="checkbox" class="form-check-input" id="track_opens" name="track_opens" value="1" <?php if(old('track_opens', $sequence->track_opens ?? true)): echo 'checked'; endif; ?>><label class="form-check-label" for="track_opens">Track opens</label></div>
            <div class="form-check form-check-inline"><input type="checkbox" class="form-check-input" id="track_clicks" name="track_clicks" value="1" <?php if(old('track_clicks', $sequence->track_clicks ?? true)): echo 'checked'; endif; ?>><label class="form-check-label" for="track_clicks">Track link clicks</label></div>
        </div>
    </div></div>
    <button class="btn btn-primary">Save sequence</button> <a href="<?php echo e($editing ? route('outreach.sequences.show', $sequence) : route('outreach.sequences.index')); ?>" class="btn btn-link">Cancel</a>
</form>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /var/www/html/resources/views/sequencer/sequences/form.blade.php ENDPATH**/ ?>