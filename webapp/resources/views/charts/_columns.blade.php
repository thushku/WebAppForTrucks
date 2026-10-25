{{--
  Grouped columns, drawn on the server as SVG (dataviz method: thin marks, 4px rounded
  data-ends square at the baseline, a 2px gap between neighbours, hairline solid grid, one
  axis, a legend for two or more series, values in the tooltip and the table view).
  $rows: [['label' => 'Oct', 'values' => ['income' => 38000, …]], …]
  $series: [['key' => 'income', 'label' => 'Income', 'color' => '#2a78d6'], …]
  $format: fn (float) => string; $short: fn (float) => string for the axis.
--}}
@php
  $w = 640; $h = 240; $left = 56; $right = 8; $top = 12; $bottom = 28;
  $plotW = $w - $left - $right; $plotH = $h - $top - $bottom;
  $max = max(1, ...array_merge([1], ...array_map(fn ($r) => array_values($r['values']), $rows)));
  // A clean axis: 4 or 5 steps of 1, 2, 2.5 or 5 times a power of ten — the lowest top that holds the data.
  $pow = 10 ** floor(log10($max));
  [$step, $ticks] = collect([1, 2, 2.5, 5, 10])->flatMap(fn ($f) => [[$f * $pow, 4], [$f * $pow, 5]])
      ->filter(fn ($c) => $c[0] * $c[1] >= $max)->sortBy(fn ($c) => $c[0] * $c[1])->first() ?? [$pow * 10, 4];
  $top_value = $step * $ticks;
  $groupW = $plotW / max(1, count($rows));
  $bar = min(24, ($groupW - 16) / count($series) - 2);
  $y = fn ($v) => $top + $plotH - ($v / $top_value) * $plotH;
  $id = 'c' . substr(md5(json_encode($rows)), 0, 6);
@endphp
<div class="relative" data-chart>
  <div class="mb-2 flex flex-wrap gap-4 text-xs text-ink-700" aria-hidden="true">
    @foreach ($series as $s)<span class="inline-flex items-center gap-1.5"><span class="inline-block size-2.5 rounded-sm" style="background: {{ $s['color'] }}"></span>{{ $s['label'] }}</span>@endforeach
  </div>
  <svg viewBox="0 0 {{ $w }} {{ $h }}" class="block w-full" role="img" aria-labelledby="{{ $id }}-title">
    <title id="{{ $id }}-title">{{ $title }}</title>
    @for ($i = 0; $i <= $ticks; $i++)
      @php $v = $step * $i; @endphp
      <line x1="{{ $left }}" x2="{{ $w - $right }}" y1="{{ round($y($v), 1) }}" y2="{{ round($y($v), 1) }}" stroke="{{ $i === 0 ? '#c9ced6' : '#eceff3' }}" stroke-width="1" />
      <text x="{{ $left - 8 }}" y="{{ round($y($v), 1) + 4 }}" text-anchor="end" font-size="11" fill="#6b7280">{{ $short($v) }}</text>
    @endfor
    @foreach ($rows as $i => $r)
      @php
        $gx = $left + $i * $groupW;
        $inner = count($series) * $bar + (count($series) - 1) * 2;
        $x0 = $gx + ($groupW - $inner) / 2;
      @endphp
      @foreach ($series as $j => $s)
        @php
          $v = (float) ($r['values'][$s['key']] ?? 0);
          $bx = round($x0 + $j * ($bar + 2), 1); $by = round($y($v), 1); $bh = round($top + $plotH - $by, 1); $rr = min(4, $bh, $bar / 2);
        @endphp
        @if ($bh > 0.5)
          <path d="M{{ $bx }},{{ $top + $plotH }} V{{ $by + $rr }} Q{{ $bx }},{{ $by }} {{ $bx + $rr }},{{ $by }} H{{ $bx + $bar - $rr }} Q{{ $bx + $bar }},{{ $by }} {{ $bx + $bar }},{{ $by + $rr }} V{{ $top + $plotH }} Z" fill="{{ $s['color'] }}" data-bar="{{ $i }}" />
        @endif
      @endforeach
      <text x="{{ round($gx + $groupW / 2, 1) }}" y="{{ $h - 8 }}" text-anchor="middle" font-size="11" fill="#6b7280">{{ $r['label'] }}</text>
      @php
        $tip = ['title' => $r['title'] ?? $r['label'], 'rows' => array_map(fn ($s) => ['label' => $s['label'], 'color' => $s['color'], 'value' => $format((float) ($r['values'][$s['key']] ?? 0))], $series)];
        $said = implode(', ', array_map(fn ($row) => $row['label'] . ' ' . $row['value'], $tip['rows']));
      @endphp
      {{-- The hit target: the whole month, so the pointer never has to land on a thin bar. --}}
      <rect x="{{ round($gx, 1) }}" y="{{ $top }}" width="{{ round($groupW, 1) }}" height="{{ $plotH }}" fill="transparent" tabindex="0" data-hit="{{ $i }}" data-tip="{{ json_encode($tip) }}"><title>{{ $tip['title'] }}: {{ $said }}</title></rect>
    @endforeach
  </svg>
  <div class="pointer-events-none absolute z-10 hidden min-w-40 rounded-xl border border-ink-100 bg-white px-3 py-2 text-xs shadow-lg" data-tooltip role="status"></div>
  <details class="mt-2 text-xs">
    <summary class="cursor-pointer text-ink-500">{{ __('Show as a table') }}</summary>
    <table class="mt-2 w-full text-sm">
      <thead><tr class="text-xs text-ink-500"><th class="py-1 text-start font-medium">{{ __('Month') }}</th>@foreach ($series as $s)<th class="py-1 text-end font-medium">{{ $s['label'] }}</th>@endforeach</tr></thead>
      <tbody>@foreach ($rows as $r)<tr class="border-t border-ink-100"><td class="py-1">{{ $r['title'] ?? $r['label'] }}</td>@foreach ($series as $s)<td class="money py-1 text-end">{{ $format((float) ($r['values'][$s['key']] ?? 0)) }}</td>@endforeach</tr>@endforeach</tbody>
    </table>
  </details>
</div>
