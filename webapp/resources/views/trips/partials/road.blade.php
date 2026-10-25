{{-- What the crew sent from the road (crew app, M6): every entry in time order, with its photos and checks. --}}
@php
  use App\Modules\Road\Models\RoadEvent;
  use App\Modules\Road\Services\EventChecks;
  use App\Modules\Road\Services\EventTypes;

  $tz = app(\App\Platform\Tenancy\CompanyContext::class)->company()->timezone ?: 'UTC';
  $tone = ['info' => 'bg-ink-100 text-ink-700', 'waiting_2nd' => 'bg-amber-50 text-partial', 'waiting_check' => 'bg-sky-50 text-sent', 'approved' => 'bg-emerald-50 text-paid',
    'queried' => 'bg-amber-50 text-partial', 'rejected' => 'bg-red-50 text-overdue', 'disputed' => 'bg-red-50 text-overdue', 'cancelled' => 'bg-ink-100 text-ink-400'];
  $money = $events->where('status', '!=', 'cancelled')->filter->isMoney();
  $approvals = auth()->user()->can('event.verify') ? app(\App\Modules\Road\Services\Approvals::class) : null;
@endphp
<section class="card overflow-hidden" id="road">
  <div class="flex flex-wrap items-center justify-between gap-3 p-6 pb-4">
    <h3 class="font-semibold">{{ __('From the road') }}</h3>
    @if ($money->isNotEmpty())
      <p class="text-sm text-ink-500">
        {{ trans_choice(':count expense|:count expenses', $money->where('type', '!=', 'money_received')->count()) }}
        @if ($canMoney) · <span class="money">{{ \App\Support\Money::company($money->where('type', '!=', 'money_received')->sum('amount')) }}</span>@endif
        @if ($money->whereIn('status', ['waiting_2nd', 'waiting_check', 'queried', 'disputed'])->isNotEmpty())
          · <span class="text-partial">{{ trans_choice(':count waiting|:count waiting', $money->whereIn('status', RoadEvent::OPEN)->count()) }}</span>
        @endif
      </p>
    @endif
  </div>
  @if ($events->isEmpty())
    <p class="px-6 pb-6 text-sm text-ink-500">{{ $t->status === 'draft' ? __('The crew’s entries appear here once the trip is sent to their app.') : __('Nothing from the crew app yet.') }}</p>
  @else
    <ol class="divide-y divide-ink-100 border-t border-ink-100">
      @foreach ($events as $e)
        <li class="flex flex-wrap items-start gap-x-4 gap-y-1 px-6 py-3 text-sm {{ $e->status === 'cancelled' ? 'opacity-50' : '' }}">
          <time class="w-24 shrink-0 font-mono text-xs text-ink-500" datetime="{{ $e->trusted_at->toIso8601String() }}">{{ $e->trusted_at->setTimezone($tz)->format('d M H:i') }}</time>
          <div class="min-w-0 flex-1">
            <div class="flex flex-wrap items-center gap-2">
              <span class="font-medium">{{ EventTypes::label($e->type) }}</span>
              @if ($e->amount !== null && $canMoney)<span class="money">{{ \App\Support\Money::company($e->amount) }}</span>@endif
              @if ($e->payment_mode)<span class="text-xs text-ink-500">{{ __(EventTypes::PAYMENT_MODES[$e->payment_mode][0] ?? $e->payment_mode) }}</span>@endif
              @if ($e->odometer)<span class="text-xs text-ink-500">{{ number_format($e->odometer) }} km</span>@endif
              @if ($e->status !== 'info')<span class="pill {{ $tone[$e->status] ?? 'bg-ink-100' }}">{{ __(RoadEvent::STATUSES[$e->status]) }}</span>@endif
            </div>
            <div class="text-xs text-ink-500">
              {{ $e->person->name }}
              @php($d = $e->data ?? [])
              @foreach (['litres' => __(':v L'), 'pump' => ':v', 'workshop' => ':v', 'plaza' => ':v', 'what' => ':v', 'reason' => ':v', 'receiver_name' => __('received by :v'), 'description' => ':v', 'slip_no' => __('slip :v')] as $key => $pattern)
                @if (! empty($d[$key])) · {{ str_replace(':v', is_numeric($d[$key]) ? rtrim(rtrim(number_format((float) $d[$key], 2), '0'), '.') : $d[$key], $pattern) }}@endif
              @endforeach
              @if ($e->note) · “{{ $e->note }}”@endif
              @if ($e->lat !== null) · <a class="hover:text-brand-700 hover:underline" href="https://www.openstreetmap.org/?mlat={{ $e->lat }}&mlon={{ $e->lng }}#map=15/{{ $e->lat }}/{{ $e->lng }}" target="_blank" rel="noopener">{{ __('map') }}</a>@endif
              @if ($e->second_reject_reason) · <span class="text-overdue">{{ __('2nd driver: :reason', ['reason' => $e->second_reject_reason]) }}</span>@endif
              @if ($e->cancel_reason) · {{ __('Cancelled: :reason', ['reason' => $e->cancel_reason]) }}@endif
            </div>
            @if ($e->flags)
              <ul class="mt-1 flex flex-wrap gap-1">
                @foreach ($e->flags as $flag)<li class="pill bg-amber-50 text-partial"><x-icon name="alert" /> {{ __(EventChecks::LABELS[$flag] ?? $flag) }}</li>@endforeach
              </ul>
            @endif
            @if ($e->approvals->isNotEmpty())
              <ul class="mt-1 space-y-0.5 text-xs text-ink-500">
                @foreach ($e->approvals as $a)
                  <li>{{ __(\App\Modules\Road\Models\EventApproval::DECISIONS[$a->decision] ?? $a->decision) }} · {{ $a->who() }} · {{ $a->created_at->setTimezone($tz)->format('d M H:i') }}@if ($a->note) · “{{ $a->note }}”@endif</li>
                @endforeach
              </ul>
            @endif
          </div>
          @if ($approvals && ($actions = $approvals->actions($e)))
            <div class="w-full sm:w-auto sm:order-last">@include('approvals._decide', ['event' => $e, 'actions' => $actions])</div>
          @endif
          @if ($e->photos->isNotEmpty() || $e->photos_expected > 0)
            <div class="flex shrink-0 items-center gap-1.5">
              @foreach ($e->photos as $p)
                <a href="{{ route('trips.photo', [$t, $p]) }}" target="_blank" rel="noopener" title="{{ __(ucfirst(str_replace('_', ' ', $p->slot))) }}">
                  <img src="{{ route('trips.photo', [$t, $p]) }}" alt="{{ __(ucfirst(str_replace('_', ' ', $p->slot))) }}" loading="lazy" class="h-12 w-12 rounded-md border object-cover {{ $p->duplicate_of ? 'border-amber-400' : 'border-ink-200' }}">
                </a>
              @endforeach
              @if ($e->photos_expected > $e->photos->count())<span class="text-xs text-ink-500">{{ __(':n of :m photos', ['n' => $e->photos->count(), 'm' => $e->photos_expected]) }}</span>@endif
            </div>
          @endif
        </li>
      @endforeach
    </ol>
  @endif
</section>
