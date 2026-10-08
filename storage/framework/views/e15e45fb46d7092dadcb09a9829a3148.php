<?php $__env->startSection('title', 'GMB Leads'); ?>
<?php $__env->startSection('page-title', 'GMB Leads'); ?>

<?php $__env->startSection('breadcrumb'); ?>
    <li class="breadcrumb-item active" aria-current="page">GMB Leads</li>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('content'); ?>


<div class="row">
    <div class="col-xl-12">
        <div class="card">
            <div class="card-header py-3">
                <h4 class="card-title"><i class="bi bi-geo-alt me-2 text-primary"></i>Find Businesses Without a Website</h4>
            </div>
            <div class="card-body">
                <form action="<?php echo e(route('gmb-leads.search')); ?>" method="POST" id="gmbSearchForm">
                    <?php echo csrf_field(); ?>
                    <div class="row g-3 align-items-start">
                        <div class="col-12 col-md-3">
                            <label class="form-label">Category</label>
                            <select name="search_category" class="form-select" required>
                                <option value="">-- Select Category --</option>
                                <?php $__currentLoopData = $categories; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $cat): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                    <option value="<?php echo e($cat->id); ?>" <?php echo e((string) old('search_category') === (string) $cat->id ? 'selected' : ''); ?>>
                                        <?php echo e($cat->name); ?>

                                    </option>
                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                            </select>
                            <?php $__errorArgs = ['search_category'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><div class="text-danger fs-13"><?php echo e($message); ?></div><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label">Keyword</label>
                            <input type="text" name="keyword" class="form-control <?php $__errorArgs = ['keyword'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>"
                                   placeholder="e.g. plumber in Manchester, bakery Meerut"
                                   value="<?php echo e(old('keyword')); ?>" required minlength="2" maxlength="200">
                            <?php $__errorArgs = ['keyword'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><div class="invalid-feedback"><?php echo e($message); ?></div><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
                            <div class="form-text">Include the city. Only businesses with a Google Business Profile and <strong>no website</strong> are saved.</div>
                        </div>
                        <div class="col-12 col-md-3">
                            <label class="form-label d-none d-md-block">&nbsp;</label>
                            <button type="submit" class="btn btn-primary w-100" id="gmbFindBtn">
                                <span class="spinner-border spinner-border-sm d-none me-1" id="gmbSpinner"></span>
                                <i class="bi bi-lightning-charge-fill me-1" id="gmbIcon"></i>Find
                            </button>
                        </div>
                    </div>
                    <div class="text-danger fs-13 mt-2 d-none" id="gmbSearchError"></div>
                </form>
            </div>
        </div>
    </div>
</div>


<div class="row">
    <div class="col-xl-12">
        
        <div class="card" data-ajax-root>

            <div class="card-header py-3 d-sm-flex d-block align-items-center justify-content-between">
                <div class="clearfix">
                    <h4 class="card-title">
                        <i class="bi bi-shop me-2 text-primary"></i>GMB Leads
                        <span class="badge badge-primary light ms-1" data-ajax-total><?php echo e($gmbLeads->total()); ?></span>
                    </h4>
                    <p class="mb-0 fs-13">Businesses with a Google Business Profile but no website.</p>
                </div>
                <div class="clearfix">
                    <a href="<?php echo e(route('gmb-leads.export')); ?>" class="btn btn-light btn-sm m-1">
                        <i class="bi bi-download me-1"></i>Export CSV
                    </a>
                </div>
            </div>

            <div class="card-header d-block pb-2">
                <div class="row filter-bar align-items-start">
                    <div class="col-12 col-md-6 col-xl-3 mb-3">
                        <label class="form-label" for="gmbSearch">Search</label>
                        <input type="text" id="gmbSearch" class="form-control" data-search-param="q"
                               value="<?php echo e($search); ?>" placeholder="Name, phone, address or keyword…" autocomplete="off">
                    </div>
                    <div class="col-6 col-md-4 col-xl-3 mb-3">
                        <label class="form-label" for="gmbCategoryFilter">Category</label>
                        <select id="gmbCategoryFilter" class="form-select select2" data-param="category" data-placeholder="All Categories">
                            <option value="">All Categories</option>
                            <?php $__currentLoopData = $categories; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $cat): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <option value="<?php echo e($cat->id); ?>" <?php echo e((int) $activeCategory === $cat->id ? 'selected' : ''); ?>><?php echo e($cat->name); ?></option>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        </select>
                    </div>
                    <div class="col-6 col-md-4 col-xl-2 mb-3">
                        <label class="form-label" for="gmbRatingFilter">Rating</label>
                        <select id="gmbRatingFilter" class="form-select select2" data-param="rating" data-placeholder="Any Rating">
                            <option value="">Any Rating</option>
                            <?php $__currentLoopData = $ratingOptions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $value => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <option value="<?php echo e($value); ?>" <?php echo e($activeRating === (string) $value ? 'selected' : ''); ?>><?php echo e($label); ?></option>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        </select>
                    </div>
                    
                    <div class="col-6 col-md-4 col-xl-2 mb-3">
                        <label class="form-label">Reviews</label>
                        <div class="input-group">
                            <input type="number" class="form-control" data-search-param="reviews_min" value="<?php echo e($reviewsMin); ?>"
                                   placeholder="Min" min="0" step="1" aria-label="Minimum reviews">
                            <input type="number" class="form-control" data-search-param="reviews_max" value="<?php echo e($reviewsMax); ?>"
                                   placeholder="Max" min="0" step="1" aria-label="Maximum reviews">
                        </div>
                    </div>
                    <div class="col-6 col-md-4 col-xl-1 mb-3">
                        <label class="form-label" aria-hidden="true">&nbsp;</label>
                        <a href="<?php echo e(route('gmb-leads.index')); ?>" class="btn btn-danger light" title="Clear all filters">
                            <i class="bi bi-x-circle me-1"></i>Clear
                        </a>
                    </div>
                </div>
            </div>

            <div class="ajax-region">
                <?php echo $__env->make('gmb-leads._table', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
            </div>

        </div>
    </div>
</div>

<?php $__env->stopSection(); ?>

<?php $__env->startPush('scripts'); ?>
<script>
    // Submit by fetch so the page does not reload. The Find button stays
    // disabled while the job runs; the notification poller announces the end
    // ('app:notification'), which re-enables it and refreshes the list.
    (function () {
        const form    = document.getElementById('gmbSearchForm');
        const btn     = document.getElementById('gmbFindBtn');
        const spinner = document.getElementById('gmbSpinner');
        const icon    = document.getElementById('gmbIcon');
        const errBox  = document.getElementById('gmbSearchError');
        let failsafe  = null;

        function setBusy(on) {
            btn.disabled = on;
            spinner.classList.toggle('d-none', ! on);
            icon.classList.toggle('d-none', on);
            window.clearTimeout(failsafe);
            // Never leave the button locked forever if the notification is missed.
            if (on) failsafe = window.setTimeout(function () { setBusy(false); }, 180000);
        }

        form.addEventListener('submit', function (e) {
            e.preventDefault();
            errBox.classList.add('d-none');
            setBusy(true);

            fetch(form.action, {
                method: 'POST',
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                body: new FormData(form),
            })
                .then(function (r) {
                    if (r.ok) {
                        form.querySelector('[name=keyword]').value = '';
                        return; // stay busy until the job's notification arrives
                    }
                    return r.json().catch(function () { return {}; }).then(function (data) {
                        const first = data.errors ? Object.values(data.errors)[0][0] : (data.message || 'Could not start the search.');
                        errBox.textContent = first;
                        errBox.classList.remove('d-none');
                        setBusy(false);
                    });
                })
                .catch(function () {
                    errBox.textContent = 'Could not start the search. Please try again.';
                    errBox.classList.remove('d-none');
                    setBusy(false);
                });
        });

        document.addEventListener('app:notification', function (e) {
            if (! /^GMB search/.test(e.detail.title || '')) return;

            setBusy(false);
            document.querySelector('[data-ajax-root]').dispatchEvent(new CustomEvent('ajax-filters:reload'));
        });
    })();
</script>
<?php $__env->stopPush(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /var/www/html/resources/views/gmb-leads/index.blade.php ENDPATH**/ ?>