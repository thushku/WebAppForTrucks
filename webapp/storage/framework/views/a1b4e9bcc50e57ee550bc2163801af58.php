<?php $__env->startSection('title', __('Dashboard')); ?>
<?php $__env->startSection('content'); ?>
<section class="card relative overflow-hidden p-6 sm:p-8">
  <div class="pointer-events-none absolute -end-16 -top-16 size-56 rounded-full bg-brand-100 blur-2xl"></div>
  <div class="relative">
    <p class="text-sm font-medium text-brand-700"><?php echo e($company->name); ?></p>
    <h2 class="mt-1 font-display text-3xl font-semibold"><?php echo e(__('Welcome, :name', ['name' => auth()->user()->name])); ?></h2>
    <p class="mt-2 max-w-2xl text-sm text-ink-500"><?php echo e(__('This is your control room. Trips, approvals, money and documents appear here as each part is switched on.')); ?></p>
  </div>
</section>

<div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
  <?php $__currentLoopData = [
    ['truck', __('Trucks'), '—', 'bg-blue-50 text-blue-700'],
    ['route', __('Trips running'), '—', 'bg-amber-50 text-amber-700'],
    ['check', __('Entries to check'), '—', 'bg-emerald-50 text-emerald-700'],
    ['wallet', __('Revenue this month'), '—', 'bg-violet-50 text-violet-700'],
  ]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as [$icon, $label, $value, $tile]): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
    <div class="stat">
      <div class="flex items-center justify-between">
        <span class="stat-label"><?php echo e($label); ?></span>
        <span class="nav-tile <?php echo e($tile); ?>"><?php if (isset($component)) { $__componentOriginalce262628e3a8d44dc38fd1f3965181bc = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalce262628e3a8d44dc38fd1f3965181bc = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.icon','data' => ['name' => $icon,'class' => 'size-4']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($icon),'class' => 'size-4']); ?>
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
      </div>
      <div class="stat-value"><?php echo e($value); ?></div>
    </div>
  <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /home/user/WebAppForTrucks/webapp/resources/views/dashboard.blade.php ENDPATH**/ ?>