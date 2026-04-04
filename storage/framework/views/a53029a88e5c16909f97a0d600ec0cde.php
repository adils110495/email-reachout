<?php $__env->startSection('title', 'Platforms — Settings'); ?>

<?php $__env->startSection('content'); ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-0 fw-bold"><i class="bi bi-grid me-2 text-primary"></i>Platforms</h4>
        <p class="text-muted small mb-0">Manage platforms shown in the Leads module.</p>
    </div>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addPlatformModal">
        <i class="bi bi-plus-lg me-1"></i>Add Platform
    </button>
</div>

<div class="card border-0 shadow-sm rounded-3">
    <div>
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th style="width:50px">#</th>
                    <th>Name</th>
                    <th style="width:120px">Status</th>
                    <th style="width:60px" class="text-center">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php $__empty_1 = true; $__currentLoopData = $platforms; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $i => $platform): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                <tr>
                    <td class="text-muted small"><?php echo e($i + 1); ?></td>
                    <td class="fw-semibold"><?php echo e($platform->name); ?></td>
                    <td>
                        <?php if($platform->status === 'active'): ?>
                            <span class="badge rounded-pill bg-success">Active</span>
                        <?php else: ?>
                            <span class="badge rounded-pill bg-secondary">Inactive</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-center">
                        <div class="dropdown">
                            <button class="btn btn-sm btn-light border rounded-circle px-2"
                                    data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="bi bi-three-dots-vertical"></i>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                                <li>
                                    <button class="dropdown-item btn-edit-platform"
                                            data-id="<?php echo e($platform->id); ?>"
                                            data-name="<?php echo e($platform->name); ?>"
                                            data-status="<?php echo e($platform->status); ?>">
                                        <i class="bi bi-pencil me-2 text-warning"></i>Edit
                                    </button>
                                </li>
                                <li><hr class="dropdown-divider"></li>
                                <li>
                                    <form method="POST" action="<?php echo e(route('platforms.destroy', $platform->id)); ?>"
                                          onsubmit="return confirm('Delete \'<?php echo e(addslashes($platform->name)); ?>\'?')">
                                        <?php echo csrf_field(); ?> <?php echo method_field('DELETE'); ?>
                                        <button type="submit" class="dropdown-item text-danger">
                                            <i class="bi bi-trash me-2"></i>Delete
                                        </button>
                                    </form>
                                </li>
                            </ul>
                        </div>
                    </td>
                </tr>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                <tr>
                    <td colspan="4" class="text-center text-muted py-4">No platforms yet.</td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>


<div class="modal fade" id="addPlatformModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-sm">
        <div class="modal-content border-0 shadow">
            <form method="POST" action="<?php echo e(route('platforms.store')); ?>">
                <?php echo csrf_field(); ?>
                <div class="modal-header">
                    <h5 class="modal-title fw-bold"><i class="bi bi-plus-circle me-2 text-primary"></i>Add Platform</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" placeholder="e.g. Twitter" required>
                    </div>
                    <div class="mb-0">
                        <label class="form-label fw-semibold">Status</label>
                        <select name="status" class="form-select">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>Add</button>
                </div>
            </form>
        </div>
    </div>
</div>


<div class="modal fade" id="editPlatformModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-sm">
        <div class="modal-content border-0 shadow">
            <form method="POST" id="editPlatformForm">
                <?php echo csrf_field(); ?> <?php echo method_field('PUT'); ?>
                <div class="modal-header">
                    <h5 class="modal-title fw-bold"><i class="bi bi-pencil-square me-2 text-warning"></i>Edit Platform</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" id="edit_platform_name" class="form-control" required>
                    </div>
                    <div class="mb-0">
                        <label class="form-label fw-semibold">Status</label>
                        <select name="status" id="edit_platform_status" class="form-select">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning"><i class="bi bi-save me-1"></i>Update</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php $__env->stopSection(); ?>

<?php $__env->startPush('scripts'); ?>
<script>
    document.querySelectorAll('.btn-edit-platform').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const id     = this.dataset.id;
            const name   = this.dataset.name;
            const status = this.dataset.status;

            document.getElementById('edit_platform_name').value   = name;
            document.getElementById('edit_platform_status').value = status;
            document.getElementById('editPlatformForm').action    = '/settings/platforms/' + id;

            new bootstrap.Modal(document.getElementById('editPlatformModal')).show();
        });
    });
</script>
<?php $__env->stopPush(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /var/www/html/resources/views/platforms/index.blade.php ENDPATH**/ ?>