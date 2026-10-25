@extends('layouts.settings')
@section('title', __('Lists'))
@section('settings')
<div class="flex flex-wrap gap-2" role="tablist" aria-label="{{ __('Lists') }}">
  @foreach ($lists as $key => [$title])
    <a href="{{ route('settings.lists', $key) }}" role="tab" aria-selected="{{ $key === $list ? 'true' : 'false' }}"
       class="rounded-xl border px-3 py-2 text-sm font-medium {{ $key === $list ? 'border-brand-300 bg-brand-50 text-brand-700' : 'border-ink-100 bg-white text-ink-700 hover:border-brand-200' }}">{{ __($title) }}</a>
  @endforeach
</div>

<section class="card overflow-hidden">
  <div class="p-6 pb-4">
    <h2 class="text-xl font-semibold">{{ __($lists[$list][0]) }}</h2>
    <p class="mt-1 text-sm text-ink-500">{{ __('Rename or hide anything. Items that came with your country’s set-up cannot be deleted, because older records use them.') }}</p>
  </div>
  <div class="divide-y divide-ink-100 border-t border-ink-100">
    @foreach ($items as $item)
      <form method="POST" action="{{ route('settings.lists.update', $item) }}" class="flex flex-wrap items-center gap-3 px-6 py-3 {{ $item->active ? '' : 'bg-ink-50/60' }}" novalidate>
        @csrf @method('PUT')
        <input name="name" value="{{ $item->name }}" class="input max-w-xs flex-1" maxlength="120" aria-label="{{ __('Name') }}" required>
        @foreach ($fields as $field => $label)
          @if (in_array($field, \App\Modules\Settings\Models\MasterItem::BOOLEAN_FIELDS, true))
            <label class="flex items-center gap-1.5 text-xs text-ink-700"><input type="checkbox" name="meta[{{ $field }}]" value="1" class="size-4 rounded border-ink-300" @checked($item->meta[$field] ?? false)> {{ __($label) }}</label>
          @elseif ($field === 'group')
            <select name="meta[group]" class="input w-40" aria-label="{{ __($label) }}">
              @foreach (['running' => 'Running', 'maintenance' => 'Maintenance', 'office' => 'Office'] as $value => $text)
                <option value="{{ $value }}" @selected(($item->meta['group'] ?? 'running') === $value)>{{ __($text) }}</option>
              @endforeach
            </select>
          @elseif ($field === 'owner')
            <select name="meta[owner]" class="input w-36" aria-label="{{ __($label) }}">
              @foreach (['truck' => 'Truck', 'person' => 'Person', 'company' => 'Company', 'party' => 'Party'] as $value => $text)
                <option value="{{ $value }}" @selected(($item->meta['owner'] ?? 'truck') === $value)>{{ __($text) }}</option>
              @endforeach
            </select>
          @else
            <label class="flex items-center gap-1.5 text-xs text-ink-500">{{ __($label) }}
              <input name="meta[{{ $field }}]" value="{{ $item->meta[$field] ?? '' }}" class="input w-20 py-1.5" inputmode="decimal">
            </label>
          @endif
        @endforeach
        <label class="flex items-center gap-1.5 text-xs text-ink-700"><input type="checkbox" name="active" value="1" class="size-4 rounded border-ink-300" @checked($item->active)> {{ __('In use') }}</label>
        <button type="submit" class="btn-ghost ms-auto px-3 py-2 text-xs">{{ __('Save') }}</button>
      </form>
    @endforeach
  </div>
</section>

<form method="POST" action="{{ route('settings.lists.store', $list) }}" class="card flex flex-wrap items-end gap-3 p-6" novalidate>
  @csrf
  <div class="min-w-[14rem] flex-1">
    <label class="label" for="new_name">{{ __('Add to this list') }}</label>
    <input id="new_name" name="name" class="input" value="{{ old('name') }}" maxlength="120" required>
    @error('name')<span class="error">{{ $message }}</span>@enderror
  </div>
  @if (isset($fields['group']))
    <select name="meta[group]" class="input w-40" aria-label="{{ __('Group') }}">
      <option value="running">{{ __('Running') }}</option><option value="maintenance">{{ __('Maintenance') }}</option><option value="office">{{ __('Office') }}</option>
    </select>
  @endif
  @if (isset($fields['owner']))
    <select name="meta[owner]" class="input w-36" aria-label="{{ __('For') }}">
      <option value="truck">{{ __('Truck') }}</option><option value="person">{{ __('Person') }}</option><option value="company">{{ __('Company') }}</option><option value="party">{{ __('Party') }}</option>
    </select>
  @endif
  <button type="submit" class="btn-primary"><x-icon name="plus" /> {{ __('Add') }}</button>
</form>
@endsection
