@extends('layouts.guest')
@section('title', __('Join :company', ['company' => $member->company->name]))
@section('content')
<div class="card p-6 sm:p-8">
  <span class="grid size-12 place-items-center rounded-2xl bg-brand-50 text-brand-700"><x-icon name="building" class="size-6" /></span>
  <h1 class="mt-4 font-display text-2xl font-semibold">{{ __('Join :company', ['company' => $member->company->name]) }}</h1>
  <p class="mt-1 text-sm text-ink-500">{{ __('You have been invited as :email.', ['email' => $member->account->email]) }}</p>

  @switch($mode)
    @case('new')
      <form method="POST" action="{{ route('invite.accept', $token) }}" class="mt-6 space-y-4" novalidate>
        @csrf
        <div>
          <label class="label" for="name">{{ __('Your name') }}</label>
          <input id="name" name="name" class="input" value="{{ old('name', $member->account->name) }}" maxlength="150" autocomplete="name" required>
          @error('name')<span class="error">{{ $message }}</span>@enderror
        </div>
        <div>
          <label class="label" for="password">{{ __('Choose a password') }}</label>
          <input id="password" name="password" type="password" class="input" autocomplete="new-password" maxlength="1024" required>
          <span class="hint">{{ __('At least 10 characters, with letters and numbers.') }}</span>
          @error('password')<span class="error">{{ $message }}</span>@enderror
        </div>
        <div>
          <label class="label" for="password_confirmation">{{ __('Password again') }}</label>
          <input id="password_confirmation" name="password_confirmation" type="password" class="input" autocomplete="new-password" maxlength="1024" required>
        </div>
        <button type="submit" class="btn-primary btn-lg w-full"><x-icon name="check" /> {{ __('Join') }}</button>
      </form>
      @break
    @case('join')
      <form method="POST" action="{{ route('invite.accept', $token) }}" class="mt-6">
        @csrf
        <button type="submit" class="btn-primary btn-lg w-full"><x-icon name="check" /> {{ __('Join :company', ['company' => $member->company->name]) }}</button>
      </form>
      @break
    @case('sign_in')
      <p class="mt-5 text-sm text-ink-700">{{ __('You already have a login. Sign in first; you will come back here to join.') }}</p>
      <a href="{{ route('login') }}" class="btn-primary btn-lg mt-4 w-full"><x-icon name="log-in" /> {{ __('Sign in') }}</a>
      @break
    @default
      <p class="mt-5 text-sm text-ink-700">{{ __('This invitation is for another login. Sign out, then open the link again.') }}</p>
      <form method="POST" action="{{ route('logout') }}" class="mt-4">@csrf<button class="btn-secondary w-full"><x-icon name="log-out" /> {{ __('Sign out') }}</button></form>
  @endswitch
</div>
@endsection
