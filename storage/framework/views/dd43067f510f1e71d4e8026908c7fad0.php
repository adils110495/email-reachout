
<div class="ajax-content">
<?php if($addresses->isEmpty()): ?>
    <div class="card-body">
        <div class="empty-state">
            <i class="bi bi-geo-alt empty-state-icon"></i>
            <?php if($activeStatus): ?>
                No <strong><?php echo e($statusOptions[$activeStatus] ?? $activeStatus); ?></strong> addresses.
                <a href="<?php echo e(route('addresses.index')); ?>">Clear the filter</a>.
            <?php else: ?>
                No addresses yet. Add one to get started.
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
                        <th class="mw-200">Address</th>
                        <th class="mw-150">Email</th>
                        <th class="mw-120">Phone</th>
                        <th class="mw-120">Alt. Phone</th>
                        <th class="mw-150">Website</th>
                        <th style="width:120px">Status</th>
                        <th style="width:80px" class="text-center">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $__currentLoopData = $addresses; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $i => $addr): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <tr data-search="<?php echo e(strtolower(implode(' ', [$addr->address, $addr->email, $addr->phone, $addr->alternate_phone, $addr->website, $addr->status]))); ?>">
                        <td><span><?php echo e($i + 1); ?></span></td>
                        <td><span><?php echo e($addr->address); ?></span></td>
                        <td>
                            <a href="mailto:<?php echo e($addr->email); ?>" class="text-primary"><?php echo e($addr->email); ?></a>
                        </td>
                        <td><span><?php echo e($addr->phone); ?></span></td>
                        <td><span><?php echo e($addr->alternate_phone ?: '—'); ?></span></td>
                        <td>
                            <?php if($addr->website): ?>
                                <a href="<?php echo e($addr->website); ?>" target="_blank" rel="noopener"
                                   class="text-primary text-truncate d-inline-block" style="max-width:150px"
                                   title="<?php echo e($addr->website); ?>">
                                    <i class="bi bi-box-arrow-up-right me-1"></i><?php echo e($addr->website); ?>

                                </a>
                            <?php else: ?>
                                <span>—</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if($addr->status === 'active'): ?>
                                <span class="badge badge-success light">Active</span>
                            <?php else: ?>
                                <span class="badge badge-secondary light">Inactive</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-center">
                            <div class="dropdown">
                                <button class="btn btn-sm btn-light btn-square"
                                        data-bs-toggle="dropdown" data-bs-strategy="fixed" aria-expanded="false" aria-label="Actions">
                                    <i class="bi bi-three-dots-vertical"></i>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <li>
                                        <button class="dropdown-item btn-edit-address"
                                                data-id="<?php echo e($addr->id); ?>"
                                                data-address="<?php echo e($addr->address); ?>"
                                                data-email="<?php echo e($addr->email); ?>"
                                                data-phone="<?php echo e($addr->phone); ?>"
                                                data-alternate_phone="<?php echo e($addr->alternate_phone); ?>"
                                                data-website="<?php echo e($addr->website); ?>"
                                                data-status="<?php echo e($addr->status); ?>">
                                            <i class="bi bi-pencil me-2 text-warning"></i>Edit
                                        </button>
                                    </li>
                                    <li>
                                        <form method="POST" action="<?php echo e(route('addresses.destroy', $addr->id)); ?>"
                                              onsubmit="return confirm('Delete this address?')">
                                            <?php echo csrf_field(); ?> <?php echo method_field('DELETE'); ?>
                                            <input type="hidden" name="_redirect_back" value="<?php echo e(request()->getQueryString() ? '?'.request()->getQueryString() : ''); ?>">
                                            <button type="submit" class="dropdown-item text-danger">
                                                <i class="bi bi-trash me-2"></i>Delete
                                            </button>
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
<?php /**PATH /var/www/html/resources/views/addresses/_table.blade.php ENDPATH**/ ?>