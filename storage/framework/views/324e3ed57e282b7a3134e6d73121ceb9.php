<?php $__env->startSection('title', 'Categories — Settings'); ?>
<?php $__env->startSection('page-title', 'Categories'); ?>

<?php $__env->startSection('breadcrumb'); ?>
    <li class="breadcrumb-item">Settings</li>
    <li class="breadcrumb-item active" aria-current="page">Categories</li>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('content'); ?>

<?php
    // Carried by every form so a row action returns to the same filtered list.
    $redirectBack = request()->getQueryString() ? '?'.request()->getQueryString() : '';
?>

<div class="row">
    <div class="col-xl-12">
        
        <div class="card" data-ajax-root>

            <div class="card-header py-3 d-sm-flex d-block align-items-center justify-content-between">
                <div class="clearfix">
                    <h4 class="card-title"><i class="bi bi-tag me-2 text-primary"></i>Categories</h4>
                    <p class="mb-0 fs-13">Manage lead categories used to organise and filter leads.</p>
                </div>
                <div class="clearfix">
                    <a href="<?php echo e(route('categories.export')); ?>" class="btn btn-light btn-sm m-1">
                        <i class="bi bi-download me-1"></i>Export CSV
                    </a>
                    <button class="btn btn-primary btn-sm m-1" data-bs-toggle="modal" data-bs-target="#addCategoryModal">
                        <i class="bi bi-plus-lg me-1"></i>Add Category
                    </button>
                </div>
            </div>

            
            <div class="card-header d-block pb-2">
                <div class="row filter-bar align-items-start">

                    
                    <div class="col-12 col-md-6 col-xl-4 mb-3">
                        <label class="form-label" for="categorySearch">Search</label>
                        <input type="text" id="categorySearch" class="form-control" data-live-filter
                               placeholder="Search by name…" autocomplete="off">
                    </div>

                    
                    <div class="col-6 col-md-4 col-xl-3 mb-3">
                        <label class="form-label" for="categoryStatusFilter">Status</label>
                        
                        <select id="categoryStatusFilter" class="form-select select2" data-param="status" data-placeholder="All Statuses">
                            <option value="">All Statuses</option>
                            <?php $__currentLoopData = $statusOptions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $value => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <option value="<?php echo e($value); ?>" <?php echo e($activeStatus === $value ? 'selected' : ''); ?>>
                                    <?php echo e($label); ?>

                                </option>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        </select>
                    </div>

                    
                    <div class="col-6 col-md-2 col-xl-2 mb-3">
                        
                        <label class="form-label" aria-hidden="true">&nbsp;</label>
                        <a href="<?php echo e(route('categories.index')); ?>" class="btn btn-danger light" title="Clear all filters">
                            <i class="bi bi-x-circle me-1"></i>Clear
                        </a>
                    </div>
                </div>
            </div>

            
            <div class="ajax-region">
                <?php echo $__env->make('categories._table', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
            </div>

        </div>
    </div>
</div>


<div class="modal fade" id="addCategoryModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <form method="POST" action="<?php echo e(route('categories.store')); ?>">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="_redirect_back" value="<?php echo e($redirectBack); ?>">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-plus-circle me-2 text-primary"></i>Add Category</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control <?php $__errorArgs = ['name'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>"
                               placeholder="e.g. Web Design" value="<?php echo e(old('name')); ?>" required>
                        <?php $__errorArgs = ['name'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
                            <div class="invalid-feedback"><?php echo e($message); ?></div>
                        <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
                    </div>
                    <div class="mb-0">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select">
                            <option value="active" <?php echo e(old('status') === 'inactive' ? '' : 'selected'); ?>>Active</option>
                            <option value="inactive" <?php echo e(old('status') === 'inactive' ? 'selected' : ''); ?>>Inactive</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>Add</button>
                </div>
            </form>
        </div>
    </div>
</div>


<div class="modal fade" id="editCategoryModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <form method="POST" id="editCategoryForm">
                <?php echo csrf_field(); ?> <?php echo method_field('PUT'); ?>
                <input type="hidden" name="_redirect_back" value="<?php echo e($redirectBack); ?>">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-pencil-square me-2 text-warning"></i>Edit Category</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" id="edit_category_name" class="form-control" required>
                    </div>
                    <div class="mb-0">
                        <label class="form-label">Status</label>
                        <select name="status" id="edit_category_status" class="form-select">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning"><i class="bi bi-save me-1"></i>Update</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php $__env->stopSection(); ?>

<?php $__env->startPush('scripts'); ?>
<script>
    // Route template resolved server-side so the URL always follows routes/web.php.
    const categoryUpdateUrl = <?php echo json_encode(route('categories.update', ['id' => '__ID__']), 512) ?>;

    // Delegated: the rows are replaced wholesale on every filter change, so a
    // listener bound to each button at load time would not survive the swap.
    document.addEventListener('click', function (e) {
        const btn = e.target.closest('.btn-edit-category');
        if (! btn) return;

        document.getElementById('edit_category_name').value   = btn.dataset.name;
        document.getElementById('edit_category_status').value = btn.dataset.status;
        document.getElementById('editCategoryForm').action    = categoryUpdateUrl.replace('__ID__', btn.dataset.id);

        new bootstrap.Modal(document.getElementById('editCategoryModal')).show();
    });

    
    <?php if($errors->any()): ?>
        new bootstrap.Modal(document.getElementById('addCategoryModal')).show();
    <?php endif; ?>
</script>
<?php $__env->stopPush(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /var/www/html/resources/views/categories/index.blade.php ENDPATH**/ ?>