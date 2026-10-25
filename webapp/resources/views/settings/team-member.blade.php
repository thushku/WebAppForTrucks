@extends('layouts.settings')
@section('title', $member->label)
@section('settings')
@php($account = $member->account)
<section class="card p-6">
  <div class="flex flex-wrap items-center gap-4">
    <span class="grid size-12 place-items-center rounded-full bg-brand-100 font-display text-sm font-bold text-brand-800">{{ $account->initials() }}</span>
    <div class="min-w-0 flex-1">
      <h2 class="truncate text-xl font-semibold">{{ $account->name }}</h2>
      <p class="truncate text-sm text-ink-500">{{ $account->email ?? $account->mobile }} · {{ __(ucfirst($member->status)) }}</p>
    </div>
    <a href="{{ route('settings.team') }}" class="btn-ghost"><x-icon name="arrow-left" /> {{ __('Team') }}</a>
  </div>
  @error('member')<p class="error mt-3">{{ $message }}</p>@enderror
</section>

@if ($canManage)
<form method="POST" action="{{ route('settings.team.update', $member) }}" class="card p-6" novalidate>
  @csrf @method('PUT')
  @can('user.edit')
    <div class="max-w-sm">
      <label class="label" for="m_title">{{ __('Job title') }}</label>
      <input id="m_title" name="title" class="input" value="{{ old('title', $member->title) }}" maxlength="80">
    </div>
  @endcan
  @can('role.grant')
    <fieldset class="mt-5">
      <legend class="label">{{ __('Roles') }}</legend>
      <div class="grid gap-2 sm:grid-cols-2">
        @foreach ($grantable as $role)
          <label class="flex items-center gap-3 rounded-xl border border-ink-100 px-3 py-2.5 text-sm has-[:checked]:border-brand-400 has-[:checked]:bg-brand-50">
            <input type="checkbox" name="roles[]" value="{{ $role->code }}" class="size-4 rounded border-ink-300" @checked(in_array($role->code, old('roles', $held), true))>
            <span class="font-medium">{{ $role->name }}</span>
          </label>
        @endforeach
      </div>
      @error('roles')<span class="error">{{ $message }}</span>@enderror
    </fieldset>
  @endcan
  <div class="mt-6 flex justify-end"><button type="submit" class="btn-primary"><x-icon name="check" /> {{ __('Save') }}</button></div>
</form>

<section class="card p-6">
  <h3 class="text-lg font-semibold">{{ __('Access') }}</h3>
  <div class="mt-4 flex flex-wrap gap-2">
    @if ($member->status === 'invited')
      @can('user.invite')
        <form method="POST" action="{{ route('settings.team.resend', $member) }}">@csrf<button class="btn-secondary"><x-icon name="mail" /> {{ __('Send the invitation again') }}</button></form>
      @endcan
    @endif
    @if ($account->isLocked())
      @can('security.unlock')
        <form method="POST" action="{{ route('settings.team.unlock', $member) }}">@csrf<button class="btn-secondary"><x-icon name="key" /> {{ __('Unlock sign-in') }}</button></form>
      @endcan
    @endif
    @if (! $isMe)
      @can('user.edit')
        @if ($member->status === 'suspended')
          <form method="POST" action="{{ route('settings.team.reactivate', $member) }}">@csrf<button class="btn-secondary"><x-icon name="check" /> {{ __('Let them sign in again') }}</button></form>
        @else
          <form method="POST" action="{{ route('settings.team.suspend', $member) }}" data-confirm="{{ __('Stop :name signing in to this company?', ['name' => $account->name]) }}">@csrf<button class="btn-secondary"><x-icon name="lock" /> {{ __('Suspend') }}</button></form>
        @endif
      @endcan
      @can('user.remove')
        <form method="POST" action="{{ route('settings.team.remove', $member) }}" data-confirm="{{ __('Remove :name from the team? Their past work keeps their name.', ['name' => $account->name]) }}">@csrf @method('DELETE')<button class="btn-danger"><x-icon name="trash" /> {{ __('Remove from team') }}</button></form>
      @endcan
    @endif
  </div>
</section>
@else
  <p class="text-sm text-ink-500">{{ __('This person has a higher role than yours, so you cannot change them.') }}</p>
@endif
@endsection
