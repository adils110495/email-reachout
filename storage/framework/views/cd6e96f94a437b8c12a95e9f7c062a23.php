<?php $__env->startSection('title', 'Branding — Settings'); ?>
<?php $__env->startSection('page-title', 'Branding'); ?>

<?php $__env->startSection('breadcrumb'); ?>
    <li class="breadcrumb-item">Settings</li>
    <li class="breadcrumb-item active" aria-current="page">Branding</li>
<?php $__env->stopSection(); ?>

<?php use \App\Models\AppSetting; ?>

<?php
    $items = [
        [
            'key'     => AppSetting::ADMIN_LOGO,
            'title'   => 'Logo',
            'help'    => 'Shown in the sidebar header, on the login page and at the top of every outreach email. A wide logo works best (about 4:1). PNG, JPG or GIF, max 2 MB.',
            'accept'  => '.png,.jpg,.jpeg,.gif',
            'url'     => AppSetting::adminLogoUrl(),
            'default' => 'SabRight logo',
        ],
        [
            'key'     => AppSetting::ADMIN_ICON,
            'title'   => 'Icon',
            'help'    => 'Square mark used for the collapsed sidebar, mobile header and browser tab (favicon). If not set, the logo is used. PNG, JPG or WEBP, max 1 MB.',
            'accept'  => '.png,.jpg,.jpeg,.webp',
            'url'     => AppSetting::adminIconUrl(),
            'default' => 'logo',
        ],
    ];
?>

<?php $__env->startSection('content'); ?>

<div class="row">
    <div class="col-xl-12">
        <div class="card">
            <div class="card-header py-3 d-block">
                <h4 class="card-title"><i class="bi bi-image me-2 text-primary"></i>Branding</h4>
                <p class="mb-0 fs-13">Brand name and logo used in the admin panel and in outgoing emails.</p>
            </div>

            <div class="card-body">
                <form method="POST" action="<?php echo e(route('branding.update')); ?>" enctype="multipart/form-data">
                    <?php echo csrf_field(); ?> <?php echo method_field('PUT'); ?>

                    <div class="row">
                        <div class="col-md-6 mb-4">
                            <label class="form-label" for="company_name">Brand Name <span class="text-danger">*</span></label>
                            <input type="text" name="<?php echo e(AppSetting::COMPANY_NAME); ?>" id="company_name" maxlength="255" required
                                   class="form-control <?php $__errorArgs = [AppSetting::COMPANY_NAME];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>"
                                   value="<?php echo e(old(AppSetting::COMPANY_NAME, AppSetting::companyName())); ?>">
                            <?php $__errorArgs = [AppSetting::COMPANY_NAME];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><div class="invalid-feedback"><?php echo e($message); ?></div><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
                            <div class="form-text">Used in the email footer ("© <?php echo e(date('Y')); ?> … All rights reserved."), in AI-written emails and as the logo's alt text.</div>
                        </div>
                    </div>

                    <div class="row">
                        <?php $__currentLoopData = $items; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <?php $custom = AppSetting::isCustom($item['key']); ?>
                            <div class="col-md-6 mb-4">
                                <div class="border rounded p-3 h-100 d-flex flex-column">
                                    <div class="d-flex align-items-center justify-content-between mb-2">
                                        <label class="form-label mb-0 fw-semibold" for="<?php echo e($item['key']); ?>"><?php echo e($item['title']); ?></label>
                                        <span class="badge <?php echo e($custom ? 'badge-success light' : 'badge-light'); ?>"><?php echo e($custom ? 'Custom' : 'Default'); ?></span>
                                    </div>

                                    <div class="branding-preview mb-3">
                                        <img src="<?php echo e($item['url']); ?>" alt="<?php echo e($item['title']); ?>" data-preview="<?php echo e($item['key']); ?>">
                                    </div>

                                    <input type="file" name="<?php echo e($item['key']); ?>" id="<?php echo e($item['key']); ?>" accept="<?php echo e($item['accept']); ?>"
                                           class="form-control <?php $__errorArgs = [$item['key']];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>">
                                    <?php $__errorArgs = [$item['key']];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><div class="invalid-feedback"><?php echo e($message); ?></div><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
                                    <div class="form-text mb-2"><?php echo e($item['help']); ?></div>

                                    <?php if($custom): ?>
                                        <div class="mt-auto">
                                            <button type="submit" class="btn btn-sm btn-light text-danger"
                                                    form="reset-<?php echo e($item['key']); ?>">
                                                <i class="bi bi-arrow-counterclockwise me-1"></i>Reset to <?php echo e($item['default']); ?>

                                            </button>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </div>

                    <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i>Save Branding</button>
                </form>

                
                <?php $__currentLoopData = $items; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <form method="POST" action="<?php echo e(route('branding.reset', $item['key'])); ?>" id="reset-<?php echo e($item['key']); ?>"
                          onsubmit="return confirm('Remove this image and use the default?');">
                        <?php echo csrf_field(); ?> <?php echo method_field('DELETE'); ?>
                    </form>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </div>
        </div>

        
        <div class="card">
            <div class="card-header py-3 d-block">
                <h4 class="card-title"><i class="bi bi-layout-text-window-reverse me-2 text-primary"></i>Email Footer</h4>
                <p class="mb-0 fs-13">
                    Shown at the bottom of every outreach email. The address, email, phone and website come from
                    <a href="<?php echo e(route('addresses.index')); ?>">Settings &rsaquo; Addresses</a> — the one picked while composing, otherwise the first active address.
                </p>
            </div>

            <div class="card-body">
                <form method="POST" action="<?php echo e(route('branding.footer')); ?>">
                    <?php echo csrf_field(); ?> <?php echo method_field('PUT'); ?>

                    <h6 class="mb-1">Social Links</h6>
                    <p class="fs-13 text-muted mb-3">Only the icons with a URL are shown in the email.</p>
                    <div class="row">
                        <?php $__currentLoopData = AppSetting::SOCIALS; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key => $social): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <div class="col-md-6 mb-3">
                                <label class="form-label" for="<?php echo e($key); ?>"><?php echo e($social['label']); ?></label>
                                <input type="url" name="<?php echo e($key); ?>" id="<?php echo e($key); ?>" maxlength="255"
                                       class="form-control <?php $__errorArgs = [$key];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>"
                                       value="<?php echo e(old($key, AppSetting::read($key))); ?>" placeholder="https://">
                                <?php $__errorArgs = [$key];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><div class="invalid-feedback"><?php echo e($message); ?></div><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
                            </div>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </div>

                    <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i>Save Footer</button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php $__env->stopSection(); ?>

<?php $__env->startPush('styles'); ?>
<style>
    .branding-preview {
        height: 7rem;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: .75rem;
        border-radius: .375rem;
        /* checkerboard so transparent logos are visible */
        background: repeating-conic-gradient(#f1f1f4 0% 25%, #ffffff 0% 50%) 50% / 16px 16px;
    }
    .branding-preview img { max-width: 100%; max-height: 100%; object-fit: contain; }
</style>
<?php $__env->stopPush(); ?>

<?php $__env->startPush('scripts'); ?>
<script>
    // Preview the chosen file before saving.
    document.querySelectorAll('input[type=file]').forEach(function (input) {
        input.addEventListener('change', function () {
            const img = document.querySelector('[data-preview="' + input.name + '"]');
            if (img && input.files[0]) {
                img.src = URL.createObjectURL(input.files[0]);
            }
        });
    });
</script>
<?php $__env->stopPush(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /var/www/html/resources/views/branding/index.blade.php ENDPATH**/ ?>