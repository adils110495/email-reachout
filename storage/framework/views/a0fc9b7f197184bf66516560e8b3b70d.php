
<div class="ajax-content" data-total="<?php echo e($results->total()); ?>">
<?php if($results->isEmpty()): ?>
    <div class="card-body">
        <div class="empty-state">
            <i class="bi bi-inbox empty-state-icon"></i>
            <?php if(array_filter($filters)): ?>
                <p class="mb-1 fw-semibold">No results match these filters</p>
                <p class="fs-13 mb-0">
                    <a href="<?php echo e(route('finder.index')); ?>">Clear the filters</a> to see everything found so far.
                </p>
            <?php else: ?>
                <p class="mb-1 fw-semibold">Nothing found yet</p>
                <p class="fs-13 mb-0">Run a domain search above — every address it turns up is kept here.</p>
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
                        <th class="mw-150">Email</th>
                        <th class="mw-150 d-none d-lg-table-cell">Company</th>
                        <th style="width:120px">Result</th>
                        <th style="width:140px" class="d-none d-md-table-cell">Confidence</th>
                        <th style="width:120px" class="d-none d-xl-table-cell">Source</th>
                        <th style="width:110px">Saved</th>
                        <th style="width:110px" class="d-none d-lg-table-cell">Found</th>
                        <th style="width:70px" class="text-center">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $__currentLoopData = $results; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $row): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <tr>
                            <td class="d-none d-md-table-cell">
                                <span><?php echo e(($results->currentPage() - 1) * $results->perPage() + $loop->iteration); ?></span>
                            </td>

                            <td class="cell-wrap mw-220">
                                <h6 class="mb-0 cell-wrap">
                                    <a href="mailto:<?php echo e($row->email); ?>"><?php echo e($row->email); ?></a>
                                </h6>
                                <span class="fs-13 text-muted"><?php echo e($row->domain); ?></span>

                                
                                <div class="d-lg-none fs-13 text-muted cell-wrap">
                                    <?php if($row->company): ?><?php echo e($row->company); ?> · <?php endif; ?>
                                    <?php echo e($row->created_at?->format('j M Y') ?? '—'); ?>

                                </div>
                                <div class="d-md-none fs-13 text-muted"><?php echo e($row->score); ?>% confidence</div>
                            </td>

                            <td class="cell-wrap d-none d-lg-table-cell">
                                <?php if($row->company): ?>
                                    <?php echo e($row->company); ?>

                                <?php else: ?>
                                    <span class="text-muted">—</span>
                                <?php endif; ?>
                                <?php if($row->person): ?>
                                    <div class="fs-13 text-muted"><?php echo e($row->person); ?></div>
                                <?php endif; ?>
                            </td>

                            <td>
                                <span class="badge badge-<?php echo e($row->status_colour); ?> light">
                                    <?php echo e(ucfirst($row->status)); ?>

                                </span>
                                <?php if($row->guessed): ?>
                                    
                                    <div class="mt-1">
                                        <span class="badge badge-secondary light" title="Generated from a name pattern, not published on the site">
                                            guessed
                                        </span>
                                    </div>
                                <?php endif; ?>
                            </td>

                            <td class="d-none d-md-table-cell">
                                <div class="d-flex align-items-center gap-2">
                                    <div class="score-meter flex-grow-1">
                                        <span class="bg-<?php echo e($row->status_colour); ?>" style="width: <?php echo e($row->score); ?>%"></span>
                                    </div>
                                    <span class="fs-13 text-muted"><?php echo e($row->score); ?>%</span>
                                </div>
                            </td>

                            <td class="d-none d-xl-table-cell">
                                <?php if($row->source === \App\Models\FinderResult::SOURCE_WEBSITE): ?>
                                    <span class="badge badge-info light" title="Published on the company website">Website</span>
                                <?php else: ?>
                                    <span class="badge badge-dark light" title="Generated pattern: <?php echo e($row->pattern); ?>">
                                        <?php echo e($row->pattern ?: 'Pattern'); ?>

                                    </span>
                                <?php endif; ?>
                            </td>

                            <td>
                                <?php if($row->isSaved()): ?>
                                    <span class="badge badge-success light">
                                        <i class="bi bi-check-lg me-1"></i>Lead
                                    </span>
                                <?php else: ?>
                                    <button type="button" class="btn btn-sm btn-primary js-save-row"
                                            data-email="<?php echo e($row->email); ?>"
                                            data-domain="<?php echo e($row->domain); ?>"
                                            data-company="<?php echo e($row->company); ?>">
                                        <i class="bi bi-plus-lg me-1"></i>Save
                                    </button>
                                <?php endif; ?>
                            </td>

                            <td class="fs-13 text-muted text-nowrap d-none d-lg-table-cell">
                                <?php echo e($row->created_at?->format('j M Y') ?? '—'); ?>

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
                                            <a class="dropdown-item" href="<?php echo e(route('verifier.index', ['q' => $row->email])); ?>">
                                                <i class="bi bi-patch-check me-2 text-primary"></i>Verify this address
                                            </a>
                                        </li>
                                        <?php if($row->lead_id): ?>
                                            <li>
                                                <a class="dropdown-item"
                                                   href="<?php echo e(route('leads.index', ['category' => $row->lead?->category_id])); ?>">
                                                    <i class="bi bi-people me-2 text-info"></i>Open in Leads
                                                </a>
                                            </li>
                                        <?php endif; ?>
                                        <li>
                                            <a class="dropdown-item" href="https://<?php echo e($row->domain); ?>"
                                               target="_blank" rel="noopener noreferrer">
                                                <i class="bi bi-box-arrow-up-right me-2 text-secondary"></i>Visit website
                                            </a>
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
                    <label class="form-label mb-0 text-nowrap" for="finderPerPage">Rows per page:</label>
                    
                    <select id="finderPerPage" class="form-select form-select-sm per-page-select" data-param="per_page">
                        <?php $__currentLoopData = [10, 25, 50, 100]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $size): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <option value="<?php echo e($size); ?>" <?php echo e((int) request('per_page', 25) === $size ? 'selected' : ''); ?>>
                                <?php echo e($size); ?>

                            </option>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </select>
                </div>

                <span class="fs-13">
                    Showing <strong><?php echo e($results->firstItem()); ?></strong> - <strong><?php echo e($results->lastItem()); ?></strong>
                    of <strong><?php echo e(number_format($results->total())); ?></strong> results
                </span>
            </div>

            <?php if($results->hasPages()): ?>
                <div><?php echo e($results->links()); ?></div>
            <?php endif; ?>

        </div>
    </div>
<?php endif; ?>
</div>
<?php /**PATH /var/www/html/resources/views/finder/_results.blade.php ENDPATH**/ ?>