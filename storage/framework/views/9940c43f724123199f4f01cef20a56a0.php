
<div class="ajax-content" data-total="<?php echo e($gmbLeads->total()); ?>">
<?php if($gmbLeads->isEmpty()): ?>
    <div class="card-body">
        <div class="empty-state">
            <i class="bi bi-geo-alt empty-state-icon"></i>
            <?php if($activeCategory || $search || $activeRating || $reviewsMin !== null || $reviewsMax !== null): ?>
                No GMB leads match. <a href="<?php echo e(route('gmb-leads.index')); ?>">Clear the filters</a>.
            <?php else: ?>
                No GMB leads yet. Search above to find businesses without a website.
            <?php endif; ?>
        </div>
    </div>
<?php else: ?>
    <div class="card-body table-card-body px-0 pt-0 pb-2">
        <div class="table-responsive">
            <table class="table">
                <thead class="table-light">
                    <tr>
                        <th style="width:60px">#</th>
                        <th class="mw-200">Business</th>
                        <th>Category</th>
                        <th>Phone</th>
                        <th class="mw-200">Address</th>
                        <th>Rating</th>
                        <th style="width:80px" class="text-center">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $__currentLoopData = $gmbLeads; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $lead): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <tr>
                            <td><?php echo e($gmbLeads->firstItem() + $loop->index); ?></td>
                            <td>
                                <h6 class="mb-0"><?php echo e($lead->name); ?></h6>
                                <small class="text-muted"><?php echo e($lead->type); ?></small>
                            </td>
                            <td><?php echo e($lead->category?->name ?? '—'); ?></td>
                            <td>
                                <?php if($lead->phone): ?>
                                    <a href="tel:<?php echo e($lead->phone); ?>"><?php echo e($lead->phone); ?></a>
                                <?php else: ?>
                                    —
                                <?php endif; ?>
                            </td>
                            <td><?php echo e($lead->address ?? '—'); ?></td>
                            <td>
                                <?php if($lead->rating): ?>
                                    <i class="bi bi-star-fill text-warning me-1"></i><?php echo e($lead->rating); ?>

                                    <small class="text-muted">(<?php echo e($lead->reviews ?? 0); ?>)</small>
                                <?php else: ?>
                                    —
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <div class="dropdown">
                                    <button class="btn btn-sm btn-light btn-square"
                                            data-bs-toggle="dropdown" aria-expanded="false" aria-label="Actions">
                                        <i class="bi bi-three-dots-vertical"></i>
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end">
                                        <?php if($lead->maps_url): ?>
                                            <li>
                                                <a class="dropdown-item" href="<?php echo e($lead->maps_url); ?>" target="_blank" rel="noopener">
                                                    <i class="bi bi-geo-alt me-2 text-primary"></i>View on Maps
                                                </a>
                                            </li>
                                        <?php endif; ?>
                                        <li>
                                            <form method="POST" action="<?php echo e(route('cash-leads.from-gmb', $lead->id)); ?>">
                                                <?php echo csrf_field(); ?>
                                                <button type="submit" class="dropdown-item">
                                                    <i class="bi bi-cash-coin me-2 text-success"></i>Mark as Deal
                                                </button>
                                            </form>
                                        </li>
                                        <li>
                                            <form method="POST" action="<?php echo e(route('gmb-leads.destroy', $lead->id)); ?>"
                                                  onsubmit="return confirm('Delete \'<?php echo e(addslashes($lead->name)); ?>\'?')">
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

    <?php if($gmbLeads->hasPages()): ?>
        <div class="card-footer"><?php echo e($gmbLeads->links()); ?></div>
    <?php endif; ?>
<?php endif; ?>
</div>
<?php /**PATH /var/www/html/resources/views/gmb-leads/_table.blade.php ENDPATH**/ ?>