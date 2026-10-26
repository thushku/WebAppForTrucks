@extends('layouts.app')
@section('title', __('Trucks'))
@section('content')
@php
  $tones = ['idle' => 'bg-emerald-50 text-paid', 'on_trip' => 'bg-brand-50 text-brand-700', 'workshop' => 'bg-amber-50 text-partial',
            'breakdown' => 'bg-red-50 text-overdue', 'out_of_service' => 'bg-ink-100 text-ink-500', 'sold' => 'bg-ink-100 text-ink-500'];
@endphp
<div class="flex flex-wrap items-center justify-between gap-3">
  <div class="flex flex-wrap gap-1.5" role="tablist" aria-label="{{ __('Status') }}">
    <a href="{{ route('trucks.index') }}" role="tab" aria-selected="{{ $status === '' ? 'true' : 'false' }}" class="rounded-xl border px-3 py-2 text-sm font-medium {{ $status === '' ? 'border-brand-300 bg-brand-50 text-brand-700' : 'border-ink-100 bg-white text-ink-700' }}">{{ __('In service') }}</a>
    @foreach (\App\Modules\Fleet\Models\Truck::STATUSES as $key => $label)
      @continue(! ($counts[$key] ?? 0) && $status !== $key)
      <a href="{{ route('trucks.index', ['status' => $key]) }}" role="tab" aria-selected="{{ $status === $key ? 'true' : 'false' }}" class="rounded-xl border px-3 py-2 text-sm font-medium {{ $status === $key ? 'border-brand-300 bg-brand-50 text-brand-700' : 'border-ink-100 bg-white text-ink-700' }}">{{ __($label) }} <span class="text-xs text-ink-400">{{ $counts[$key] ?? 0 }}</span></a>
    @endforeach
  </div>
  <div class="flex flex-wrap gap-2">
    <a href="{{ route('trucks.tyres') }}" class="btn-secondary"><x-icon name="tyre" /> {{ __('Tyres') }}</a>
    <a href="{{ route('trucks.emis') }}" class="btn-secondary"><x-icon name="wallet" /> {{ __('EMIs') }}</a>
    @can('truck.manage')<a href="{{ route('trucks.create') }}" class="btn-primary"><x-icon name="plus" /> {{ __('Add truck') }}</a>@endcan
  </div>
</div>

<section class="card overflow-hidden">
  <form method="GET" action="{{ route('trucks.index') }}" class="flex gap-2 border-b border-ink-100 p-4" role="search">
    @if ($status)<input type="hidden" name="status" value="{{ $status }}">@endif
    <input name="q" value="{{ $search }}" class="input max-w-xs" placeholder="{{ __('Number, make or model') }}" maxlength="40" aria-label="{{ __('Search trucks') }}">
    <button class="btn-secondary px-3" aria-label="{{ __('Search') }}"><x-icon name="search" /></button>
  </form>
  <div class="overflow-x-auto">
    <table class="table">
      <thead><tr><th>{{ __('Truck') }}</th><th>{{ __('Type') }}</th><th>{{ __('Status') }}</th><th class="text-end">{{ __('Odometer') }}</th><th>{{ __('Complaints') }}</th></tr></thead>
      <tbody>
        @forelse ($trucks as $truck)
          <tr>
            <td>
              <a href="{{ route('trucks.show', $truck) }}" class="docno font-mono font-semibold text-ink-900 hover:text-brand-700">{{ $truck->reg_no }}</a>
              <div class="text-xs text-ink-500">{{ collect([$truck->make, $truck->model, $truck->year()])->filter()->join(' · ') }}@if ($truck->ownership !== 'own') · {{ __(ucfirst($truck->ownership)) }}@endif</div>
            </td>
            <td class="text-sm">{{ $types[$truck->truck_type] ?? '—' }}</td>
            <td><span class="pill {{ $tones[$truck->status] ?? '' }}">{{ __(\App\Modules\Fleet\Models\Truck::STATUSES[$truck->status]) }}</span></td>
            <td class="nowrap text-end text-sm">{{ $truck->odometer_km !== null ? number_format($truck->odometer_km) . ' km' : '—' }}</td>
            <td>@if ($truck->open_complaints_count)<span class="pill bg-red-50 text-overdue">{{ trans_choice(':count open|:count open', $truck->open_complaints_count) }}</span>@endif</td>
          </tr>
        @empty
          <tr><td colspan="5" class="py-12 text-center text-ink-500">{{ $search !== '' ? __('No truck matches.') : __('No trucks yet — add your first truck.') }}</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
  @if ($trucks->hasPages())<div class="p-4">{{ $trucks->links() }}</div>@endif
</section>
@endsection
