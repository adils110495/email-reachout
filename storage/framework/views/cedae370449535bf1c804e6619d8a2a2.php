<?php $__env->startSection('title', 'Mail Settings — Settings'); ?>
<?php $__env->startSection('page-title', 'Mail Settings'); ?>

<?php $__env->startSection('breadcrumb'); ?>
    <li class="breadcrumb-item">Settings</li>
    <li class="breadcrumb-item active" aria-current="page">Mail Settings</li>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('content'); ?>

<div class="row">
    <div class="col-xl-12">
        <div class="card">
            <div class="card-header py-3 d-sm-flex d-block align-items-center justify-content-between">
                <div>
                    <h4 class="card-title"><i class="bi bi-envelope-gear me-2 text-primary"></i>Mail Settings</h4>
                    <p class="mb-0 fs-13">Sending accounts. The <strong>default</strong> account sends emails from the Leads page; sequences can use any active account.
                        If no account is saved, the values from <code>.env</code> are used. Passwords are stored encrypted and never shown.</p>
                </div>
                <a href="<?php echo e(route('mail-settings.create')); ?>" class="btn btn-primary btn-sm m-1 text-nowrap"><i class="bi bi-plus-lg me-1"></i>Add Account</a>
            </div>

            <?php if($accounts->isEmpty()): ?>
                <div class="card-body">
                    <div class="empty-state">
                        <i class="bi bi-envelope-gear empty-state-icon"></i>
                        No accounts yet. <a href="<?php echo e(route('mail-settings.create')); ?>">Add your first sending account</a>.
                    </div>
                </div>
            <?php else: ?>
            <div class="card-body table-card-body px-0 pt-0 pb-2">
                <div class="table-responsive">
                    <table class="table">
                        <thead class="table-light">
                            <tr>
                                <th class="mw-150">Account</th>
                                <th class="mw-150">SMTP (sending)</th>
                                <th class="mw-150 d-none d-md-table-cell">IMAP (sent copy + replies)</th>
                                <th class="d-none d-lg-table-cell">Limits</th>
                                <th style="width:110px">Status</th>
                                <th style="width:80px" class="text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $__currentLoopData = $accounts; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $account): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <tr>
                                <td>
                                    <h6 class="mb-0 cell-wrap"><?php echo e($account->name); ?> <?php if($account->is_default): ?><span class="badge badge-primary light ms-1">Default</span><?php endif; ?></h6>
                                    <div class="fs-13 text-muted"><?php echo e($account->from_name ? $account->from_name.' · ' : ''); ?><?php echo e($account->senderEmail()); ?></div>
                                    <?php if($unreadable = $account->unreadableSecrets()): ?>
                                        <div class="fs-13 text-danger mt-1">
                                            <i class="bi bi-exclamation-triangle"></i>
                                            Saved <?php echo e(implode(' and ', $unreadable)); ?> password can't be read (app key changed).
                                            <a href="<?php echo e(route('mail-settings.edit', $account)); ?>">Re-enter it</a>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span><?php echo e($account->host ?: '—'); ?><?php echo e($account->host ? ':'.$account->port : ''); ?></span>
                                    <div class="fs-13">
                                        <?php if($account->smtp_ok === true): ?><span class="text-success"><i class="bi bi-check-circle"></i> OK</span>
                                        <?php elseif($account->smtp_ok === false): ?><span class="text-danger"><i class="bi bi-x-circle"></i> Failed</span>
                                        <?php else: ?><span class="text-muted">Not tested</span><?php endif; ?>
                                    </div>
                                </td>
                                <td class="d-none d-md-table-cell">
                                    <?php if($account->hasImap()): ?>
                                        <span><?php echo e($account->imap_host); ?>:<?php echo e($account->imap_port); ?></span>
                                        <div class="fs-13">
                                            <?php if($account->imap_ok === true): ?><span class="text-success"><i class="bi bi-check-circle"></i> OK</span>
                                            <?php elseif($account->imap_ok === false): ?><span class="text-danger" title="<?php echo e($account->imap_last_error); ?>"><i class="bi bi-x-circle"></i> <?php echo e(Str::limit($account->imap_last_error ?: 'Failed', 40)); ?></span>
                                            <?php else: ?><span class="text-muted">Not tested</span><?php endif; ?>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-muted">Not configured</span>
                                    <?php endif; ?>
                                </td>
                                <td class="d-none d-lg-table-cell fs-13"><?php echo e($account->rate_limit_per_minute); ?>/min<br><?php echo e($account->daily_limit ? $account->daily_limit.'/day' : 'no daily cap'); ?></td>
                                <td>
                                    <?php if($account->is_active): ?><span class="badge badge-success light">Active</span>
                                    <?php else: ?><span class="badge badge-secondary light">Inactive</span><?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <div class="dropdown">
                                        <button class="btn btn-sm btn-light btn-square" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Actions" title="Actions">
                                            <i class="bi bi-three-dots-vertical"></i>
                                        </button>
                                        <ul class="dropdown-menu dropdown-menu-end">
                                            <li><a class="dropdown-item" href="<?php echo e(route('mail-settings.edit', $account)); ?>"><i class="bi bi-pencil me-2 text-warning"></i>Edit</a></li>
                                            <li>
                                                <form method="POST" action="<?php echo e(route('mail-settings.check', $account)); ?>"><?php echo csrf_field(); ?>
                                                    <button type="submit" class="dropdown-item"><i class="bi bi-plug me-2 text-primary"></i>Test connection</button>
                                                </form>
                                            </li>
                                            <?php if (! ($account->is_default)): ?>
                                            <li>
                                                <form method="POST" action="<?php echo e(route('mail-settings.default', $account)); ?>"><?php echo csrf_field(); ?>
                                                    <button type="submit" class="dropdown-item"><i class="bi bi-star me-2 text-success"></i>Make default</button>
                                                </form>
                                            </li>
                                            <?php endif; ?>
                                            <li>
                                                <form method="POST" action="<?php echo e(route('mail-settings.destroy', $account)); ?>" onsubmit="return confirm('Delete this account?')"><?php echo csrf_field(); ?> <?php echo method_field('DELETE'); ?>
                                                    <button type="submit" class="dropdown-item text-danger"><i class="bi bi-trash me-2"></i>Delete</button>
                                                </form>
                                            </li>
                                        </ul>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /var/www/html/resources/views/mail-settings/index.blade.php ENDPATH**/ ?>