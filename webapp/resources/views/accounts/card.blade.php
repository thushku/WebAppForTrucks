@extends('layouts.app')
@section('title', $statement->file_name)
@section('content')
@include('accounts._nav')
@php
  $m = fn ($v) => \App\Support\Money::company($v);
  $canPost = auth()->user()->can('accounts.post');
  $tz = app(\App\Platform\Tenancy\CompanyContext::class)->company()->timezone ?: 'UTC';
  $tones = ['open' => 'bg-amber-50 text-partial', 'matched' => 'bg-emerald-50 text-paid', 'booked' => 'bg-brand-50 text-brand-700', 'ignored' => 'bg-ink-100 text-ink-500'];
  $tabs = ['all' => __('All')] + array_map(fn ($l) => __($l), \App\Modules\Accounts\Models\CardStatementLine::STATUSES);
@endphp
<section class="card p-6">
  <a href="{{ route('accounts.cards') }}" class="inline-flex items-center gap-1 text-sm text-brand-700 hover:underline"><x-icon name="arrow-left" class="size-4" /> {{ __('Card statements') }}</a>
  <h2 class="mt-1 text-lg font-semibold">{{ __(\App\Modules\Accounts\Models\CardStatement::KINDS[$statement->kind] ?? $statement->kind) }} · {{ $statement->file_name }}</h2>
  <p class="text-sm text-ink-500">{{ $statement->period_from?->format('d M') }} – {{ $statement->period_to?->format('d M Y') }} · {{ trans_choice(':count charge|:count charges', $statement->lines) }}</p>
  <div class="mt-4 flex flex-wrap gap-2" role="tablist">
    @foreach ($tabs as $key => $label)
      <a href="{{ route('accounts.cards.show', [$statement, 'show' => $key]) }}" role="tab" aria-selected="{{ $show === $key ? 'true' : 'false' }}" class="rounded-xl border px-3 py-2 text-sm font-medium {{ $show === $key ? 'border-brand-300 bg-brand-50 text-brand-700' : 'border-ink-100 bg-white text-ink-700' }}">{{ $label }}@if ($key !== 'all') <span class="text-xs text-ink-400">{{ $counts[$key] ?? 0 }}</span>@endif</a>
    @endforeach
  </div>
</section>

<section class="card overflow-hidden">
  <div class="relative overflow-x-auto">
    <table class="table">
      <thead><tr><th>{{ __('When') }}</th><th>{{ __('Vehicle') }}</th><th>{{ __('Where') }}</th><th class="text-end">{{ __('Amount') }}</th><th>{{ __('Status') }}</th><th>{{ __('Entry or voucher') }}</th></tr></thead>
      <tbody>
        @forelse ($lines as $l)
          <tr>
            <td class="whitespace-nowrap text-sm">{{ $l->txn_at->setTimezone($tz)->format('d M H:i') }}<div class="font-mono text-xs text-ink-400">{{ \Illuminate\Support\Str::limit($l->reference, 18) }}</div></td>
            <td class="whitespace-nowrap text-sm">@if ($l->truck)<a href="{{ route('trucks.show', $l->truck_id) }}" class="font-mono hover:text-brand-700">{{ $l->truck->reg_no }}</a>@else<span class="font-mono text-ink-500">{{ $l->vehicle ?: '—' }}</span>@if ($l->vehicle)<div class="text-xs text-overdue">{{ __('Not one of our trucks') }}</div>@endif @endif
              @if ($l->trip)<div class="text-xs"><a href="{{ route('trips.show', $l->trip_id) }}" class="font-mono text-ink-500 hover:text-brand-700">{{ $l->trip->trip_number }}</a></div>@endif</td>
            <td class="text-sm">{{ $l->place ?: '—' }}</td>
            <td class="money whitespace-nowrap text-end text-sm">{{ $m($l->amount) }}</td>
            <td class="text-sm"><span class="pill {{ $tones[$l->status] ?? '' }}">{{ __(\App\Modules\Accounts\Models\CardStatementLine::STATUSES[$l->status]) }}</span>@if ($l->note)<div class="text-xs text-ink-500">{{ $l->note }}</div>@endif</td>
            <td class="text-sm">
              @if ($l->status === 'matched' && $l->event)
                {{ $l->event->person?->name }} · {{ $l->event->trusted_at->setTimezone($tz)->format('d M H:i') }} · {{ $m($l->event->amount) }}@if ($l->match_how === 'hand') <span class="text-xs text-ink-500">({{ __('by hand') }})</span>@endif
                @if ($canPost)<form method="POST" action="{{ route('accounts.cards.line', $l) }}" class="inline">@csrf<button name="action" value="undo" class="text-xs text-ink-500 hover:underline">{{ __('Undo') }}</button></form>@endif
              @elseif ($l->status === 'booked' && $l->voucher)
                <a href="{{ route('accounts.vouchers.show', $l->voucher_id) }}" class="font-mono text-brand-700 hover:underline">{{ $l->voucher->number }}</a>
              @elseif ($l->status === 'open' && $canPost)
                <div class="flex flex-wrap items-center gap-1.5">
                  @if (($candidates[$l->id] ?? collect())->isNotEmpty())
                    <form method="POST" action="{{ route('accounts.cards.line', $l) }}" class="flex gap-1.5">@csrf
                      <select name="event_id" class="input py-1.5 text-sm" aria-label="{{ __('Crew entry') }}">@foreach ($candidates[$l->id] as $e)<option value="{{ $e->id }}">{{ $e->person?->name }} · {{ $e->trusted_at->setTimezone($tz)->format('d M H:i') }} · {{ $m($e->amount) }}</option>@endforeach</select>
                      <button name="action" value="match" class="btn-secondary py-1.5 text-sm">{{ __('Match') }}</button>
                    </form>
                  @endif
                  <form method="POST" action="{{ route('accounts.cards.line', $l) }}">@csrf<button name="action" value="book" class="btn-primary py-1.5 text-sm">{{ __('Book it') }}</button></form>
                  <form method="POST" action="{{ route('accounts.cards.line', $l) }}" class="flex gap-1.5">@csrf
                    <input name="note" class="input w-32 py-1.5 text-sm" maxlength="255" placeholder="{{ __('Why') }}" aria-label="{{ __('Why it is set aside') }}">
                    <button name="action" value="ignore" class="btn-secondary py-1.5 text-sm">{{ __('Set aside') }}</button>
                  </form>
                </div>
              @endif
            </td>
          </tr>
        @empty
          <tr><td colspan="6" class="py-10 text-center text-ink-500">{{ __('Nothing here.') }}</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
  @error('line')<p class="error px-6 pb-4">{{ $message }}</p>@enderror @error('note')<p class="error px-6 pb-4">{{ $message }}</p>@enderror @error('event')<p class="error px-6 pb-4">{{ $message }}</p>@enderror
</section>

@if ($unmatched->isNotEmpty())
<section class="card overflow-hidden" data-without-charge>
  <h3 class="px-6 pt-5 font-semibold">{{ trans_choice(':count entry says the card paid, but the statement has no charge|:count entries say the card paid, but the statement has no charge', $unmatched->count()) }}</h3>
  <p class="px-6 text-sm text-ink-500">{{ __('Worth a question to the crew: paid in cash and entered as card, entered twice, or a charge still to come.') }}</p>
  <div class="relative mt-2 overflow-x-auto">
    <table class="table">
      <thead><tr><th>{{ __('When') }}</th><th>{{ __('Who') }}</th><th>{{ __('Trip') }}</th><th class="text-end">{{ __('Amount') }}</th><th>{{ __('Status') }}</th></tr></thead>
      <tbody>
        @foreach ($unmatched as $e)
          <tr>
            <td class="whitespace-nowrap text-sm">{{ $e->trusted_at->setTimezone($tz)->format('d M H:i') }}</td>
            <td class="text-sm">{{ $e->person?->name }}</td>
            <td class="text-sm"><a href="{{ route('trips.show', $e->trip_id) }}" class="font-mono hover:text-brand-700">{{ $e->trip?->trip_number }}</a> <span class="font-mono text-xs text-ink-500">{{ $e->trip?->truck?->reg_no }}</span></td>
            <td class="money text-end text-sm">{{ $m($e->amount) }}</td>
            <td class="text-sm">{{ __(\App\Modules\Road\Models\RoadEvent::STATUSES[$e->status] ?? $e->status) }}</td>
          </tr>
        @endforeach
      </tbody>
    </table>
  </div>
</section>
@endif
@endsection
