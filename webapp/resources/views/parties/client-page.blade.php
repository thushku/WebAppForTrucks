<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>{{ $party->name }} — {{ $company->name }}</title>
@vite(['resources/css/app.css'])
</head>
<body class="min-h-screen bg-ink-50">
{{-- A client's own page (M47): their loads with tracking, their open bills. --}}
@php($m = fn ($v) => \App\Support\Money::format((float) $v, $company->currency))
<main class="mx-auto max-w-3xl space-y-4 px-4 py-8">
  <header class="card p-6">
    <p class="text-sm text-ink-500">{{ $company->name }}</p>
    <h1 class="mt-1 text-2xl font-semibold">{{ $party->name }}</h1>
    <p class="mt-1 text-sm text-ink-500">{{ __('Your loads of the last :days days and your open bills.', ['days' => \App\Modules\Parties\Services\ClientPage::DAYS]) }}</p>
    <div class="mt-4 grid grid-cols-2 gap-3">
      <div class="rounded-xl bg-ink-50 p-3"><div class="text-xs text-ink-500">{{ __('Due') }}</div><div class="money text-lg font-semibold">{{ $m($due) }}</div></div>
      <div class="rounded-xl bg-ink-50 p-3"><div class="text-xs text-ink-500">{{ __('Overdue') }}</div><div class="money text-lg font-semibold {{ $overdue > 0 ? 'text-overdue' : '' }}">{{ $m($overdue) }}</div></div>
    </div>
  </header>

  <section class="card overflow-hidden" data-loads>
    <h2 class="px-6 pt-5 font-semibold">{{ __('Loads') }}</h2>
    <ul class="mt-2 divide-y divide-ink-100">
      @forelse ($loads as $r)
        <li class="flex flex-wrap items-center justify-between gap-2 px-6 py-3 text-sm">
          <div class="min-w-0"><span class="docno font-mono font-semibold">{{ $r->load->lr_number }}</span> · {{ $r->load->lr_date?->format('d M') }}<div class="text-xs text-ink-500">{{ $r->route ?: '—' }}</div></div>
          <div class="flex items-center gap-3"><span class="pill bg-ink-100 text-ink-700">{{ __(\App\Modules\Trips\Models\Consignment::STATUSES[$r->load->status] ?? $r->load->status) }}</span>
            @if ($r->track)<a href="{{ $r->track }}" class="font-semibold text-brand-700 hover:underline">{{ __('Track') }}</a>@endif</div>
        </li>
      @empty
        <li class="px-6 py-6 text-sm text-ink-500">{{ __('No load in the last :days days.', ['days' => \App\Modules\Parties\Services\ClientPage::DAYS]) }}</li>
      @endforelse
    </ul>
  </section>

  <section class="card overflow-hidden" data-bills>
    <h2 class="px-6 pt-5 font-semibold">{{ __('Open bills') }}</h2>
    <ul class="mt-2 divide-y divide-ink-100">
      @forelse ($bills as $b)
        <li class="flex flex-wrap items-center justify-between gap-2 px-6 py-3 text-sm">
          <div class="min-w-0"><span class="docno font-mono font-semibold">{{ $b->number }}</span> · {{ $b->issue_date?->format('d M Y') }}
            <div class="text-xs {{ $b->due_date && $b->due_date->toDateString() < $today ? 'font-semibold text-overdue' : 'text-ink-500' }}">{{ __('Due :date', ['date' => $b->due_date?->format('d M Y')]) }}</div></div>
          <div class="flex items-center gap-3"><span class="money font-semibold">{{ $m($b->balance_due) }}</span>
            @if ($b->public_token)<a href="{{ route('documents.public', $b->public_token) }}" class="font-semibold text-brand-700 hover:underline">{{ __('Open') }}</a>@endif</div>
        </li>
      @empty
        <li class="px-6 py-6 text-sm text-ink-500">{{ __('Nothing due. Thank you!') }}</li>
      @endforelse
    </ul>
  </section>

  <footer class="px-2 text-center text-xs text-ink-500">
    {{ __('Questions? Call :company', ['company' => $company->name]) }}@if ($company->phone) · <a href="tel:{{ preg_replace('/[^0-9+]/', '', $company->phone) }}" class="text-brand-700">{{ $company->phone }}</a>@endif
  </footer>
</main>
</body>
</html>
