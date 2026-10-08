<?php $__env->startSection('title', 'Dashboard — AI Client Finder'); ?>
<?php $__env->startSection('page-title', 'Dashboard'); ?>

<?php $__env->startSection('breadcrumb'); ?>
    <li class="breadcrumb-item active" aria-current="page">Dashboard</li>
<?php $__env->stopSection(); ?>

<?php $__env->startSection('content'); ?>


<div class="row">
    <?php
        // One shape for every card so the row stays even at any value length.
        $cards = [
            [
                'label' => 'Total Leads',
                'value' => $leadStats['total'],
                'meta'  => $leadStats['new'].' new · '.$leadStats['sent'].' contacted',
                'icon'  => 'bi-people-fill',
                'tint'  => 'primary',
            ],
            [
                'label' => 'Contactable',
                'value' => $leadStats['with_email'],
                'meta'  => $leadStats['coverage'].'% of leads have an email',
                'icon'  => 'bi-envelope-at-fill',
                'tint'  => 'info',
            ],
            [
                'label' => 'Emails Sent',
                'value' => $emailStats['sent'],
                'meta'  => $emailStats['today'].' today · '.$emailStats['this_week'].' this week',
                'icon'  => 'bi-send-fill',
                'tint'  => 'success',
            ],
            [
                'label' => 'Verified Valid',
                'value' => $verifyStats['valid'],
                'meta'  => $verifyStats['total'].' address'.($verifyStats['total'] === 1 ? '' : 'es').' checked',
                'icon'  => 'bi-patch-check-fill',
                'tint'  => 'warning',
            ],
        ];
    ?>

    <?php $__currentLoopData = $cards; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $card): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <div class="col-xl-3 col-sm-6 mb-4">
            <div class="card stat-card">
                <div class="card-body">
                    <div class="stat-icon tint-<?php echo e($card['tint']); ?>">
                        <i class="bi <?php echo e($card['icon']); ?>"></i>
                    </div>
                    <div class="stat-body">
                        <div class="stat-value"><?php echo e(number_format($card['value'])); ?></div>
                        <div class="stat-label"><?php echo e($card['label']); ?></div>
                        <div class="stat-meta"><?php echo e($card['meta']); ?></div>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
</div>

<div class="row">

    
    <div class="col-xl-8 mb-4">
        <div class="card h-100">
            <div class="card-header py-3 d-sm-flex d-block align-items-center justify-content-between">
                <div class="clearfix">
                    <h4 class="card-title"><i class="bi bi-graph-up-arrow me-2 text-primary"></i>Last 14 Days</h4>
                    <p class="mb-0 fs-13">Leads discovered against emails sent.</p>
                </div>
                <div class="chart-legend mt-2 mt-sm-0">
                    <span><span class="swatch bg-primary"></span>Leads found</span>
                    <span><span class="swatch bg-success"></span>Emails sent</span>
                </div>
            </div>
            <div class="card-body">
                <?php if(array_sum($activity['leads']) === 0 && array_sum($activity['emails']) === 0): ?>
                    <div class="empty-state">
                        <i class="bi bi-bar-chart-line empty-state-icon"></i>
                        <p class="mb-1 fw-semibold">No activity in the last 14 days</p>
                        <p class="fs-13 mb-0">
                            Start with the <a href="<?php echo e(route('finder.index')); ?>">Finder</a> to add some leads.
                        </p>
                    </div>
                <?php else: ?>
                    
                    <div class="activity-chart">
                        <?php $__currentLoopData = $activity['labels']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $i => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <?php
                                $leadCount  = $activity['leads'][$i];
                                $emailCount = $activity['emails'][$i];
                            ?>
                            <div class="activity-day">
                                <div class="activity-bars">
                                    <span class="activity-bar bar-leads"
                                          style="height: <?php echo e(max(2, round(($leadCount / $activity['max']) * 100))); ?>%"
                                          title="<?php echo e($label); ?>: <?php echo e($leadCount); ?> lead(s) found"></span>
                                    <span class="activity-bar bar-emails"
                                          style="height: <?php echo e(max(2, round(($emailCount / $activity['max']) * 100))); ?>%"
                                          title="<?php echo e($label); ?>: <?php echo e($emailCount); ?> email(s) sent"></span>
                                </div>
                                <span class="activity-label"><?php echo e($label); ?></span>
                            </div>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    
    <div class="col-xl-4 mb-4">
        <div class="card h-100">
            <div class="card-header py-3">
                <div class="clearfix">
                    <h4 class="card-title"><i class="bi bi-funnel-fill me-2 text-primary"></i>Lead Pipeline</h4>
                    <p class="mb-0 fs-13">Where every lead currently sits.</p>
                </div>
            </div>
            <div class="card-body">
                <?php
                    $pipeline = [
                        ['label' => 'New',     'key' => 'new',     'colour' => 'primary'],
                        ['label' => 'Sent',    'key' => 'sent',    'colour' => 'success'],
                        ['label' => 'Replied', 'key' => 'replied', 'colour' => 'info'],
                        ['label' => 'Failed',  'key' => 'failed',  'colour' => 'danger'],
                    ];
                    $pipelineTotal = max(1, $leadStats['total']);
                ?>

                <?php if($leadStats['total'] === 0): ?>
                    <div class="empty-state">
                        <i class="bi bi-funnel empty-state-icon"></i>
                        <p class="mb-1 fw-semibold">No leads yet</p>
                        <p class="fs-13 mb-0">Run a search from the <a href="<?php echo e(route('finder.index')); ?>">Finder</a>.</p>
                    </div>
                <?php else: ?>
                    <?php $__currentLoopData = $pipeline; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $stage): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <?php $count = $leadStats[$stage['key']]; ?>
                        <div class="mb-3">
                            <div class="d-flex justify-content-between align-items-baseline mb-1">
                                <span class="fw-medium"><?php echo e($stage['label']); ?></span>
                                <span class="fs-13 text-muted">
                                    <?php echo e(number_format($count)); ?>

                                    <span class="ms-1">(<?php echo e(round(($count / $pipelineTotal) * 100)); ?>%)</span>
                                </span>
                            </div>
                            <div class="score-meter">
                                <span class="bg-<?php echo e($stage['colour']); ?>" style="width: <?php echo e(round(($count / $pipelineTotal) * 100)); ?>%"></span>
                            </div>
                        </div>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>

                    <div class="d-flex justify-content-between align-items-center pt-2 border-top mt-3">
                        <span class="fs-13 text-muted">Reply rate</span>
                        <span class="fw-semibold"><?php echo e($emailStats['reply_rate']); ?>%</span>
                    </div>
                <?php endif; ?>
            </div>
            <div class="card-footer py-3">
                <a href="<?php echo e(route('leads.index')); ?>" class="btn btn-light btn-sm w-100">
                    <i class="bi bi-people me-1"></i>Open Leads
                </a>
            </div>
        </div>
    </div>
</div>

<div class="row">

    
    <div class="col-xl-4 mb-4">
        <div class="card h-100">
            <div class="card-header py-3">
                <div class="clearfix">
                    <h4 class="card-title"><i class="bi bi-patch-check-fill me-2 text-primary"></i>Deliverability</h4>
                    <p class="mb-0 fs-13">Results across every verification run.</p>
                </div>
            </div>
            <div class="card-body">
                <?php if($verifyStats['total'] === 0): ?>
                    <div class="empty-state">
                        <i class="bi bi-patch-question empty-state-icon"></i>
                        <p class="mb-1 fw-semibold">Nothing verified yet</p>
                        <p class="fs-13 mb-0">Check an address in the <a href="<?php echo e(route('verifier.index')); ?>">Verifier</a>.</p>
                    </div>
                <?php else: ?>
                    <?php
                        $verifyRows = [
                            ['label' => 'Valid',   'key' => 'valid',   'colour' => 'success'],
                            ['label' => 'Risky',   'key' => 'risky',   'colour' => 'warning'],
                            ['label' => 'Invalid', 'key' => 'invalid', 'colour' => 'danger'],
                            ['label' => 'Unknown', 'key' => 'unknown', 'colour' => 'secondary'],
                        ];
                    ?>

                    <?php $__currentLoopData = $verifyRows; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $row): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <?php $count = $verifyStats[$row['key']]; ?>
                        <div class="mb-3">
                            <div class="d-flex justify-content-between align-items-baseline mb-1">
                                <span class="fw-medium">
                                    <span class="badge badge-<?php echo e($row['colour']); ?> light"><?php echo e($row['label']); ?></span>
                                </span>
                                <span class="fs-13 text-muted">
                                    <?php echo e(number_format($count)); ?>

                                    (<?php echo e(round(($count / max(1, $verifyStats['total'])) * 100)); ?>%)
                                </span>
                            </div>
                            <div class="score-meter">
                                <span class="bg-<?php echo e($row['colour']); ?>" style="width: <?php echo e(round(($count / max(1, $verifyStats['total'])) * 100)); ?>%"></span>
                            </div>
                        </div>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                <?php endif; ?>
            </div>
            <div class="card-footer py-3">
                <a href="<?php echo e(route('verifier.index')); ?>" class="btn btn-light btn-sm w-100">
                    <i class="bi bi-patch-check me-1"></i>Open Verifier
                </a>
            </div>
        </div>
    </div>

    
    <div class="col-xl-8 mb-4">
        <div class="card h-100">
            <div class="card-header py-3">
                <div class="clearfix">
                    <h4 class="card-title"><i class="bi bi-tags-fill me-2 text-primary"></i>Top Categories</h4>
                    <p class="mb-0 fs-13">Busiest categories and how many of their leads are contactable.</p>
                </div>
            </div>
            <?php if(empty($topCategories)): ?>
                <div class="card-body">
                    <div class="empty-state">
                        <i class="bi bi-tag empty-state-icon"></i>
                        <p class="mb-1 fw-semibold">No categorised leads yet</p>
                        <p class="fs-13 mb-0">Leads are filed under a category when they are found.</p>
                    </div>
                </div>
            <?php else: ?>
                <div class="card-body table-card-body px-0 pt-0 pb-2">
                    <div class="table-responsive">
                        <table class="table">
                            <thead class="table-light">
                                <tr>
                                    <th>Category</th>
                                    <th style="width:100px" class="text-end">Leads</th>
                                    <th style="width:110px" class="text-end d-none d-md-table-cell">With email</th>
                                    <th style="width:180px" class="d-none d-sm-table-cell">Coverage</th>
                                    <th style="width:60px"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $__currentLoopData = $topCategories; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $category): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                    <tr>
                                        <td>
                                            <h6 class="mb-0 cell-wrap"><?php echo e($category['name']); ?></h6>
                                            
                                            <div class="d-md-none fs-13 text-muted">
                                                <?php echo e(number_format($category['with_email'])); ?> with email
                                                (<?php echo e($category['percent']); ?>%)
                                            </div>
                                        </td>
                                        <td class="text-end"><?php echo e(number_format($category['total'])); ?></td>
                                        <td class="text-end d-none d-md-table-cell"><?php echo e(number_format($category['with_email'])); ?></td>
                                        <td class="d-none d-sm-table-cell">
                                            <div class="d-flex align-items-center gap-2">
                                                <div class="score-meter flex-grow-1">
                                                    <span class="bg-<?php echo e($category['percent'] >= 60 ? 'success' : ($category['percent'] >= 30 ? 'warning' : 'danger')); ?>"
                                                          style="width: <?php echo e($category['percent']); ?>%"></span>
                                                </div>
                                                <span class="fs-13 text-muted"><?php echo e($category['percent']); ?>%</span>
                                            </div>
                                        </td>
                                        <td class="text-end">
                                            <a href="<?php echo e(route('finder.index', ['category' => $category['id']])); ?>"
                                               class="btn btn-sm btn-light btn-square" title="View in Finder">
                                                <i class="bi bi-arrow-right"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="row">

    
    <div class="col-xl-7 mb-4">
        <div class="card h-100">
            <div class="card-header py-3 d-sm-flex d-block align-items-center justify-content-between">
                <div class="clearfix">
                    <h4 class="card-title"><i class="bi bi-clock-history me-2 text-primary"></i>Recent Leads</h4>
                </div>
                <a href="<?php echo e(route('leads.index')); ?>" class="btn btn-light btn-sm">View all</a>
            </div>

            <?php if($recentLeads->isEmpty()): ?>
                <div class="card-body">
                    <div class="empty-state">
                        <i class="bi bi-inbox empty-state-icon"></i>
                        No leads yet.
                    </div>
                </div>
            <?php else: ?>
                <div class="card-body table-card-body px-0 pt-0 pb-2">
                    <div class="table-responsive">
                        <table class="table">
                            <thead class="table-light">
                                <tr>
                                    <th class="mw-150">Company</th>
                                    <th class="mw-150">Email</th>
                                    <th style="width:110px">Status</th>
                                    <th style="width:110px" class="d-none d-md-table-cell">Found</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $__currentLoopData = $recentLeads; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $lead): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                    <tr>
                                        <td>
                                            <h6 class="mb-0 cell-wrap"><?php echo e(Str::limit($lead->company_name, 40)); ?></h6>
                                            <span class="fs-13 text-muted">
                                                <?php echo e($lead->category?->name ?? '—'); ?>

                                                
                                                <span class="d-md-none">· <?php echo e($lead->created_at?->diffForHumans(short: true)); ?></span>
                                            </span>
                                        </td>
                                        <td class="cell-wrap">
                                            <?php if($lead->email): ?>
                                                <a href="mailto:<?php echo e($lead->email); ?>"><?php echo e($lead->email); ?></a>
                                            <?php else: ?>
                                                <span class="text-muted">—</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php
                                                $leadColour = match ($lead->status) {
                                                    'sent'    => 'success',
                                                    'replied' => 'info',
                                                    'failed'  => 'danger',
                                                    default   => 'primary',
                                                };
                                            ?>
                                            <span class="badge badge-<?php echo e($leadColour); ?> light"><?php echo e(ucfirst($lead->status)); ?></span>
                                        </td>
                                        <td class="fs-13 text-muted d-none d-md-table-cell"><?php echo e($lead->created_at?->diffForHumans(short: true)); ?></td>
                                    </tr>
                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    
    <div class="col-xl-5 mb-4">
        <div class="card h-100">
            <div class="card-header py-3 d-sm-flex d-block align-items-center justify-content-between">
                <div class="clearfix">
                    <h4 class="card-title"><i class="bi bi-stack me-2 text-primary"></i>Bulk Runs</h4>
                    <p class="mb-0 fs-13">
                        <?php echo e(number_format($bulkStats['records'])); ?> record(s) processed across <?php echo e($bulkStats['total']); ?> run(s).
                    </p>
                </div>
                <a href="<?php echo e(route('bulks.index')); ?>" class="btn btn-light btn-sm">View all</a>
            </div>

            <?php if($runningBulks->isEmpty()): ?>
                <div class="card-body">
                    <div class="empty-state">
                        <i class="bi bi-file-earmark-arrow-up empty-state-icon"></i>
                        <p class="mb-1 fw-semibold">No bulk runs yet</p>
                        <p class="fs-13 mb-0">Upload a CSV from <a href="<?php echo e(route('bulks.index')); ?>">Bulks</a>.</p>
                    </div>
                </div>
            <?php else: ?>
                <div class="card-body">
                    <?php $__currentLoopData = $runningBulks; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $bulk): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <div class="mb-3 pb-3 <?php echo e($loop->last ? '' : 'border-bottom'); ?>">
                            <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                                <div style="min-width:0">
                                    <a href="<?php echo e(route('bulks.show', $bulk->id)); ?>" class="fw-semibold cell-wrap">
                                        <?php echo e(Str::limit($bulk->name, 34)); ?>

                                    </a>
                                    <div class="fs-13 text-muted">
                                        <?php echo e($bulk->type === 'find' ? 'Email finder' : 'Verification'); ?>

                                        · <?php echo e($bulk->created_at?->diffForHumans(short: true)); ?>

                                    </div>
                                </div>
                                <span class="badge badge-<?php echo e($bulk->status_colour); ?> light text-nowrap">
                                    <?php echo e(ucfirst($bulk->status)); ?>

                                </span>
                            </div>
                            <div class="progress bulk-progress">
                                <div class="progress-bar bg-<?php echo e($bulk->status_colour); ?>"
                                     role="progressbar"
                                     style="width: <?php echo e($bulk->progress); ?>%"
                                     aria-valuenow="<?php echo e($bulk->progress); ?>" aria-valuemin="0" aria-valuemax="100"
                                     aria-label="<?php echo e($bulk->name); ?> progress"></div>
                            </div>
                            <div class="fs-13 text-muted mt-1">
                                <?php echo e(number_format($bulk->processed_records)); ?> / <?php echo e(number_format($bulk->total_records)); ?> processed
                                · <?php echo e(number_format($bulk->successful_records)); ?> successful
                            </div>
                        </div>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>


<div class="row">
    <div class="col-xl-12 mb-4">
        <div class="card">
            <div class="card-header py-3">
                <h4 class="card-title"><i class="bi bi-lightning-charge-fill me-2 text-primary"></i>Quick Actions</h4>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <?php
                        $actions = [
                            ['route' => 'finder.index',    'icon' => 'bi-search',        'label' => 'Find emails',      'meta' => 'Search a company domain'],
                            ['route' => 'verifier.index',  'icon' => 'bi-patch-check',   'label' => 'Verify an address', 'meta' => 'Check deliverability'],
                            ['route' => 'bulks.index',     'icon' => 'bi-stack',         'label' => 'Upload a CSV',      'meta' => 'Bulk verify or find'],
                            ['route' => 'templates.index', 'icon' => 'bi-envelope-paper','label' => 'Email templates',   'meta' => $templateCount.' active'],
                        ];
                    ?>

                    <?php $__currentLoopData = $actions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $action): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <div class="col-xl-3 col-sm-6">
                            <a href="<?php echo e(route($action['route'])); ?>" class="card stat-card mb-0 text-decoration-none">
                                <div class="card-body">
                                    <div class="stat-icon tint-primary">
                                        <i class="bi <?php echo e($action['icon']); ?>"></i>
                                    </div>
                                    <div class="stat-body">
                                        <div class="fw-semibold"><?php echo e($action['label']); ?></div>
                                        <div class="stat-meta"><?php echo e($action['meta']); ?></div>
                                    </div>
                                </div>
                            </a>
                        </div>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /var/www/html/resources/views/dashboard/index.blade.php ENDPATH**/ ?>