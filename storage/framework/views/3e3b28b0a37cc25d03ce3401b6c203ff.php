<?php $__env->startSection('title', 'Deals'); ?>
<?php $__env->startSection('page-title', 'Deals'); ?>

<?php $__env->startSection('breadcrumb'); ?>
    <li class="breadcrumb-item active" aria-current="page">Deals</li>
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
                    <h4 class="card-title">
                        <i class="bi bi-cash-coin me-2 text-primary"></i>Deals
                        <span class="badge badge-primary light ms-1" data-ajax-total><?php echo e($cashLeads->total()); ?></span>
                    </h4>
                    <p class="mb-0 fs-13">Leads you have talked to and expect to turn into paying customers.</p>
                </div>
                <div class="clearfix">
                    <a href="<?php echo e(route('cash-leads.export')); ?>" class="btn btn-light btn-sm m-1">
                        <i class="bi bi-download me-1"></i>Export CSV
                    </a>
                    <button class="btn btn-primary btn-sm m-1" data-bs-toggle="modal" data-bs-target="#addCashModal">
                        <i class="bi bi-plus-lg me-1"></i>Add Deal
                    </button>
                </div>
            </div>

            
            <div class="card-header d-block pb-2">
                <div class="row filter-bar align-items-start">
                    <div class="col-12 col-md-6 col-xl-4 mb-3">
                        <label class="form-label" for="cashSearch">Search</label>
                        <input type="text" id="cashSearch" class="form-control" data-search-param="q"
                               value="<?php echo e($search); ?>" placeholder="Company, email, phone, website or notes…" autocomplete="off">
                    </div>
                    <div class="col-6 col-md-3 col-xl-3 mb-3">
                        <label class="form-label" for="cashCategoryFilter">Category</label>
                        <select id="cashCategoryFilter" class="form-select select2" data-param="category" data-placeholder="All Categories">
                            <option value="">All Categories</option>
                            <?php $__currentLoopData = $categories; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $cat): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <option value="<?php echo e($cat->id); ?>" <?php echo e((int) $activeCategory === $cat->id ? 'selected' : ''); ?>><?php echo e($cat->name); ?></option>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        </select>
                    </div>
                    <div class="col-6 col-md-3 col-xl-3 mb-3">
                        <label class="form-label" for="cashSourceFilter">Source</label>
                        <select id="cashSourceFilter" class="form-select select2" data-param="source" data-placeholder="All Sources">
                            <option value="">All Sources</option>
                            <?php $__currentLoopData = $sources; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $value => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <option value="<?php echo e($value); ?>" <?php echo e($activeSource === $value ? 'selected' : ''); ?>><?php echo e($label); ?></option>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        </select>
                    </div>
                    <div class="col-6 col-md-2 col-xl-2 mb-3">
                        <label class="form-label" aria-hidden="true">&nbsp;</label>
                        <a href="<?php echo e(route('cash-leads.index')); ?>" class="btn btn-danger light" title="Clear all filters">
                            <i class="bi bi-x-circle me-1"></i>Clear
                        </a>
                    </div>
                </div>
            </div>

            <div class="ajax-region">
                <?php echo $__env->make('cash-leads._table', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
            </div>

        </div>
    </div>
</div>


<?php $__currentLoopData = ['add' => ['Add Deal', 'bi-plus-circle text-primary', route('cash-leads.store'), false],
          'edit' => ['Edit Deal', 'bi-pencil-square text-warning', '#', true]]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $mode => [$title, $iconClass, $action, $isEdit]): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
<div class="modal fade" id="<?php echo e($mode); ?>CashModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
        <div class="modal-content">
            <form method="POST" action="<?php echo e($action); ?>" id="<?php echo e($mode); ?>CashForm">
                <?php echo csrf_field(); ?>
                <?php if($isEdit): ?> <?php echo method_field('PUT'); ?> <?php endif; ?>
                <input type="hidden" name="_redirect_back" value="<?php echo e($redirectBack); ?>">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi <?php echo e($iconClass); ?> me-2"></i><?php echo e($title); ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label">Company / Business name <span class="text-danger">*</span></label>
                            <input type="text" name="company_name" id="<?php echo e($mode); ?>_cash_company"
                                   class="form-control <?php if(! $isEdit): ?> <?php $__errorArgs = ['company_name'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?> <?php endif; ?>"
                                   value="<?php echo e($isEdit ? '' : old('company_name')); ?>" required maxlength="255">
                            <?php if(! $isEdit): ?> <?php $__errorArgs = ['company_name'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><div class="invalid-feedback"><?php echo e($message); ?></div><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?> <?php endif; ?>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Category</label>
                            <select name="category_id" id="<?php echo e($mode); ?>_cash_category" class="form-select">
                                <option value="">— None —</option>
                                <?php $__currentLoopData = $categories; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $cat): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                    <option value="<?php echo e($cat->id); ?>" <?php echo e(! $isEdit && (string) old('category_id') === (string) $cat->id ? 'selected' : ''); ?>><?php echo e($cat->name); ?></option>
                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" id="<?php echo e($mode); ?>_cash_email" class="form-control"
                                   value="<?php echo e($isEdit ? '' : old('email')); ?>" maxlength="255">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Phone</label>
                            <input type="text" name="phone" id="<?php echo e($mode); ?>_cash_phone" class="form-control"
                                   value="<?php echo e($isEdit ? '' : old('phone')); ?>" maxlength="50">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Website</label>
                            <input type="text" name="website" id="<?php echo e($mode); ?>_cash_website" class="form-control"
                                   value="<?php echo e($isEdit ? '' : old('website')); ?>" maxlength="255">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Address</label>
                            <input type="text" name="address" id="<?php echo e($mode); ?>_cash_address" class="form-control"
                                   value="<?php echo e($isEdit ? '' : old('address')); ?>" maxlength="255">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Notes</label>
                            <textarea name="notes" id="<?php echo e($mode); ?>_cash_notes" class="form-control" rows="3"
                                      placeholder="What was discussed, expected deal, next step…" maxlength="5000"><?php echo e($isEdit ? '' : old('notes')); ?></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn <?php echo e($isEdit ? 'btn-warning' : 'btn-primary'); ?>">
                        <i class="bi <?php echo e($isEdit ? 'bi-save' : 'bi-plus-lg'); ?> me-1"></i><?php echo e($isEdit ? 'Update' : 'Add'); ?>

                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>

<?php $__env->stopSection(); ?>

<?php $__env->startPush('scripts'); ?>
<script>
    // Route template resolved server-side so the URL always follows routes/web.php.
    const cashUpdateUrl = <?php echo json_encode(route('cash-leads.update', ['id' => '__ID__']), 512) ?>;

    // Delegated: the rows are replaced wholesale on every filter change, so a
    // listener bound to each button at load time would not survive the swap.
    document.addEventListener('click', function (e) {
        const btn = e.target.closest('.btn-edit-cash');
        if (! btn) return;

        document.getElementById('edit_cash_company').value  = btn.dataset.company  || '';
        document.getElementById('edit_cash_category').value = btn.dataset.category || '';
        document.getElementById('edit_cash_email').value    = btn.dataset.email    || '';
        document.getElementById('edit_cash_phone').value    = btn.dataset.phone    || '';
        document.getElementById('edit_cash_website').value  = btn.dataset.website  || '';
        document.getElementById('edit_cash_address').value  = btn.dataset.address  || '';
        document.getElementById('edit_cash_notes').value    = btn.dataset.notes    || '';
        document.getElementById('editCashForm').action      = cashUpdateUrl.replace('__ID__', btn.dataset.id);

        new bootstrap.Modal(document.getElementById('editCashModal')).show();
    });

    
    <?php if($errors->any()): ?>
        new bootstrap.Modal(document.getElementById('addCashModal')).show();
    <?php endif; ?>
</script>
<?php $__env->stopPush(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /var/www/html/resources/views/cash-leads/index.blade.php ENDPATH**/ ?>