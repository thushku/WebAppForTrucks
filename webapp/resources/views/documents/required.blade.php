@extends('layouts.app')
@section('title', __('Required papers'))
@section('content')
<a href="{{ route('documents.index') }}" class="btn-ghost"><x-icon name="arrow-left" /> {{ __('Documents') }}</a>
<section class="card overflow-hidden">
  <div class="p-6 pb-4">
    <h2 class="text-xl font-semibold">{{ __('Papers every truck and person must have') }}</h2>
    <p class="mt-1 text-sm text-ink-500">{{ __('A required paper with no document shows as “Missing”. If its type stops dispatch, a truck or driver without it cannot be sent on a trip.') }}</p>
  </div>
  <div class="overflow-x-auto">
    <table class="table">
      <thead><tr><th>{{ __('Who') }}</th><th>{{ __('Paper') }}</th><th>{{ __('Stops dispatch') }}</th><th></th></tr></thead>
      <tbody>
        @forelse ($rules as $r)
          <tr>
            <td>{{ match ($r->applies_to) { 'truck' => $r->subtype ? __('Trucks: :type', ['type' => $truckTypes[$r->subtype] ?? $r->subtype]) : __('Every truck'), 'person' => $r->subtype ? __(':type', ['type' => __($personTypes[$r->subtype] ?? $r->subtype)]) : __('Every person'), default => __('The company') } }}</td>
            <td>{{ $types[$r->document_type]['name'] ?? $r->document_type }}</td>
            <td>@if ($types[$r->document_type]['blocks_dispatch'] ?? false)<span class="pill bg-red-50 text-overdue">{{ __('Yes') }}</span>@endif</td>
            <td class="text-end">@can('document.manage')<form method="POST" action="{{ route('documents.required.destroy', $r) }}">@csrf @method('DELETE')<button class="btn-ghost px-2 py-1.5" aria-label="{{ __('Remove') }}"><x-icon name="trash" class="size-4" /></button></form>@endcan</td>
          </tr>
        @empty
          <tr><td colspan="4" class="py-8 text-center text-ink-500">{{ __('No rules yet.') }}</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</section>

@can('document.manage')
<form method="POST" action="{{ route('documents.required.store') }}" class="card grid gap-3 p-6 sm:grid-cols-[1fr_1fr_1fr_auto] sm:items-end" novalidate>
  @csrf
  <div>
    <label class="label" for="rq_who">{{ __('Who') }}</label>
    <select id="rq_who" name="applies_to" class="input"><option value="truck">{{ __('Trucks') }}</option><option value="person">{{ __('People') }}</option><option value="company">{{ __('The company') }}</option></select>
  </div>
  <div>
    <label class="label" for="rq_sub">{{ __('Only these') }}</label>
    <select id="rq_sub" name="subtype" class="input">
      <option value="">{{ __('All') }}</option>
      <optgroup label="{{ __('Truck types') }}">@foreach ($truckTypes as $code => $name)<option value="{{ $code }}">{{ $name }}</option>@endforeach</optgroup>
      <optgroup label="{{ __('People') }}">@foreach ($personTypes as $code => $name)<option value="{{ $code }}">{{ __($name) }}</option>@endforeach</optgroup>
    </select>
    @error('subtype')<span class="error">{{ $message }}</span>@enderror
  </div>
  <div>
    <label class="label" for="rq_type">{{ __('Paper') }}</label>
    <select id="rq_type" name="document_type" class="input">
      @foreach (['truck' => __('Truck papers'), 'person' => __('Personal papers'), 'company' => __('Company papers')] as $owner => $group)
        <optgroup label="{{ $group }}">@foreach ($types->where('owner', $owner) as $code => $t)<option value="{{ $code }}">{{ $t['name'] }}</option>@endforeach</optgroup>
      @endforeach
    </select>
    @error('document_type')<span class="error">{{ $message }}</span>@enderror
  </div>
  <button class="btn-primary"><x-icon name="plus" /> {{ __('Add rule') }}</button>
</form>
@endcan
@endsection
