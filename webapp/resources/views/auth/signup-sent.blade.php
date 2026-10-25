@extends('layouts.guest')
@section('title', __('Check your email'))
@section('content')
<div class="card p-6 sm:p-8">
  <span class="grid size-11 place-items-center rounded-xl bg-brand-50 text-brand-700"><x-icon name="mail" /></span>
  <h1 class="mt-4 font-display text-2xl font-semibold">{{ __('Check your email') }}</h1>
  <p class="mt-2 text-sm text-ink-700">{{ __('If :email can be used, we have sent a link to it. Open the link to confirm your email and start.', ['email' => $email ?: __('your address')]) }}</p>
  <form method="POST" action="{{ route('signup.resend') }}" class="mt-6 space-y-3">
    @csrf
    <input type="hidden" name="email" value="{{ $email }}">
    <button type="submit" class="btn-secondary w-full">{{ __('Send the link again') }}</button>
  </form>
</div>
@endsection
