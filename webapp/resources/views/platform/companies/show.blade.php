@extends('platform.layout')
@section('title', $company->name)
@section('content')
@php
  $labels = ['trial' => __('On trial'), 'active' => __('Paying'), 'lapsed' => __('Read-only'), 'suspended' => __('Suspended')];
  $plan = $company->plan;
  $limit = fn ($used, $max, $unit = '') => $max ? __(':used of :max', ['used' => $used . $unit, 'max' => $max . $unit]) : __(':used (no limit)', ['used' => $used . $unit]);
@endphp
<div class="flex flex-wrap items-end justify-between gap-3">
  <div>
    <a href="{{ route('platform.companies') }}" class="text-sm text-brand-700 hover:underline">← {{ __('Companies') }}</a>
    <h1 class="mt-1 font-display text-2xl font-semibold">{{ $company->name }}</h1>
    <p class="text-sm text-ink-500">{{ $company->country }} · {{ $company->currency }} · {{ __('since :date', ['date' => $company->created_at->format('d M Y')]) }} · {{ $company->owner?->name }} ({{ $company->owner?->email ?? $company->owner?->mobile }})</p>
  </div>
  <span class="pill bg-ink-100 text-ink-800">{{ $labels[$company->status] ?? $company->status }}</span>
</div>
@if ($company->requestedPlan || $company->requested_coupon)
  <p class="rounded-xl bg-brand-50 px-4 py-3 text-sm text-brand-900">{{ __('Chose on the website:') }} {{ $company->requestedPlan?->name }}@if ($company->requested_coupon) · {{ __('coupon :code', ['code' => $company->requested_coupon]) }}@endif</p>
@endif
@if ($company->suspended_reason)<p class="rounded-xl bg-red-50 px-4 py-3 text-sm text-overdue">{{ __('Suspended: :reason', ['reason' => $company->suspended_reason]) }}</p>@endif

<div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
  <div class="stat"><div class="stat-label">{{ __('Plan') }}</div><div class="stat-value text-xl">{{ $plan?->name ?? __('Trial') }}</div>
    <div class="mt-1 text-xs text-ink-500">@if ($current){{ $current->ends_on ? __('until :date', ['date' => $current->ends_on->format('d M Y')]) : __('for life') }}@elseif ($company->trial_ends_at){{ __('trial until :date', ['date' => $company->trial_ends_at->format('d M Y')]) }}@endif</div></div>
  <div class="stat"><div class="stat-label">{{ __('Trucks') }}</div><div class="stat-value text-xl">{{ $limit($usage['trucks'], $plan?->max_trucks) }}</div></div>
  <div class="stat"><div class="stat-label">{{ __('Users') }}</div><div class="stat-value text-xl">{{ $limit($usage['users'], $plan?->max_users) }}</div></div>
  <div class="stat"><div class="stat-label">{{ __('Storage') }}</div><div class="stat-value text-xl">{{ $limit($usage['storage_mb'], $plan?->storage_mb, ' MB') }}</div></div>
</div>

<div class="grid gap-6 lg:grid-cols-3">
  <form method="POST" action="{{ route('platform.companies.subscribe', $company) }}" class="card space-y-4 p-6 lg:col-span-2">
    @csrf
    <h2 class="font-display text-lg font-semibold">{{ __('Put on a plan') }}</h2>
    <p class="text-sm text-ink-500">{{ __('Record a payment received (bank transfer, UPI, cheque). The running plan ends the day before; the price comes from the plan, less the coupon.') }}</p>
    <div class="grid gap-4 sm:grid-cols-2">
      <div><label class="label" for="plan_id">{{ __('Plan') }}</label>
        <select id="plan_id" name="plan_id" class="input" required>
          @foreach ($plans as $p)<option value="{{ $p->id }}" @selected(old('plan_id', $company->plan_id ?? $company->requested_plan_id) == $p->id)>{{ $p->name }} — {{ __(\App\Platform\Billing\Plan::PRICING[$p->pricing]) }}, {{ $p->currency }} {{ $p->pricing === 'lifetime' ? $p->price_lifetime : ($p->price_monthly ?? '—') . ' / ' . ($p->price_annual ?? '—') }}</option>@endforeach
        </select></div>
      <div><label class="label" for="period">{{ __('Period') }}</label>
        <select id="period" name="period" class="input">@foreach (\App\Platform\Billing\Subscription::PERIODS as $key => $label)<option value="{{ $key }}" @selected(old('period') === $key)>{{ __($label) }}</option>@endforeach</select></div>
      <div><label class="label" for="starts_on">{{ __('From') }}</label><input id="starts_on" name="starts_on" type="date" class="input" value="{{ old('starts_on', now()->toDateString()) }}" required></div>
      <div><label class="label" for="trucks">{{ __('Trucks billed') }}</label><input id="trucks" name="trucks" type="number" min="1" class="input" value="{{ old('trucks', max(1, $usage['trucks'])) }}"><span class="hint">{{ __('For a per-truck plan.') }}</span></div>
      <div><label class="label" for="coupon">{{ __('Coupon') }}</label><input id="coupon" name="coupon" class="input uppercase" value="{{ old('coupon', $company->plan_id ? null : $company->requested_coupon) }}" maxlength="30"></div>
      <div><label class="label" for="reference">{{ __('Payment reference') }}</label><input id="reference" name="reference" class="input" value="{{ old('reference') }}" maxlength="80" placeholder="{{ __('UTR, cheque no.') }}"></div>
      <div class="sm:col-span-2"><label class="label" for="notes">{{ __('Notes') }}</label><input id="notes" name="notes" class="input" value="{{ old('notes') }}" maxlength="255"></div>
    </div>
    @if ($errors->any())<ul class="space-y-1">@foreach ($errors->all() as $e)<li class="error">{{ $e }}</li>@endforeach</ul>@endif
    <button class="btn-primary" @disabled($plans->isEmpty())>{{ __('Save the plan') }}</button>
    @if ($plans->isEmpty())<p class="hint">{{ __('No plan is offered in :country yet. Add one under Plans.', ['country' => $company->country]) }}</p>@endif
  </form>

  <div class="space-y-6">
    <form method="POST" action="{{ route('platform.companies.trial', $company) }}" class="card space-y-3 p-6">
      @csrf
      <h2 class="font-display text-lg font-semibold">{{ __('Extend the trial') }}</h2>
      <div class="flex gap-2"><input name="days" type="number" min="1" max="365" value="7" class="input w-24" aria-label="{{ __('Days') }}"><span class="self-center text-sm text-ink-500">{{ __('days') }}</span></div>
      <input name="reason" class="input" placeholder="{{ __('Why (kept in the log)') }}" required minlength="3" maxlength="255" aria-label="{{ __('Reason') }}">
      <button class="btn-secondary">{{ __('Extend') }}</button>
    </form>
    @if ($company->status === 'suspended')
      <form method="POST" action="{{ route('platform.companies.resume', $company) }}" class="card space-y-3 p-6">
        @csrf
        <h2 class="font-display text-lg font-semibold">{{ __('Resume') }}</h2>
        <p class="text-sm text-ink-500">{{ __('The company goes back to what its dates say: paying, on trial or read-only.') }}</p>
        <button class="btn-primary">{{ __('Resume the company') }}</button>
      </form>
    @else
      <form method="POST" action="{{ route('platform.companies.suspend', $company) }}" class="card space-y-3 p-6" data-confirm="{{ __('Suspend :name? Nobody can sign in to it until it is resumed.', ['name' => $company->name]) }}">
        @csrf
        <h2 class="font-display text-lg font-semibold">{{ __('Suspend') }}</h2>
        <p class="text-sm text-ink-500">{{ __('For abuse or fraud. Nobody can sign in to it; nothing is deleted.') }}</p>
        <input name="reason" class="input" placeholder="{{ __('Reason (kept in the log)') }}" required minlength="5" maxlength="255" aria-label="{{ __('Reason') }}">
        <button class="btn-danger">{{ __('Suspend the company') }}</button>
      </form>
    @endif
  </div>
</div>

<section class="card overflow-x-auto">
  <h2 class="border-b border-ink-100 px-5 py-4 font-display text-lg font-semibold">{{ __('Subscriptions') }}</h2>
  <table class="table">
    <thead><tr><th>{{ __('Plan') }}</th><th>{{ __('Period') }}</th><th>{{ __('From') }}</th><th>{{ __('Until') }}</th><th class="text-end">{{ __('Amount') }}</th><th>{{ __('Coupon') }}</th><th>{{ __('Reference') }}</th><th>{{ __('Status') }}</th></tr></thead>
    <tbody>
      @forelse ($subscriptions as $s)
        <tr>
          <td class="font-medium">{{ $s->plan->name }}@if ($s->trucks) <span class="text-xs text-ink-500">× {{ $s->trucks }}</span>@endif</td>
          <td>{{ __(\App\Platform\Billing\Subscription::PERIODS[$s->period] ?? $s->period) }}</td>
          <td class="nowrap">{{ $s->starts_on->format('d M Y') }}</td>
          <td class="nowrap">{{ $s->ends_on?->format('d M Y') ?? __('For life') }}</td>
          <td class="nowrap text-end">{{ $s->currency }} {{ number_format((float) $s->amount, 2) }}@if ((float) $s->discount > 0)<div class="text-xs text-ink-500">{{ __('less :d', ['d' => number_format((float) $s->discount, 2)]) }}</div>@endif</td>
          <td>{{ $s->coupon?->code ?? '—' }}</td>
          <td>{{ $s->reference ?? '—' }}</td>
          <td>{{ __(ucfirst($s->status)) }}</td>
        </tr>
      @empty
        <tr><td colspan="8" class="text-ink-500">{{ __('No subscription yet — the company is on its trial.') }}</td></tr>
      @endforelse
    </tbody>
  </table>
</section>

<div class="grid gap-6 lg:grid-cols-2">
  <section class="card overflow-x-auto">
    <h2 class="border-b border-ink-100 px-5 py-4 font-display text-lg font-semibold">{{ __('Team') }}</h2>
    <table class="table">
      <tbody>
        @foreach ($members as $member)
          <tr><td class="font-medium">{{ $member->account?->name }}</td><td>{{ $member->account?->email }}</td><td>{{ $member->status }}</td><td class="nowrap text-xs text-ink-500">{{ $member->last_seen_at?->diffForHumans() ?? '—' }}</td></tr>
        @endforeach
      </tbody>
    </table>
  </section>
  <section class="card">
    <h2 class="border-b border-ink-100 px-5 py-4 font-display text-lg font-semibold">{{ __('Billing history') }}</h2>
    <ul class="divide-y divide-ink-100 text-sm">
      @forelse ($audit as $row)
        <li class="px-5 py-3"><div>{{ $row->summary }}</div>@if ($row->reason)<div class="text-xs text-ink-500">{{ $row->reason }}</div>@endif<div class="text-xs text-ink-400">{{ \Carbon\CarbonImmutable::parse($row->occurred_at)->format('d M Y H:i') }}</div></li>
      @empty
        <li class="px-5 py-4 text-ink-500">{{ __('Nothing yet.') }}</li>
      @endforelse
    </ul>
  </section>
</div>
@endsection
