@extends('platform.layout')
@section('title', 'Enter your code')
@section('content')
<div class="mx-auto max-w-md">
  <div class="card p-6 sm:p-8">
    <h1 class="font-display text-2xl font-semibold">{{ __('Enter your code') }}</h1>
    <p class="mt-1 text-sm text-ink-500">{{ __('We sent a 6-digit code to :where. It works for 5 minutes.', ['where' => $challenge->destination_hint]) }}</p>
    <form method="POST" action="{{ route('platform.otp.verify') }}" class="mt-6 space-y-4">
      @csrf
      <input name="code" class="input text-center text-2xl tracking-[.5em]" inputmode="numeric" autocomplete="one-time-code" maxlength="6" required autofocus aria-label="{{ __('Code') }}">
      @error('code')<span class="error">{{ $message }}</span>@enderror
      <button type="submit" class="btn-primary btn-lg w-full">{{ __('Open the console') }}</button>
    </form>
  </div>
</div>
@endsection
