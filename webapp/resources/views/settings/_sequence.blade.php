{{-- One number series' form (Settings → Numbering). --}}
  <form method="POST" action="{{ route('settings.numbering.update', $sequence) }}" class="card p-6" data-numbering data-preview-url="{{ route('settings.numbering.preview', $sequence) }}" novalidate>
    @csrf @method('PUT')
    <div class="flex flex-wrap items-center justify-between gap-3">
      <h3 class="text-lg font-semibold">{{ $sequence->displayName() }}@if ($labels[$sequence->id] ?? null) <span class="text-base font-normal text-ink-500">· {{ $labels[$sequence->id] }}</span>@endif
        @if ($sequence->max_length)<span class="pill ms-1 bg-amber-50 text-partial">{{ __('max :n characters', ['n' => $sequence->max_length]) }}</span>@endif
      </h3>
      <div class="text-sm">{{ __('Next') }}: <output class="docno rounded-lg bg-brand-50 px-2.5 py-1 font-mono font-semibold text-brand-800" data-preview>{{ $previews[$sequence->id] }}</output></div>
    </div>
    <div class="mt-4 grid gap-4 sm:grid-cols-[8rem_minmax(0,1fr)]">
      <div>
        <label class="label" for="pre_{{ $sequence->id }}">{{ __('Prefix') }}</label>
        <input id="pre_{{ $sequence->id }}" name="prefix" class="input font-mono" value="{{ $sequence->prefix }}" maxlength="20">
      </div>
      <div>
        <label class="label" for="fmt_{{ $sequence->id }}">{{ __('Format') }}</label>
        <input id="fmt_{{ $sequence->id }}" name="format" class="input font-mono text-sm" value="{{ $sequence->format }}" maxlength="60" required>
      </div>
    </div>
    <div class="mt-4 grid gap-4 sm:grid-cols-[minmax(0,1fr)_7rem_7rem]">
      <div>
        <label class="label" for="rst_{{ $sequence->id }}">{{ __('Start again') }}</label>
        <select id="rst_{{ $sequence->id }}" name="reset_policy" class="input">
          @foreach (\App\Modules\Settings\Models\DocumentSequence::RESET_POLICIES as $value => $label)
            <option value="{{ $value }}" @selected($sequence->reset_policy === $value)>{{ __($label) }}</option>
          @endforeach
        </select>
      </div>
      <div>
        <label class="label whitespace-nowrap" for="nxt_{{ $sequence->id }}">{{ __('Next no.') }}</label>
        <input id="nxt_{{ $sequence->id }}" name="next_number" type="number" min="1" class="input" value="{{ $nexts[$sequence->id] }}" title="{{ __('For period :period', ['period' => $periods[$sequence->id]]) }}">
      </div>
      <div>
        <label class="label whitespace-nowrap" for="st_{{ $sequence->id }}">{{ __('Starts at') }}</label>
        <input id="st_{{ $sequence->id }}" name="start_number" type="number" min="1" class="input" value="{{ $sequence->start_number }}" title="{{ __('Where each new period starts') }}">
      </div>
    </div>
    @if ($sequence->scope_type === 'company' && in_array($sequence->doc_type, \App\Modules\Settings\Models\DocumentSequence::BRANCH_TYPES, true) && $branchCount > 1)
      <label class="mt-4 flex items-center gap-2 text-sm"><input type="checkbox" name="per_branch" value="1" class="size-4 rounded border-ink-300" @checked($sequence->per_branch)> {{ __('Number each branch separately (its own printed run, with the branch code in front)') }}</label>
    @endif
    <p class="error" data-preview-error @if (! $errors->any()) hidden @endif>{{ $errors->first() }}</p>
    <div class="mt-4 flex items-center justify-between gap-3">
      <p class="text-xs text-ink-500">{{ __('“Next no.” is for the current period (:period).', ['period' => $periods[$sequence->id]]) }}</p>
      <button type="submit" class="btn-secondary"><x-icon name="check" /> {{ __('Save') }}</button>
    </div>
  </form>
