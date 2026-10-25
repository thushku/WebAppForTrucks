{{-- One plan's fields, for "New plan" and for each plan's edit panel. --}}
@php
  $mine = old('_plan') !== null && (string) old('_plan') === (string) ($plan?->id ?? 'new');
  $v = fn ($key, $default = null) => $mine ? old($key, $default) : ($plan?->{$key} ?? $default);
  // A box left unticked is not sent: after a refused save, absent means off.
  $ticked = fn ($key, $default) => $mine ? (bool) old($key) : (bool) ($plan?->{$key} ?? $default);
  $id = fn ($key) => ($plan?->id ?? 'new') . '-' . $key;
  $modules = (array) ($v('modules') ?? []);
@endphp
<input type="hidden" name="_plan" value="{{ $plan?->id ?? 'new' }}">
<div class="grid gap-4 sm:grid-cols-3">
  <div><label class="label" for="{{ $id('name') }}">{{ __('Name') }}</label><input id="{{ $id('name') }}" name="name" class="input" value="{{ $v('name') }}" maxlength="80" required></div>
  <div><label class="label" for="{{ $id('code') }}">{{ __('Code') }}</label><input id="{{ $id('code') }}" name="code" class="input" value="{{ $v('code') }}" maxlength="30" pattern="[a-z0-9_\-]+" required><span class="hint">{{ __('Small letters, digits, - and _.') }}</span></div>
  <div><label class="label" for="{{ $id('sort_order') }}">{{ __('Order') }}</label><input id="{{ $id('sort_order') }}" name="sort_order" type="number" min="0" max="999" class="input" value="{{ $v('sort_order', 0) }}"></div>
  <div class="sm:col-span-3"><label class="label" for="{{ $id('description') }}">{{ __('Description') }}</label><input id="{{ $id('description') }}" name="description" class="input" value="{{ $v('description') }}" maxlength="255"></div>
  <div><label class="label" for="{{ $id('country') }}">{{ __('Country') }}</label>
    <select id="{{ $id('country') }}" name="country" class="input"><option value="">{{ __('Every country') }}</option>@foreach (['IN' => __('India'), 'AE' => __('United Arab Emirates')] as $code => $name)<option value="{{ $code }}" @selected($v('country') === $code)>{{ $name }}</option>@endforeach</select></div>
  <div><label class="label" for="{{ $id('currency') }}">{{ __('Currency') }}</label>
    <select id="{{ $id('currency') }}" name="currency" class="input">@foreach (['INR', 'AED', 'USD'] as $cur)<option @selected($v('currency', 'INR') === $cur)>{{ $cur }}</option>@endforeach</select></div>
  <div><label class="label" for="{{ $id('pricing') }}">{{ __('Priced') }}</label>
    <select id="{{ $id('pricing') }}" name="pricing" class="input">@foreach (\App\Platform\Billing\Plan::PRICING as $key => $label)<option value="{{ $key }}" @selected($v('pricing', 'per_truck') === $key)>{{ __($label) }}</option>@endforeach</select></div>
  <div><label class="label" for="{{ $id('price_monthly') }}">{{ __('Monthly price') }}</label><input id="{{ $id('price_monthly') }}" name="price_monthly" type="number" step="0.01" min="0" class="input" value="{{ $v('price_monthly') }}"></div>
  <div><label class="label" for="{{ $id('price_annual') }}">{{ __('Annual price') }}</label><input id="{{ $id('price_annual') }}" name="price_annual" type="number" step="0.01" min="0" class="input" value="{{ $v('price_annual') }}"></div>
  <div><label class="label" for="{{ $id('price_lifetime') }}">{{ __('Lifetime price') }}</label><input id="{{ $id('price_lifetime') }}" name="price_lifetime" type="number" step="0.01" min="0" class="input" value="{{ $v('price_lifetime') }}"></div>
  <div><label class="label" for="{{ $id('max_trucks') }}">{{ __('Most trucks') }}</label><input id="{{ $id('max_trucks') }}" name="max_trucks" type="number" min="1" class="input" value="{{ $v('max_trucks') }}" placeholder="{{ __('No limit') }}"></div>
  <div><label class="label" for="{{ $id('max_users') }}">{{ __('Most users') }}</label><input id="{{ $id('max_users') }}" name="max_users" type="number" min="1" class="input" value="{{ $v('max_users') }}" placeholder="{{ __('No limit') }}"></div>
  <div><label class="label" for="{{ $id('storage_mb') }}">{{ __('Storage (MB)') }}</label><input id="{{ $id('storage_mb') }}" name="storage_mb" type="number" min="1" class="input" value="{{ $v('storage_mb') }}" placeholder="{{ __('No limit') }}"></div>
</div>
<fieldset>
  <legend class="label">{{ __('Modules included (none ticked: all)') }}</legend>
  <div class="flex flex-wrap gap-2">
    @foreach (\App\Platform\Billing\Plan::MODULES as $key => $label)
      <label class="flex items-center gap-2 rounded-xl border border-ink-100 bg-white px-3 py-1.5 text-sm"><input type="checkbox" name="modules[]" value="{{ $key }}" class="size-4" @checked(in_array($key, $modules, true))> {{ __($label) }}</label>
    @endforeach
  </div>
</fieldset>
<div class="flex flex-wrap gap-4 text-sm">
  <label class="flex items-center gap-2"><input type="checkbox" name="active" value="1" class="size-4" @checked($ticked('active', true))> {{ __('Offered') }}</label>
  <label class="flex items-center gap-2"><input type="checkbox" name="is_public" value="1" class="size-4" @checked($ticked('is_public', true))> {{ __('Shown on the website and the billing page') }}</label>
  <label class="flex items-center gap-2"><input type="checkbox" name="is_student" value="1" class="size-4" @checked($ticked('is_student', false))> {{ __('Student plan') }}</label>
</div>
