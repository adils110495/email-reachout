
<?php
    $badges = [
        'not_opened' => ['class' => 'badge-secondary', 'icon' => 'bi-envelope',      'label' => 'Not opened'],
        'opened'     => ['class' => 'badge-info',      'icon' => 'bi-envelope-open', 'label' => 'Opened'],
        'replied'    => ['class' => 'badge-success',   'icon' => 'bi-reply',         'label' => 'Replied'],
    ];
?>
<div class="ajax-content">
<?php if($emails->isEmpty()): ?>
    <div class="card-body">
        <div class="empty-state">
            <i class="bi bi-activity empty-state-icon"></i>
            No sent emails match.
            <?php if($activity || $search): ?>
                <a href="<?php echo e(route('email-activity.index')); ?>">Clear the filters</a>.
            <?php endif; ?>
        </div>
    </div>
<?php else: ?>
    <div class="card-body table-card-body px-0 pt-0 pb-2">
        <div class="table-responsive">
            <table class="table">
                <thead class="table-light">
                    <tr>
                        <th style="width:60px">#</th>
                        <th class="mw-150">Lead</th>
                        <th class="mw-200">Subject</th>
                        <th>Sent</th>
                        <th>Activity</th>
                        <th class="text-center">Opens</th>
                        <th>Last opened</th>
                        <th>Replied</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $__currentLoopData = $emails; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $email): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <?php $b = $badges[$email->activity]; ?>
                        <tr>
                            <td><?php echo e($emails->firstItem() + $loop->index); ?></td>
                            <td>
                                <h6 class="mb-0"><?php echo e($email->lead?->company_name ?? '—'); ?></h6>
                                <small class="text-muted"><?php echo e($email->lead?->email); ?></small>
                            </td>
                            <td><?php echo e($email->subject); ?></td>
                            <td><?php echo e($email->sent_at?->format('d M Y, h:i A')); ?></td>
                            <td>
                                <span class="badge <?php echo e($b['class']); ?> light">
                                    <i class="bi <?php echo e($b['icon']); ?> me-1"></i><?php echo e($b['label']); ?>

                                </span>
                            </td>
                            <td class="text-center"><?php echo e($email->open_count); ?></td>
                            <td><?php echo e($email->last_opened_at?->diffForHumans() ?? '—'); ?></td>
                            <td><?php echo e($email->replied_at?->format('d M Y, h:i A') ?? '—'); ?></td>
                        </tr>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php if($emails->hasPages()): ?>
        <div class="card-footer"><?php echo e($emails->links()); ?></div>
    <?php endif; ?>
<?php endif; ?>
</div>
<?php /**PATH /var/www/html/resources/views/email-activity/_table.blade.php ENDPATH**/ ?>