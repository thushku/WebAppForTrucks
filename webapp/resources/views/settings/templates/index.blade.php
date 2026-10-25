@extends('layouts.settings')
@use('App\Modules\Invoicing\Design\DesignSchema')
@section('title', __('Templates'))
@section('settings')
<section class="card p-6">
  <h2 class="text-xl font-semibold">{{ __('Templates') }}</h2>
  <p class="mt-1 text-sm text-ink-500">{{ __('How your bills, LRs and receipts look: the theme, colour, font, paper, what prints and what it is called. One template of each kind is the default; a client or a branch can have its own. An issued bill keeps the look it was issued with.') }}</p>

  <nav class="mt-4 flex flex-wrap gap-2" aria-label="{{ __('Document kinds') }}">
    @foreach (DesignSchema::FAMILIES as $key => $label)
      <a href="{{ route('settings.templates', ['family' => $key]) }}" class="rounded-xl border px-3 py-2 text-sm font-medium {{ $family === $key ? 'border-brand-300 bg-brand-50 text-brand-700' : 'border-ink-100 bg-white text-ink-700' }}" @if ($family === $key) aria-current="page" @endif>{{ __($label) }}<span class="ms-1 text-xs text-ink-400">{{ $counts[$key] ?? 0 }}</span></a>
    @endforeach
  </nav>

  <div class="mt-4 space-y-3">
    @forelse ($templates as $t)
      <div class="flex flex-wrap items-center gap-4 rounded-2xl border border-ink-100 p-4 text-sm">
        <span class="size-8 shrink-0 rounded-lg border border-ink-100" style="background: {{ $t->designNormalized()['accent'] }}" aria-hidden="true"></span>
        <div class="min-w-48 flex-1">
          <div class="font-medium"><a href="{{ route('settings.templates.edit', $t) }}" class="hover:underline">{{ $t->name }}</a>
            @if ($t->is_default)<span class="pill ms-1 bg-emerald-50 text-emerald-700">{{ __('Default') }}</span>@endif</div>
          <div class="text-xs text-ink-500">{{ $t->themeName() }} · {{ __(DesignSchema::PAPERS[$t->designNormalized()['paper']][0]) }} · {{ __('version :v', ['v' => $t->version]) }}
            @if ($t->party_ids) · {{ trans_choice(':count client|:count clients', count($t->party_ids)) }}: {{ collect($t->party_ids)->map(fn ($id) => $parties[$id] ?? null)->filter()->take(3)->join(', ') }}@endif
            @if ($t->branch_ids) · {{ trans_choice(':count branch|:count branches', count($t->branch_ids)) }}: {{ collect($t->branch_ids)->map(fn ($id) => $branches[$id] ?? null)->filter()->join(', ') }}@endif
          </div>
        </div>
        <a href="{{ route('settings.templates.edit', $t) }}" class="btn-ghost text-xs"><x-icon name="palette" class="size-4" /> {{ __('Edit') }}</a>
        <form method="POST" action="{{ route('settings.templates.duplicate', $t) }}">@csrf<button class="btn-ghost text-xs">{{ __('Duplicate') }}</button></form>
        @unless ($t->is_default)
          <form method="POST" action="{{ route('settings.templates.default', $t) }}">@csrf<button class="btn-ghost text-xs">{{ __('Make default') }}</button></form>
        @endunless
      </div>
    @empty
      <p class="rounded-xl bg-ink-50 px-4 py-3 text-sm">{{ __('No template yet: these documents print with the built-in design. Start one from a theme below.') }}</p>
    @endforelse
  </div>
</section>

<div class="grid gap-6 xl:grid-cols-2">
  <form method="POST" action="{{ route('settings.templates.store') }}" class="card space-y-4 p-6">@csrf
    <input type="hidden" name="family" value="{{ $family }}">
    <h3 class="text-lg font-semibold">{{ __('New from a theme') }}</h3>
    <fieldset class="grid gap-3 sm:grid-cols-2">
      <legend class="label">{{ __('Theme') }}</legend>
      @foreach (DesignSchema::THEMES[$family] as $key => [$label, $accent, $paper])
        <label class="flex cursor-pointer items-center gap-3 rounded-2xl border border-ink-100 p-3 text-sm has-[:checked]:border-brand-500 has-[:checked]:bg-brand-50">
          <input type="radio" name="theme" value="{{ $key }}" class="size-4" @checked($loop->first)>
          <span class="size-6 rounded" style="background: {{ $accent }}" aria-hidden="true"></span>
          <span><span class="font-medium">{{ __($label) }}</span><span class="block text-xs text-ink-500">{{ __(DesignSchema::PAPERS[$paper][0]) }}</span></span>
        </label>
      @endforeach
    </fieldset>
    <div><label class="label" for="tp_name">{{ __('Name') }}</label><input id="tp_name" name="name" class="input" maxlength="70" required value="{{ old('name', __('My :kind', ['kind' => mb_strtolower(__(DesignSchema::FAMILIES[$family]))])) }}">@error('name')<span class="error">{{ $message }}</span>@enderror</div>
    <div class="text-end"><button class="btn-primary"><x-icon name="plus" /> {{ __('Create and edit') }}</button></div>
  </form>

  <form method="POST" action="{{ route('settings.templates.import') }}" enctype="multipart/form-data" class="card space-y-4 p-6">@csrf
    <input type="hidden" name="family" value="{{ $family }}">
    <h3 class="text-lg font-semibold">{{ __('Import a design') }}</h3>
    <p class="text-sm text-ink-500">{{ __('A design file from Export, here or in another company. Every value is checked; anything this version does not know is left out and listed.') }}</p>
    <div><label class="label" for="tp_file">{{ __('Design file (.json)') }}</label><input id="tp_file" type="file" name="file" accept=".json,application/json" class="input" required>@error('file')<span class="error">{{ $message }}</span>@enderror</div>
    <div class="text-end"><button class="btn-ghost"><x-icon name="upload" /> {{ __('Import') }}</button></div>
  </form>
</div>
@endsection
