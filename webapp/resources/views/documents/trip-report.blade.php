{{-- The trip report of the filtered list (M25, 13-parity #5): to print or save as PDF. --}}
@php
  $m = fn ($v) => \App\Support\Money::company($v);
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{ __('Trip report') }}</title>
@include('documents._page-style')
<style>@page { size: A4 landscape; } .page { max-width: 297mm; } td, th { font-size: 11px; }</style>
</head>
<body>
<div class="page">
  @include('documents._page-head', ['docTitle' => __('Trip report'), 'docLines' => array_filter([$period ?: __('All dates'), ...$filters, __('Made on :date', ['date' => now($tz)->format('d M Y H:i')])])])

  <div class="scroll" style="margin-top:14px"><table>
    <thead><tr><th>{{ __('Date') }}</th><th>{{ __('Trip no.') }}</th><th>{{ __('Route') }}</th><th>{{ __('Truck') }}</th><th>{{ __('Driver') }}</th><th>{{ __('Client') }}</th>
      <th class="num">{{ __('Freight') }}</th><th class="num">{{ __('Other charges') }}</th><th class="num">{{ __('Total') }}</th><th>{{ __('Office note') }}</th></tr></thead>
    <tbody>
      @forelse ($rows as $r)
        <tr><td>{{ $r['date'] }}</td><td>{{ $r['number'] }}</td><td>{{ $r['route'] }}</td><td>{{ $r['truck'] }}</td><td>{{ $r['driver'] }}</td><td>{{ $r['client'] }}</td>
          <td class="num">{{ $m($r['freight']) }}</td><td class="num">{{ $m($r['other']) }}</td><td class="num">{{ $m($r['total']) }}</td><td class="muted">{{ $r['note'] }}</td></tr>
      @empty
        <tr><td colspan="10" class="muted">{{ __('No trips for these filters.') }}</td></tr>
      @endforelse
      <tr class="sum"><td colspan="6">{{ trans_choice(':count trip|:count trips', $rows->count()) }}</td><td class="num">{{ $m($totals['freight']) }}</td><td class="num">{{ $m($totals['other']) }}</td><td class="num">{{ $m($totals['total']) }}</td><td></td></tr>
    </tbody>
  </table></div>
</div>
</body>
</html>
