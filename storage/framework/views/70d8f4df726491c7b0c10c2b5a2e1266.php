
<div id="leadsContent" data-total="<?php echo e($activeCategory ? $leads->total() : 0); ?>">
<?php if(!$activeCategory): ?>
    <div class="card-body">
        <div class="empty-state">
            <i class="bi bi-tag empty-state-icon"></i>
            <p class="mb-1 fw-semibold">Select a category to view leads</p>
            <p class="fs-13 mb-0">Use the <strong>Category</strong> filter above to load leads for a specific category.</p>
        </div>
    </div>
<?php elseif($leads->isEmpty()): ?>
    <div class="card-body">
        <div class="empty-state">
            <i class="bi bi-inbox empty-state-icon"></i>
            No leads found for <strong><?php echo e($activeCatObj->name ?? ''); ?></strong>.
        </div>
    </div>
<?php else: ?>
    <div class="card-body table-card-body px-0 pt-0 pb-2">
        <div class="table-responsive">
            <table class="table" id="leadsTable">
                <thead class="table-light">
                    <tr>
                        <th scope="col" style="width:40px">
                            <input type="checkbox" class="form-check-input" id="selectAll" title="Select all">
                        </th>
                        <th scope="col" class="sortable d-none d-md-table-cell" data-col="0">S.No
                            <i class="bi bi-chevron-expand sort-icon text-muted ms-1"></i>
                        </th>
                        <th scope="col" class="sortable mw-150" data-col="1">Company
                            <i class="bi bi-chevron-expand sort-icon text-muted ms-1"></i>
                        </th>
                        <th scope="col" class="sortable mw-150" data-col="2">Website
                            <i class="bi bi-chevron-expand sort-icon text-muted ms-1"></i>
                        </th>
                        <th scope="col" class="sortable mw-150" data-col="3">Email
                            <i class="bi bi-chevron-expand sort-icon text-muted ms-1"></i>
                        </th>
                        <th scope="col" class="sortable mw-100" data-col="4">Status
                            <i class="bi bi-chevron-expand sort-icon text-muted ms-1"></i>
                        </th>
                        <th scope="col" class="sortable mw-100 d-none d-lg-table-cell" data-col="5">Platform
                            <i class="bi bi-chevron-expand sort-icon text-muted ms-1"></i>
                        </th>
                        <th scope="col" class="sortable mw-100 d-none d-lg-table-cell" data-col="6">Found
                            <i class="bi bi-chevron-expand sort-icon text-muted ms-1"></i>
                        </th>
                        <th scope="col" class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $__currentLoopData = $leads; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $lead): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <tr data-id="<?php echo e($lead->id); ?>"
                        data-search="<?php echo e(strtolower($lead->company_name . ' ' . implode(' ', $lead->email_list) . ' ' . $lead->website . ' ' . $lead->status)); ?>">

                        
                        <td>
                            <input type="checkbox" class="form-check-input row-check" value="<?php echo e($lead->id); ?>">
                        </td>

                        <td data-val="<?php echo e($lead->id); ?>" class="d-none d-md-table-cell"><span><?php echo e(($leads->currentPage() - 1) * $leads->perPage() + $loop->iteration); ?></span></td>

                        <td data-val="<?php echo e(strtolower($lead->company_name)); ?>">
                            <h6 class="mb-0 cell-wrap"><?php echo e($lead->company_name); ?></h6>
                            
                            <div class="d-lg-none fs-13 text-muted">
                                <?php if($lead->platform): ?><?php echo e($lead->platform->name); ?> · <?php endif; ?>
                                <?php echo e($lead->created_at->diffForHumans()); ?>

                            </div>
                        </td>

                        <td data-val="<?php echo e(parse_url($lead->website, PHP_URL_HOST)); ?>" style="max-width:180px;">
                            <a href="<?php echo e($lead->website); ?>" target="_blank" rel="noopener" class="text-primary d-block text-truncate" title="<?php echo e($lead->website); ?>">
                                <i class="bi bi-box-arrow-up-right me-1"></i>
                                <?php echo e(parse_url($lead->website, PHP_URL_HOST)); ?>

                            </a>
                        </td>

                        <td data-val="<?php echo e(strtolower(implode(',', $lead->email_list))); ?>">
                            <?php if($lead->email_list): ?>
                                
                                <?php $__currentLoopData = $lead->email_list; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $address): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                    <a href="mailto:<?php echo e($address); ?>" class="text-primary"><?php echo e($address); ?></a><?php if(! $loop->last): ?>, <?php endif; ?>
                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                            <?php else: ?>
                                <span class="fst-italic">Not found</span>
                            <?php endif; ?>
                        </td>

                        <td data-val="<?php echo e($lead->status); ?>">
                            <?php
                                $badgeClass = match($lead->status) {
                                    'sent'    => 'badge-primary',
                                    'failed'  => 'badge-danger',
                                    'replied' => 'badge-success',
                                    default   => 'badge-secondary',
                                };
                            ?>
                            <span class="badge <?php echo e($badgeClass); ?> light"><?php echo e(ucfirst($lead->status)); ?></span>
                        </td>

                        <td data-val="<?php echo e(strtolower($lead->platform?->name ?? '')); ?>" class="d-none d-lg-table-cell">
                            <?php
                                $platformIcons = [
                                    'google'      => ['icon' => 'bi-google',           'color' => '#4285F4'],
                                    'linkedin'    => ['icon' => 'bi-linkedin',         'color' => '#0A66C2'],
                                    'upwork'      => ['icon' => 'bi-briefcase',        'color' => '#6fda44'],
                                    'freelancing' => ['icon' => 'bi-person-workspace', 'color' => '#f26722'],
                                    'facebook'    => ['icon' => 'bi-facebook',         'color' => '#1877F2'],
                                ];
                                $platformName = $lead->platform?->name ?? '—';
                                $key = strtolower($platformName);
                                $p   = $platformIcons[$key] ?? ['icon' => 'bi-globe', 'color' => '#6c757d'];
                            ?>
                            <span style="color:<?php echo e($p['color']); ?>">
                                <i class="bi <?php echo e($p['icon']); ?> me-1"></i><?php echo e($platformName); ?>

                            </span>
                        </td>

                        <td data-val="<?php echo e($lead->created_at->timestamp); ?>" class="d-none d-lg-table-cell">
                            <span class="text-nowrap"><?php echo e($lead->created_at->diffForHumans()); ?></span>
                        </td>

                        
                        <td class="text-end">
                            <div class="dropdown">
                                <button
                                    type="button"
                                    class="btn btn-sm btn-light btn-square"
                                    data-bs-toggle="dropdown"
                                    data-bs-strategy="fixed"
                                    aria-expanded="false"
                                    aria-label="Actions"
                                >
                                    <i class="bi bi-three-dots-vertical"></i>
                                </button>

                                <ul class="dropdown-menu dropdown-menu-end" style="min-width:180px;">

                                    
                                    <li>
                                        <button type="button"
                                            class="dropdown-item btn-view"
                                            data-id="<?php echo e($lead->id); ?>">
                                            <i class="bi bi-eye me-2 text-secondary"></i>View Details
                                        </button>
                                    </li>

                                    
                                    <li>
                                        <button type="button"
                                            class="dropdown-item btn-edit"
                                            data-id="<?php echo e($lead->id); ?>">
                                            <i class="bi bi-pencil-square me-2 text-warning"></i>Edit
                                        </button>
                                    </li>

                                    
                                    <?php if($lead->status === 'sent'): ?>
                                        <li>
                                            <button type="button"
                                                class="dropdown-item btn-show-email"
                                                data-id="<?php echo e($lead->id); ?>">
                                                <i class="bi bi-envelope-open me-2 text-success"></i>Show Email
                                            </button>
                                        </li>
                                    <?php endif; ?>

                                    
                                    <?php if($lead->email && in_array($lead->status, ['new', 'failed'])): ?>
                                        <li>
                                            <button type="button"
                                                class="dropdown-item btn-compose"
                                                data-id="<?php echo e($lead->id); ?>"
                                                data-name="<?php echo e(addslashes($lead->company_name)); ?>"
                                                data-website="<?php echo e($lead->website); ?>"
                                                data-to="<?php echo e($lead->email); ?>"
                                                data-emails="<?php echo e(json_encode($lead->email_list)); ?>">
                                                <?php if($lead->status === 'failed'): ?>
                                                    <i class="bi bi-arrow-repeat me-2 text-danger"></i>Retry Email
                                                <?php else: ?>
                                                    <i class="bi bi-send me-2 text-primary"></i>Send Email
                                                <?php endif; ?>
                                            </button>
                                        </li>
                                    <?php endif; ?>

                                    
                                    <?php if($lead->email && $lead->status === 'replied'): ?>
                                        <li>
                                            <form method="POST"
                                                action="<?php echo e(route('leads.mark-sent', $lead->id)); ?>"
                                                onsubmit="return confirm('Mark \'<?php echo e(addslashes($lead->company_name)); ?>\' as Sent?')">
                                                <?php echo csrf_field(); ?>
                                                <button type="submit" class="dropdown-item text-success">
                                                    <i class="bi bi-check2-circle me-2"></i>Mark as Sent
                                                </button>
                                            </form>
                                        </li>
                                    <?php endif; ?>

                                    
                                    <li>
                                        <form method="POST" action="<?php echo e(route('cash-leads.from-lead', $lead->id)); ?>">
                                            <?php echo csrf_field(); ?>
                                            <button type="submit" class="dropdown-item">
                                                <i class="bi bi-cash-coin me-2 text-success"></i>Mark as Cash Lead
                                            </button>
                                        </form>
                                    </li>

                                    
                                    <li>
                                        <form method="POST"
                                            action="<?php echo e(route('leads.destroy', $lead->id)); ?>"
                                            onsubmit="return confirm('Delete <?php echo e(addslashes($lead->company_name)); ?>?')">
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

    
    <div class="card-footer py-3">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">

            
            <div class="d-flex align-items-center gap-3 flex-wrap">

                
                <div class="d-flex align-items-center gap-2">
                    <label class="form-label mb-0 text-nowrap">Rows per page:</label>
                    <select id="perPageSelect" class="form-select form-select-sm per-page-select">
                        <?php $__currentLoopData = [10, 25, 50, 100]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $size): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <option value="<?php echo e($size); ?>" <?php echo e(request('per_page', 25) == $size ? 'selected' : ''); ?>>
                                <?php echo e($size); ?>

                            </option>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </select>
                </div>

                
                <span class="fs-13">
                    Showing
                    <strong><?php echo e($leads->firstItem()); ?></strong> - <strong><?php echo e($leads->lastItem()); ?></strong>
                    of <strong><?php echo e($leads->total()); ?></strong> results
                </span>

            </div>

            
            <?php if($leads->hasPages()): ?>
                <div><?php echo e($leads->appends(request()->query())->links()); ?></div>
            <?php endif; ?>

        </div>
    </div>
<?php endif; ?>
</div>
<?php /**PATH /var/www/html/resources/views/leads/_table.blade.php ENDPATH**/ ?>