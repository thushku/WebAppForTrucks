@extends('layouts.app')
@section('title', __('Card statements'))
@section('content')
@include('accounts._nav')
@php($m = fn ($v) => \App\Support\Money::company($v))
<section class="card space-y-4 p-6">
  <div class="flex flex-wrap items-end justify-between gap-4">
    <div>
      <h2 class="text-lg font-semibold">{{ __('FASTag and fuel-card statements') }}</h2>
      <p class="text-sm text-ink-500">{{ __('Bring in the CSV from the FASTag or fuel-card portal. Each charge is matched to the crew’s entry paid by that card — same truck, same amount, within a day; a charge nobody entered is booked as the truck’s cost or set aside; entries that claim the card with no charge are listed.') }}</p>
    </div>
    @can('accounts.post')
      <form method="POST" action="{{ route('accounts.cards.import') }}" enctype="multipart/form-data" class="flex flex-wrap items-end gap-2">@csrf
        <div><label class="label" for="cd_kind">{{ __('Card') }}</label><select id="cd_kind" name="kind" class="input">@foreach (\App\Modules\Accounts\Models\CardStatement::KINDS as $k => $l)<option value="{{ $k }}">{{ __($l) }}</option>@endforeach</select></div>
        <div><label class="label" for="cd_file">{{ __('Statement (CSV)') }}</label><input id="cd_file" name="file" type="file" accept=".csv,text/csv" class="input" required></div>
        <button class="btn-primary"><x-icon name="upload" class="size-4" /> {{ __('Bring it in') }}</button>
      </form>
    @endcan
  </div>
  @error('file')<p class="error">{{ $message }}</p>@enderror @error('kind')<p class="error">{{ $message }}</p>@enderror
  @if ($open->isNotEmpty())
    <p class="rounded-xl bg-amber-50 px-4 py-2 text-sm text-amber-900">{{ $open->map(fn ($o) => __(':kind: :n charges to look at, :amount', ['kind' => __(\App\Modules\Accounts\Models\CardStatement::KINDS[$o->kind] ?? $o->kind), 'n' => $o->n, 'amount' => $m($o->total)]))->join(' · ') }}</p>
  @endif
</section>

<section class="card overflow-hidden">
  <div class="relative overflow-x-auto">
    <table class="table">
      <thead><tr><th>{{ __('Statement') }}</th><th>{{ __('Card') }}</th><th>{{ __('Period') }}</th><th class="text-end">{{ __('Charges') }}</th><th class="text-end">{{ __('To look at') }}</th><th>{{ __('Brought in') }}</th></tr></thead>
      <tbody>
        @forelse ($statements as $s)
          <tr>
            <td class="text-sm"><a href="{{ route('accounts.cards.show', $s) }}" class="font-medium text-brand-700 hover:underline">{{ $s->file_name }}</a></td>
            <td class="text-sm">{{ __(\App\Modules\Accounts\Models\CardStatement::KINDS[$s->kind] ?? $s->kind) }}</td>
            <td class="whitespace-nowrap text-sm">{{ $s->period_from?->format('d M') }} – {{ $s->period_to?->format('d M Y') }}</td>
            <td class="text-end text-sm">{{ $s->lines }}@if ($s->skipped)<div class="text-xs text-ink-500">{{ __(':n left out', ['n' => $s->skipped]) }}</div>@endif</td>
            <td class="text-end text-sm {{ $s->open ? 'font-semibold text-partial' : '' }}">{{ $s->open }}</td>
            <td class="whitespace-nowrap text-sm">{{ $s->created_at->format('d M Y') }}</td>
          </tr>
        @empty
          <tr><td colspan="6" class="py-10 text-center text-ink-500">{{ __('No statement brought in yet.') }}</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</section>
@endsection
