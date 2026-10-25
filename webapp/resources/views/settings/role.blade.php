@extends('layouts.settings')
@section('title', $role->name)
@section('settings')
<form method="POST" action="{{ route('settings.roles.update', $role) }}" class="space-y-6" novalidate>
  @csrf @method('PUT')
  <section class="card p-6">
    <div class="flex flex-wrap items-end gap-4">
      <div class="min-w-0 flex-1">
        @if ($editable)
          <label class="label" for="role_name">{{ __('Role name') }}</label>
          <input id="role_name" name="name" class="input max-w-sm" value="{{ old('name', $role->name) }}" maxlength="60" required>
          @error('name')<span class="error">{{ $message }}</span>@enderror
        @else
          <h2 class="text-xl font-semibold">{{ $role->name }}</h2>
          <p class="mt-1 text-sm text-ink-500">{{ $role->code === 'owner' ? __('The Owner may do everything; this cannot be changed.') : __('You can see this role but not change it.') }}</p>
        @endif
      </div>
      <a href="{{ route('settings.roles') }}" class="btn-ghost"><x-icon name="arrow-left" /> {{ __('Roles') }}</a>
    </div>
    @error('permissions')<p class="error mt-3">{{ $message }}</p>@enderror
  </section>

  <div class="grid gap-4 xl:grid-cols-2">
    @foreach ($groups as $area => $permissions)
      <fieldset class="card p-5">
        <legend class="sr-only">{{ __($area) }}</legend>
        <h3 class="font-semibold">{{ __($area) }}</h3>
        <ul class="mt-3 space-y-1.5">
          @foreach ($permissions as $code => [$description, $elevated])
            @php($held = in_array($code, $granted, true))
            <li>
              <label class="flex items-start gap-3 rounded-lg px-2 py-1.5 text-sm hover:bg-ink-50">
                <input type="checkbox" name="permissions[]" value="{{ $code }}" class="mt-0.5 size-4 rounded border-ink-300" @checked($held) @disabled(! $editable || ! in_array($code, $mine, true))>
                <span class="flex-1">{{ __($description) }}
                  @if ($elevated)<span class="pill ms-1 bg-amber-50 px-2 py-0.5 text-[10px] text-partial">{{ __('sensitive') }}</span>@endif
                </span>
              </label>
              {{-- A box the editor may not change still sends its value, so saving keeps it. --}}
              @if ($editable && $held && ! in_array($code, $mine, true))
                <input type="hidden" name="permissions[]" value="{{ $code }}">
              @endif
            </li>
          @endforeach
        </ul>
      </fieldset>
    @endforeach
  </div>

  @if ($editable)
    <div class="flex justify-end"><button type="submit" class="btn-primary"><x-icon name="check" /> {{ __('Save role') }}</button></div>
  @endif
</form>
@endsection
