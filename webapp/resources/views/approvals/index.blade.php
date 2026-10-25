@extends('layouts.app')
@section('title', __('Approvals'))
@section('content')
@php
  use App\Modules\Road\Services\EventChecks;
  use App\Modules\Road\Services\EventTypes;
  use App\Http\Controllers\Trips\ApprovalController;

  $tz = app(\App\Platform\Tenancy\CompanyContext::class)->company()->timezone ?: 'UTC';
  $approvals = app(\App\Modules\Road\Services\Approvals::class);
  $canVerify = auth()->user()->can('event.verify');
@endphp
<div class="flex flex-wrap items-center justify-between gap-3">
  <div class="flex flex-wrap gap-1.5" role="tablist" aria-label="{{ __('Status') }}">
    @foreach (ApprovalController::TABS as $key => $label)
      <a href="{{ route('approvals.index', array_filter(['status' => $key, 'type' => $filters['type'] ?? null, 'q' => $filters['q'] ?? null])) }}" role="tab" aria-selected="{{ $status === $key ? 'true' : 'false' }}"
         class="rounded-xl border px-3 py-2 text-sm font-medium {{ $status === $key ? 'border-brand-300 bg-brand-50 text-brand-700' : 'border-ink-100 bg-white text-ink-700' }}">
        {{ __($label) }}<span class="ms-1 text-xs {{ ($counts[$key] ?? 0) ? 'font-semibold text-partial' : 'text-ink-400' }}">{{ $counts[$key] ?? 0 }}</span>
      </a>
    @endforeach
  </div>
  <form method="GET" action="{{ route('approvals.index') }}" class="flex flex-wrap items-end gap-2">
    <input type="hidden" name="status" value="{{ $status }}">
    <select name="type" class="input w-48 py-2" aria-label="{{ __('Kind of entry') }}">
      <option value="">{{ __('Every kind') }}</option>
      @foreach (EventTypes::TYPES as $code => $t)@if ($t[2] !== 'info')<option value="{{ $code }}" @selected(($filters['type'] ?? null) === $code)>{{ __($t[0]) }}</option>@endif @endforeach
    </select>
    <input name="q" value="{{ $filters['q'] ?? '' }}" class="input w-40 py-2" maxlength="40" placeholder="{{ __('Trip no.') }}" aria-label="{{ __('Trip no.') }}">
    <button class="btn-secondary"><x-icon name="search" /> {{ __('Show') }}</button>
  </form>
</div>

@if ($status === 'waiting_2nd')
  <p class="rounded-2xl bg-amber-50 px-5 py-3 text-sm text-amber-900">{{ __('Payments without receipt wait for the other driver on the trip. On a trip with one driver you give the second approval here (Settings → Rules).') }}</p>
@endif

<form id="bulk" method="POST" action="{{ route('approvals.many') }}">@csrf</form>

<section class="card overflow-hidden">
  <div class="overflow-x-auto">
    <table class="table">
      <thead>
        <tr>
          <th class="w-8">@if ($canVerify && $status === 'waiting_check')<input type="checkbox" data-check-all="events" aria-label="{{ __('Tick every entry without flags') }}">@endif</th>
          <th>{{ __('When') }}</th><th>{{ __('Trip') }}</th><th>{{ __('Entry') }}</th><th class="text-end">{{ __('Amount') }}</th><th>{{ __('Photos') }}</th><th>{{ __('Checks') }}</th><th class="text-end">{{ __('Decide') }}</th>
        </tr>
      </thead>
      <tbody>
        @forelse ($events as $e)
          @php($actions = $canVerify ? $approvals->actions($e) : [])
          <tr class="align-top">
            <td>@if ($canVerify && $status === 'waiting_check')<input type="checkbox" form="bulk" name="events[]" value="{{ $e->id }}" data-check-item="events" @disabled(! empty($e->flags)) aria-label="{{ __('Tick') }}" @if ($e->flags) title="{{ __('Has flags: look at it on its own') }}" @endif>@endif</td>
            <td class="whitespace-nowrap font-mono text-xs text-ink-500">{{ $e->trusted_at->setTimezone($tz)->format('d M H:i') }}</td>
            <td><a href="{{ route('trips.show', $e->trip_id) }}#road" class="docno font-mono text-xs font-semibold text-brand-700 hover:underline">{{ $e->trip->trip_number }}</a><div class="text-xs text-ink-500">{{ $e->person->name }}</div></td>
            <td class="text-sm">
              <div class="font-medium">{{ EventTypes::label($e->type) }}</div>
              <div class="text-xs text-ink-500">
                {{ collect([$e->payment_mode ? __(EventTypes::PAYMENT_MODES[$e->payment_mode][0] ?? $e->payment_mode) : null, $e->data['litres'] ?? null ? $e->data['litres'] . ' L' : null,
                    $e->data['pump'] ?? $e->data['plaza'] ?? $e->data['what'] ?? $e->data['reason'] ?? $e->data['receiver_name'] ?? null, $e->odometer ? number_format($e->odometer) . ' km' : null])->filter()->join(' · ') }}
              </div>
              @if ($e->decision_note && $e->status === 'queried')<div class="mt-1 text-xs text-partial">{{ __('Asked: :q', ['q' => $e->decision_note]) }}</div>@endif
              @if ($e->second_reject_reason)<div class="mt-1 text-xs text-overdue">{{ __('2nd driver: :reason', ['reason' => $e->second_reject_reason]) }}</div>@endif
            </td>
            <td class="money whitespace-nowrap text-end">{{ $e->amount !== null ? \App\Support\Money::company($e->amount) : '—' }}</td>
            <td>
              <div class="flex gap-1">
                @foreach ($e->photos as $p)
                  <a href="{{ route('approvals.photo', [$e, $p]) }}" target="_blank" rel="noopener"><img src="{{ route('approvals.photo', [$e, $p]) }}" alt="{{ __(ucfirst(str_replace('_', ' ', $p->slot))) }}" loading="lazy" class="h-10 w-10 rounded-md border object-cover {{ $p->duplicate_of ? 'border-amber-400' : 'border-ink-200' }}"></a>
                @endforeach
                @if ($e->photos_expected > $e->photos->count())<span class="text-xs text-ink-500">{{ $e->photos->count() }}/{{ $e->photos_expected }}</span>@endif
              </div>
            </td>
            <td>
              @forelse ($e->flags ?? [] as $flag)<div class="text-xs text-partial"><x-icon name="alert" class="inline size-3" /> {{ __(EventChecks::LABELS[$flag] ?? $flag) }}</div>@empty<span class="text-xs text-paid">{{ __('No flags') }}</span>@endforelse
            </td>
            <td class="text-end">
              @if ($actions)
                @include('approvals._decide', ['event' => $e, 'actions' => $actions])
              @elseif ($e->status === 'waiting_2nd')
                <span class="text-xs text-ink-500">{{ __('Waiting for the other driver') }}</span>
              @endif
            </td>
          </tr>
        @empty
          <tr><td colspan="8" class="py-10 text-center text-ink-500">{{ __('Nothing is waiting here.') }}</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
  @if ($canVerify && $status === 'waiting_check' && $events->isNotEmpty())
    <div class="flex flex-wrap items-center justify-between gap-3 border-t border-ink-100 px-6 py-4">
      <p class="text-xs text-ink-500">{{ __('Entries with flags are approved one by one, after a look at their photos.') }}</p>
      <button form="bulk" class="btn-primary"><x-icon name="check" /> {{ __('Approve ticked') }}</button>
    </div>
  @endif
</section>
{{ $events->links() }}
@endsection
