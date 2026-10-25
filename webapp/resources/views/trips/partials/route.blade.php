{{-- The trip's route from the crew's phones, with the road entries that carry a place, and a replay (M12). --}}
<section class="card overflow-hidden">
  <div class="flex flex-wrap items-center justify-between gap-2 px-6 pt-5">
    <h3 class="font-semibold">{{ __('Route') }}</h3>
    <span class="text-xs text-ink-500">{{ __('Green: start · dark: last point · yellow: entries') }}</span>
  </div>
  <div class="relative mt-3">
    <div class="h-80 bg-ink-50" data-route-map data-url="{{ route('trips.route', $t) }}" data-start="{{ __('Start') }}" data-end="{{ __('Last point') }}" aria-label="{{ __('Map of the trip’s route') }}"></div>
    <p class="hidden px-6 py-3 text-sm text-ink-500" data-route-empty>{{ __('No GPS points from the crew’s phones on this trip yet.') }}</p>
    <div class="hidden flex-wrap items-center gap-3 border-t border-ink-100 px-6 py-3" data-replay>
      <button type="button" class="btn-secondary text-sm" data-replay-play data-play="{{ __('Play') }}" data-pause="{{ __('Pause') }}">{{ __('Play') }}</button>
      <input type="range" min="0" max="0" value="0" class="min-w-48 flex-1" data-replay-slider aria-label="{{ __('Replay position') }}">
      <span class="text-xs text-ink-500" data-replay-time></span>
    </div>
  </div>
</section>
