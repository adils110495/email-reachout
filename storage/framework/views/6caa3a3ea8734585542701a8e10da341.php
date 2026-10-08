

<div class="header">
    <div class="header-content">
        <nav class="navbar navbar-expand">
            <div class="collapse navbar-collapse justify-content-between align-items-center">
                <div class="header-left">
                    <div class="dashboard_bar"><?php echo $__env->yieldContent('page-title', 'Dashboard'); ?></div>
                </div>

                
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
            </div>
        </nav>
    </div>
</div>

<?php /**PATH /var/www/html/resources/views/layouts/partials/header.blade.php ENDPATH**/ ?>