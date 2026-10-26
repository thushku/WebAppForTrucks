<section class="card overflow-hidden">
  <div class="flex flex-wrap items-center justify-between gap-3 p-6 pb-4">
    <div>
      <h3 class="text-lg font-semibold">{{ __('Rate contracts') }}</h3>
      <p class="mt-1 text-sm text-ink-500">{{ __('Approved rates fill new trips and LRs automatically. Change an approved contract by making a new version from a date.') }}</p>
    </div>
    @can('party.manage')<a href="{{ route('parties.rates.create', $party) }}" class="btn-primary"><x-icon name="plus" /> {{ __('New rate contract') }}</a>@endcan
  </div>
  <div class="overflow-x-auto">
    <table class="table">
      <thead><tr><th>{{ __('Contract') }}</th><th>{{ __('Valid') }}</th><th>{{ __('Routes') }}</th><th>{{ __('Status') }}</th><th></th></tr></thead>
      <tbody>
        @forelse ($party->rateContracts as $c)
          <tr class="{{ $c->status === 'cancelled' ? 'opacity-50' : '' }}">
            <td class="font-medium">{{ $c->name }} <span class="text-xs text-ink-400">v{{ $c->version }}</span></td>
            <td class="nowrap text-sm">{{ $c->valid_from->format('d M Y') }} – {{ $c->valid_to?->format('d M Y') ?? __('open') }}</td>
            <td>{{ $c->lines_count }}</td>
            <td>
              @switch($c->status)
                @case('approved')<span class="pill bg-emerald-50 text-paid">{{ __('Approved') }}</span>@break
                @case('draft')<span class="pill bg-amber-50 text-partial">{{ __('Draft') }}</span>@break
                @default<span class="pill bg-ink-100 text-ink-500">{{ __('Cancelled') }}</span>
              @endswitch
            </td>
            <td class="text-end">@can('party.manage')<a href="{{ route('parties.rates.edit', [$party, $c]) }}" class="text-sm font-semibold text-brand-700 hover:underline">{{ $c->isEditable() ? __('Edit') : __('View') }}</a>@endcan</td>
          </tr>
        @empty
          <tr><td colspan="5" class="py-10 text-center text-ink-500">{{ __('No rates yet. Add the routes and prices you agreed.') }}</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</section>
