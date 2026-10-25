@extends('layouts.settings')
@section('title', __('Team'))
@section('settings')
<section class="card overflow-hidden">
  <div class="flex flex-wrap items-center justify-between gap-3 p-6 pb-4">
    <div>
      <h2 class="text-xl font-semibold">{{ __('Team') }}</h2>
      <p class="mt-1 text-sm text-ink-500">{{ __('People who sign in to this company. Crew who use only the phone app are added under People.') }}</p>
    </div>
    <a href="{{ request()->query('show') === 'left' ? route('settings.team') : route('settings.team', ['show' => 'left']) }}" class="text-sm font-medium text-brand-700 hover:underline">
      {{ request()->query('show') === 'left' ? __('Hide people who left') : __('Show people who left') }}
    </a>
  </div>
  <div class="overflow-x-auto">
    <table class="table">
      <thead><tr><th>{{ __('Person') }}</th><th>{{ __('Roles') }}</th><th>{{ __('Status') }}</th><th>{{ __('Last seen') }}</th><th></th></tr></thead>
      <tbody>
        @foreach ($members as $m)
          @php($account = $m->account)
          <tr>
            <td>
              <div class="flex items-center gap-3">
                <span class="grid size-9 shrink-0 place-items-center rounded-full bg-brand-100 font-display text-xs font-bold text-brand-800">{{ $account->initials() }}</span>
                <div class="min-w-0">
                  <div class="truncate font-medium">{{ $account->name }} @if ($m->id === $me->id)<span class="text-xs text-ink-400">({{ __('you') }})</span>@endif</div>
                  <div class="truncate text-xs text-ink-500">{{ $account->email ?? $account->mobile }}{{ $m->title ? ' · ' . $m->title : '' }}</div>
                </div>
              </div>
            </td>
            <td>
              @foreach (($roles[$m->id] ?? collect())->pluck('role')->filter()->unique('id') as $role)
                <span class="pill bg-ink-100 text-ink-700">{{ $role->name }}</span>
              @endforeach
            </td>
            <td>
              @switch($m->status)
                @case('active')<span class="pill bg-emerald-50 text-paid">{{ __('Active') }}</span>@break
                @case('invited')<span class="pill bg-sky-50 text-sent">{{ __('Invited') }}</span>@break
                @case('suspended')<span class="pill bg-amber-50 text-partial">{{ __('Suspended') }}</span>@break
                @default<span class="pill bg-ink-100 text-ink-500">{{ __('Left') }}</span>
              @endswitch
              @if ($account->isLocked())<span class="pill bg-red-50 text-overdue"><x-icon name="lock" class="size-3" /> {{ __('Locked') }}</span>@endif
            </td>
            <td class="nowrap text-xs text-ink-500">{{ $m->last_seen_at?->diffForHumans() ?? '—' }}</td>
            <td class="text-end">
              @if ($m->status !== 'left' && $canManage($m))
                <a href="{{ route('settings.team.edit', $m) }}" class="text-sm font-semibold text-brand-700 hover:underline">{{ __('Manage') }}</a>
              @endif
            </td>
          </tr>
        @endforeach
      </tbody>
    </table>
  </div>
</section>

@can('user.invite')
<form method="POST" action="{{ route('settings.team.invite') }}" class="card p-6" novalidate>
  @csrf
  <h3 class="text-lg font-semibold">{{ __('Invite someone') }}</h3>
  <p class="mt-1 text-sm text-ink-500">{{ __('They get an email with a link to join. Someone who already has a login joins with it.') }}</p>
  <div class="mt-4 grid gap-4 sm:grid-cols-3">
    <div>
      <label class="label" for="i_name">{{ __('Name') }}</label>
      <input id="i_name" name="name" class="input" value="{{ old('name') }}" maxlength="150" required>
      @error('name')<span class="error">{{ $message }}</span>@enderror
    </div>
    <div>
      <label class="label" for="i_email">{{ __('Email') }}</label>
      <input id="i_email" name="email" type="email" class="input" value="{{ old('email') }}" maxlength="255" required>
      @error('email')<span class="error">{{ $message }}</span>@enderror
    </div>
    <div>
      <label class="label" for="i_title">{{ __('Job title') }} <span class="text-ink-400">({{ __('optional') }})</span></label>
      <input id="i_title" name="title" class="input" value="{{ old('title') }}" maxlength="80" placeholder="{{ __('Dispatcher, Accounts…') }}">
    </div>
  </div>
  <fieldset class="mt-4">
    <legend class="label">{{ __('Roles') }}</legend>
    <div class="flex flex-wrap gap-2">
      @foreach ($grantable as $role)
        <label class="flex items-center gap-2 rounded-xl border border-ink-100 px-3 py-2 text-sm has-[:checked]:border-brand-400 has-[:checked]:bg-brand-50">
          <input type="checkbox" name="roles[]" value="{{ $role->code }}" class="size-4 rounded border-ink-300" @checked(in_array($role->code, old('roles', []), true))> {{ $role->name }}
        </label>
      @endforeach
    </div>
    @error('roles')<span class="error">{{ $message }}</span>@enderror
  </fieldset>
  <div class="mt-6 flex justify-end"><button type="submit" class="btn-primary"><x-icon name="send" /> {{ __('Send invitation') }}</button></div>
</form>
@endcan
@endsection
