
<div class="ajax-content">
<?php if($platforms->isEmpty()): ?>
    <div class="card-body">
        <div class="empty-state">
            <i class="bi bi-grid empty-state-icon"></i>
            <?php if($activeStatus): ?>
                No <strong><?php echo e($statusOptions[$activeStatus] ?? $activeStatus); ?></strong> platforms.
                <a href="<?php echo e(route('platforms.index')); ?>">Clear the filter</a>.
            <?php else: ?>
                No platforms yet.
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
                        <th class="mw-150">Name</th>
                        <th style="width:130px">Status</th>
                        <th style="width:80px" class="text-center">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $__currentLoopData = $platforms; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $i => $platform): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <tr data-search="<?php echo e(strtolower($platform->name . ' ' . $platform->status)); ?>">
                        <td><span><?php echo e($i + 1); ?></span></td>
                        <td><h6 class="mb-0"><?php echo e($platform->name); ?></h6></td>
                        <td>
                            <?php if($platform->status === 'active'): ?>
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
                                        <button class="dropdown-item btn-edit-platform"
                                                data-id="<?php echo e($platform->id); ?>"
                                                data-name="<?php echo e($platform->name); ?>"
                                                data-status="<?php echo e($platform->status); ?>">
                                            <i class="bi bi-pencil me-2 text-warning"></i>Edit
                                        </button>
                                    </li>
                                    <li>
                                        <form method="POST" action="<?php echo e(route('platforms.destroy', $platform->id)); ?>"
                                              onsubmit="return confirm('Delete \'<?php echo e(addslashes($platform->name)); ?>\'?')">
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
<?php /**PATH /var/www/html/resources/views/platforms/_table.blade.php ENDPATH**/ ?>