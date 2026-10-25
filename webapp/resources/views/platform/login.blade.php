@extends('platform.layout')
@section('title', 'Sign in')
@section('content')
<div class="mx-auto max-w-md">
  <div class="card p-6 sm:p-8">
    <h1 class="font-display text-2xl font-semibold">{{ __('Console sign-in') }}</h1>
    <p class="mt-1 text-sm text-ink-500">{{ __('After your password we email you a one-time code.') }}</p>
    <form method="POST" action="{{ route('platform.login.attempt') }}" class="mt-6 space-y-4">
      @csrf
      <div>
        <label class="label" for="email">{{ __('Email') }}</label>
        <input id="email" name="email" type="email" class="input" value="{{ old('email') }}" maxlength="255" autocomplete="username" required autofocus>
        @error('email')<span class="error">{{ $message }}</span>@enderror
      </div>
      <div>
        <label class="label" for="password">{{ __('Password') }}</label>
        <input id="password" name="password" type="password" class="input" maxlength="1024" autocomplete="current-password" required>
      </div>
      @include('partials.captcha')
      <button type="submit" class="btn-primary btn-lg w-full">{{ __('Continue') }}</button>
    </form>
  </div>
</div>
@endsection
