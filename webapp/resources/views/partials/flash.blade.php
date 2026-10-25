@if (session('status'))
  <div data-flash class="flex items-start gap-3 rounded-xl border border-brand-200 bg-brand-50 px-4 py-3 text-sm text-brand-800" role="status">
    <x-icon name="check" class="size-5 shrink-0 text-brand-600" />
    <div class="flex-1">{{ session('status') }}</div>
    <button type="button" data-dismiss class="text-brand-600/70 hover:text-brand-800" aria-label="{{ __('Dismiss') }}"><x-icon name="x" class="size-4" /></button>
  </div>
@endif
@if (session('error'))
  <div data-flash class="flex items-start gap-3 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" role="alert">
    <x-icon name="alert" class="size-5 shrink-0 text-overdue" />
    <div class="flex-1">{{ session('error') }}</div>
    <button type="button" data-dismiss class="text-red-600/70 hover:text-red-800" aria-label="{{ __('Dismiss') }}"><x-icon name="x" class="size-4" /></button>
  </div>
@endif
