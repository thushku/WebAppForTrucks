@extends('platform.layout')
@section('title', 'Plans')
@section('content')
@php
  $label = fn ($plan) => $plan->name . ($plan->country ? ' (' . $plan->country . ')' : '');
  $price = fn ($plan) => ! (float) $plan->price_monthly && ! (float) $plan->price_annual && ! (float) $plan->price_lifetime ? __('Free') : ($plan->pricing === 'lifetime'
    ? __(':cur :p once', ['cur' => $plan->currency, 'p' => number_format((float) $plan->price_lifetime, 2)])
    : collect([$plan->price_monthly !== null ? __(':cur :p a month', ['cur' => $plan->currency, 'p' => number_format((float) $plan->price_monthly, 2)]) : null,
               $plan->price_annual !== null ? __(':cur :p a year', ['cur' => $plan->currency, 'p' => number_format((float) $plan->price_annual, 2)]) : null])->filter()->join(' · ') . ($plan->pricing === 'per_truck' ? ' ' . __('per truck') : ''));
@endphp
<div>
  <h1 class="font-display text-2xl font-semibold">{{ __('Plans and coupons') }}</h1>
  <p class="text-sm text-ink-500">{{ __('Everything a company can buy is set here. A plan in use is switched off, never deleted, so old subscriptions keep their name and price.') }}</p>
</div>
@if ($errors->any())<ul class="card space-y-1 p-4">@foreach ($errors->all() as $e)<li class="error">{{ $e }}</li>@endforeach</ul>@endif

<section class="card overflow-x-auto">
  <table class="table">
    <thead><tr><th>{{ __('Plan') }}</th><th>{{ __('Country') }}</th><th>{{ __('Price') }}</th><th>{{ __('Limits') }}</th><th class="text-end">{{ __('Companies on it') }}</th><th>{{ __('State') }}</th></tr></thead>
    <tbody>
      @forelse ($plans as $plan)
        <tr>
          <td class="font-medium">{{ $plan->name }} <span class="text-xs text-ink-500">{{ $plan->code }}</span>@if ($plan->is_student) <span class="pill bg-violet-50 text-violet-800">{{ __('Student') }}</span>@endif</td>
          <td>{{ $plan->country ?? __('All') }}</td>
          <td>{{ $price($plan) }}</td>
          <td class="text-xs text-ink-600">{{ __(':t trucks · :u users · :s MB', ['t' => $plan->max_trucks ?? '∞', 'u' => $plan->max_users ?? '∞', 's' => $plan->storage_mb ?? '∞']) }}</td>
          <td class="text-end">{{ $plan->subscriptions_count }}</td>
          <td>{{ $plan->active ? ($plan->is_public ? __('Offered') : __('Offered, hidden')) : __('Off') }}</td>
        </tr>
        <tr>
          <td colspan="6" class="bg-ink-50/40">
            <details @if ((string) old('_plan') === (string) $plan->id) open @endif>
              <summary class="cursor-pointer text-sm text-brand-700">{{ __('Change :name', ['name' => $plan->name]) }}</summary>
              <form method="POST" action="{{ route('platform.plans.update', $plan) }}" class="mt-4 space-y-4">
                @csrf @method('PUT')
                @include('platform._plan-form', ['plan' => $plan])
                <button class="btn-primary">{{ __('Save the plan') }}</button>
              </form>
            </details>
          </td>
        </tr>
      @empty
        <tr><td colspan="6" class="text-ink-500">{{ __('No plan yet. Companies stay on their trial until there is one.') }}</td></tr>
      @endforelse
    </tbody>
  </table>
</section>

<details class="card p-6" @if (old('_plan') === 'new') open @endif>
  <summary class="cursor-pointer font-display text-lg font-semibold">{{ __('New plan') }}</summary>
  <form method="POST" action="{{ route('platform.plans.store') }}" class="mt-4 space-y-4">
    @csrf
    @include('platform._plan-form', ['plan' => null])
    <button class="btn-primary">{{ __('Create the plan') }}</button>
  </form>
</details>

<section class="card overflow-x-auto">
  <h2 class="border-b border-ink-100 px-5 py-4 font-display text-lg font-semibold">{{ __('Coupons') }}</h2>
  <table class="table">
    <thead><tr><th>{{ __('Code') }}</th><th>{{ __('Off') }}</th><th>{{ __('Valid') }}</th><th>{{ __('Used') }}</th><th>{{ __('Plans') }}</th><th></th></tr></thead>
    <tbody>
      @forelse ($coupons as $coupon)
        <tr>
          <td class="font-mono font-medium">{{ $coupon->code }}</td>
          <td>{{ $coupon->kind === 'percent' ? rtrim(rtrim(number_format((float) $coupon->value, 2), '0'), '.') . ' %' : $coupon->currency . ' ' . number_format((float) $coupon->value, 2) }}</td>
          <td class="nowrap">{{ $coupon->valid_from?->format('d M Y') ?? '…' }} – {{ $coupon->valid_until?->format('d M Y') ?? '…' }}</td>
          <td>{{ $coupon->max_uses ? __(':n of :m', ['n' => $coupon->used_count, 'm' => $coupon->max_uses]) : $coupon->used_count }}</td>
          <td class="text-xs">{{ $coupon->plan_ids ? $plans->whereIn('id', $coupon->plan_ids)->map($label)->join(', ') : __('Every plan') }}</td>
          <td class="text-end"><form method="POST" action="{{ route('platform.coupons.toggle', $coupon) }}">@csrf<button class="btn-secondary px-3 py-1.5">{{ $coupon->active ? __('Switch off') : __('Switch on') }}</button></form></td>
        </tr>
      @empty
        <tr><td colspan="6" class="text-ink-500">{{ __('No coupon yet.') }}</td></tr>
      @endforelse
    </tbody>
  </table>
  <form method="POST" action="{{ route('platform.coupons.store') }}" class="grid gap-4 border-t border-ink-100 p-5 sm:grid-cols-4">
    @csrf
    <div><label class="label" for="c-code">{{ __('Code') }}</label><input id="c-code" name="code" class="input uppercase" value="{{ old('code') }}" maxlength="30" required></div>
    <div><label class="label" for="c-kind">{{ __('Kind') }}</label><select id="c-kind" name="kind" class="input"><option value="percent">{{ __('Percentage off') }}</option><option value="fixed" @selected(old('kind') === 'fixed')>{{ __('Amount off') }}</option></select></div>
    <div><label class="label" for="c-value">{{ __('Value') }}</label><input id="c-value" name="value" type="number" step="0.01" min="0.01" class="input" value="{{ old('value') }}" required></div>
    <div><label class="label" for="c-currency">{{ __('Currency (amount off)') }}</label><select id="c-currency" name="currency" class="input"><option value="">—</option>@foreach (['INR', 'AED', 'USD'] as $cur)<option @selected(old('currency') === $cur)>{{ $cur }}</option>@endforeach</select></div>
    <div><label class="label" for="c-from">{{ __('From') }}</label><input id="c-from" name="valid_from" type="date" class="input" value="{{ old('valid_from') }}"></div>
    <div><label class="label" for="c-until">{{ __('Until') }}</label><input id="c-until" name="valid_until" type="date" class="input" value="{{ old('valid_until') }}"></div>
    <div><label class="label" for="c-max">{{ __('Most uses') }}</label><input id="c-max" name="max_uses" type="number" min="1" class="input" value="{{ old('max_uses') }}" placeholder="{{ __('No limit') }}"></div>
    <div><label class="label" for="c-plans">{{ __('Only for') }}</label><select id="c-plans" name="plan_ids[]" class="input" multiple size="3">@foreach ($plans as $plan)<option value="{{ $plan->id }}">{{ $label($plan) }}</option>@endforeach</select></div>
    <div class="sm:col-span-4"><button class="btn-primary">{{ __('Create the coupon') }}</button></div>
  </form>
</section>
@endsection
