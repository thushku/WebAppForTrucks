<?php ($brand = app(\App\Platform\Settings\PlatformSettings::class)->brandName()); ?>
<!DOCTYPE html>
<html lang="<?php echo e(str_replace('_', '-', app()->getLocale())); ?>" dir="<?php echo e(\App\Support\Locales::isRtl(app()->getLocale()) ? 'rtl' : 'ltr'); ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?php echo $__env->yieldContent('title', __('Sign in')); ?> — <?php echo e($brand); ?></title>
<?php echo \App\Support\Fonts::preloadTags(); ?>

<?php echo app('Illuminate\Foundation\Vite')(['resources/css/app.css', 'resources/js/app.js']); ?>
</head>
<body class="min-h-screen">
<div class="nav-progress" data-nav-progress aria-hidden="true"></div>
<div class="grid min-h-screen lg:grid-cols-[1.1fr_minmax(420px,560px)]">

  <aside class="relative hidden overflow-hidden bg-brand-800 text-white lg:flex lg:flex-col lg:justify-between lg:p-12">
    <div class="pointer-events-none absolute -end-24 -top-24 size-[420px] rounded-full bg-brand-500/40 blur-3xl"></div>
    <div class="pointer-events-none absolute -bottom-32 -start-16 size-[360px] rounded-full bg-road-500/25 blur-3xl"></div>

    <div class="relative flex items-center gap-3">
      <span class="grid size-10 place-items-center rounded-xl bg-white/15"><?php if (isset($component)) { $__componentOriginalce262628e3a8d44dc38fd1f3965181bc = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalce262628e3a8d44dc38fd1f3965181bc = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.icon','data' => ['name' => 'truck','class' => 'size-6']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'truck','class' => 'size-6']); ?>
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
      <span class="font-display text-xl font-semibold"><?php echo e($brand); ?></span>
    </div>

    <div class="relative max-w-md">
      <h2 class="font-display text-4xl font-semibold leading-tight"><?php echo e(__('Every trip, every rupee, proved from the road.')); ?></h2>
      <ul class="mt-8 space-y-5">
        <?php $__currentLoopData = [
          [__('Plan trips, the crew records the rest'), __('Fuel, tolls and payments with GPS, time and photos — even without network.')],
          [__('Ledgers for every person and party'), __('Drivers, helpers, clients, suppliers and vendors, settled without disputes.')],
          [__('Invoices, LR and books that meet the law'), __('GST and VAT ready, with your own templates.')],
        ]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as [$headline, $detail]): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
          <li class="flex gap-3">
            <span class="mt-0.5 grid size-6 shrink-0 place-items-center rounded-full bg-white/15"><?php if (isset($component)) { $__componentOriginalce262628e3a8d44dc38fd1f3965181bc = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalce262628e3a8d44dc38fd1f3965181bc = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.icon','data' => ['name' => 'check','class' => 'size-3.5']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'check','class' => 'size-3.5']); ?>
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
            <div><b class="block font-semibold"><?php echo e($headline); ?></b><span class="text-sm text-white/75"><?php echo e($detail); ?></span></div>
          </li>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
      </ul>
    </div>

    <p class="relative text-xs text-white/60"><?php echo e(__('Version')); ?> <?php echo e(config('app.version')); ?> (<?php echo e(config('app.build')); ?>)</p>
  </aside>

  <main class="flex items-center justify-center px-5 py-10 sm:px-8">
    <div class="w-full max-w-[440px] space-y-5">
      <div class="flex items-center gap-2 lg:hidden">
        <span class="grid size-9 place-items-center rounded-xl bg-brand-600 text-white"><?php if (isset($component)) { $__componentOriginalce262628e3a8d44dc38fd1f3965181bc = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalce262628e3a8d44dc38fd1f3965181bc = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.icon','data' => ['name' => 'truck','class' => 'size-5']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'truck','class' => 'size-5']); ?>
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
        <span class="font-display text-lg font-semibold"><?php echo e($brand); ?></span>
      </div>
      <?php echo $__env->make('partials.flash', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
      <?php echo $__env->yieldContent('content'); ?>
      <p class="text-center text-[11px] text-ink-400 lg:hidden"><?php echo e(__('Version')); ?> <?php echo e(config('app.version')); ?> (<?php echo e(config('app.build')); ?>)</p>
    </div>
  </main>
</div>
</body>
</html>
<?php /**PATH /home/user/WebAppForTrucks/webapp/resources/views/layouts/guest.blade.php ENDPATH**/ ?>