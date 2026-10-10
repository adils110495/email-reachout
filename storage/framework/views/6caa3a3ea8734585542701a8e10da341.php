

<div class="header">
    <div class="header-content">
        <nav class="navbar navbar-expand">
            <div class="collapse navbar-collapse justify-content-between align-items-center">
                <div class="header-left">
                    <div class="dashboard_bar"><?php echo $__env->yieldContent('page-title', 'Dashboard'); ?></div>
                </div>

                <div class="d-flex align-items-center gap-2">

                
                <div class="dropdown" id="notifDropdown">
                    <button class="btn btn-light position-relative" type="button" id="notifBell"
                            data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false" aria-label="Notifications">
                        <i class="bi bi-bell"></i>
                        <span id="notifBadge" class="position-absolute badge rounded-pill bg-danger d-none"
                              style="top:2px;right:2px;font-size:10px;line-height:1;padding:3px 5px;">0</span>
                    </button>
                    <div class="dropdown-menu dropdown-menu-end p-0" style="width:340px;max-width:92vw;">
                        <div class="d-flex justify-content-between align-items-center px-3 py-2 border-bottom">
                            <strong>Notifications</strong>
                            <button type="button" class="btn btn-link btn-sm p-0" id="notifMarkAll">Mark all read</button>
                        </div>
                        <div id="notifList" style="max-height:360px;overflow-y:auto;">
                            <div class="text-center text-muted fs-13 py-4">No notifications yet.</div>
                        </div>
                    </div>
                </div>

                
                <div class="dropdown">
                    <button class="btn btn-light d-inline-flex align-items-center gap-2" type="button"
                            data-bs-toggle="dropdown" aria-expanded="false" aria-label="Account">
                        <i class="bi bi-person-circle"></i>
                        <span class="d-none d-md-inline"><?php echo e(auth()->user()->name); ?></span>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li class="px-3 py-2 fs-13 text-muted">Signed in as <strong><?php echo e(auth()->user()->username); ?></strong></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="<?php echo e(route('outreach.profile.edit')); ?>"><i class="bi bi-person-gear me-2"></i>Profile &amp; settings</a></li>
                        <li>
                            <form method="POST" action="<?php echo e(route('logout')); ?>">
                                <?php echo csrf_field(); ?>
                                <button type="submit" class="dropdown-item text-danger">
                                    <i class="bi bi-box-arrow-right me-2"></i>Sign out
                                </button>
                            </form>
                        </li>
                    </ul>
                </div>

                </div>
            </div>
        </nav>
    </div>
</div>

<?php /**PATH /var/www/html/resources/views/layouts/partials/header.blade.php ENDPATH**/ ?>