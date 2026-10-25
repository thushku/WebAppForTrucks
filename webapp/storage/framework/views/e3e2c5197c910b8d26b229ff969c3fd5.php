<?php $__env->startSection('title', __('Choose a company')); ?>
<?php $__env->startSection('content'); ?>
<div class="card p-6 sm:p-8">
  <h1 class="font-display text-2xl font-semibold"><?php echo e(__('Choose a company')); ?></h1>
  <?php if($memberships->isEmpty()): ?>
    <p class="mt-2 text-sm text-ink-500"><?php echo e(__('Your login is not part of any active company yet. Ask your company to invite you, or start your own free trial.')); ?></p>
    <a href="<?php echo e(route('signup')); ?>" class="btn-secondary mt-5 w-full"><?php echo e(__('Start a free trial')); ?></a>
  <?php else: ?>
    <p class="mt-1 text-sm text-ink-500"><?php echo e(__('You work with more than one company. Which one do you want to open?')); ?></p>
    <div class="mt-5 space-y-2">
      <?php $__currentLoopData = $memberships; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $member): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <form method="POST" action="<?php echo e(route('company.switch')); ?>">
          <?php echo csrf_field(); ?>
          <input type="hidden" name="company" value="<?php echo e($member->company->uuid); ?>">
          <button type="submit" class="flex w-full items-center gap-3 rounded-xl border border-ink-100 bg-white px-4 py-3 text-start hover:border-brand-300">
            <span class="grid size-10 place-items-center rounded-xl bg-brand-50 text-brand-700"><?php if (isset($component)) { $__componentOriginalce262628e3a8d44dc38fd1f3965181bc = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalce262628e3a8d44dc38fd1f3965181bc = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.icon','data' => ['name' => 'building']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'building']); ?>
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
            <span class="min-w-0 flex-1">
              <span class="block truncate font-semibold"><?php echo e($member->company->name); ?></span>
              <span class="block text-xs text-ink-500"><?php echo e($member->title ?: __('Member')); ?> · <?php echo e($member->company->country); ?></span>
            </span>
            <?php if (isset($component)) { $__componentOriginalce262628e3a8d44dc38fd1f3965181bc = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalce262628e3a8d44dc38fd1f3965181bc = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.icon','data' => ['name' => 'arrow-right','class' => 'size-4 text-ink-400']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'arrow-right','class' => 'size-4 text-ink-400']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalce262628e3a8d44dc38fd1f3965181bc)): ?>
<?php $attributes = $__attributesOriginalce262628e3a8d44dc38fd1f3965181bc; ?>
<?php unset($__attributesOriginalce262628e3a8d44dc38fd1f3965181bc); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalce262628e3a8d44dc38fd1f3965181bc)): ?>
<?php $component = $__componentOriginalce262628e3a8d44dc38fd1f3965181bc; ?>
<?php unset($__componentOriginalce262628e3a8d44dc38fd1f3965181bc); ?>
<?php endif; ?>
          </button>
        </form>
      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </div>
  <?php endif; ?>
  <form method="POST" action="<?php echo e(route('logout')); ?>" class="mt-5 text-center">
    <?php echo csrf_field(); ?>
    <button type="submit" class="text-sm text-ink-500 hover:underline"><?php echo e(__('Sign out')); ?></button>
  </form>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.guest', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /home/user/WebAppForTrucks/webapp/resources/views/auth/choose-company.blade.php ENDPATH**/ ?>