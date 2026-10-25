@extends('layouts.guest')
@section('title', __('Enter your code'))
@section('content')
<div class="card p-6 sm:p-8">
  <span class="grid size-11 place-items-center rounded-xl bg-brand-50 text-brand-700"><x-icon name="shield" /></span>
  <h1 class="mt-4 font-display text-2xl font-semibold">{{ __('Enter your code') }}</h1>
  <p class="mt-1 text-sm text-ink-500">{{ __('We sent a 6-digit code to :where. It works for 5 minutes.', ['where' => $challenge->destination_hint ?? __('your email')]) }}</p>

  <form method="POST" action="{{ route('login.otp.verify') }}" class="mt-6 space-y-4">
    @csrf
    <div>
      <label class="label" for="code">{{ __('Code') }}</label>
      <input id="code" name="code" class="input text-center text-2xl tracking-[.5em]" inputmode="numeric" autocomplete="one-time-code" pattern="\d{6}" maxlength="6" required autofocus>
      @error('code')<span class="error">{{ $message }}</span>@enderror
    </div>
    <label class="flex items-center gap-2 text-sm text-ink-700">
      <input type="checkbox" name="trust" value="1" class="size-4 rounded border-ink-300"> {{ __('Trust this computer for 30 days') }}
    </label>
    <button type="submit" class="btn-primary btn-lg w-full"><x-icon name="check" /> {{ __('Continue') }}</button>
  </form>
  <form method="POST" action="{{ route('login.otp.resend') }}" class="mt-3 text-center">
    @csrf
    <button type="submit" class="text-sm font-medium text-brand-700 hover:underline">{{ __('Send a new code') }}</button>
  </form>
</div>
@endsection
