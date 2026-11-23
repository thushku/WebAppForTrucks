@extends('layouts.app')
@section('title', __('Service due'))
@section('content')
<div class="flex flex-wrap items-end justify-between gap-3">
  <div>
    <a href="{{ route('trucks.index') }}" class="inline-flex items-center gap-1 text-sm text-brand-700 hover:underline"><x-icon name="arrow-left" class="size-4" /> {{ __('Trucks') }}</a>
    <h1 class="mt-1 font-display text-2xl font-semibold">{{ __('Service due') }}</h1>
    <p class="text-sm text-ink-500">{{ __('Every service overdue or coming up, with the date it is likely from each truck’s own running of the last :days days — to book the workshop between trips.', ['days' => \App\Modules\Fleet\Services\Maintenance::PACE_DAYS]) }}</p>
  </div>
  <div class="flex flex-wrap gap-2" role="tablist">
    @foreach (\App\Http\Controllers\Fleet\ServiceDueController::WINDOWS as $d)
      <a href="{{ route('trucks.service-due', ['days' => $d]) }}" role="tab" aria-selected="{{ $days === $d ? 'true' : 'false' }}" class="rounded-xl border px-3 py-2 text-sm font-medium {{ $days === $d ? 'border-brand-300 bg-brand-50 text-brand-700' : 'border-ink-100 bg-white text-ink-700' }}">{{ trans_choice(':count day|:count days', $d) }}</a>
    @endforeach
  </div>
</div>

<section class="card mt-5 overflow-hidden">
  <div class="relative overflow-x-auto">
    <table class="table">
      <thead><tr><th>{{ __('Truck') }}</th><th>{{ __('Service') }}</th><th>{{ __('Due') }}</th><th>{{ __('Likely') }}</th><th></th></tr></thead>
      <tbody>
        @forelse ($rows as $r)
          @php($s = $r['schedule'])
          <tr>
            <td class="whitespace-nowrap"><a href="{{ route('trucks.show', [$r['truck'], 'tab' => 'service']) }}" class="docno font-mono font-semibold text-brand-700 hover:underline">{{ $r['truck']->reg_no }}</a>
              @if ($r['truck']->status !== 'idle')<div class="text-xs text-ink-500">{{ __(\App\Modules\Fleet\Models\Truck::STATUSES[$r['truck']->status] ?? $r['truck']->status) }}</div>@endif</td>
            <td class="text-sm font-medium">{{ $s->name }}<div class="text-xs font-normal text-ink-500">{{ collect([$s->every_km ? __('every :km km', ['km' => number_format($s->every_km)]) : null, $s->every_days ? trans_choice('every :count day|every :count days', $s->every_days) : null])->filter()->join(' / ') }}</div></td>
            <td class="text-sm">
              <span class="pill {{ ['overdue' => 'bg-red-50 text-overdue', 'soon' => 'bg-amber-50 text-partial', 'ok' => 'bg-ink-100 text-ink-700'][$r['state']] ?? 'bg-ink-100 text-ink-500' }}">{{ collect([$r['due_km'] ? number_format($r['due_km']) . ' km' : null, $r['due_on']?->format('d M Y')])->filter()->join(' / ') ?: '—' }}</span>
              @if ($r['km_left'] !== null)<div class="mt-1 text-xs {{ $r['km_left'] < 0 ? 'font-semibold text-overdue' : 'text-ink-500' }}">{{ $r['km_left'] < 0 ? __(':km km over', ['km' => number_format(-$r['km_left'])]) : __(':km km to go', ['km' => number_format($r['km_left'])]) }}</div>@endif
            </td>
            <td class="whitespace-nowrap text-sm">
              @if ($r['state'] === 'overdue')<span class="font-semibold text-overdue">{{ __('Now') }}</span>
              @elseif ($r['expected_on'])
                {{ $r['expected_on']->format('d M Y') }}
                <div class="text-xs text-ink-500">{{ $r['by'] === 'km' ? __('at :pace km a day', ['pace' => number_format($r['pace'])]) : __('by date') }}</div>
              @else — @endif
            </td>
            <td class="text-end">@can('complaint.manage')<a href="{{ route('trucks.service.create', [$r['truck'], 'schedule' => $s->id]) }}" class="text-xs font-semibold text-brand-700 hover:underline">{{ __('Record service') }}</a>@endcan</td>
          </tr>
        @empty
          <tr><td colspan="5" class="py-10 text-center text-ink-500">{{ trans_choice('Nothing due in the next :count day.|Nothing due in the next :count days.', $days) }}</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</section>

@if ($without->isNotEmpty())
  <p class="mt-4 text-sm text-ink-500">{{ __('No service schedule yet:') }} @foreach ($without as $t)<a href="{{ route('trucks.show', [$t, 'tab' => 'service']) }}" class="font-mono text-brand-700 hover:underline">{{ $t->reg_no }}</a>@if (! $loop->last), @endif @endforeach</p>
@endif
@endsection
