{{-- One price: what is paid, the full price struck through when a coupon takes some off. --}}
<p>
  <span class="font-display text-4xl font-semibold">{{ $money($p['pay'], $currency) }}</span>
  <span class="text-sm text-ink-500">{{ $per }}</span>
</p>
@if ($p['pay'] < $p['full'])<p class="text-sm text-ink-400"><s>{{ $money($p['full'], $currency) }}</s> <span class="sr-only">{{ __('before the coupon') }}</span></p>@endif
