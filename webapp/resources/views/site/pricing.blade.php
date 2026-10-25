@extends('layouts.site')
@section('title', __('Pricing'))
@section('description', __('Simple plans for truck owners and transport companies. Free trial, no card. Monthly, yearly or once for life.'))
@section('content')
@php
  $style = $country === 'IN' ? 'in' : 'us';
  // Whole amounts without the ".00": ₹499, not ₹499.00.
  $money = fn ($amount, $currency) => preg_replace('/\.00$/', '', \App\Support\Money::format($amount, $currency, $style));
  $coupon = $table['coupon'];
@endphp
<section class="site-hero">
  <div class="mx-auto max-w-7xl px-4 pb-24 pt-14 text-center sm:px-6">
    <span class="site-kicker">{{ __('Pricing') }}</span>
    <h1 class="mx-auto mt-4 max-w-3xl font-display text-4xl font-semibold leading-tight sm:text-5xl">{{ __('Start free. Pay when it pays for itself.') }}</h1>
    <p class="mx-auto mt-4 max-w-2xl text-lg text-white/80">{{ __(':days days with every feature and no card. Then choose a plan — monthly, yearly or once for life.', ['days' => $trialDays]) }}</p>
    <div class="mt-8 inline-flex rounded-full bg-white/10 p-1 text-sm" role="group" aria-label="{{ __('Country') }}">
      @foreach (\App\Platform\Website\Pricing::COUNTRIES as $code => $name)
        <a href="{{ route('site.pricing', array_filter(['country' => $code, 'coupon' => request('coupon')])) }}" class="rounded-full px-4 py-2 font-semibold {{ $country === $code ? 'bg-white text-ink-900' : 'text-white/85 hover:bg-white/10' }}" @if ($country === $code) aria-current="true" @endif>{{ __($name) }}</a>
      @endforeach
    </div>
  </div>
</section>

<section class="mx-auto -mt-12 max-w-7xl px-4 pb-20 sm:px-6">
  <input type="radio" name="period" id="period-monthly" class="sr-only" checked>
  <input type="radio" name="period" id="period-annual" class="sr-only">
  <div>
    <div class="relative z-10 flex flex-wrap items-center justify-between gap-4 rounded-3xl border border-ink-100 bg-white p-4 shadow-[var(--shadow-card)]">
      <div class="inline-flex rounded-full bg-ink-100 p-1" role="group" aria-label="{{ __('Billing period') }}">
        <label for="period-monthly" class="period-tab">{{ __('Monthly') }}</label>
        <label for="period-annual" class="period-tab">{{ __('Yearly') }} @if (collect($table['plans'])->max('saves'))<span class="ms-1 rounded-full bg-emerald-100 px-1.5 py-0.5 text-[11px] text-emerald-800">{{ __('save up to :p%', ['p' => collect($table['plans'])->max('saves')]) }}</span>@endif</label>
      </div>
      <form method="GET" action="{{ route('site.pricing') }}" class="flex flex-wrap items-center gap-2">
        <input type="hidden" name="country" value="{{ $country }}">
        <label for="coupon" class="text-sm font-medium text-ink-700">{{ __('Have a coupon?') }}</label>
        <input id="coupon" name="coupon" value="{{ request('coupon') }}" maxlength="30" class="input w-40 uppercase" autocomplete="off" @if ($table['couponError']) aria-invalid="true" aria-describedby="coupon-error" @endif>
        <button class="btn-secondary">{{ __('Apply') }}</button>
      </form>
    </div>
    @if ($table['couponError'])
      <p id="coupon-error" class="error mt-3 text-center">{{ $table['couponError'] }}</p>
    @elseif ($coupon)
      <p class="mt-3 text-center text-sm font-medium text-emerald-700" role="status">
        <x-icon name="check" class="inline size-4" />
        {{ $coupon->kind === 'percent' ? __(':code: :p% off the plans marked below.', ['code' => $coupon->code, 'p' => rtrim(rtrim(number_format((float) $coupon->value, 2), '0'), '.')]) : __(':code: :amount off your first bill on the plans marked below.', ['code' => $coupon->code, 'amount' => $money($coupon->value, $coupon->currency ?? ($country === 'IN' ? 'INR' : 'AED'))]) }}
      </p>
    @endif

    @if (empty($table['plans']))
      <div class="site-card mt-8 text-center">
        <h2 class="font-display text-xl font-semibold">{{ __('Plans for this country are on their way') }}</h2>
        <p class="mt-2 text-ink-600">{{ __('Start the free trial today; we will tell you the price before it ends.') }}</p>
        <a href="{{ route('signup') }}" class="btn-primary mt-5">{{ __('Start free trial') }}</a>
      </div>
    @else
      <div class="mt-8 grid gap-5 md:grid-cols-2 {{ [1 => 'md:grid-cols-1 xl:max-w-md xl:mx-auto', 2 => 'xl:grid-cols-2', 3 => 'xl:grid-cols-3'][count($table['plans'])] ?? 'xl:grid-cols-4' }}">
        @foreach ($table['plans'] as $row)
          @php
            $plan = $row['plan'];
            $featured = $loop->count > 1 && $plan->pricing === 'per_truck' && ! $plan->is_student;
            $signup = route('signup', array_filter(['plan' => $plan->code, 'coupon' => $row['coupon'] ? $coupon->code : null]));
          @endphp
          <article class="site-card relative flex flex-col {{ $featured ? 'border-brand-300 ring-2 ring-brand-400' : '' }}">
            @if ($featured)<span class="pill absolute -top-3 start-6 bg-brand-600 text-white">{{ __('Best for growing fleets') }}</span>@endif
            <div class="flex items-start justify-between gap-2">
              <h2 class="font-display text-xl font-semibold">{{ $plan->name }}</h2>
              @if ($plan->is_student)<span class="pill bg-violet-50 text-violet-800">{{ __('Student') }}</span>@endif
              @if ($row['coupon'])<span class="pill bg-emerald-50 text-emerald-800">{{ $coupon->code }}</span>@endif
            </div>
            @if ($plan->description)<p class="mt-1 text-sm text-ink-600">{{ $plan->description }}</p>@endif

            <div class="mt-5 min-h-[5.5rem]">
              @if ($row['free'])
                <p class="font-display text-4xl font-semibold">{{ __('Free') }}</p>
              @elseif ($row['lifetime'])
                @include('site._price', ['p' => $row['lifetime'], 'per' => __('once, for life'), 'currency' => $plan->currency])
              @else
                <div class="price-monthly">@if ($row['monthly'])@include('site._price', ['p' => $row['monthly'], 'per' => $plan->pricing === 'per_truck' ? __('per truck a month') : __('a month'), 'currency' => $plan->currency])@else<p class="text-sm text-ink-500">{{ __('Yearly only') }}</p>@endif</div>
                <div class="price-annual">@if ($row['annual'])@include('site._price', ['p' => $row['annual'], 'per' => $plan->pricing === 'per_truck' ? __('per truck a year') : __('a year'), 'currency' => $plan->currency])@if ($row['saves'])<p class="mt-1 text-xs font-semibold text-emerald-700">{{ __('You save :p% against monthly', ['p' => $row['saves']]) }}</p>@endif @else<p class="text-sm text-ink-500">{{ __('Monthly only') }}</p>@endif</div>
              @endif
              @if ($row['coupon'] && $coupon->kind === 'fixed' && $plan->pricing === 'per_truck')<p class="mt-1 text-xs font-semibold text-emerald-700">{{ __(':amount off the bill with :code', ['amount' => $money($coupon->value, $coupon->currency ?? $plan->currency), 'code' => $coupon->code]) }}</p>@endif
            </div>

            <ul class="mt-5 space-y-2 text-sm text-ink-700">
              <li class="flex gap-2"><x-icon name="truck" class="mt-0.5 size-4 shrink-0 text-ink-400" /> {{ $plan->max_trucks ? trans_choice('Up to :count truck|Up to :count trucks', $plan->max_trucks) : __('Any number of trucks') }}</li>
              <li class="flex gap-2"><x-icon name="users" class="mt-0.5 size-4 shrink-0 text-ink-400" /> {{ $plan->max_users ? trans_choice('Up to :count office user|Up to :count office users', $plan->max_users) : __('Any number of office users') }} · {{ __('crew app free') }}</li>
              @if ($plan->storage_mb)<li class="flex gap-2"><x-icon name="folder" class="mt-0.5 size-4 shrink-0 text-ink-400" /> {{ $plan->storage_mb >= 1024 ? __(':n GB for documents and photos', ['n' => rtrim(rtrim(number_format($plan->storage_mb / 1024, 1), '0'), '.')]) : __(':n MB for documents and photos', ['n' => $plan->storage_mb]) }}</li>@endif
              <li class="flex gap-2"><x-icon name="check" class="mt-0.5 size-4 shrink-0 text-emerald-600" /> {{ empty($plan->modules) ? __('Every feature') : collect($plan->modules)->map(fn ($m) => __(\App\Platform\Billing\Plan::MODULES[$m] ?? $m))->join(', ') }}</li>
            </ul>
            <div class="mt-auto pt-6"><a href="{{ $signup }}" class="{{ $featured ? 'btn-primary' : 'btn-secondary' }} w-full">{{ __('Start free trial') }}</a></div>
          </article>
        @endforeach
      </div>
    @endif

    <p class="mt-6 text-center text-sm text-ink-500">{{ $country === 'IN' ? __('Prices are before GST.') : __('Prices are before VAT.') }} {{ __('The trial needs no card; you choose a plan when it ends. The crew app is free for every driver and helper.') }}</p>
  </div>
</section>

<section class="site-slant bg-ink-50 py-20">
  <div class="mx-auto grid max-w-7xl gap-10 px-4 sm:px-6 lg:grid-cols-2">
    <div>
      <h2 class="font-display text-3xl font-semibold">{{ __('Every plan includes') }}</h2>
      <ul class="mt-6 grid gap-3 text-sm text-ink-800 sm:grid-cols-2">
        @foreach ([__('The crew app for every driver and helper'), __('Trips with full and part loads, hired trucks'), __('Approvals and crew settlement'), __('Invoices, LR, receipts with TDS'), __('GST or VAT, by the rules of your country'), __('Ledgers, P&L and a Tally export'), __('Document Manager with expiry reminders'), __('Live map and route replay'), __('An audit log of every change'), __('Help by WhatsApp and email')] as $item)
          <li class="flex gap-2"><x-icon name="check" class="mt-0.5 size-4 shrink-0 text-emerald-600" /> {{ $item }}</li>
        @endforeach
      </ul>
    </div>
    <div class="space-y-3">
      <h2 class="font-display text-3xl font-semibold">{{ __('Billing questions') }}</h2>
      @foreach ($questions as [$q, $a])
        <details class="site-card p-5"><summary class="cursor-pointer font-semibold">{{ $q }}</summary><p class="mt-2 text-sm text-ink-600">{{ $a }}</p></details>
      @endforeach
      <a href="{{ route('site.faq') }}" class="inline-block pt-2 font-semibold text-brand-700 hover:underline">{{ __('More questions') }} →</a>
    </div>
  </div>
</section>
@endsection
