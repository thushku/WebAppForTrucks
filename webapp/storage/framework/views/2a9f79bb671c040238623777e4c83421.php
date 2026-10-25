<?php $__env->startSection('title', 'Overview'); ?>
<?php $__env->startSection('content'); ?>
<div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
  <?php $__currentLoopData = [[__('Companies'), $counts['companies']], [__('On trial'), $counts['trial']], [__('Paying'), $counts['active']], [__('People'), $counts['accounts']], [__('Failed sign-ins (24 h)'), $failures]]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as [$label, $value]): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
    <div class="stat"><div class="stat-label"><?php echo e($label); ?></div><div class="stat-value"><?php echo e(number_format($value)); ?></div></div>
  <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
</div>

<section class="card">
  <h2 class="border-b border-ink-100 px-5 py-4 font-display text-lg font-semibold"><?php echo e(__('Locked logins')); ?></h2>
  <?php if($locked->isEmpty()): ?>
    <p class="px-5 py-6 text-sm text-ink-500"><?php echo e(__('No login is locked.')); ?></p>
  <?php else: ?>
    <table class="table">
      <thead><tr><th><?php echo e(__('Person')); ?></th><th><?php echo e(__('Login')); ?></th><th><?php echo e(__('Locked')); ?></th><th></th></tr></thead>
      <tbody>
        <?php $__currentLoopData = $locked; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $account): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
          <tr>
            <td class="font-medium"><?php echo e($account->name); ?></td>
            <td><?php echo e($account->email ?? $account->mobile); ?></td>
            <td><?php echo e($account->locked_hard_at ? __('Until unlocked') : __('Until :time', ['time' => $account->locked_until?->format('d M H:i')])); ?></td>
            <td class="text-end">
              <form method="POST" action="<?php echo e(route('platform.accounts.unlock', $account)); ?>"><?php echo csrf_field(); ?><button class="btn-secondary px-3 py-1.5"><?php echo e(__('Unlock')); ?></button></form>
            </td>
          </tr>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
      </tbody>
    </table>
  <?php endif; ?>
</section>

<section class="card overflow-x-auto">
  <h2 class="border-b border-ink-100 px-5 py-4 font-display text-lg font-semibold"><?php echo e(__('Companies')); ?></h2>
  <table class="table">
    <thead><tr><th><?php echo e(__('Company')); ?></th><th><?php echo e(__('Country')); ?></th><th><?php echo e(__('Status')); ?></th><th><?php echo e(__('Trial ends')); ?></th><th><?php echo e(__('Owner')); ?></th><th><?php echo e(__('Since')); ?></th></tr></thead>
    <tbody>
      <?php $__empty_1 = true; $__currentLoopData = $companies; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $company): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
        <tr>
          <td class="font-medium"><?php echo e($company->name); ?></td>
          <td><?php echo e($company->country); ?></td>
          <td><span class="pill bg-ink-100 text-ink-700"><?php echo e($company->status); ?></span></td>
          <td class="nowrap"><?php echo e($company->trial_ends_at?->format('d M Y') ?? '—'); ?></td>
          <td><?php echo e($company->owner?->email); ?></td>
          <td class="nowrap"><?php echo e($company->created_at->format('d M Y')); ?></td>
        </tr>
      <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
        <tr><td colspan="6" class="text-ink-500"><?php echo e(__('No companies yet.')); ?></td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</section>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('platform.layout', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /home/user/WebAppForTrucks/webapp/resources/views/platform/home.blade.php ENDPATH**/ ?>