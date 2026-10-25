<?php $__env->startSection('title', __('Sign in')); ?>
<?php $__env->startSection('content'); ?>
<div class="card p-6 sm:p-8">
  <h1 class="font-display text-2xl font-semibold"><?php echo e(__('Sign in')); ?></h1>
  <p class="mt-1 text-sm text-ink-500"><?php echo e(__('Use your email or mobile number.')); ?></p>

  <form method="POST" action="<?php echo e(route('login.attempt')); ?>" class="mt-6 space-y-4" novalidate>
    <?php echo csrf_field(); ?>
    <div>
      <label class="label" for="login"><?php echo e(__('Email or mobile')); ?></label>
      <input id="login" name="login" type="text" class="input" value="<?php echo e(old('login')); ?>" autocomplete="username" maxlength="255" required autofocus>
      <?php $__errorArgs = ['login'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><span class="error"><?php echo e($message); ?></span><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
    </div>
    <div>
      <div class="flex items-end justify-between">
        <label class="label" for="password"><?php echo e(__('Password')); ?></label>
        <a href="<?php echo e(route('password.forgot')); ?>" class="mb-1.5 text-xs font-medium text-brand-700 hover:underline"><?php echo e(__('Forgot password?')); ?></a>
      </div>
      <input id="password" name="password" type="password" class="input" autocomplete="current-password" maxlength="1024" required>
    </div>
    <?php if($captcha): ?>
      <?php echo $__env->make('partials.captcha', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    <?php endif; ?>
    <label class="flex items-center gap-2 text-sm text-ink-700">
      <input type="checkbox" name="remember" value="1" class="size-4 rounded border-ink-300" <?php if(old('remember')): echo 'checked'; endif; ?>> <?php echo e(__('Keep me signed in on this computer')); ?>

    </label>
    <button type="submit" class="btn-primary btn-lg w-full"><?php if (isset($component)) { $__componentOriginalce262628e3a8d44dc38fd1f3965181bc = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalce262628e3a8d44dc38fd1f3965181bc = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.icon','data' => ['name' => 'log-in']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'log-in']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalce262628e3a8d44dc38fd1f3965181bc)): ?>
<?php $attributes = $__attributesOriginalce262628e3a8d44dc38fd1f3965181bc; ?>
<?php unset($__attributesOriginalce262628e3a8d44dc38fd1f3965181bc); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalce262628e3a8d44dc38fd1f3965181bc)): ?>
<?php $component = $__componentOriginalce262628e3a8d44dc38fd1f3965181bc; ?>
<?php unset($__componentOriginalce262628e3a8d44dc38fd1f3965181bc); ?>
<?php endif; ?> <?php echo e(__('Sign in')); ?></button>
  </form>
</div>
<p class="text-center text-sm text-ink-500"><?php echo e(__('New here?')); ?> <a href="<?php echo e(route('signup')); ?>" class="font-semibold text-brand-700 hover:underline"><?php echo e(__('Start a free trial')); ?></a></p>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.guest', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /home/user/WebAppForTrucks/webapp/resources/views/auth/login.blade.php ENDPATH**/ ?>