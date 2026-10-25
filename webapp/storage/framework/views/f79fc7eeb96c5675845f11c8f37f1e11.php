<?php ($brand = app(\App\Platform\Settings\PlatformSettings::class)->brandName()); ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?php echo $__env->yieldContent('title', 'Console'); ?> — <?php echo e($brand); ?> console</title>
<?php echo app('Illuminate\Foundation\Vite')(['resources/css/app.css', 'resources/js/app.js']); ?>
</head>
<body class="min-h-screen">
<div class="nav-progress" data-nav-progress aria-hidden="true"></div>
<header class="flex items-center gap-4 border-b border-ink-100 bg-ink-900 px-6 py-3 text-white">
  <span class="grid size-9 place-items-center rounded-xl bg-white/10"><?php if (isset($component)) { $__componentOriginalce262628e3a8d44dc38fd1f3965181bc = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalce262628e3a8d44dc38fd1f3965181bc = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.icon','data' => ['name' => 'shield','class' => 'size-5']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'shield','class' => 'size-5']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalce262628e3a8d44dc38fd1f3965181bc)): ?>
<?php $attributes = $__attributesOriginalce262628e3a8d44dc38fd1f3965181bc; ?>
<?php unset($__attributesOriginalce262628e3a8d44dc38fd1f3965181bc); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalce262628e3a8d44dc38fd1f3965181bc)): ?>
<?php $component = $__componentOriginalce262628e3a8d44dc38fd1f3965181bc; ?>
<?php unset($__componentOriginalce262628e3a8d44dc38fd1f3965181bc); ?>
<?php endif; ?></span>
  <span class="font-display text-lg font-semibold"><?php echo e($brand); ?> · <?php echo e(__('Console')); ?></span>
  <?php if(auth()->guard('platform')->check()): ?>
    <nav class="ms-6 hidden gap-1 text-sm sm:flex">
      <a href="<?php echo e(route('platform.home')); ?>" class="rounded-lg px-3 py-1.5 hover:bg-white/10 <?php echo e(request()->routeIs('platform.home') ? 'bg-white/10' : ''); ?>"><?php echo e(__('Overview')); ?></a>
      <a href="<?php echo e(route('platform.settings')); ?>" class="rounded-lg px-3 py-1.5 hover:bg-white/10 <?php echo e(request()->routeIs('platform.settings') ? 'bg-white/10' : ''); ?>"><?php echo e(__('Settings')); ?></a>
    </nav>
    <form method="POST" action="<?php echo e(route('platform.logout')); ?>" class="ms-auto">
      <?php echo csrf_field(); ?>
      <button class="rounded-lg px-3 py-1.5 text-sm hover:bg-white/10"><?php echo e(__('Sign out')); ?></button>
    </form>
  <?php endif; ?>
</header>
<main class="mx-auto max-w-6xl space-y-6 px-4 py-8 sm:px-6">
  <?php echo $__env->make('partials.flash', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
  <?php echo $__env->yieldContent('content'); ?>
</main>
<footer class="pb-8 text-center text-[11px] text-ink-400"><?php echo e(__('Version')); ?> <?php echo e(config('app.version')); ?> (<?php echo e(config('app.build')); ?>)</footer>
</body>
</html>
<?php /**PATH /home/user/WebAppForTrucks/webapp/resources/views/platform/layout.blade.php ENDPATH**/ ?>