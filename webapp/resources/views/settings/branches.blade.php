@extends('layouts.settings')
@section('title', __('Branches'))
@section('settings')
<section class="card overflow-hidden">
  <div class="flex flex-wrap items-center justify-between gap-3 p-6 pb-4">
    <div>
      <h2 class="text-xl font-semibold">{{ __('Branches') }}</h2>
      <p class="mt-1 text-sm text-ink-500">{{ __('Your offices. A role can be limited to one branch, and the branch code can appear in document numbers.') }}</p>
    </div>
  </div>
  <div class="overflow-x-auto">
    <table class="table">
      <thead><tr><th>{{ __('Branch') }}</th><th>{{ __('Code') }}</th><th>{{ __('City') }}</th><th>{{ __('Status') }}</th><th></th></tr></thead>
      <tbody>
        @foreach ($branches as $branch)
          <tr>
            <td class="font-medium">{{ $branch->name }} @if ($branch->is_head_office)<span class="pill ms-1 bg-brand-50 text-brand-700">{{ __('Head office') }}</span>@endif</td>
            <td class="docno font-mono text-xs">{{ $branch->code }}</td>
            <td>{{ $branch->city }}{{ $branch->state_code ? ', ' . \App\Support\Regions::name($country, $branch->state_code) : '' }}</td>
            <td>@if ($branch->active)<span class="pill bg-emerald-50 text-paid">{{ __('Active') }}</span>@else<span class="pill bg-ink-100 text-ink-500">{{ __('Switched off') }}</span>@endif</td>
            <td class="text-end"><a href="{{ route('settings.branches.edit', $branch) }}" class="text-sm font-semibold text-brand-700 hover:underline">{{ __('Edit') }}</a></td>
          </tr>
        @endforeach
      </tbody>
    </table>
  </div>
</section>

@php($b = $editing)
<form method="POST" action="{{ $b ? route('settings.branches.update', $b) : route('settings.branches.store') }}" class="card p-6" novalidate>
  @csrf
  @if ($b) @method('PUT') @endif
  <h3 class="text-lg font-semibold">{{ $b ? __('Edit :name', ['name' => $b->name]) : __('Add a branch') }}</h3>
  <div class="mt-4 grid gap-4 sm:grid-cols-3">
    <div class="sm:col-span-2">
      <label class="label" for="b_name">{{ __('Name') }}</label>
      <input id="b_name" name="name" class="input" value="{{ old('name', $b?->name) }}" maxlength="120" required>
      @error('name')<span class="error">{{ $message }}</span>@enderror
    </div>
    <div>
      <label class="label" for="b_code">{{ __('Code') }}</label>
      <input id="b_code" name="code" class="input uppercase" value="{{ old('code', $b?->code) }}" maxlength="6" required>
      @error('code')<span class="error">{{ $message }}</span>@enderror
    </div>
    <div class="sm:col-span-2">
      <label class="label" for="b_address_line1">{{ __('Address') }}</label>
      <input id="b_address_line1" name="address_line1" class="input" value="{{ old('address_line1', $b?->address_line1) }}" maxlength="200">
    </div>
    <div>
      <label class="label" for="b_phone">{{ __('Phone') }}</label>
      <input id="b_phone" name="phone" class="input" value="{{ old('phone', $b?->phone) }}" maxlength="20" inputmode="tel">
      @error('phone')<span class="error">{{ $message }}</span>@enderror
    </div>
    <div>
      <label class="label" for="b_city">{{ __('City') }}</label>
      <input id="b_city" name="city" class="input" value="{{ old('city', $b?->city) }}" maxlength="100">
    </div>
    <div>
      <label class="label" for="b_state">{{ \App\Support\Regions::label($country) }}</label>
      <select id="b_state" name="state_code" class="input">
        <option value="">—</option>
        @foreach ($regions as $code => $region)
          <option value="{{ $code }}" @selected(old('state_code', $b?->state_code) === (string) $code)>{{ $region }}</option>
        @endforeach
      </select>
    </div>
    <div>
      <label class="label" for="b_postal">{{ $country === 'IN' ? __('PIN code') : __('P.O. Box') }}</label>
      <input id="b_postal" name="postal_code" class="input" value="{{ old('postal_code', $b?->postal_code) }}" maxlength="20">
    </div>
  </div>
  <div class="mt-4 flex flex-wrap gap-5">
    <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_head_office" value="1" class="size-4 rounded border-ink-300" @checked(old('is_head_office', $b?->is_head_office))> {{ __('Head office') }}</label>
    @if ($b)
      <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="active" value="1" class="size-4 rounded border-ink-300" @checked(old('active', $b->active))> {{ __('Active') }}</label>
    @endif
  </div>
  @error('active')<span class="error">{{ $message }}</span>@enderror
  <div class="mt-6 flex justify-end gap-2">
    @if ($b)<a href="{{ route('settings.branches') }}" class="btn-ghost">{{ __('Cancel') }}</a>@endif
    <button type="submit" class="btn-primary"><x-icon name="{{ $b ? 'check' : 'plus' }}" /> {{ $b ? __('Save') : __('Add branch') }}</button>
  </div>
</form>
@endsection
