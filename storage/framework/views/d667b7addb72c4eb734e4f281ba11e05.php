<?php $__env->startSection('title', 'Email Templates — Settings'); ?>

<?php $__env->startSection('content'); ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-0 fw-bold"><i class="bi bi-envelope-paper me-2 text-primary"></i>Email Templates</h4>
        <p class="text-muted small mb-0">Create reusable templates to load into the compose window.</p>
    </div>
    <a href="<?php echo e(route('templates.create')); ?>" class="btn btn-primary">
        <i class="bi bi-plus-lg me-1"></i>New Template
    </a>
</div>


<div class="card border-0 shadow-sm rounded-3 mb-3">
    <div class="card-body py-2 px-3">
        <div class="row g-2 align-items-center">
            <div class="col">
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-light border-end-0">
                        <i class="bi bi-funnel"></i>
                    </span>
                    <input type="text" id="templateSearch" class="form-control border-start-0 bg-light"
                           placeholder="Search by name or subject…" autocomplete="off">
                </div>
            </div>
            <div class="col-auto d-flex gap-1 align-items-center">
                <span class="badge bg-success text-white tpl-badge" data-status="active" role="button" style="cursor:pointer;" title="Filter by Active">Active</span>
                <span class="badge bg-secondary text-white tpl-badge" data-status="inactive" role="button" style="cursor:pointer;" title="Filter by Inactive">Inactive</span>
                <span class="badge bg-danger text-white tpl-badge" data-status="deleted" role="button" style="cursor:pointer;" title="Filter by Deleted">Deleted</span>
                <a href="<?php echo e(route('templates.index')); ?>" class="btn btn-sm btn-primary py-0 px-2 ms-1" title="Clear all filters">
                    <i class="bi bi-x-circle me-1"></i>Clear
                </a>
            </div>
        </div>
    </div>
</div>

<?php if($templates->isEmpty()): ?>
    <div class="card border-0 shadow-sm rounded-3">
        <div class="card-body text-center py-5 text-muted">
            <i class="bi bi-envelope-paper display-4 d-block mb-3"></i>
            No templates found. <a href="<?php echo e(route('templates.create')); ?>">Create your first template</a>.
        </div>
    </div>
<?php else: ?>
    <div class="card border-0 shadow-sm rounded-3">
        <div>
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width:50px">#</th>
                        <th>Template Name</th>
                        <th>Subject</th>
                        <th style="width:110px">Status</th>
                        <th style="width:80px">Created</th>
                        <th style="width:60px" class="text-center">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $__currentLoopData = $templates; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $i => $template): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <tr data-name="<?php echo e(strtolower($template->name)); ?>"
                        data-subject="<?php echo e(strtolower($template->subject)); ?>"
                        data-status="<?php echo e($template->status); ?>"
                        class="template-row">
                        <td class="text-muted small"><?php echo e($i + 1); ?></td>
                        <td class="fw-semibold"><?php echo e($template->name); ?></td>
                        <td class="text-muted"><?php echo e(Str::limit($template->subject, 60)); ?></td>
                        <td>
                            <?php if($template->status === 'active'): ?>
                                <span class="badge rounded-pill bg-success">Active</span>
                            <?php elseif($template->status === 'inactive'): ?>
                                <span class="badge rounded-pill bg-secondary">Inactive</span>
                            <?php else: ?>
                                <span class="badge rounded-pill bg-danger">Deleted</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-muted small text-nowrap"><?php echo e($template->created_at->diffForHumans()); ?></td>
                        <td class="text-center">
                            <div class="dropdown">
                                <button class="btn btn-sm btn-light border rounded-circle px-2"
                                        data-bs-toggle="dropdown" aria-expanded="false"
                                        title="Actions">
                                    <i class="bi bi-three-dots-vertical"></i>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                                    <li>
                                        <a class="dropdown-item" href="<?php echo e(route('templates.edit', $template->id)); ?>">
                                            <i class="bi bi-pencil me-2 text-warning"></i>Edit
                                        </a>
                                    </li>
                                    <?php if($template->status !== 'active'): ?>
                                    <li>
                                        <form method="POST" action="<?php echo e(route('templates.toggle', $template->id)); ?>">
                                            <?php echo csrf_field(); ?>
                                            <input type="hidden" name="status" value="active">
                                            <button type="submit" class="dropdown-item">
                                                <i class="bi bi-toggle-on me-2 text-success"></i>Set Active
                                            </button>
                                        </form>
                                    </li>
                                    <?php endif; ?>
                                    <?php if($template->status !== 'inactive'): ?>
                                    <li>
                                        <form method="POST" action="<?php echo e(route('templates.toggle', $template->id)); ?>">
                                            <?php echo csrf_field(); ?>
                                            <input type="hidden" name="status" value="inactive">
                                            <button type="submit" class="dropdown-item">
                                                <i class="bi bi-toggle-off me-2 text-secondary"></i>Set Inactive
                                            </button>
                                        </form>
                                    </li>
                                    <?php endif; ?>
                                    <li><hr class="dropdown-divider"></li>
                                    <li>
                                        <form method="POST" action="<?php echo e(route('templates.destroy', $template->id)); ?>"
                                              onsubmit="return confirm('Delete \'<?php echo e(addslashes($template->name)); ?>\'?')">
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
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<?php $__env->stopSection(); ?>

<?php $__env->startPush('scripts'); ?>
<script>
    let activeStatusFilter = '';

    function filterRows() {
        const keyword = document.getElementById('templateSearch').value.toLowerCase().trim();

        document.querySelectorAll('.template-row').forEach(row => {
            const matchSearch = !keyword ||
                row.dataset.name.includes(keyword) ||
                row.dataset.subject.includes(keyword);

            const rowStatus = row.dataset.status;
            const matchStatus = activeStatusFilter
                ? rowStatus === activeStatusFilter
                : rowStatus !== 'deleted';

            row.style.display = (matchSearch && matchStatus) ? '' : 'none';
        });
    }

    document.getElementById('templateSearch').addEventListener('keyup', filterRows);

    document.querySelectorAll('.tpl-badge').forEach(function (badge) {
        badge.addEventListener('click', function () {
            const status = this.dataset.status;
            if (activeStatusFilter === status) {
                activeStatusFilter = '';
                document.querySelectorAll('.tpl-badge').forEach(b => b.style.outline = '');
            } else {
                activeStatusFilter = status;
                document.querySelectorAll('.tpl-badge').forEach(b => {
                    b.style.outline = b.dataset.status === status ? '2px solid #000' : '';
                });
            }
            filterRows();
        });
    });

    // Hide deleted by default on load
    filterRows();
</script>
<?php $__env->stopPush(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /var/www/html/resources/views/templates/index.blade.php ENDPATH**/ ?>