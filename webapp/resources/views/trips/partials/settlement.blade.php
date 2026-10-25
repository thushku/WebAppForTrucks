{{-- Settlement (the "hisaab", blueprint 03 §8 / 04 §3.5): each crew member's trip cash, settled one by one. --}}
@php
  use App\Modules\Trips\Models\TripSettlement;
  $settler = app(\App\Modules\Trips\Services\SettlementService::class);
  $money = fn ($v) => \App\Support\Money::company($v);
@endphp
<section class="card p-6" id="settlement">
  <div class="flex flex-wrap items-center justify-between gap-3">
    <h3 class="font-semibold">{{ __('Settlement') }}</h3>
    @if ($t->settled_at)<span class="text-sm text-paid">{{ __('Settled :date', ['date' => $t->settled_at->format('d M Y')]) }}</span>@endif
  </div>

  @if ($t->status === 'ended')
    @if ($blockers)
      <p class="mt-3 flex gap-2 rounded-xl bg-amber-50 px-3 py-2 text-sm text-amber-900"><x-icon name="alert" class="size-4 shrink-0" /> {{ $settler->blockerText($blockers) }}
        @can('event.verify')<a href="#road" class="ms-auto font-medium underline">{{ __('See the entries') }}</a>@endcan</p>
    @else
      <p class="mt-3 flex gap-2 rounded-xl bg-emerald-50 px-3 py-2 text-sm text-emerald-900"><x-icon name="check" class="size-4 shrink-0" /> {{ __('Every entry is decided.') }}
        {{ $live->where('pod_status', 'none')->isNotEmpty() ? __('POD not yet in for :n load(s).', ['n' => $live->where('pod_status', 'none')->count()]) : __('POD in.') }}</p>
    @endif
  @endif

  @if ($accounts->isEmpty())
    <p class="mt-4 text-sm text-ink-500">{{ __('No crew of ours on this trip: nothing to settle in cash.') }}</p>
    @if ($t->status === 'ended')
      @can('trip.settle')
        <form method="POST" action="{{ route('trips.settle.all', $t) }}" class="mt-3">@csrf<button class="btn-primary" @disabled($blockers)>{{ __('Mark settled') }}</button></form>
      @endcan
    @endif
  @else
    <div class="mt-4 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
      @foreach ($accounts as $personId => $a)
        @php($s = $a['settlement'])
        <article class="rounded-2xl border border-ink-100 p-4">
          <div class="flex items-baseline justify-between gap-2">
            <h4 class="font-semibold">{{ $a['person']->name }}</h4>
            <span class="text-xs text-ink-500">{{ __(\App\Modules\Trips\Models\TripCrew::ROLES[$a['role']]) }}</span>
          </div>
          <dl class="mt-3 space-y-1 text-sm">
            <div class="flex justify-between"><dt class="text-ink-500">{{ __('Advances') }}</dt><dd class="money">{{ $money($s?->advances ?? $a['advances']) }}</dd></div>
            <div class="flex justify-between"><dt class="text-ink-500">{{ __('Collected on the road') }}</dt><dd class="money">{{ $money($s?->collected ?? $a['collected']) }}</dd></div>
            <div class="flex justify-between"><dt class="text-ink-500">{{ __('Spent from own pocket') }}</dt><dd class="money">− {{ $money($s?->spent ?? $a['spent']) }}</dd></div>
            @php($balance = (float) ($s?->balance ?? $a['balance']))
            <div class="flex justify-between border-t border-ink-100 pt-1 font-semibold {{ $balance > 0 ? 'text-partial' : ($balance < 0 ? 'text-sent' : '') }}">
              <dt>{{ $balance > 0 ? __('Returns to the office') : ($balance < 0 ? __('The company owes') : __('Balance')) }}</dt><dd class="money">{{ $money(abs($balance)) }}</dd>
            </div>
            @if ($a['waiting'] > 0 && ! $s)<div class="text-xs text-partial">{{ __(':amount still waiting for approval', ['amount' => $money($a['waiting'])]) }}</div>@endif
          </dl>

          @if ($s)
            <p class="mt-3 rounded-xl bg-emerald-50 px-3 py-2 text-xs text-emerald-900">
              <x-icon name="check" class="inline size-3.5" /> {{ __(TripSettlement::MODES[$s->mode]) }}{{ $s->reference ? ' · ' . $s->reference : '' }} · {{ $s->settled_at->format('d M Y') }}
              @if ((float) $s->bata_amount > 0)<br>{{ __('Bata :amount to the personal ledger', ['amount' => $money($s->bata_amount)]) }}{{ $s->bata_days ? ' (' . trans_choice(':count day|:count days', $s->bata_days) . ')' : '' }}@endif
            </p>
          @elseif ($t->status === 'ended')
            @can('trip.settle')
              <form method="POST" action="{{ route('trips.settle', [$t, $a['person']]) }}" class="mt-3 grid gap-2" novalidate>
                @csrf
                @if ($balance == 0.0)
                  <input type="hidden" name="mode" value="nil">
                @else
                  <select name="mode" class="input py-2" aria-label="{{ __('Settled by') }}">
                    @foreach (TripSettlement::MODES as $mode => $label)
                      @continue($mode === 'nil' || ($mode === 'cash_returned' && $balance < 0) || ($mode === 'paid_to_crew' && $balance > 0))
                      <option value="{{ $mode }}">{{ __($label) }}</option>
                    @endforeach
                  </select>
                  <input name="reference" class="input py-2" maxlength="60" placeholder="{{ __('Receipt / UPI ref (optional)') }}" aria-label="{{ __('Reference') }}">
                @endif
                <label class="flex items-center justify-between gap-2 text-xs text-ink-500">
                  <span>{{ __('Bata') }}{{ $a['bata_days'] ? ' · ' . trans_choice(':count day|:count days', $a['bata_days']) : '' }}</span>
                  <input name="bata_amount" type="number" min="0" step="0.01" value="{{ $a['bata'] }}" class="input w-28 py-1.5 text-end" aria-label="{{ __('Bata') }}">
                </label>
                <button class="btn-primary" @disabled($blockers)><x-icon name="check" /> {{ __('Settle :name', ['name' => $a['person']->name]) }}</button>
              </form>
            @endcan
          @endif
        </article>
      @endforeach
    </div>
    <p class="mt-3 text-xs text-ink-500">{{ __('Money the office sends on the way is counted from the advances recorded here. Fuel card, FASTag and credit spending is the company’s money, not the crew’s.') }}</p>
  @endif
  @foreach (['mode', 'person', 'bata_amount'] as $f)@error($f)<p class="error mt-2">{{ $message }}</p>@enderror @endforeach
</section>
