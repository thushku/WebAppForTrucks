@extends('layouts.guest')
@section('title', __('Forgot password'))
@section('content')
<div class="card p-6 sm:p-8">
  <h1 class="font-display text-2xl font-semibold">{{ __('Forgot your password?') }}</h1>
  <p class="mt-1 text-sm text-ink-500">{{ __('We will email you a link to choose a new one.') }}</p>
  <form method="POST" action="{{ route('password.email') }}" class="mt-6 space-y-4">
    @csrf
    <div>
      <label class="label" for="email">{{ __('Email') }}</label>
      <input id="email" name="email" type="email" class="input" value="{{ old('email') }}" maxlength="255" required autofocus>
      @error('email')<span class="error">{{ $message }}</span>@enderror
    </div>
    <button type="submit" class="btn-primary btn-lg w-full"><x-icon name="send" /> {{ __('Send the link') }}</button>
  </form>
</div>
<p class="text-center text-sm"><a href="{{ route('login') }}" class="font-semibold text-brand-700 hover:underline">{{ __('Back to sign in') }}</a></p>
@endsection
