@extends('layouts.app')
@section('title', $truck->reg_no)
@section('content')
@php
  $tones = ['idle' => 'bg-emerald-50 text-paid', 'on_trip' => 'bg-brand-50 text-brand-700', 'workshop' => 'bg-amber-50 text-partial',
            'breakdown' => 'bg-red-50 text-overdue', 'out_of_service' => 'bg-ink-100 text-ink-500', 'sold' => 'bg-ink-100 text-ink-500'];
  $tabs = ['overview' => __('Overview'), 'complaints' => __('Complaints'), 'service' => __('Service'), 'tyres' => __('Tyres')];
@endphp
<section class="card p-6">
  <div class="flex flex-wrap items-start gap-4">
    <span class="grid size-12 shrink-0 place-items-center rounded-2xl bg-blue-50 text-blue-700"><x-icon name="truck" class="size-6" /></span>
    <div class="min-w-0 flex-1">
      <h2 class="docno break-all font-mono text-xl font-semibold sm:text-2xl">{{ $truck->reg_no }}</h2>
      <p class="mt-0.5 text-sm text-ink-500">{{ collect([$truck->make, $truck->model, $types[$truck->truck_type] ?? null, $truck->payload_mt ? rtrim(rtrim((string) $truck->payload_mt, '0'), '.') . ' MT' : null])->filter()->join(' · ') }}</p>
      <div class="mt-2 flex flex-wrap gap-1.5">
        <span class="pill {{ $tones[$truck->status] ?? '' }}">{{ __(\App\Modules\Fleet\Models\Truck::STATUSES[$truck->status]) }}</span>
        @if ($truck->ownership !== 'own')<span class="pill bg-violet-50 text-violet-700">{{ __(ucfirst($truck->ownership)) }}@if ($truck->owner) · {{ $truck->owner->name }}@endif</span>@endif
        @if ($truck->age())<span class="pill bg-ink-100 text-ink-700">{{ __('Age') }} {{ $truck->age() }}</span>@endif
      </div>
    </div>
    <div class="flex flex-wrap gap-2">
      @can('truck.manage')
        @if ($truck->status !== 'on_trip')
          <form method="POST" action="{{ route('trucks.status', $truck) }}" class="flex gap-2">
            @csrf
            <select name="status" class="input py-2" aria-label="{{ __('Change status') }}">
              @foreach (['idle', 'workshop', 'breakdown', 'out_of_service', 'sold'] as $s)<option value="{{ $s }}" @selected($truck->status === $s)>{{ __(\App\Modules\Fleet\Models\Truck::STATUSES[$s]) }}</option>@endforeach
            </select>
            <button class="btn-secondary">{{ __('Set') }}</button>
          </form>
        @endif
        <a href="{{ route('trucks.edit', $truck) }}" class="btn-primary">{{ __('Edit') }}</a>
      @endcan
    </div>
  </div>
  @error('status')<p class="error mt-2">{{ $message }}</p>@enderror
  <nav class="mt-5 flex gap-1 overflow-x-auto border-b border-ink-100" aria-label="{{ __('Truck sections') }}">
    @foreach ($tabs as $key => $label)
      <a href="{{ route('trucks.show', [$truck, 'tab' => $key]) }}" class="-mb-px whitespace-nowrap border-b-2 px-3 py-2 text-sm font-medium {{ $tab === $key ? 'border-brand-600 text-brand-700' : 'border-transparent text-ink-500 hover:text-ink-900' }}" @if ($tab === $key) aria-current="page" @endif>{{ $label }}</a>
    @endforeach
    @can('document.view')<a href="{{ route('documents.owner', ['truck', $truck->id]) }}" class="-mb-px whitespace-nowrap border-b-2 border-transparent px-3 py-2 text-sm font-medium text-ink-500 hover:text-ink-900">{{ __('Documents') }}</a>@endcan
    @foreach ([__('Trips'), __('Fuel'), __('Costs')] as $soon)
      <span class="whitespace-nowrap px-3 py-2 text-sm text-ink-300" title="{{ __('Coming in a later milestone') }}">{{ $soon }}</span>
    @endforeach
  </nav>
</section>

@include('trucks.partials.' . $tab)
@endsection
