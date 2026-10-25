@extends('layouts.guest')
@section('title', __('Start your free trial'))
@section('content')
<div class="card p-6 sm:p-8">
  <h1 class="font-display text-2xl font-semibold">{{ __('Start your free trial') }}</h1>
  <p class="mt-1 text-sm text-ink-500">{{ __(':days days free. No card needed.', ['days' => $trialDays]) }}</p>

  <form method="POST" action="{{ route('signup.store') }}" class="mt-6 space-y-4" novalidate>
    @csrf
    <div>
      <label class="label" for="company">{{ __('Company name') }}</label>
      <input id="company" name="company" class="input" value="{{ old('company') }}" maxlength="150" required autofocus>
      @error('company')<span class="error">{{ $message }}</span>@enderror
    </div>
    <div>
      <label class="label" for="country">{{ __('Country') }}</label>
      <select id="country" name="country" class="input">
        <option value="IN" @selected(old('country', 'IN') === 'IN')>{{ __('India') }}</option>
        <option value="AE" @selected(old('country') === 'AE')>{{ __('United Arab Emirates') }}</option>
      </select>
    </div>
    <div>
      <label class="label" for="name">{{ __('Your name') }}</label>
      <input id="name" name="name" class="input" value="{{ old('name') }}" maxlength="150" autocomplete="name" required>
      @error('name')<span class="error">{{ $message }}</span>@enderror
    </div>
    <div class="grid gap-4 sm:grid-cols-2">
      <div class="min-w-0">
        <label class="label" for="email">{{ __('Email') }}</label>
        <input id="email" name="email" type="email" class="input" value="{{ old('email') }}" maxlength="255" autocomplete="email" required>
        @error('email')<span class="error">{{ $message }}</span>@enderror
      </div>
      <div class="min-w-0">
        <label class="label" for="mobile">{{ __('Mobile') }}</label>
        <input id="mobile" name="mobile" type="tel" class="input" value="{{ old('mobile') }}" maxlength="20" autocomplete="tel">
        @error('mobile')<span class="error">{{ $message }}</span>@enderror
      </div>
    </div>
    <div class="grid gap-4 sm:grid-cols-2">
      <div class="min-w-0">
        <label class="label" for="password">{{ __('Password') }}</label>
        <input id="password" name="password" type="password" class="input" maxlength="1024" autocomplete="new-password" required>
        <span class="hint">{{ __('At least 10 characters, with letters and numbers.') }}</span>
        @error('password')<span class="error">{{ $message }}</span>@enderror
      </div>
      <div class="min-w-0">
        <label class="label" for="password_confirmation">{{ __('Password again') }}</label>
        <input id="password_confirmation" name="password_confirmation" type="password" class="input" maxlength="1024" autocomplete="new-password" required>
      </div>
    </div>
    @include('partials.captcha')
    <label class="flex items-start gap-2 text-sm text-ink-700">
      <input type="checkbox" name="terms" value="1" class="mt-0.5 size-4 rounded border-ink-300" @checked(old('terms'))>
      <span>{{ __('I agree to the terms of service and the privacy policy.') }}</span>
    </label>
    @error('terms')<span class="error">{{ $message }}</span>@enderror
    <button type="submit" class="btn-primary btn-lg w-full"><x-icon name="sparkle" /> {{ __('Create my company') }}</button>
  </form>
</div>
<p class="text-center text-sm text-ink-500">{{ __('Already have an account?') }} <a href="{{ route('login') }}" class="font-semibold text-brand-700 hover:underline">{{ __('Sign in') }}</a></p>
@endsection
