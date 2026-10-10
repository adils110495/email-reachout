<?php $__env->startSection('title', 'Email Activity'); ?>
<?php $__env->startSection('page-title', 'Email Activity'); ?>

<?php $__env->startSection('breadcrumb'); ?>
    <li class="breadcrumb-item active" aria-current="page">Email Activity</li>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('content'); ?>

<div class="row">
    <div class="col-xl-12">
        
        <div class="card" data-ajax-root>

            <div class="card-header py-3 d-sm-flex d-block align-items-center justify-content-between">
                <div class="clearfix">
                    <h4 class="card-title"><i class="bi bi-activity me-2 text-primary"></i>Email Activity</h4>
                    <p class="mb-0 fs-13">What happened to every email you sent: opened, replied, or not opened yet.</p>
                </div>
                <div class="clearfix">
                    
                    <form method="POST" action="<?php echo e(route('email-activity.check-replies')); ?>" class="d-inline" id="checkRepliesForm">
                        <?php echo csrf_field(); ?>
                        <button type="submit" class="btn btn-primary btn-sm m-1" id="checkRepliesBtn">
                            <i class="bi bi-arrow-repeat me-1"></i>Check Replies
                        </button>
                    </form>
                </div>
            </div>

            
            <div id="checkRepliesResult" class="px-3 pt-3" hidden></div>

            
            <div class="card-header d-block pb-2">
                <div class="row filter-bar align-items-start">

                    
                    <div class="col-12 col-md-6 col-xl-4 mb-3">
                        <label class="form-label" for="activitySearch">Search</label>
                        <input type="text" id="activitySearch" class="form-control" data-search-param="q"
                               value="<?php echo e($search); ?>" placeholder="Company, email or subject…" autocomplete="off">
                    </div>

                    
                    <div class="col-6 col-md-4 col-xl-3 mb-3">
                        <label class="form-label" for="activityFilter">Activity</label>
                        <select id="activityFilter" class="form-select select2" data-param="activity" data-placeholder="All Activity">
                            <option value="">All Activity (<?php echo e($counts['total']); ?>)</option>
                            <?php $__currentLoopData = $activityOptions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $value => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <option value="<?php echo e($value); ?>" <?php echo e($activity === $value ? 'selected' : ''); ?>>
                                    <?php echo e($label); ?> (<?php echo e($counts[$value]); ?>)
                                </option>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        </select>
                    </div>

                    
                    <?php if($sequences->isNotEmpty()): ?>
                    <div class="col-6 col-md-4 col-xl-3 mb-3">
                        <label class="form-label" for="sequenceFilter">Sequence</label>
                        <select id="sequenceFilter" class="form-select select2" data-param="sequence" data-placeholder="All Emails">
                            <option value="">All Emails</option>
                            <?php $__currentLoopData = $sequences; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $seq): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <option value="<?php echo e($seq->id); ?>" <?php echo e($sequenceId === $seq->id ? 'selected' : ''); ?>><?php echo e($seq->name); ?></option>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        </select>
                    </div>
                    <?php endif; ?>

                    
                    <div class="col-6 col-md-2 col-xl-2 mb-3">
                        
                        <label class="form-label" aria-hidden="true">&nbsp;</label>
                        <a href="<?php echo e(route('email-activity.index')); ?>" class="btn btn-danger light" title="Clear all filters">
                            <i class="bi bi-x-circle me-1"></i>Clear
                        </a>
                    </div>
                </div>
            </div>

            
            <div class="ajax-region">
                <?php echo $__env->make('email-activity._table', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
            </div>

        </div>
    </div>
</div>

<?php $__env->stopSection(); ?>

<?php $__env->startPush('scripts'); ?>
<script>
// "Check Replies" without a page reload: show a spinner while the inbox is scanned,
// then show the result, update the filter counts and reload just the table.
(function () {
    const form   = document.getElementById('checkRepliesForm');
    const btn    = document.getElementById('checkRepliesBtn');
    const result = document.getElementById('checkRepliesResult');
    const root   = form.closest('[data-ajax-root]');
    const idle   = btn.innerHTML;

    function showResult(type, icon, message) {
        result.innerHTML = '';
        const alert = document.createElement('div');
        alert.className = 'alert alert-' + type + ' alert-dismissible fade show mb-0';
        alert.setAttribute('role', 'alert');
        alert.innerHTML = '<i class="bi ' + icon + ' me-2"></i><span></span>'
            + '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>';
        alert.querySelector('span').textContent = message;   // server text, never parsed as HTML
        alert.addEventListener('closed.bs.alert', function () { result.hidden = true; });
        result.appendChild(alert);
        result.hidden = false;
    }

    function updateCounts(counts) {
        const select = document.getElementById('activityFilter');
        if (! select || ! counts) return;
        Array.from(select.options).forEach(function (option) {
            const key = option.value === '' ? 'total' : option.value;
            if (counts[key] !== undefined) {
                option.textContent = option.textContent.replace(/\(\d+\)\s*$/, '(' + counts[key] + ')');
            }
        });
        if (window.jQuery && jQuery.fn.select2) jQuery(select).trigger('change.select2');   // refresh the visible label only
    }

    form.addEventListener('submit', function (event) {
        event.preventDefault();
        if (btn.disabled) return;

        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>Checking…';
        showResult('info', 'bi-hourglass-split', 'Checking the inbox for new replies and bounces…');

        const sequence = new URLSearchParams(window.location.search).get('sequence');
        const body = new FormData(form);
        if (sequence) body.append('sequence', sequence);

        fetch(form.action, {
            method: 'POST',
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            body: body,
        })
            .then(function (response) {
                return response.json().catch(function () { return { ok: false, message: 'Unexpected response (HTTP ' + response.status + ').' }; });
            })
            .then(function (data) {
                if (! data.ok) {
                    showResult('danger', 'bi-exclamation-triangle-fill', data.message || 'Could not check replies.');
                    return;
                }
                showResult('success', 'bi-check-circle-fill', data.message);
                updateCounts(data.counts);
                if (data.replies > 0 && root) root.dispatchEvent(new CustomEvent('ajax-filters:reload'));
            })
            .catch(function () {
                showResult('danger', 'bi-exclamation-triangle-fill', 'Network error - please try again.');
            })
            .finally(function () {
                btn.disabled = false;
                btn.innerHTML = idle;
            });
    });
})();
</script>
<?php $__env->stopPush(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /var/www/html/resources/views/email-activity/index.blade.php ENDPATH**/ ?>