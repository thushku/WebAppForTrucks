@extends('layouts.settings')
@section('title', __('Plan and billing'))
@section('settings')
@php
  $style = $company->country === 'IN' ? 'in' : 'us';
  $money = fn ($amount, $currency) => \App\Support\Money::format($amount, $currency, $style);
  $trucks = max(1, $usage['trucks']);
  $meters = [
      ['trucks', __('Trucks'), $usage['trucks'], $plan?->max_trucks, ''],
      ['users', __('Team members'), $usage['users'], $plan?->max_users, ''],
      ['storage', __('Storage'), $usage['storage_mb'], $plan?->storage_mb, ' MB'],
  ];
  $ask = function (?\App\Platform\Billing\Plan $p) use ($company) {
      return $p ? __('Please move :company (:id) to the :plan plan.', ['company' => $company->name, 'id' => substr($company->uuid, 0, 8), 'plan' => $p->name])
                : __('Please renew the plan of :company (:id).', ['company' => $company->name, 'id' => substr($company->uuid, 0, 8)]);
  };
  $digits = fn ($n) => preg_replace('/\D+/', '', (string) $n);
@endphp

<section class="card space-y-5 p-6">
  <div class="flex flex-wrap items-start justify-between gap-3">
    <div>
      <p class="text-sm text-ink-500">{{ __('Your plan') }}</p>
      <h2 class="font-display text-2xl font-semibold">{{ $plan?->name ?? __('Free trial') }}</h2>
      <p class="mt-1 text-sm text-ink-700">
        @if ($company->isReadOnly())
          {{ __('Ended — the company is read-only. Everything is kept; renew to make changes again.') }}
        @elseif ($current)
          {{ $current->ends_on ? __('Paid until :date.', ['date' => $current->ends_on->translatedFormat('j F Y')]) : __('Paid for life.') }}
        @elseif ($company->onTrial())
          {{ trans_choice('The trial ends on :date (:count day left).|The trial ends on :date (:count days left).', $company->trialDaysLeft(), ['date' => $company->trial_ends_at->translatedFormat('j F Y')]) }}
        @endif
      </p>
    </div>
    @if ($company->isReadOnly())<span class="pill bg-amber-50 text-amber-900"><x-icon name="lock" class="size-3.5" /> {{ __('Read-only') }}</span>@endif
  </div>

  <dl class="grid gap-4 sm:grid-cols-3">
    @foreach ($meters as [$key, $label, $used, $max, $unit])
      @php
        $share = $max ? min(100, (int) round($used / $max * 100)) : null;
        $tone = $share === null ? 'bg-brand-500' : ($share >= 100 ? 'bg-overdue' : ($share >= 80 ? 'bg-partial' : 'bg-brand-500'));
      @endphp
      <div class="rounded-2xl border border-ink-100 p-4">
        <dt class="text-sm text-ink-500">{{ $label }}</dt>
        <dd class="mt-1">
          <span class="font-display text-xl font-semibold">{{ $used }}{{ $unit }}</span>
          <span class="text-sm text-ink-500">{{ $max ? __('of :max', ['max' => $max . $unit]) : __('no limit') }}</span>
          @if ($max)
            <div class="mt-2 h-2 rounded-full bg-ink-100" role="meter" aria-label="{{ $label }}" aria-valuemin="0" aria-valuemax="{{ $max }}" aria-valuenow="{{ $used }}">
              <div class="h-2 rounded-full {{ $tone }}" style="width: {{ max(2, $share) }}%"></div>
            </div>
            @if ($share >= 100)<p class="mt-1 text-xs text-overdue">{{ __('Full — a bigger plan has room for more.') }}</p>@endif
          @endif
        </dd>
      </div>
    @endforeach
  </dl>
</section>

<section class="space-y-3">
  <h2 class="font-display text-lg font-semibold">{{ __('Plans') }}</h2>
  @if ($plans->isEmpty())
    <p class="card p-5 text-sm text-ink-500">{{ __('Plans are being set up. Contact :brand to continue after the trial.', ['brand' => $brand]) }}</p>
  @else
    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
      @foreach ($plans as $p)
        @php
          $mine = $plan && $plan->id === $p->id;
          $prices = $p->pricing === 'lifetime' ? [[__('once'), $p->price('lifetime')]] : array_values(array_filter([
              $p->price_monthly !== null ? [__('a month'), $p->price('monthly', $trucks)] : null,
              $p->price_annual !== null ? [__('a year'), $p->price('annual', $trucks)] : null,
          ]));
          $free = collect($prices)->every(fn ($row) => $row[1] <= 0);
        @endphp
        <article class="card flex flex-col gap-3 p-5 {{ $mine ? 'ring-2 ring-brand-400' : '' }}">
          <div class="flex items-start justify-between gap-2">
            <h3 class="font-display text-lg font-semibold">{{ $p->name }}</h3>
            @if ($mine)<span class="pill bg-brand-50 text-brand-700">{{ __('Your plan') }}</span>@elseif ($p->is_student)<span class="pill bg-violet-50 text-violet-800">{{ __('Student') }}</span>@endif
          </div>
          @if ($p->description)<p class="text-sm text-ink-600">{{ $p->description }}</p>@endif
          <div class="space-y-0.5">
            @if ($free)
              <p class="font-display text-xl font-semibold">{{ __('Free') }}</p>
            @else
              @foreach ($prices as [$per, $amount])
                <p><span class="money font-display text-xl font-semibold">{{ $money($amount, $p->currency) }}</span> <span class="text-sm text-ink-500">{{ $per }}</span></p>
              @endforeach
            @endif
            @if ($p->pricing === 'per_truck' && ! $free)<p class="text-xs text-ink-500">{{ trans_choice('For your :count truck (:each each).|For your :count trucks (:each each).', $trucks, ['each' => $money($p->price_monthly ?? $p->price_annual, $p->currency)]) }}</p>@endif
          </div>
          <ul class="space-y-1 text-sm text-ink-700">
            <li>{{ $p->max_trucks ? trans_choice('Up to :count truck|Up to :count trucks', $p->max_trucks) : __('Any number of trucks') }}</li>
            <li>{{ $p->max_users ? trans_choice('Up to :count team member|Up to :count team members', $p->max_users) : __('Any number of team members') }}</li>
            @if ($p->storage_mb)<li>{{ __(':mb MB for documents and photos', ['mb' => number_format($p->storage_mb)]) }}</li>@endif
            <li>{{ empty($p->modules) ? __('Every part of the app') : collect($p->modules)->map(fn ($m) => __(\App\Platform\Billing\Plan::MODULES[$m] ?? $m))->join(', ') }}</li>
          </ul>
          @if (! $mine || $company->isReadOnly())
            <div class="mt-auto flex flex-wrap gap-2 pt-2">
              @if ($contact['whatsapp'])<a class="btn-primary" target="_blank" rel="noopener" href="https://wa.me/{{ $digits($contact['whatsapp']) }}?text={{ rawurlencode($ask($p)) }}">{{ __('Ask on WhatsApp') }}</a>@endif
              @if ($contact['email'])<a class="btn-secondary" href="mailto:{{ $contact['email'] }}?subject={{ rawurlencode($ask($p)) }}&body={{ rawurlencode($ask($p)) }}">{{ __('Ask by email') }}</a>@endif
            </div>
          @endif
        </article>
      @endforeach
    </div>
  @endif
  <p class="text-sm text-ink-500">
    {{ __('Pay :brand by bank transfer or UPI; your plan starts as soon as the payment is recorded. Online card payment is coming.', ['brand' => $brand]) }}
    @if ($contact['phone']) {{ __('Phone: :phone.', ['phone' => $contact['phone']]) }}@endif
    @if ($contact['email']) {{ __('Email: :email.', ['email' => $contact['email']]) }}@endif
  </p>
</section>

@if ($paid->isNotEmpty())
  <section class="card overflow-x-auto">
    <h2 class="border-b border-ink-100 px-5 py-4 font-display text-lg font-semibold">{{ __('Payments') }}</h2>
    <table class="table">
      <thead><tr><th>{{ __('Plan') }}</th><th>{{ __('From') }}</th><th>{{ __('Until') }}</th><th class="text-end">{{ __('Paid') }}</th><th>{{ __('Reference') }}</th></tr></thead>
      <tbody>
        @foreach ($paid as $s)
          <tr>
            <td class="font-medium">{{ $s->plan->name }}</td>
            <td class="nowrap">{{ $s->starts_on->format('d M Y') }}</td>
            <td class="nowrap">{{ $s->ends_on?->format('d M Y') ?? __('For life') }}</td>
            <td class="nowrap text-end money">{{ $money($s->amount, $s->currency) }}</td>
            <td>{{ $s->reference ?? '—' }}</td>
          </tr>
        @endforeach
      </tbody>
    </table>
  </section>
@endif
@endsection
