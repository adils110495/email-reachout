
<div class="ajax-content">
<?php if($categories->isEmpty()): ?>
    <div class="card-body">
        <div class="empty-state">
            <i class="bi bi-tag empty-state-icon"></i>
            <?php if($activeStatus): ?>
                No <strong><?php echo e($statusOptions[$activeStatus] ?? $activeStatus); ?></strong> categories.
                <a href="<?php echo e(route('categories.index')); ?>">Clear the filter</a>.
            <?php else: ?>
                No categories yet. Add one to get started.
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
                    <?php $__currentLoopData = $categories; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $i => $category): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <tr data-search="<?php echo e(strtolower($category->name . ' ' . $category->status)); ?>">
                        <td><span><?php echo e($i + 1); ?></span></td>
                        <td><h6 class="mb-0"><?php echo e($category->name); ?></h6></td>
                        <td>
                            <?php if($category->status === 'active'): ?>
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
                                        <button class="dropdown-item btn-edit-category"
                                                data-id="<?php echo e($category->id); ?>"
                                                data-name="<?php echo e($category->name); ?>"
                                                data-status="<?php echo e($category->status); ?>">
                                            <i class="bi bi-pencil me-2 text-warning"></i>Edit
                                        </button>
                                    </li>
                                    <li>
                                        <form method="POST" action="<?php echo e(route('categories.destroy', $category->id)); ?>"
                                              onsubmit="return confirm('Delete \'<?php echo e(addslashes($category->name)); ?>\'?')">
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
<?php /**PATH /var/www/html/resources/views/categories/_table.blade.php ENDPATH**/ ?>