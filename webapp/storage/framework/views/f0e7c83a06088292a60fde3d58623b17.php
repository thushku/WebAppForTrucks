
<?php
  $groups = [
    __('Work') => [
      ['dashboard', 'home', __('Dashboard'), 'dashboard.view', 'bg-brand-50 text-brand-700'],
      [null, 'route', __('Trips'), 'trip.view', 'bg-amber-50 text-amber-700'],
      [null, 'check', __('Approvals'), 'event.verify', 'bg-emerald-50 text-emerald-700'],
      [null, 'map', __('Live map'), 'map.view', 'bg-sky-50 text-sky-700'],
    ],
    __('Money') => [
      [null, 'receipt', __('Invoices & LR'), 'invoice.view', 'bg-violet-50 text-violet-700'],
      [null, 'ledger', __('Ledgers'), 'ledger.view', 'bg-rose-50 text-rose-700'],
      [null, 'wallet', __('Accounts'), 'accounts.view', 'bg-teal-50 text-teal-700'],
      [null, 'payroll', __('Payroll'), 'payroll.view', 'bg-orange-50 text-orange-700'],
    ],
    __('Fleet & people') => [
      [null, 'truck', __('Trucks'), 'truck.view', 'bg-blue-50 text-blue-700'],
      [null, 'folder', __('Documents'), 'document.view', 'bg-yellow-50 text-yellow-700'],
      [null, 'users', __('People'), 'people.view', 'bg-indigo-50 text-indigo-700'],
      [null, 'handshake', __('Parties'), 'party.view', 'bg-lime-50 text-lime-700'],
    ],
    __('Insight') => [
      [null, 'bar-chart', __('Reports'), 'report.view', 'bg-cyan-50 text-cyan-700'],
      [null, 'settings', __('Settings'), 'settings.company', 'bg-slate-100 text-slate-700'],
    ],
  ];
?>
<nav id="main-nav" class="-mx-1 flex-1 overflow-y-auto px-1" aria-label="<?php echo e(__('Main menu')); ?>">
  <?php $__currentLoopData = $groups; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $label => $items): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
    <?php ($visible = array_filter($items, fn ($i) => auth()->user()?->can($i[3]))); ?>
    <?php if(empty($visible)) continue; ?>
    <div class="nav-group-label"><?php echo e($label); ?></div>
    <ul class="space-y-0.5">
      <?php $__currentLoopData = $visible; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as [$route, $icon, $text, $permission, $tile]): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <li>
          <?php if($route && Route::has($route)): ?>
            <a href="<?php echo e(route($route)); ?>" class="nav-link <?php echo e(request()->routeIs($route . '*') ? 'is-active' : ''); ?>" <?php if(request()->routeIs($route . '*')): ?> aria-current="page" <?php endif; ?>>
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
<?php endif; ?></span><?php echo e($text); ?>

            </a>
          <?php else: ?>
            <span class="nav-link cursor-default opacity-60" title="<?php echo e(__('Coming in a later milestone')); ?>">
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
<?php endif; ?></span><?php echo e($text); ?>

              <span class="ms-auto rounded-md bg-ink-100 px-1.5 py-0.5 text-[10px] font-bold uppercase tracking-wide text-ink-500"><?php echo e(__('soon')); ?></span>
            </span>
          <?php endif; ?>
        </li>
      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </ul>
  <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
</nav>
<?php /**PATH /home/user/WebAppForTrucks/webapp/resources/views/partials/main-nav.blade.php ENDPATH**/ ?>