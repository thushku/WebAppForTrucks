@extends('layouts.settings')
@section('title', __('Places'))
@section('settings')
<section class="card overflow-hidden">
  <div class="flex flex-wrap items-end justify-between gap-3 p-6 pb-4">
    <div>
      <h2 class="text-xl font-semibold">{{ __('Places') }}</h2>
      <p class="mt-1 text-sm text-ink-500">{{ __('Where trips load, unload and stop. The short code goes into trip numbers: Kochi (COK) to Mumbai (BOM).') }}</p>
    </div>
    <form method="GET" action="{{ route('settings.places') }}" class="flex gap-2" role="search">
      <input name="q" value="{{ $search }}" class="input w-48" placeholder="{{ __('Search') }}" maxlength="100" aria-label="{{ __('Search places') }}">
      <select name="type" class="input w-40" aria-label="{{ __('Type') }}">
        <option value="">{{ __('All types') }}</option>
        @foreach (\App\Modules\Settings\Models\Place::TYPES as $value => $label)
          <option value="{{ $value }}" @selected($type === $value)>{{ __($label) }}</option>
        @endforeach
      </select>
      <button class="btn-secondary px-3" aria-label="{{ __('Search') }}"><x-icon name="search" /></button>
    </form>
  </div>
  <div class="overflow-x-auto">
    <table class="table">
      <thead><tr><th>{{ __('Place') }}</th><th>{{ __('Code') }}</th><th>{{ __('Type') }}</th><th>{{ __('City') }}</th><th></th></tr></thead>
      <tbody>
        @forelse ($places as $place)
          <tr class="{{ $place->active ? '' : 'opacity-60' }}">
            <td class="font-medium">{{ $place->name }}</td>
            <td class="docno font-mono text-xs">{{ $place->short_code }}</td>
            <td class="text-ink-500">{{ __(\App\Modules\Settings\Models\Place::TYPES[$place->type] ?? $place->type) }}</td>
            <td>{{ $place->city }}{{ $place->state_code ? ', ' . \App\Support\Regions::name($country, $place->state_code) : '' }}</td>
            <td class="text-end"><a href="{{ route('settings.places.edit', $place) }}" class="text-sm font-semibold text-brand-700 hover:underline">{{ __('Edit') }}</a></td>
          </tr>
        @empty
          <tr><td colspan="5" class="py-10 text-center text-ink-500">{{ $search !== '' ? __('No place matches.') : __('No places yet. Add the towns and customer sites your trucks go to.') }}</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
  @if ($places->hasPages())<div class="p-4">{{ $places->links() }}</div>@endif
</section>

@php($p = $editing)
<form method="POST" action="{{ $p ? route('settings.places.update', $p) : route('settings.places.store') }}" class="card p-6" data-code-suggest="{{ route('settings.places.suggest') }}" novalidate>
  @csrf
  @if ($p) @method('PUT') @endif
  <h3 class="text-lg font-semibold">{{ $p ? __('Edit :name', ['name' => $p->name]) : __('Add a place') }}</h3>
  <div class="mt-4 grid gap-4 sm:grid-cols-4">
    <div class="sm:col-span-2">
      <label class="label" for="p_name">{{ __('Name') }}</label>
      <input id="p_name" name="name" class="input" value="{{ old('name', $p?->name) }}" maxlength="150" required data-suggest-name>
      @error('name')<span class="error">{{ $message }}</span>@enderror
    </div>
    <div>
      <label class="label" for="p_code">{{ __('Short code') }}</label>
      <input id="p_code" name="short_code" class="input uppercase" value="{{ old('short_code', $p?->short_code) }}" maxlength="6" required data-suggest-code @if ($p) data-touched="1" @endif>
      @error('short_code')<span class="error">{{ $message }}</span>@enderror
    </div>
    <div>
      <label class="label" for="p_type">{{ __('Type') }}</label>
      <select id="p_type" name="type" class="input">
        @foreach (\App\Modules\Settings\Models\Place::TYPES as $value => $label)
          <option value="{{ $value }}" @selected(old('type', $p?->type ?? 'city') === $value)>{{ __($label) }}</option>
        @endforeach
      </select>
    </div>
    <div class="sm:col-span-2">
      <label class="label" for="p_address">{{ __('Address') }}</label>
      <input id="p_address" name="address" class="input" value="{{ old('address', $p?->address) }}" maxlength="500">
    </div>
    <div>
      <label class="label" for="p_city">{{ __('City') }}</label>
      <input id="p_city" name="city" class="input" value="{{ old('city', $p?->city) }}" maxlength="100">
    </div>
    <div>
      <label class="label" for="p_state">{{ \App\Support\Regions::label($country) }}</label>
      <select id="p_state" name="state_code" class="input">
        <option value="">—</option>
        @foreach ($regions as $code => $region)
          <option value="{{ $code }}" @selected(old('state_code', $p?->state_code) === (string) $code)>{{ $region }}</option>
        @endforeach
      </select>
    </div>
    <div>
      <label class="label" for="p_postal">{{ $country === 'IN' ? __('PIN code') : __('P.O. Box') }}</label>
      <input id="p_postal" name="postal_code" class="input" value="{{ old('postal_code', $p?->postal_code) }}" maxlength="20">
    </div>
    <div>
      <label class="label" for="p_lat">{{ __('Latitude') }}</label>
      <input id="p_lat" name="lat" class="input" value="{{ old('lat', $p?->lat) }}" inputmode="decimal" placeholder="9.9312">
      @error('lat')<span class="error">{{ $message }}</span>@enderror
    </div>
    <div>
      <label class="label" for="p_lng">{{ __('Longitude') }}</label>
      <input id="p_lng" name="lng" class="input" value="{{ old('lng', $p?->lng) }}" inputmode="decimal" placeholder="76.2673">
      @error('lng')<span class="error">{{ $message }}</span>@enderror
    </div>
    @if ($p)
      <label class="flex items-center gap-2 self-end pb-3 text-sm"><input type="checkbox" name="active" value="1" class="size-4 rounded border-ink-300" @checked(old('active', $p->active))> {{ __('Active') }}</label>
    @endif
  </div>
  <p class="hint">{{ __('Map position is optional now; it lets the app check arrivals later.') }}</p>
  <div class="mt-6 flex justify-end gap-2">
    @if ($p)<a href="{{ route('settings.places') }}" class="btn-ghost">{{ __('Cancel') }}</a>@endif
    <button type="submit" class="btn-primary"><x-icon name="{{ $p ? 'check' : 'plus' }}" /> {{ $p ? __('Save') : __('Add place') }}</button>
  </div>
</form>
@endsection
