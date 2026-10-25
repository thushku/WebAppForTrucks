@extends('layouts.settings')
@section('title', __('Sign-in security'))
@section('settings')
<form method="POST" action="{{ route('settings.security.update') }}" class="card p-6" novalidate>
  @csrf @method('PUT')
  <h2 class="text-xl font-semibold">{{ __('Two-step sign-in') }}</h2>
  <p class="mt-1 text-sm text-ink-500">{{ __('With two-step sign-in, a code is emailed when someone signs in on a new browser. A browser can be trusted for 30 days.') }}</p>
  <div class="mt-5 space-y-2">
    @foreach ($policies as $value => $label)
      <label class="flex items-center gap-3 rounded-xl border border-ink-100 px-4 py-3 text-sm has-[:checked]:border-brand-400 has-[:checked]:bg-brand-50">
        <input type="radio" name="mfa_policy" value="{{ $value }}" class="size-4" @checked($company->mfa_policy === $value)> {{ __($label) }}
      </label>
    @endforeach
  </div>
  @error('mfa_policy')<span class="error">{{ $message }}</span>@enderror
  <div class="mt-6 flex justify-end"><button type="submit" class="btn-primary"><x-icon name="check" /> {{ __('Save') }}</button></div>
</form>

<section class="card p-6">
  <h2 class="text-lg font-semibold">{{ __('Locked out now') }}</h2>
  <p class="mt-1 text-sm text-ink-500">{{ __('After 5 wrong passwords a login waits 15 minutes, then 1 hour, then stays locked until someone unlocks it.') }}</p>
  @forelse ($locked as $account)
    <div class="mt-3 flex items-center justify-between gap-3 rounded-xl border border-ink-100 px-4 py-3">
      <div class="min-w-0">
        <div class="truncate font-medium">{{ $account->name }}</div>
        <div class="text-xs text-ink-500">{{ $account->locked_hard_at ? __('Locked until unlocked') : __('Locked until :time', ['time' => $account->locked_until?->setTimezone($company->timezone)->format('H:i')]) }}</div>
      </div>
      @can('security.unlock')
        <form method="POST" action="{{ route('settings.team.unlock', $members[$account->id]) }}">@csrf<button class="btn-secondary"><x-icon name="key" /> {{ __('Unlock') }}</button></form>
      @endcan
    </div>
  @empty
    <p class="mt-3 text-sm text-ink-500">{{ __('Nobody is locked out.') }}</p>
  @endforelse
</section>
@endsection
