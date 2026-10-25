@extends('layouts.guest')
@section('title', __('Choose a new password'))
@section('content')
<div class="card p-6 sm:p-8">
  <h1 class="font-display text-2xl font-semibold">{{ __('Choose a new password') }}</h1>
  <form method="POST" action="{{ route('password.update', $token) }}" class="mt-6 space-y-4">
    @csrf
    <div>
      <label class="label" for="password">{{ __('New password') }}</label>
      <input id="password" name="password" type="password" class="input" maxlength="1024" autocomplete="new-password" required autofocus>
      <span class="hint">{{ __('At least 10 characters, with letters and numbers.') }}</span>
      @error('password')<span class="error">{{ $message }}</span>@enderror
    </div>
    <div>
      <label class="label" for="password_confirmation">{{ __('New password again') }}</label>
      <input id="password_confirmation" name="password_confirmation" type="password" class="input" maxlength="1024" autocomplete="new-password" required>
    </div>
    <button type="submit" class="btn-primary btn-lg w-full"><x-icon name="key" /> {{ __('Save new password') }}</button>
  </form>
</div>
@endsection
