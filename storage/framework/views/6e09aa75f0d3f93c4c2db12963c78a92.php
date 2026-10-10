
<?php
    $isFind = $bulk->type === 'find';
    $isSeq  = $bulk->isSequenceAction();
    // Only verify / find runs produce a confidence score.
    $showScore = ! $isSeq && ! $bulk->isImport();
    $itemLeads = $itemLeads ?? [];
?>

<div class="ajax-content">
<?php if($items->isEmpty()): ?>
    <div class="card-body">
        <div class="empty-state">
            <i class="bi bi-inbox empty-state-icon"></i>
            <?php if(array_filter($filters)): ?>
                <p class="mb-1 fw-semibold">No records match these filters</p>
                <p class="fs-13 mb-0">
                    <a href="<?php echo e(route('bulks.show', $bulk->id)); ?>">Clear the filters</a> to see every record.
                </p>
            <?php elseif($bulk->isRunning()): ?>
                <p class="mb-1 fw-semibold">Waiting for the first results</p>
                <p class="fs-13 mb-0">Records appear here as the queue works through them.</p>
            <?php else: ?>
                <p class="mb-1 fw-semibold"><?php echo e($bulk->status === 'draft' ? 'Waiting for you to confirm the import above' : 'This run has no records'); ?></p>
                <p class="fs-13 mb-0">Nothing usable was found in the uploaded file.</p>
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
                        <th class="mw-150"><?php echo e($isFind ? 'Domain' : ($isSeq ? 'Lead' : 'Email')); ?></th>
                        <?php if($isFind): ?>
                            <th class="mw-150">Email found</th>
                        <?php endif; ?>
                        <th style="width:120px">Result</th>
                        <?php if($showScore): ?>
                            <th style="width:150px" class="d-none d-md-table-cell">Confidence</th>
                        <?php endif; ?>
                        <th class="mw-150 d-none d-lg-table-cell">Notes</th>
                        <th style="width:70px" class="text-center">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $__currentLoopData = $items; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <tr>
                            <td class="d-none d-md-table-cell">
                                <span><?php echo e(($items->currentPage() - 1) * $items->perPage() + $loop->iteration); ?></span>
                            </td>

                            <td class="cell-wrap mw-220">
                                <?php if($isSeq): ?>
                                    
                                    <?php $lead = $itemLeads[$item->id] ?? null; ?>
                                    <?php if($lead): ?>
                                        <h6 class="mb-0 cell-wrap">
                                            <a href="<?php echo e(route('outreach.activity.index', ['lead' => $lead->id])); ?>" title="Open this lead's timeline"><?php echo e($lead->displayName()); ?></a>
                                        </h6>
                                        <span class="fs-13 text-muted"><?php echo e($lead->email ?: ($item->extra ?: 'no email address')); ?></span>
                                    <?php else: ?>
                                        <h6 class="mb-0 cell-wrap text-muted">Record #<?php echo e($item->input); ?> (no longer exists)</h6>
                                        <?php if($item->extra): ?>
                                            <span class="fs-13 text-muted"><?php echo e($item->extra); ?></span>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <h6 class="mb-0 cell-wrap"><?php echo e($item->input); ?></h6>
                                    <?php if($item->extra): ?>
                                        <span class="fs-13 text-muted"><?php echo e($item->extra); ?></span>
                                    <?php endif; ?>
                                <?php endif; ?>

                                
                                <?php if($item->message): ?>
                                    <div class="d-lg-none fs-13 text-muted cell-wrap"><?php echo e($item->message); ?></div>
                                <?php endif; ?>
                                <?php if($item->score !== null): ?>
                                    <div class="d-md-none fs-13 text-muted"><?php echo e($item->score); ?>% confidence</div>
                                <?php endif; ?>
                            </td>

                            <?php if($isFind): ?>
                                <td class="cell-wrap mw-220">
                                    <?php if($item->result_value): ?>
                                        <a href="mailto:<?php echo e($item->result_value); ?>"><?php echo e($item->result_value); ?></a>
                                    <?php else: ?>
                                        <span class="text-muted">—</span>
                                    <?php endif; ?>
                                </td>
                            <?php endif; ?>

                            <td>
                                <?php if($item->status === 'pending'): ?>
                                    <span class="badge badge-info light">
                                        <i class="bi bi-hourglass me-1"></i>Queued
                                    </span>
                                <?php elseif($item->status === 'processing'): ?>
                                    <span class="badge badge-primary light">
                                        <span class="spinner-border spinner-border-sm me-1" style="width:.6rem;height:.6rem"></span>Running
                                    </span>
                                <?php else: ?>
                                    <span class="badge badge-<?php echo e($item->result_colour); ?> light">
                                        <?php echo e(str_replace('_', ' ', ucfirst($item->result_status ?? 'unknown'))); ?>

                                    </span>
                                <?php endif; ?>
                            </td>

                            <?php if($showScore): ?>
                                <td class="d-none d-md-table-cell">
                                    <?php if($item->score !== null): ?>
                                        <div class="d-flex align-items-center gap-2">
                                            <div class="score-meter flex-grow-1">
                                                <span class="bg-<?php echo e($item->result_colour); ?>" style="width: <?php echo e($item->score); ?>%"></span>
                                            </div>
                                            <span class="fs-13 text-muted"><?php echo e($item->score); ?>%</span>
                                        </div>
                                    <?php else: ?>
                                        <span class="fs-13 text-muted">—</span>
                                    <?php endif; ?>
                                </td>
                            <?php endif; ?>

                            <td class="fs-13 cell-wrap d-none d-lg-table-cell"><?php echo e($item->message ?: '—'); ?></td>

                            <td class="text-center">
                                <?php
                                    // The address worth acting on: the one that was
                                    // verified, or the one the finder discovered.
                                    $address = $bulk->isSequenceAction() ? null : ($isFind ? $item->result_value : $item->input);
                                ?>

                                <?php if($isSeq && ($itemLeads[$item->id] ?? null)): ?>
                                    <a href="<?php echo e(route('outreach.activity.index', ['lead' => $itemLeads[$item->id]->id])); ?>"
                                       class="btn btn-sm btn-light btn-square" title="Lead timeline" aria-label="Lead timeline">
                                        <i class="bi bi-clock-history"></i>
                                    </a>
                                <?php elseif($address): ?>
                                    <div class="dropdown">
                                        <button class="btn btn-sm btn-light btn-square"
                                                data-bs-toggle="dropdown" data-bs-strategy="fixed"
                                                aria-expanded="false" aria-label="Actions">
                                            <i class="bi bi-three-dots-vertical"></i>
                                        </button>
                                        <ul class="dropdown-menu dropdown-menu-end">
                                            <li>
                                                <a class="dropdown-item" href="<?php echo e(route('verifier.index', ['q' => $address])); ?>">
                                                    <i class="bi bi-patch-check me-2 text-primary"></i>Verification history
                                                </a>
                                            </li>
                                            <li>
                                                <a class="dropdown-item" href="<?php echo e(route('finder.index', ['q' => $address])); ?>">
                                                    <i class="bi bi-search me-2 text-info"></i>Find in leads
                                                </a>
                                            </li>
                                            <li>
                                                <a class="dropdown-item" href="mailto:<?php echo e($address); ?>">
                                                    <i class="bi bi-envelope me-2 text-secondary"></i>Compose email
                                                </a>
                                            </li>
                                        </ul>
                                    </div>
                                <?php else: ?>
                                    <span class="text-muted">—</span>
                                <?php endif; ?>
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
                    <label class="form-label mb-0 text-nowrap" for="itemsPerPage">Rows per page:</label>
                    <select id="itemsPerPage" class="form-select form-select-sm per-page-select" data-param="per_page">
                        <?php $__currentLoopData = [10, 25, 50, 100]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $size): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <option value="<?php echo e($size); ?>" <?php echo e((int) request('per_page', 25) === $size ? 'selected' : ''); ?>>
                                <?php echo e($size); ?>

                            </option>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </select>
                </div>

                <span class="fs-13">
                    Showing <strong><?php echo e($items->firstItem()); ?></strong> - <strong><?php echo e($items->lastItem()); ?></strong>
                    of <strong><?php echo e(number_format($items->total())); ?></strong> results
                </span>
            </div>

            <?php if($items->hasPages()): ?>
                <div><?php echo e($items->links()); ?></div>
            <?php endif; ?>

        </div>
    </div>
<?php endif; ?>
</div>
<?php /**PATH /var/www/html/resources/views/bulks/_items.blade.php ENDPATH**/ ?>