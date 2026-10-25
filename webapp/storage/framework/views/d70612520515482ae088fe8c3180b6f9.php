<?php $__env->startSection('title', 'Sign in'); ?>
<?php $__env->startSection('content'); ?>
<div class="mx-auto max-w-md">
  <div class="card p-6 sm:p-8">
    <h1 class="font-display text-2xl font-semibold"><?php echo e(__('Console sign-in')); ?></h1>
    <p class="mt-1 text-sm text-ink-500"><?php echo e(__('After your password we email you a one-time code.')); ?></p>
    <form method="POST" action="<?php echo e(route('platform.login.attempt')); ?>" class="mt-6 space-y-4">
      <?php echo csrf_field(); ?>
      <div>
        <label class="label" for="email"><?php echo e(__('Email')); ?></label>
        <input id="email" name="email" type="email" class="input" value="<?php echo e(old('email')); ?>" maxlength="255" autocomplete="username" required autofocus>
        <?php $__errorArgs = ['email'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><span class="error"><?php echo e($message); ?></span><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
      </div>
      <div>
        <label class="label" for="password"><?php echo e(__('Password')); ?></label>
        <input id="password" name="password" type="password" class="input" maxlength="1024" autocomplete="current-password" required>
      </div>
      <?php echo $__env->make('partials.captcha', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
      <button type="submit" class="btn-primary btn-lg w-full"><?php echo e(__('Continue')); ?></button>
    </form>
  </div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('platform.layout', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /home/user/WebAppForTrucks/webapp/resources/views/platform/login.blade.php ENDPATH**/ ?>