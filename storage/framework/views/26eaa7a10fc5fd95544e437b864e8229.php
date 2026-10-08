
<div class="ajax-content" data-total="<?php echo e($cashLeads->total()); ?>">
<?php if($cashLeads->isEmpty()): ?>
    <div class="card-body">
        <div class="empty-state">
            <i class="bi bi-cash-coin empty-state-icon"></i>
            <?php if($activeCategory || $activeSource || $search): ?>
                No deals match. <a href="<?php echo e(route('cash-leads.index')); ?>">Clear the filters</a>.
            <?php else: ?>
                No deals yet. Mark a lead as a Deal, or add one with the button above.
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
                        <th class="mw-200">Company</th>
                        <th>Category</th>
                        <th class="mw-150">Contact</th>
                        <th>Source</th>
                        <th class="mw-200">Notes</th>
                        <th>Added</th>
                        <th style="width:80px" class="text-center">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $__currentLoopData = $cashLeads; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $cash): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <tr>
                            <td><?php echo e($cashLeads->firstItem() + $loop->index); ?></td>
                            <td>
                                <h6 class="mb-0"><?php echo e($cash->company_name); ?></h6>
                                <?php if($cash->website): ?>
                                    <small class="text-muted"><?php echo e($cash->website); ?></small>
                                <?php endif; ?>
                            </td>
                            <td><?php echo e($cash->category?->name ?? '—'); ?></td>
                            <td>
                                <?php if($cash->email): ?><div><?php echo e($cash->email); ?></div><?php endif; ?>
                                <?php if($cash->phone): ?><div><a href="tel:<?php echo e($cash->phone); ?>"><?php echo e($cash->phone); ?></a></div><?php endif; ?>
                                <?php if(! $cash->email && ! $cash->phone): ?>—<?php endif; ?>
                            </td>
                            <td><span class="badge badge-success light"><?php echo e($sources[$cash->source] ?? $cash->source); ?></span></td>
                            <td><span class="fs-13"><?php echo e(\Illuminate\Support\Str::limit($cash->notes, 80) ?: '—'); ?></span></td>
                            <td><?php echo e($cash->created_at->format('d M Y')); ?></td>
                            <td class="text-center">
                                <div class="dropdown">
                                    <button class="btn btn-sm btn-light btn-square"
                                            data-bs-toggle="dropdown" data-bs-strategy="fixed" aria-expanded="false" aria-label="Actions">
                                        <i class="bi bi-three-dots-vertical"></i>
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end">
                                        <li>
                                            <button type="button" class="dropdown-item btn-edit-cash"
                                                    data-id="<?php echo e($cash->id); ?>"
                                                    data-company="<?php echo e($cash->company_name); ?>"
                                                    data-category="<?php echo e($cash->category_id); ?>"
                                                    data-email="<?php echo e($cash->email); ?>"
                                                    data-phone="<?php echo e($cash->phone); ?>"
                                                    data-website="<?php echo e($cash->website); ?>"
                                                    data-address="<?php echo e($cash->address); ?>"
                                                    data-notes="<?php echo e($cash->notes); ?>">
                                                <i class="bi bi-pencil me-2 text-warning"></i>Edit
                                            </button>
                                        </li>
                                        <li>
                                            <form method="POST" action="<?php echo e(route('cash-leads.destroy', $cash->id)); ?>"
                                                  onsubmit="return confirm('Remove \'<?php echo e(addslashes($cash->company_name)); ?>\' from Deals?')">
                                                <?php echo csrf_field(); ?> <?php echo method_field('DELETE'); ?>
                                                <input type="hidden" name="_redirect_back" value="<?php echo e(request()->getQueryString() ? '?'.request()->getQueryString() : ''); ?>">
                                                <button type="submit" class="dropdown-item text-danger">
                                                    <i class="bi bi-trash me-2"></i>Remove
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

    <?php if($cashLeads->hasPages()): ?>
        <div class="card-footer"><?php echo e($cashLeads->links()); ?></div>
    <?php endif; ?>
<?php endif; ?>
</div>
<?php /**PATH /var/www/html/resources/views/cash-leads/_table.blade.php ENDPATH**/ ?>