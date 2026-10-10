<?php $__env->startSection('title', 'Forgot password'); ?>
<?php $__env->startSection('heading', 'Forgot your password?'); ?>
<?php $__env->startSection('subheading', 'Enter the email address on your account and we will send you a reset link.'); ?>

<?php $__env->startSection('content'); ?>
<form method="POST" action="<?php echo e(route('password.email')); ?>" novalidate>
    <?php echo csrf_field(); ?>
    <div class="mb-3">
        <label class="form-label" for="email">Email</label>
        <input type="email" id="email" name="email" value="<?php echo e(old('email')); ?>" autofocus required autocomplete="email" class="form-control <?php $__errorArgs = ['email'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> is-invalid <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>">
        <?php $__errorArgs = ['email'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><div class="invalid-feedback"><?php echo e($message); ?></div><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
    </div>
    <button type="submit" class="btn btn-primary w-100">Send reset link</button>
    <p class="text-center fs-13 mt-3 mb-0"><a href="<?php echo e(route('login')); ?>">Back to sign in</a></p>
</form>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.auth', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /var/www/html/resources/views/auth/forgot-password.blade.php ENDPATH**/ ?>