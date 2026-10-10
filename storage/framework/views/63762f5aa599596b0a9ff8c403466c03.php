
<div class="ajax-content">
<?php if($bulks->isEmpty()): ?>
    <div class="card-body">
        <div class="empty-state">
            <i class="bi bi-file-earmark-arrow-up empty-state-icon"></i>
            <?php if(array_filter($filters)): ?>
                <p class="mb-1 fw-semibold">No runs match these filters</p>
                <p class="fs-13 mb-0"><a href="<?php echo e(route('bulks.index')); ?>">Clear the filters</a> to see everything.</p>
            <?php else: ?>
                <p class="mb-1 fw-semibold">No bulk runs yet</p>
                <p class="fs-13 mb-0">Upload a CSV of email addresses to verify, or of domains to find addresses for.</p>
            <?php endif; ?>
        </div>
    </div>
<?php else: ?>
    <?php
        $redirectBack = request()->getQueryString() ? '?'.request()->getQueryString() : '';
    ?>

    <div class="card-body table-card-body px-0 pt-0 pb-2">
        <div class="table-responsive">
            <table class="table">
                <thead class="table-light">
                    <tr>
                        
                        <th style="width:60px" class="d-none d-md-table-cell">#</th>
                        <th class="mw-150">Run</th>
                        <th style="width:130px" class="d-none d-lg-table-cell">Type</th>
                        <th style="width:120px">Status</th>
                        <th style="width:220px">Progress</th>
                        <th style="width:110px" class="text-end d-none d-lg-table-cell">Success</th>
                        <th style="width:110px" class="text-end d-none d-lg-table-cell">Failed</th>
                        <th style="width:130px" class="d-none d-xl-table-cell">Started</th>
                        <th style="width:70px" class="text-center">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $__currentLoopData = $bulks; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $bulk): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        
                        <tr data-search="<?php echo e(strtolower($bulk->name.' '.$bulk->original_filename.' '.$bulk->type.' '.$bulk->status)); ?>"
                            <?php if($bulk->isRunning()): ?> data-bulk-running="<?php echo e($bulk->id); ?>" <?php endif; ?>>

                            <td class="d-none d-md-table-cell">
                                <span><?php echo e(($bulks->currentPage() - 1) * $bulks->perPage() + $loop->iteration); ?></span>
                            </td>

                            <td>
                                <h6 class="mb-0 cell-wrap">
                                    <a href="<?php echo e(route('bulks.show', $bulk->id)); ?>"><?php echo e($bulk->name); ?></a>
                                </h6>
                                <?php if($bulk->original_filename): ?>
                                    <span class="fs-13 text-muted cell-wrap"><?php echo e($bulk->original_filename); ?></span>
                                <?php endif; ?>

                                
                                <div class="d-lg-none fs-13 text-muted">
                                    <?php echo e($bulk->type === 'find' ? 'Finder' : 'Verify'); ?>

                                    · <span data-cell="successful-sm"><?php echo e(number_format($bulk->successful_records)); ?></span> ok
                                    · <span data-cell="failed-sm"><?php echo e(number_format($bulk->failed_records)); ?></span> failed
                                </div>
                            </td>

                            <td class="d-none d-lg-table-cell">
                                <?php if($bulk->type === 'find'): ?>
                                    <span class="badge badge-info light"><i class="bi bi-search me-1"></i>Finder</span>
                                <?php else: ?>
                                    <span class="badge badge-primary light"><i class="bi bi-patch-check me-1"></i>Verify</span>
                                <?php endif; ?>
                            </td>

                            <td>
                                <span class="badge badge-<?php echo e($bulk->status_colour); ?> light" data-cell="status">
                                    <?php echo e(ucfirst($bulk->status)); ?>

                                </span>
                            </td>

                            <td>
                                <div class="progress bulk-progress mb-1">
                                    <div class="progress-bar bg-<?php echo e($bulk->status_colour); ?> <?php echo e($bulk->isRunning() ? 'progress-bar-striped progress-bar-animated' : ''); ?>"
                                         role="progressbar" data-cell="bar"
                                         style="width: <?php echo e($bulk->progress); ?>%"
                                         aria-valuenow="<?php echo e($bulk->progress); ?>" aria-valuemin="0" aria-valuemax="100"
                                         aria-label="<?php echo e($bulk->name); ?> progress"></div>
                                </div>
                                <span class="fs-13 text-muted" data-cell="counts">
                                    <?php echo e(number_format($bulk->processed_records)); ?> / <?php echo e(number_format($bulk->total_records)); ?>

                                    (<?php echo e($bulk->progress); ?>%)
                                </span>
                            </td>

                            <td class="text-end text-success fw-medium d-none d-lg-table-cell" data-cell="successful"><?php echo e(number_format($bulk->successful_records)); ?></td>
                            <td class="text-end text-danger fw-medium d-none d-lg-table-cell" data-cell="failed"><?php echo e(number_format($bulk->failed_records)); ?></td>

                            <td class="fs-13 text-muted text-nowrap d-none d-xl-table-cell">
                                <?php echo e(($bulk->started_at ?? $bulk->created_at)?->format('j M Y, H:i') ?? '—'); ?>

                            </td>

                            <td class="text-center">
                                <div class="dropdown">
                                    <button class="btn btn-sm btn-light btn-square"
                                            data-bs-toggle="dropdown"
                                            aria-expanded="false" aria-label="Actions">
                                        <i class="bi bi-three-dots-vertical"></i>
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end">
                                        <li>
                                            <a class="dropdown-item" href="<?php echo e(route('bulks.show', $bulk->id)); ?>">
                                                <i class="bi bi-eye me-2 text-primary"></i>View results
                                            </a>
                                        </li>
                                        <li>
                                            <a class="dropdown-item" href="<?php echo e(route('bulks.export', $bulk->id)); ?>">
                                                <i class="bi bi-download me-2 text-info"></i>Download CSV
                                            </a>
                                        </li>

                                        <?php if($bulk->isRunning()): ?>
                                            <li>
                                                <form method="POST" action="<?php echo e(route('bulks.cancel', $bulk->id)); ?>"
                                                      onsubmit="return confirm('Cancel this run? Results collected so far are kept.')">
                                                    <?php echo csrf_field(); ?>
                                                    <button type="submit" class="dropdown-item text-warning">
                                                        <i class="bi bi-stop-circle me-2"></i>Cancel
                                                    </button>
                                                </form>
                                            </li>
                                        <?php elseif($bulk->failed_records > 0 || $bulk->status === 'failed' || $bulk->status === 'cancelled'): ?>
                                            <li>
                                                <form method="POST" action="<?php echo e(route('bulks.retry', $bulk->id)); ?>">
                                                    <?php echo csrf_field(); ?>
                                                    <button type="submit" class="dropdown-item text-success">
                                                        <i class="bi bi-arrow-clockwise me-2"></i>Retry unfinished
                                                    </button>
                                                </form>
                                            </li>
                                        <?php endif; ?>

                                        <li>
                                            <form method="POST" action="<?php echo e(route('bulks.destroy', $bulk->id)); ?>"
                                                  onsubmit="return confirm('Delete &quot;<?php echo e(addslashes($bulk->name)); ?>&quot; and all of its results?')">
                                                <?php echo csrf_field(); ?> <?php echo method_field('DELETE'); ?>
                                                <input type="hidden" name="_redirect_back" value="<?php echo e($redirectBack); ?>">
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

    
    <div class="card-footer py-3">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">

            <div class="d-flex align-items-center gap-3 flex-wrap">
                <div class="d-flex align-items-center gap-2">
                    <label class="form-label mb-0 text-nowrap" for="bulksPerPage">Rows per page:</label>
                    <select id="bulksPerPage" class="form-select form-select-sm per-page-select" data-param="per_page">
                        <?php $__currentLoopData = [10, 25, 50, 100]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $size): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <option value="<?php echo e($size); ?>" <?php echo e((int) request('per_page', 25) === $size ? 'selected' : ''); ?>>
                                <?php echo e($size); ?>

                            </option>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </select>
                </div>

                <span class="fs-13">
                    Showing <strong><?php echo e($bulks->firstItem()); ?></strong> - <strong><?php echo e($bulks->lastItem()); ?></strong>
                    of <strong><?php echo e(number_format($bulks->total())); ?></strong> results
                </span>
            </div>

            <?php if($bulks->hasPages()): ?>
                <div><?php echo e($bulks->links()); ?></div>
            <?php endif; ?>

        </div>
    </div>
<?php endif; ?>
</div>
<?php /**PATH /var/www/html/resources/views/bulks/_table.blade.php ENDPATH**/ ?>