@extends('layouts.settings')
@section('title', __('Company'))
@section('settings')
<form method="POST" action="{{ route('settings.company.update') }}" enctype="multipart/form-data" class="card p-6 sm:p-8" novalidate>
  @csrf @method('PUT')
  <div class="flex flex-wrap items-start justify-between gap-4">
    <div>
      <h2 class="text-xl font-semibold">{{ __('Company details') }}</h2>
      <p class="mt-1 text-sm text-ink-500">{{ __('As they print on LRs, invoices and statements.') }}</p>
    </div>
    <div class="text-end text-xs text-ink-500">
      <div>{{ __('Country') }}: <strong class="text-ink-900">{{ $company->country }}</strong> · {{ __('Currency') }}: <strong class="text-ink-900">{{ $company->currency }}</strong></div>
      <div class="mt-0.5">{{ __('Financial year starts in month :month', ['month' => $company->fy_start_month]) }} · {{ $company->timezone }}</div>
    </div>
  </div>

  <div class="mt-6 grid gap-5 sm:grid-cols-2">
    <div>
      <label class="label" for="name">{{ __('Trading name') }}</label>
      <input id="name" name="name" class="input" value="{{ old('name', $company->name) }}" maxlength="150" required>
      @error('name')<span class="error">{{ $message }}</span>@enderror
    </div>
    <div>
      <label class="label" for="legal_name">{{ __('Legal name') }}</label>
      <input id="legal_name" name="legal_name" class="input" value="{{ old('legal_name', $company->legal_name) }}" maxlength="200">
      <span class="hint">{{ __('As registered, if different (for example “Sharma Roadlines Pvt Ltd”).') }}</span>
    </div>
    <div>
      <label class="label" for="short_code">{{ __('Short code') }}</label>
      <input id="short_code" name="short_code" class="input uppercase" value="{{ old('short_code', $company->short_code) }}" maxlength="6">
      <span class="hint">{{ __('2 to 6 letters, for number formats.') }}</span>
      @error('short_code')<span class="error">{{ $message }}</span>@enderror
    </div>
    <div class="sm:col-span-2 grid gap-5 sm:grid-cols-2">
      <div>
        <label class="label" for="address_line1">{{ __('Address') }}</label>
        <input id="address_line1" name="address_line1" class="input" value="{{ old('address_line1', $company->address_line1) }}" maxlength="200" placeholder="{{ __('Building, street') }}">
      </div>
      <div>
        <label class="label" for="address_line2">{{ __('Address line 2') }}</label>
        <input id="address_line2" name="address_line2" class="input" value="{{ old('address_line2', $company->address_line2) }}" maxlength="200" placeholder="{{ __('Area, landmark') }}">
      </div>
    </div>
    <div>
      <label class="label" for="city">{{ __('City') }}</label>
      <input id="city" name="city" class="input" value="{{ old('city', $company->city) }}" maxlength="100">
    </div>
    <div class="grid grid-cols-[1fr_9rem] gap-3">
      <div>
        <label class="label" for="state_code">{{ \App\Support\Regions::label($company->country) }}</label>
        <select id="state_code" name="state_code" class="input">
          <option value="">—</option>
          @foreach ($regions as $code => $region)
            <option value="{{ $code }}" @selected(old('state_code', $company->state_code) === (string) $code)>{{ $region }}@if ($company->country === 'IN') ({{ $code }})@endif</option>
          @endforeach
        </select>
        @error('state_code')<span class="error">{{ $message }}</span>@enderror
      </div>
      <div>
        <label class="label" for="postal_code">{{ $company->country === 'IN' ? __('PIN code') : __('P.O. Box') }}</label>
        <input id="postal_code" name="postal_code" class="input" value="{{ old('postal_code', $company->postal_code) }}" maxlength="20" inputmode="numeric">
        @error('postal_code')<span class="error">{{ $message }}</span>@enderror
      </div>
    </div>
    <div>
      <label class="label" for="phone">{{ __('Phone') }}</label>
      <input id="phone" name="phone" class="input" value="{{ old('phone', $company->phone) }}" maxlength="20" inputmode="tel">
      @error('phone')<span class="error">{{ $message }}</span>@enderror
    </div>
    <div>
      <label class="label" for="email">{{ __('Email') }}</label>
      <input id="email" name="email" type="email" class="input" value="{{ old('email', $company->email) }}" maxlength="255">
      @error('email')<span class="error">{{ $message }}</span>@enderror
    </div>
    <div>
      <label class="label" for="website">{{ __('Website') }}</label>
      <input id="website" name="website" type="url" class="input" value="{{ old('website', $company->website) }}" maxlength="255" placeholder="https://">
      @error('website')<span class="error">{{ $message }}</span>@enderror
    </div>
    <div>
      <label class="label" for="logo">{{ __('Logo') }}</label>
      <div class="flex items-center gap-4">
        @if ($company->logo_path)
          <img src="{{ route('settings.company.logo') }}" alt="{{ __('Logo') }}" class="h-14 w-auto max-w-[140px] rounded-lg border border-ink-100 bg-white object-contain p-1">
        @endif
        <input id="logo" name="logo" type="file" accept="image/png,image/jpeg,image/webp" class="block w-full text-sm text-ink-700 file:me-3 file:rounded-lg file:border-0 file:bg-brand-50 file:px-3 file:py-2 file:font-semibold file:text-brand-700">
      </div>
      <span class="hint">{{ __('PNG, JPG or WebP, at most 300 KB.') }}</span>
      @if ($company->logo_path)
        <label class="mt-2 flex items-center gap-2 text-sm text-ink-700"><input type="checkbox" name="remove_logo" value="1" class="size-4 rounded border-ink-300"> {{ __('Remove the logo') }}</label>
      @endif
      @error('logo')<span class="error">{{ $message }}</span>@enderror
    </div>
  </div>

  <div class="mt-8 flex justify-end">
    <button type="submit" class="btn-primary"><x-icon name="check" /> {{ __('Save') }}</button>
  </div>
</form>

<p class="text-sm text-ink-500">{{ __('Tax registrations (GSTIN, TRN) and bank accounts are set up with invoicing.') }}</p>
@endsection
