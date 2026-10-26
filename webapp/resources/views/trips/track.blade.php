<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>{{ __('Track :lr', ['lr' => $load->lr_number ?? '—']) }} — {{ $company->name }}</title>
@vite(['resources/css/app.css'])
</head>
<body class="min-h-screen bg-ink-50">
{{-- A client's tracking link (M45): the steps, the nearest place while on the road, the delivery. Never the freight or the crew. --}}
<main class="mx-auto max-w-xl space-y-4 px-4 py-8">
  <header class="card p-6">
    <p class="text-sm text-ink-500">{{ $company->name }}</p>
    <p class="mt-2 text-xs font-semibold uppercase tracking-wide text-ink-500">{{ __('LR') }}</p>
    <h1 class="docno break-all font-mono text-2xl font-semibold">{{ $load->lr_number ?? '—' }}</h1>
    <p class="mt-1 text-sm text-ink-600">{{ collect([$from, $to])->filter()->join(' → ') }}</p>
    <p class="mt-1 text-xs text-ink-500">{{ collect([$load->consignor?->name, $load->consignee?->name])->filter()->join(' → ') }}{{ $load->material ? ' · ' . $load->material : '' }}{{ $load->packages ? ' · ' . trans_choice(':count package|:count packages', $load->packages) : '' }}</p>
  </header>

  @if ($position)
    <section class="card p-6" data-position>
      <h2 class="font-semibold">{{ __('On the way') }}</h2>
      <p class="mt-1 text-sm">{{ $position['near'] ? __('Near :place (about :km km)', ['place' => $position['near'], 'km' => $position['km']]) : __('On the road') }}</p>
      <p class="text-xs text-ink-500">{{ __('Last update :time', ['time' => $position['at']->format('d M, H:i')]) }} · <a href="https://www.openstreetmap.org/?mlat={{ $position['lat'] }}&amp;mlon={{ $position['lng'] }}#map=10/{{ $position['lat'] }}/{{ $position['lng'] }}" class="text-brand-700 hover:underline" target="_blank" rel="noopener noreferrer">{{ __('See on the map') }}</a></p>
    </section>
  @endif

  <section class="card p-6">
    <ol class="space-y-3">
      @foreach ($steps as $s)
        <li class="flex items-start gap-3" data-step="{{ $s['key'] }}">
          <span class="mt-0.5 grid size-6 shrink-0 place-items-center rounded-full text-xs font-bold {{ $s['done'] ? 'bg-paid text-white' : 'border-2 border-ink-200 text-ink-300' }}">{{ $s['done'] ? '✓' : '' }}</span>
          <div class="min-w-0">
            <p class="text-sm font-medium {{ $s['done'] ? 'text-ink-900' : 'text-ink-400' }}">{{ $s['label'] }}</p>
            @if ($s['done'] && $s['at'])<p class="text-xs text-ink-500">{{ $s['at']->format('d M Y, H:i') }}</p>@elseif ($s['done'] && ! empty($s['day']))<p class="text-xs text-ink-500">{{ $s['day']->format('d M Y') }}</p>@endif
          </div>
        </li>
      @endforeach
    </ol>
  </section>

  @if ($delivery)
    <section class="card p-6" data-delivery>
      <h2 class="font-semibold text-paid">{{ __('Delivered') }}</h2>
      <p class="mt-1 text-sm">{{ $delivery['at']->format('d M Y, H:i') }}@if ($delivery['receiver']) · {{ __('received by :name', ['name' => $delivery['receiver']]) }}@endif</p>
      @if ($delivery['short'] || $delivery['damaged'])<p class="mt-1 text-sm text-partial">{{ collect([$delivery['short'] ? __(':n short', ['n' => $delivery['short']]) : null, $delivery['damaged'] ? __(':n damaged', ['n' => $delivery['damaged']]) : null])->filter()->join(' · ') }}</p>@endif
      @if ($delivery['remarks'])<p class="mt-1 text-xs text-ink-500">{{ $delivery['remarks'] }}</p>@endif
    </section>
  @endif

  <footer class="px-2 text-center text-xs text-ink-500">
    {{ __('Questions about this load? Call :company', ['company' => $company->name]) }}@if ($company->phone) · <a href="tel:{{ preg_replace('/[^0-9+]/', '', $company->phone) }}" class="text-brand-700">{{ $company->phone }}</a>@endif
  </footer>
</main>
</body>
</html>
