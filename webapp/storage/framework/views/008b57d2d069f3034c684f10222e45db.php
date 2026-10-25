<?php
  $context = app(\App\Platform\Tenancy\CompanyContext::class);
  $company = $context->has() ? $context->company() : null;
  $account = auth()->user();
  $brand   = app(\App\Platform\Settings\PlatformSettings::class)->brandName();
  $rtl     = \App\Support\Locales::isRtl(app()->getLocale());
?>
<!DOCTYPE html>
<html lang="<?php echo e(str_replace('_', '-', app()->getLocale())); ?>" dir="<?php echo e($rtl ? 'rtl' : 'ltr'); ?>" class="h-full">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="<?php echo e(csrf_token()); ?>">
<title><?php echo $__env->yieldContent('title', __('Dashboard')); ?> — <?php echo e($brand); ?></title>
<?php echo \App\Support\Fonts::preloadTags(); ?>

<style><?php echo \App\Support\Fonts::linkedCss(['Noto Sans', 'Noto Sans Devanagari', 'Noto Sans Arabic']); ?></style>
<?php echo app('Illuminate\Foundation\Vite')(['resources/css/app.css', 'resources/js/app.js']); ?>
<?php echo $__env->yieldPushContent('head'); ?>
</head>
<body class="min-h-full">
<div class="nav-progress" data-nav-progress aria-hidden="true"></div>

<?php if(session()->pull('splash')): ?>
  
  <div class="splash" data-splash role="status" aria-label="<?php echo e(__('Opening your data')); ?>">
    <div class="text-center">
      <div class="mx-auto grid size-16 place-items-center rounded-2xl bg-white/15"><?php if (isset($component)) { $__componentOriginalce262628e3a8d44dc38fd1f3965181bc = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalce262628e3a8d44dc38fd1f3965181bc = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.icon','data' => ['name' => 'truck','class' => 'size-9']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'truck','class' => 'size-9']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalce262628e3a8d44dc38fd1f3965181bc)): ?>
<?php $attributes = $__attributesOriginalce262628e3a8d44dc38fd1f3965181bc; ?>
<?php unset($__attributesOriginalce262628e3a8d44dc38fd1f3965181bc); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalce262628e3a8d44dc38fd1f3965181bc)): ?>
<?php $component = $__componentOriginalce262628e3a8d44dc38fd1f3965181bc; ?>
<?php unset($__componentOriginalce262628e3a8d44dc38fd1f3965181bc); ?>
<?php endif; ?></div>
      <div class="mt-4 font-display text-2xl font-semibold"><?php echo e($brand); ?></div>
      <p class="mt-1 text-sm text-white/70"><?php echo e(__('Opening your data…')); ?></p>
      <div class="splash-bar mx-auto mt-5"><div class="splash-fill"></div></div>
      <p class="mt-4 text-xs text-white/50"><?php echo e(__('Version')); ?> <?php echo e(config('app.version')); ?> (<?php echo e(config('app.build')); ?>)</p>
    </div>
  </div>
<?php endif; ?>

<div class="min-h-screen lg:grid lg:grid-cols-[264px_1fr]">
  <aside id="sidebar" class="hidden flex-col gap-4 border-e border-ink-100 bg-white px-3.5 py-5 lg:sticky lg:top-0 lg:flex lg:h-screen">
    <a href="<?php echo e(route('dashboard')); ?>" class="flex items-center gap-3 px-2">
      <span class="grid size-10 place-items-center rounded-xl bg-brand-600 text-white shadow-[var(--shadow-card)]"><?php if (isset($component)) { $__componentOriginalce262628e3a8d44dc38fd1f3965181bc = $component; } ?>
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
      <span class="min-w-0">
        <span class="block font-display text-lg font-semibold leading-tight"><?php echo e($brand); ?></span>
        <span class="block truncate text-xs text-ink-500"><?php echo e($company?->name); ?></span>
      </span>
    </a>

    <?php echo $__env->make('partials.main-nav', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

    <?php if($company?->onTrial()): ?>
      <?php ($days = $company->trialDaysLeft()); ?>
      <div class="rounded-2xl border border-ink-100 bg-ink-50 p-3.5">
        <div class="flex items-center justify-between text-sm">
          <span class="font-semibold"><?php echo e(__('Free trial')); ?></span>
          <span class="pill bg-brand-50 text-brand-700"><?php echo e($days); ?> <?php echo e(trans_choice('day|days', $days)); ?> <?php echo e(__('left')); ?></span>
        </div>
        <p class="mt-2 text-xs text-ink-500"><?php echo e(__('Choose a plan any time before it ends — nothing is lost.')); ?></p>
      </div>
    <?php endif; ?>

    
    <p class="mt-auto px-2 text-[11px] text-ink-400" data-version><?php echo e(__('Version')); ?> <?php echo e(config('app.version')); ?> (<?php echo e(config('app.build')); ?>)</p>
  </aside>

  <div class="flex min-h-screen flex-col">
    <header class="fl-topbar sticky top-0 z-20 flex items-center gap-3 border-b border-ink-100 bg-white/85 px-4 py-3 backdrop-blur sm:px-6 lg:px-8">
      <button type="button" class="btn-ghost -ms-2 px-2 lg:hidden" data-toggle="sidebar" aria-controls="sidebar" aria-expanded="false" aria-label="<?php echo e(__('Menu')); ?>">
        <?php if (isset($component)) { $__componentOriginalce262628e3a8d44dc38fd1f3965181bc = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalce262628e3a8d44dc38fd1f3965181bc = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.icon','data' => ['name' => 'menu']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'menu']); ?>
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
      <h1 class="min-w-0 flex-1 truncate font-display text-lg font-semibold"><?php echo $__env->yieldContent('title', __('Dashboard')); ?></h1>

      <div class="relative" data-menu>
        <button type="button" data-menu-button class="flex items-center gap-2 rounded-full border border-ink-100 bg-white py-1 ps-1 pe-3 text-sm hover:border-brand-300" aria-haspopup="menu" aria-expanded="false">
          <span class="grid size-8 place-items-center rounded-full bg-brand-100 font-display text-xs font-bold text-brand-800"><?php echo e($account?->initials()); ?></span>
          <span class="hidden max-w-[10rem] truncate font-medium sm:block"><?php echo e($account?->name); ?></span>
        </button>
        <div data-menu-panel class="card absolute end-0 z-30 mt-2 hidden w-64 p-2" role="menu">
          <div class="px-3 py-2">
            <div class="truncate text-sm font-semibold"><?php echo e($account?->name); ?></div>
            <div class="truncate text-xs text-ink-500"><?php echo e($account?->email ?? $account?->mobile); ?></div>
          </div>
          <div class="my-1 border-t border-ink-100"></div>
          <?php if($account && $account->activeMemberships()->count() > 1): ?>
            <a href="<?php echo e(route('company.choose')); ?>" class="nav-link" role="menuitem"><?php if (isset($component)) { $__componentOriginalce262628e3a8d44dc38fd1f3965181bc = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalce262628e3a8d44dc38fd1f3965181bc = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.icon','data' => ['name' => 'switch']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'switch']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalce262628e3a8d44dc38fd1f3965181bc)): ?>
<?php $attributes = $__attributesOriginalce262628e3a8d44dc38fd1f3965181bc; ?>
<?php unset($__attributesOriginalce262628e3a8d44dc38fd1f3965181bc); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalce262628e3a8d44dc38fd1f3965181bc)): ?>
<?php $component = $__componentOriginalce262628e3a8d44dc38fd1f3965181bc; ?>
<?php unset($__componentOriginalce262628e3a8d44dc38fd1f3965181bc); ?>
<?php endif; ?> <?php echo e(__('Switch company')); ?></a>
          <?php endif; ?>
          <form method="POST" action="<?php echo e(route('logout')); ?>">
            <?php echo csrf_field(); ?>
            <button type="submit" class="nav-link w-full" role="menuitem"><?php if (isset($component)) { $__componentOriginalce262628e3a8d44dc38fd1f3965181bc = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalce262628e3a8d44dc38fd1f3965181bc = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.icon','data' => ['name' => 'log-out']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'log-out']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginalce262628e3a8d44dc38fd1f3965181bc)): ?>
<?php $attributes = $__attributesOriginalce262628e3a8d44dc38fd1f3965181bc; ?>
<?php unset($__attributesOriginalce262628e3a8d44dc38fd1f3965181bc); ?>
<?php endif; ?>
<?php if (isset($__componentOriginalce262628e3a8d44dc38fd1f3965181bc)): ?>
<?php $component = $__componentOriginalce262628e3a8d44dc38fd1f3965181bc; ?>
<?php unset($__componentOriginalce262628e3a8d44dc38fd1f3965181bc); ?>
<?php endif; ?> <?php echo e(__('Sign out')); ?></button>
          </form>
        </div>
      </div>
    </header>

    <main class="flex-1 space-y-6 px-4 py-6 sm:px-6 lg:px-8">
      <?php if($company?->isReadOnly()): ?>
        <div class="flex items-center gap-3 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900" role="status">
          <?php if (isset($component)) { $__componentOriginalce262628e3a8d44dc38fd1f3965181bc = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalce262628e3a8d44dc38fd1f3965181bc = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.icon','data' => ['name' => 'lock','class' => 'size-5 shrink-0']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('icon'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'lock','class' => 'size-5 shrink-0']); ?>
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
          <p><?php echo e(__('This company is read-only because its plan has ended. Everything is kept; renew to make changes.')); ?></p>
        </div>
      <?php endif; ?>
      <?php echo $__env->yieldContent('content'); ?>
    </main>
  </div>

  <div class="pointer-events-none fixed inset-x-4 top-[4.25rem] z-30 flex flex-col items-end gap-2 sm:inset-x-auto sm:end-6 sm:w-[30rem] [&_[data-flash]]:pointer-events-auto [&_[data-flash]]:w-full">
    <?php echo $__env->make('partials.flash', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
  </div>
</div>
</body>
</html>
<?php /**PATH /home/user/WebAppForTrucks/webapp/resources/views/layouts/app.blade.php ENDPATH**/ ?>