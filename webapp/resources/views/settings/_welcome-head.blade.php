{{-- One getting-started step's heading: its number or a tick, the title and what it is for. --}}
<div class="flex items-start gap-4">
  @if ($s['done'])
    <span class="grid size-9 shrink-0 place-items-center rounded-full bg-emerald-600 text-white"><x-icon name="check" class="size-5" /><span class="sr-only">{{ __('Done') }}</span></span>
  @else
    <span class="grid size-9 shrink-0 place-items-center rounded-full bg-brand-50 font-display font-semibold text-brand-700">{{ $s['n'] }}</span>
  @endif
  <div>
    <h2 class="text-lg font-semibold">{{ $s['title'] }}</h2>
    <p class="text-sm text-ink-500">{{ $hint }}</p>
  </div>
</div>
