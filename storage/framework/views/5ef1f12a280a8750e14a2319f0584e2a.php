
<div class="ajax-content">
<?php if($templates->isEmpty()): ?>
    <div class="card-body">
        <div class="empty-state">
            <i class="bi bi-envelope-paper empty-state-icon"></i>
            <?php if($activeStatus): ?>
                No <strong><?php echo e($statusOptions[$activeStatus] ?? $activeStatus); ?></strong> templates.
                <a href="<?php echo e(route('templates.index')); ?>">Clear the filter</a>.
            <?php else: ?>
                No templates found. <a href="<?php echo e(route('templates.create')); ?>">Create your first template</a>.
            <?php endif; ?>
        </div>
    </div>
<?php else: ?>
    <div class="card-body table-card-body px-0 pt-0 pb-2">
        <div class="table-responsive">
            <table class="table">
                <thead class="table-light">
                    <tr>
                        <th style="width:60px" class="d-none d-md-table-cell">#</th>
                        <th class="mw-150">Template Name</th>
                        <th class="mw-200">Subject</th>
                        <th style="width:120px">Status</th>
                        <th class="mw-100 d-none d-lg-table-cell">Created</th>
                        <th style="width:80px" class="text-center">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $__currentLoopData = $templates; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $i => $template): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <tr data-search="<?php echo e(strtolower($template->name . ' ' . $template->subject)); ?>">
                        <td class="d-none d-md-table-cell"><span><?php echo e($i + 1); ?></span></td>
                        <td><h6 class="mb-0 cell-wrap"><?php echo e($template->name); ?></h6><div class="d-lg-none fs-13 text-muted"><?php echo e($template->created_at->diffForHumans()); ?></div></td>
                        <td><span><?php echo e(Str::limit($template->subject, 60)); ?></span></td>
                        <td>
                            <?php if($template->status === 'active'): ?>
                                <span class="badge badge-success light">Active</span>
                            <?php elseif($template->status === 'inactive'): ?>
                                <span class="badge badge-secondary light">Inactive</span>
                            <?php else: ?>
                                <span class="badge badge-danger light">Deleted</span>
                            <?php endif; ?>
                        </td>
                        <td class="d-none d-lg-table-cell"><span class="text-nowrap"><?php echo e($template->created_at->diffForHumans()); ?></span></td>
                        <td class="text-center">
                            <div class="dropdown">
                                <button class="btn btn-sm btn-light btn-square"
                                        data-bs-toggle="dropdown" data-bs-strategy="fixed" aria-expanded="false"
                                        aria-label="Actions" title="Actions">
                                    <i class="bi bi-three-dots-vertical"></i>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <li>
                                        <a class="dropdown-item" href="<?php echo e(route('templates.edit', $template->id)); ?>">
                                            <i class="bi bi-pencil me-2 text-warning"></i>Edit
                                        </a>
                                    </li>
                                    <?php if($template->status !== 'active'): ?>
                                    <li>
                                        <form method="POST" action="<?php echo e(route('templates.toggle', $template->id)); ?>">
                                            <?php echo csrf_field(); ?>
                                            <input type="hidden" name="_redirect_back" value="<?php echo e(request()->getQueryString() ? '?'.request()->getQueryString() : ''); ?>">
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
                                            <input type="hidden" name="_redirect_back" value="<?php echo e(request()->getQueryString() ? '?'.request()->getQueryString() : ''); ?>">
                                            <input type="hidden" name="status" value="inactive">
                                            <button type="submit" class="dropdown-item">
                                                <i class="bi bi-toggle-off me-2 text-secondary"></i>Set Inactive
                                            </button>
                                        </form>
                                    </li>
                                    <?php endif; ?>
                                    <li>
                                        <form method="POST" action="<?php echo e(route('templates.destroy', $template->id)); ?>"
                                              onsubmit="return confirm('Delete \'<?php echo e(addslashes($template->name)); ?>\'?')">
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
<?php /**PATH /var/www/html/resources/views/templates/_table.blade.php ENDPATH**/ ?>