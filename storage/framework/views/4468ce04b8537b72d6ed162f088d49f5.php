
<div class="ajax-content">
<?php if($history->isEmpty()): ?>
    <div class="card-body">
        <div class="empty-state">
            <i class="bi bi-patch-question empty-state-icon"></i>
            <?php if(array_filter($filters)): ?>
                <p class="mb-1 fw-semibold">Nothing matches these filters</p>
                <p class="fs-13 mb-0"><a href="<?php echo e(route('verifier.index')); ?>">Clear the filters</a> to see everything.</p>
            <?php else: ?>
                <p class="mb-1 fw-semibold">No verifications yet</p>
                <p class="fs-13 mb-0">Check an address above, or upload a list from
                    <a href="<?php echo e(route('bulks.index')); ?>">Bulks</a>.</p>
            <?php endif; ?>
        </div>
    </div>
<?php else: ?>
    <?php
        // Carried by each delete form so the row action returns to this filtered page.
        $redirectBack = request()->getQueryString() ? '?'.request()->getQueryString() : '';
    ?>

    <div class="card-body table-card-body px-0 pt-0 pb-2">
        <div class="table-responsive">
            <table class="table">
                <thead class="table-light">
                    <tr>
                        
                        <th style="width:60px" class="d-none d-md-table-cell">#</th>
                        <th class="mw-150">Email</th>
                        <th style="width:120px">Result</th>
                        <th style="width:150px" class="d-none d-md-table-cell">Confidence</th>
                        <th class="mw-150 d-none d-xl-table-cell">Reason</th>
                        <th style="width:150px" class="d-none d-xl-table-cell">Signals</th>
                        <th style="width:110px" class="d-none d-lg-table-cell">Source</th>
                        <th style="width:130px" class="d-none d-lg-table-cell">Checked</th>
                        <th style="width:70px" class="text-center">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $__currentLoopData = $history; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $row): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <tr>
                            <td class="d-none d-md-table-cell">
                                <span><?php echo e(($history->currentPage() - 1) * $history->perPage() + $loop->iteration); ?></span>
                            </td>

                            <td class="cell-wrap mw-220">
                                <h6 class="mb-0 cell-wrap"><?php echo e($row->email); ?></h6>
                                <span class="fs-13 text-muted"><?php echo e($row->domain); ?></span>

                                
                                <div class="d-lg-none fs-13 text-muted">
                                    <?php echo e(ucfirst($row->source)); ?> · <?php echo e($row->created_at?->format('j M Y, H:i') ?? '—'); ?>

                                </div>
                                <div class="d-md-none fs-13 text-muted"><?php echo e($row->score); ?>% confidence</div>
                            </td>

                            <td>
                                <span class="badge badge-<?php echo e($row->status_colour); ?> light">
                                    <?php echo e(\App\Models\EmailVerification::STATUSES[$row->status] ?? ucfirst($row->status)); ?>

                                </span>
                            </td>

                            <td class="d-none d-md-table-cell">
                                <div class="d-flex align-items-center gap-2">
                                    <div class="score-meter flex-grow-1">
                                        <span class="bg-<?php echo e($row->status_colour); ?>" style="width: <?php echo e($row->score); ?>%"></span>
                                    </div>
                                    <span class="fs-13 text-muted"><?php echo e($row->score); ?>%</span>
                                </div>
                            </td>

                            <td class="fs-13 cell-wrap d-none d-xl-table-cell"><?php echo e($row->reason); ?></td>

                            <td class="d-none d-xl-table-cell">
                                
                                <div class="d-flex flex-wrap gap-1">
                                    <?php if(! empty($row->checks['mx'])): ?>
                                        <span class="badge badge-success light" title="Domain publishes mail exchangers">MX</span>
                                    <?php else: ?>
                                        <span class="badge badge-danger light" title="No mail exchangers published">No MX</span>
                                    <?php endif; ?>

                                    <?php if(! empty($row->checks['disposable'])): ?>
                                        <span class="badge badge-warning light" title="Throwaway inbox provider">Disposable</span>
                                    <?php endif; ?>

                                    <?php if(! empty($row->checks['role'])): ?>
                                        <span class="badge badge-dark light" title="Shared mailbox, not an individual">Role</span>
                                    <?php endif; ?>

                                    <?php if(! empty($row->checks['free'])): ?>
                                        <span class="badge badge-secondary light" title="Free consumer provider">Free</span>
                                    <?php endif; ?>

                                    <?php if(! empty($row->checks['catch_all'])): ?>
                                        <span class="badge badge-warning light" title="Domain accepts every address">Catch-all</span>
                                    <?php endif; ?>
                                </div>
                            </td>

                            <td class="d-none d-lg-table-cell">
                                <span class="badge badge-primary light"><?php echo e(ucfirst($row->source)); ?></span>
                            </td>

                            <td class="fs-13 text-muted text-nowrap d-none d-lg-table-cell">
                                <?php echo e($row->created_at?->format('j M Y, H:i') ?? '—'); ?>

                            </td>

                            <td class="text-center">
                                <div class="dropdown">
                                    <button class="btn btn-sm btn-light btn-square"
                                            data-bs-toggle="dropdown" data-bs-strategy="fixed"
                                            aria-expanded="false" aria-label="Actions">
                                        <i class="bi bi-three-dots-vertical"></i>
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end">
                                        <?php if($row->lead_id): ?>
                                            <li>
                                                <a class="dropdown-item"
                                                   href="<?php echo e(route('finder.index', ['q' => $row->email])); ?>">
                                                    <i class="bi bi-search me-2 text-primary"></i>View lead
                                                </a>
                                            </li>
                                        <?php endif; ?>
                                        <?php if($row->bulk_id): ?>
                                            <li>
                                                <a class="dropdown-item" href="<?php echo e(route('bulks.show', $row->bulk_id)); ?>">
                                                    <i class="bi bi-stack me-2 text-info"></i>Open bulk run
                                                </a>
                                            </li>
                                        <?php endif; ?>
                                        <li>
                                            <form method="POST" action="<?php echo e(route('verifier.destroy', $row->id)); ?>"
                                                  onsubmit="return confirm('Delete this verification record?')">
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
                    <label class="form-label mb-0 text-nowrap" for="historyPerPage">Rows per page:</label>
                    <select id="historyPerPage" class="form-select form-select-sm per-page-select" data-param="per_page">
                        <?php $__currentLoopData = [10, 25, 50, 100]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $size): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <option value="<?php echo e($size); ?>" <?php echo e((int) request('per_page', 25) === $size ? 'selected' : ''); ?>>
                                <?php echo e($size); ?>

                            </option>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </select>
                </div>

                <span class="fs-13">
                    Showing <strong><?php echo e($history->firstItem()); ?></strong> - <strong><?php echo e($history->lastItem()); ?></strong>
                    of <strong><?php echo e(number_format($history->total())); ?></strong> results
                </span>
            </div>

            <?php if($history->hasPages()): ?>
                <div><?php echo e($history->links()); ?></div>
            <?php endif; ?>

        </div>
    </div>
<?php endif; ?>
</div>
<?php /**PATH /var/www/html/resources/views/verifier/_history.blade.php ENDPATH**/ ?>