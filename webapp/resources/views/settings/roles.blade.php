@extends('layouts.settings')
@section('title', __('Roles'))
@section('settings')
<section class="card overflow-hidden">
  <div class="p-6 pb-4">
    <h2 class="text-xl font-semibold">{{ __('Roles') }}</h2>
    <p class="mt-1 text-sm text-ink-500">{{ __('What each role may do. Screens check these permissions, never the role’s name, so you can rename a role or change it freely.') }}</p>
  </div>
  <div class="overflow-x-auto">
    <table class="table">
      <thead><tr><th>{{ __('Role') }}</th><th>{{ __('Permissions') }}</th><th>{{ __('People') }}</th><th></th></tr></thead>
      <tbody>
        @foreach ($roles as $role)
          <tr>
            <td class="font-medium">{{ $role->name }} @if (! $role->is_system)<span class="pill ms-1 bg-violet-50 text-violet-700">{{ __('Custom') }}</span>@endif</td>
            <td class="text-ink-500">{{ $role->code === 'owner' ? __('Everything') : $role->permissions->where('allow', true)->count() }}</td>
            <td>{{ $role->assignments_count }}</td>
            <td class="text-end"><a href="{{ route('settings.roles.edit', $role) }}" class="text-sm font-semibold text-brand-700 hover:underline">{{ auth()->user()->can('role.define') && $role->code !== 'owner' ? __('Edit') : __('View') }}</a></td>
          </tr>
        @endforeach
      </tbody>
    </table>
  </div>
</section>

@can('role.define')
<form method="POST" action="{{ route('settings.roles.store') }}" class="card p-6" novalidate>
  @csrf
  <h3 class="text-lg font-semibold">{{ __('New role') }}</h3>
  <div class="mt-4 grid gap-4 sm:grid-cols-[1fr_1fr_auto] sm:items-end">
    <div>
      <label class="label" for="r_name">{{ __('Name') }}</label>
      <input id="r_name" name="name" class="input" value="{{ old('name') }}" maxlength="60" placeholder="{{ __('Dispatcher') }}" required>
      @error('name')<span class="error">{{ $message }}</span>@enderror
    </div>
    <div>
      <label class="label" for="r_copy">{{ __('Start as a copy of') }}</label>
      <select id="r_copy" name="copy" class="input">
        @foreach ($roles->where('code', '!=', 'owner') as $role)
          <option value="{{ $role->id }}">{{ $role->name }}</option>
        @endforeach
      </select>
    </div>
    <button type="submit" class="btn-primary"><x-icon name="plus" /> {{ __('Create') }}</button>
  </div>
  @error('permissions')<span class="error">{{ $message }}</span>@enderror
</form>
@endcan
@endsection
