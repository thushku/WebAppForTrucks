@extends('layouts.guest')
@section('title', __('Sign in'))
@section('content')
<div class="card p-6 sm:p-8">
  <h1 class="font-display text-2xl font-semibold">{{ __('Sign in') }}</h1>
  <p class="mt-1 text-sm text-ink-500">{{ __('Use your email or mobile number.') }}</p>

  <form method="POST" action="{{ route('login.attempt') }}" class="mt-6 space-y-4" novalidate>
    @csrf
    <div>
      <label class="label" for="login">{{ __('Email or mobile') }}</label>
      <input id="login" name="login" type="text" class="input" value="{{ old('login') }}" autocomplete="username" maxlength="255" required autofocus>
      @error('login')<span class="error">{{ $message }}</span>@enderror
    </div>
    <div>
      <div class="flex items-end justify-between">
        <label class="label" for="password">{{ __('Password') }}</label>
        <a href="{{ route('password.forgot') }}" class="mb-1.5 text-xs font-medium text-brand-700 hover:underline">{{ __('Forgot password?') }}</a>
      </div>
      <input id="password" name="password" type="password" class="input" autocomplete="current-password" maxlength="1024" required>
    </div>
    @if ($captcha)
      @include('partials.captcha')
    @endif
    <label class="flex items-center gap-2 text-sm text-ink-700">
      <input type="checkbox" name="remember" value="1" class="size-4 rounded border-ink-300" @checked(old('remember'))> {{ __('Keep me signed in on this computer') }}
    </label>
    <button type="submit" class="btn-primary btn-lg w-full"><x-icon name="log-in" /> {{ __('Sign in') }}</button>
  </form>
</div>
<p class="text-center text-sm text-ink-500">{{ __('New here?') }} <a href="{{ route('signup') }}" class="font-semibold text-brand-700 hover:underline">{{ __('Start a free trial') }}</a></p>
@endsection
