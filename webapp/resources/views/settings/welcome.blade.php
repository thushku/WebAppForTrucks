@extends('layouts.app')
@section('title', __('Getting started'))
@section('content')
@php
  $total = count($steps);
  $months = collect(range(1, 12))->mapWithKeys(fn ($m) => [$m => \Carbon\CarbonImmutable::create(2026, $m, 1)->translatedFormat('F')]);
  $mark = fn (string $key) => $steps[$key];
@endphp
<div class="mx-auto max-w-3xl space-y-6">
  <div>
    <p class="text-sm font-medium text-brand-700">{{ __(':done of :total done', ['done' => $done, 'total' => $total]) }}</p>
    <h1 class="mt-1 font-display text-3xl font-semibold">{{ __('Welcome to :company', ['company' => $company->name]) }}</h1>
    <p class="mt-1 text-ink-600">{{ __('Five short steps and your first trip is on the road. Skip any of them and come back later.') }}</p>
    <div class="mt-4 h-2 rounded-full bg-ink-100" role="progressbar" aria-label="{{ __('Getting started') }}" aria-valuemin="0" aria-valuemax="{{ $total }}" aria-valuenow="{{ $done }}">
      <div class="h-2 rounded-full bg-brand-500" style="width: {{ max(3, (int) round($done / $total * 100)) }}%"></div>
    </div>
  </div>

  @php
    $step = function (string $key, int $n, string $title) use ($mark) {
        return ['key' => $key, 'n' => $n, 'title' => $title, 'done' => $mark($key)];
    };
  @endphp

  {{-- 1. Company, tax ID and financial year. --}}
  @php $s = $step('company', 1, __('Your company')); @endphp
  <section id="company" class="card scroll-mt-24 p-6">
    @include('settings._welcome-head', ['s' => $s, 'hint' => __('Printed on every LR and bill.')])
    <form method="POST" action="{{ route('welcome.company') }}" class="mt-5 grid gap-4 sm:grid-cols-2">
      @csrf
      <div class="sm:col-span-2"><label class="label" for="legal_name">{{ __('Legal name') }}</label><input id="legal_name" name="legal_name" class="input" value="{{ old('legal_name', $company->legal_name ?? $company->name) }}" maxlength="200"></div>
      <div class="sm:col-span-2"><label class="label" for="address_line1">{{ __('Address') }}</label><input id="address_line1" name="address_line1" class="input" value="{{ old('address_line1', $company->address_line1) }}" maxlength="200" required>@error('address_line1')<span class="error">{{ $message }}</span>@enderror</div>
      <div><label class="label" for="city">{{ __('City') }}</label><input id="city" name="city" class="input" value="{{ old('city', $company->city) }}" maxlength="100" required>@error('city')<span class="error">{{ $message }}</span>@enderror</div>
      <div><label class="label" for="state_code">{{ $company->country === 'IN' ? __('State') : __('Emirate') }}</label>
        <select id="state_code" name="state_code" class="input" required><option value="">—</option>@foreach ($regions as $code => $name)<option value="{{ $code }}" @selected(old('state_code', $company->state_code) == $code)>{{ $name }}</option>@endforeach</select>@error('state_code')<span class="error">{{ $message }}</span>@enderror</div>
      <div><label class="label" for="postal_code">{{ $company->country === 'IN' ? __('PIN code') : __('P.O. box') }}</label><input id="postal_code" name="postal_code" class="input" value="{{ old('postal_code', $company->postal_code) }}" maxlength="20">@error('postal_code')<span class="error">{{ $message }}</span>@enderror</div>
      <div><label class="label" for="phone">{{ __('Office phone') }}</label><input id="phone" name="phone" type="tel" class="input" value="{{ old('phone', $company->phone) }}" maxlength="20">@error('phone')<span class="error">{{ $message }}</span>@enderror</div>
      <div><label class="label" for="tax_id">{{ $taxLabel }} <span class="text-ink-400">({{ __('optional') }})</span></label>
        @if ($registration)
          <input id="tax_id" class="input bg-ink-50" value="{{ $registration->tax_id }}" disabled><span class="hint">{{ __('More registrations under Settings → Tax.') }}</span>
        @else
          <input id="tax_id" name="tax_id" class="input uppercase" value="{{ old('tax_id') }}" maxlength="20">@error('tax_id')<span class="error">{{ $message }}</span>@enderror
        @endif
      </div>
      <div><label class="label" for="fy_start_month">{{ __('Financial year starts in') }}</label>
        <select id="fy_start_month" name="fy_start_month" class="input" @disabled($booksStarted)>@foreach ($months as $m => $name)<option value="{{ $m }}" @selected((int) old('fy_start_month', $company->fy_start_month) === $m)>{{ $name }}</option>@endforeach</select>
        @if ($booksStarted)<input type="hidden" name="fy_start_month" value="{{ $company->fy_start_month }}"><span class="hint">{{ __('Fixed: the books have entries.') }}</span>@else<span class="hint">{{ $company->country === 'IN' ? __('April in India.') : __('Usually January in the UAE.') }}</span>@endif
        @error('fy_start_month')<span class="error">{{ $message }}</span>@enderror
      </div>
      <div class="sm:col-span-2"><button class="btn-primary">{{ __('Save and continue') }}</button></div>
    </form>
  </section>

  {{-- 2. The first truck. --}}
  @php $s = $step('truck', 2, __('Your first truck')); @endphp
  <section id="truck" class="card scroll-mt-24 p-6">
    @include('settings._welcome-head', ['s' => $s, 'hint' => __('Registration number, type and the RC owner. Add its papers later for expiry reminders.')])
    <div class="mt-4">@if ($s['done'])<a href="{{ route('trucks.index') }}" class="btn-secondary">{{ __('See your trucks') }}</a>@else<a href="{{ route('trucks.create') }}" class="btn-primary"><x-icon name="plus" /> {{ __('Add a truck') }}</a>@endif</div>
  </section>

  {{-- 3. The first driver, with the activation code. --}}
  @php $s = $step('driver', 3, __('Your first driver')); @endphp
  <section id="driver" class="card scroll-mt-24 p-6">
    @include('settings._welcome-head', ['s' => $s, 'hint' => __('Name and mobile number. On the driver’s page, “Make an activation code” shows the code and a QR the phone scans to sign in.')])
    <div class="mt-4">@if ($s['done'])<a href="{{ route('people.index') }}" class="btn-secondary">{{ __('See your people') }}</a>@else<a href="{{ route('people.create') }}" class="btn-primary"><x-icon name="plus" /> {{ __('Add a driver') }}</a>@endif</div>
  </section>

  {{-- 4. The crew app on the driver's phone. --}}
  @php $s = $step('app', 4, __('The crew app on the driver’s phone')); @endphp
  <section id="app" class="card scroll-mt-24 p-6">
    @include('settings._welcome-head', ['s' => $s, 'hint' => __('The driver installs the app, enters the mobile number and the activation code. Ticked when the first phone signs in.')])
    @if ($appQr)
      <div class="mt-4 flex flex-wrap items-center gap-5">
        <div class="rounded-2xl border border-ink-100 bg-white p-3">{!! $appQr !!}</div>
        <p class="text-sm text-ink-600">{{ __('Scan to download the crew app, or send this link to the driver:') }}<br><a href="{{ $appUrl }}" class="break-all font-medium text-brand-700 hover:underline" target="_blank" rel="noopener">{{ $appUrl }}</a></p>
      </div>
    @else
      <p class="mt-4 rounded-xl bg-ink-50 px-4 py-3 text-sm text-ink-600">{{ __('The download link appears here as soon as the crew app is published.') }}</p>
    @endif
  </section>

  {{-- 5. The first trip. --}}
  @php $s = $step('trip', 5, __('Your first trip')); @endphp
  <section id="trip" class="card scroll-mt-24 p-6">
    @include('settings._welcome-head', ['s' => $s, 'hint' => __('Truck, crew, the load and where it goes. Send it and the trip appears on the driver’s phone.')])
    <div class="mt-4">@if ($s['done'])<a href="{{ route('trips.index') }}" class="btn-secondary">{{ __('See your trips') }}</a>@else<a href="{{ route('trips.create') }}" class="btn-primary"><x-icon name="plus" /> {{ __('Plan a trip') }}</a>@endif</div>
  </section>

  <form method="POST" action="{{ route('welcome.finish') }}" class="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-dashed border-ink-200 p-5">
    @csrf
    <p class="text-sm text-ink-600">{{ $done === $total ? __('Everything is set up.') : __('You can do the rest later from the menu.') }}</p>
    <button class="{{ $done === $total ? 'btn-primary' : 'btn-secondary' }}">{{ $done === $total ? __('Go to the dashboard') : __('I’ll do the rest later') }}</button>
  </form>
</div>
@endsection
