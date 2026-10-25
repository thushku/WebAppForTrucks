@extends('layouts.app')
@use('App\Modules\Invoicing\Design\DesignSchema')
@section('title', $template->name)
@section('content')
@php
  $f = $family;
  $contrast = DesignSchema::contrastOnWhite($design['accent']);
@endphp
<div class="mb-4 flex flex-wrap items-center justify-between gap-3">
  <div>
    <a href="{{ route('settings.templates', ['family' => $f]) }}" class="inline-flex items-center gap-1 text-sm text-brand-700 hover:underline"><x-icon name="arrow-left" class="size-4" /> {{ __('Templates') }}</a>
    <h1 class="mt-1 text-2xl font-semibold">{{ $template->name }}
      <span class="text-base font-normal text-ink-500">· {{ __(DesignSchema::FAMILIES[$f]) }} · {{ __('version :v', ['v' => $template->version]) }}</span>
      @if ($template->is_default)<span class="pill ms-1 bg-emerald-50 align-middle text-emerald-700">{{ __('Default') }}</span>@endif
    </h1>
  </div>
  <div class="flex flex-wrap gap-2">
    @unless ($template->is_default)
      <form method="POST" action="{{ route('settings.templates.default', $template) }}">@csrf<button class="btn-secondary text-sm">{{ __('Make default') }}</button></form>
    @endunless
    <form method="POST" action="{{ route('settings.templates.duplicate', $template) }}">@csrf<button class="btn-secondary text-sm">{{ __('Duplicate') }}</button></form>
    <a href="{{ route('settings.templates.export', $template) }}" class="btn-secondary text-sm"><x-icon name="download" class="size-4" /> {{ __('Export') }}</a>
  </div>
</div>

@if (session('dropped'))
  <div class="mb-4 rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm" role="status">
    <p class="font-medium">{{ __('Saved, but some values were not kept:') }}</p>
    <ul class="mt-1 list-disc ps-5">@foreach (session('dropped') as $line)<li>{{ $line }}</li>@endforeach</ul>
  </div>
@endif

<div class="grid gap-6 xl:grid-cols-[400px_minmax(0,1fr)]">
  {{-- The choices --}}
  <form method="POST" action="{{ route('settings.templates.update', $template) }}" class="space-y-4" data-design-form data-preview-url="{{ route('settings.templates.preview', $f) }}" novalidate>
    @csrf @method('PUT')
    <section class="card space-y-4 p-5">
      <div><label class="label" for="d_name">{{ __('Name') }}</label><input id="d_name" name="name" class="input" maxlength="70" required value="{{ old('name', $template->name) }}">@error('name')<span class="error">{{ $message }}</span>@enderror</div>
      <fieldset>
        <legend class="label">{{ __('Theme') }}</legend>
        <div class="grid gap-2">
          @foreach (DesignSchema::THEMES[$f] as $key => [$label, , $paper])
            <label class="flex cursor-pointer items-center gap-2 rounded-xl border border-ink-100 px-3 py-2 text-sm has-[:checked]:border-brand-500 has-[:checked]:bg-brand-50">
              <input type="radio" name="design[theme]" value="{{ $key }}" class="size-4" data-theme-paper="{{ $paper }}" @checked($design['theme'] === $key)> {{ __($label) }}
            </label>
          @endforeach
        </div>
      </fieldset>
      <div>
        <label class="label" for="d_accent">{{ __('Accent colour') }}</label>
        <div class="flex items-center gap-2">
          <input id="d_accent" type="color" name="design[accent]" value="{{ $design['accent'] }}" class="h-10 w-14 cursor-pointer rounded-lg border border-ink-100" data-accent>
          <div class="flex flex-wrap gap-1.5">
            @foreach (DesignSchema::SWATCHES as $hex => $name)
              <button type="button" class="size-7 rounded-lg border border-ink-100" style="background: {{ $hex }}" title="{{ __($name) }}" aria-label="{{ __($name) }}" data-swatch="{{ $hex }}"></button>
            @endforeach
          </div>
        </div>
        <span class="hint {{ $contrast < 3 ? '' : 'hidden' }} !text-partial" data-contrast-warning>{{ __('This colour is light on white: titles in it may be hard to read on paper.') }}</span>
      </div>
      <div class="grid grid-cols-2 gap-3">
        <div><label class="label" for="d_font">{{ __('Font') }}</label>
          <select id="d_font" name="design[font]" class="input">@foreach (DesignSchema::FONTS as $key => [$label])<option value="{{ $key }}" @selected($design['font'] === $key)>{{ __($label) }}</option>@endforeach</select></div>
        <div><label class="label" for="d_paper">{{ __('Paper') }}</label>
          <select id="d_paper" name="design[paper]" class="input" data-paper>@foreach (DesignSchema::PAPERS as $key => [$label])<option value="{{ $key }}" @selected($design['paper'] === $key)>{{ __($label) }}</option>@endforeach</select></div>
        <div><label class="label" for="d_density">{{ __('Table rows') }}</label>
          <select id="d_density" name="design[density]" class="input">
            <option value="comfortable" @selected($design['density'] === 'comfortable')>{{ __('Comfortable') }}</option>
            <option value="compact" @selected($design['density'] === 'compact')>{{ __('Compact') }}</option>
          </select></div>
        @foreach (['base_pt' => [__('Text size (pt)'), 0.5], 'margin_mm' => [__('Margins (mm)'), 1], 'logo_mm' => [__('Logo height (mm)'), 1]] as $key => [$label, $step])
          <div><label class="label" for="d_{{ $key }}">{{ $label }}</label>
            <input id="d_{{ $key }}" type="number" name="design[{{ $key }}]" value="{{ $design[$key] }}" min="{{ DesignSchema::RANGES[$key][0] }}" max="{{ DesignSchema::RANGES[$key][1] }}" step="{{ $step }}" class="input"></div>
        @endforeach
      </div>
    </section>

    <details class="card p-5" open>
      <summary class="cursor-pointer font-semibold">{{ __('What prints') }}</summary>
      <div class="mt-3 grid gap-2 sm:grid-cols-2 xl:grid-cols-1">
        @foreach (DesignSchema::FIELDS[$f] as $field => $default)
          <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="design[fields][{{ $field }}]" value="1" class="size-4 rounded border-ink-300" @checked($design['fields'][$field])> {{ __(DesignSchema::FIELD_LABELS[$field] ?? $field) }}</label>
        @endforeach
      </div>
      @if ($f === 'invoice')<p class="hint">{{ __('The reverse-charge and exemption lines always print: the law asks for them.') }}</p>@endif
    </details>

    <details class="card p-5">
      <summary class="cursor-pointer font-semibold">{{ __('Titles and labels') }}</summary>
      <p class="hint">{{ __('Leave a box empty for the usual wording, in the language the document prints in. A renamed label prints as you type it.') }}</p>
      <div class="mt-3 space-y-3">
        @foreach (DesignSchema::TITLES[$f] as $key => $default)
          <div><label class="label" for="dt_{{ $key }}">{{ __('Title: :t', ['t' => __($default)]) }}</label><input id="dt_{{ $key }}" name="design[titles][{{ $key }}]" value="{{ $design['titles'][$key] }}" placeholder="{{ __($default) }}" maxlength="{{ DesignSchema::TEXT_MAX }}" class="input"></div>
        @endforeach
        @foreach (DesignSchema::LABELS[$f] as $key => $default)
          <div><label class="label" for="dl_{{ $key }}">{{ __($default) }}</label><input id="dl_{{ $key }}" name="design[labels][{{ $key }}]" value="{{ $design['labels'][$key] }}" placeholder="{{ __($default) }}" maxlength="{{ DesignSchema::TEXT_MAX }}" class="input"></div>
        @endforeach
        <div><label class="label" for="d_footer">{{ __('Footer line') }}</label><input id="d_footer" name="design[footer]" value="{{ $design['footer'] }}" maxlength="200" class="input" placeholder="{{ __('e.g. Subject to Kochi jurisdiction') }}"></div>
      </div>
    </details>

    <details class="card p-5" @if ($f === 'lr') open @endif>
      <summary class="cursor-pointer font-semibold">{{ __('Copies') }}</summary>
      <p class="hint">{{ $f === 'lr' ? __('Each copy prints on its own page with its label and a light tint. The driver’s copy usually hides the freight.') : __('Each copy prints on its own page with its label. An empty label prints no badge.') }}</p>
      <table class="mt-3 w-full text-sm">
        <thead><tr class="text-xs text-ink-500"><th class="pb-1 text-start font-medium">{{ __('Label') }}</th><th class="pb-1 font-medium">{{ __('Tint') }}</th>@if ($f === 'lr')<th class="pb-1 font-medium">{{ __('No freight') }}</th>@endif<th></th></tr></thead>
        <tbody id="copy-rows">
          @foreach ($design['copies'] as $i => $copy)
            @include('settings.templates._copy', ['i' => $i, 'copy' => $copy])
          @endforeach
        </tbody>
      </table>
      <template id="copy-template">@include('settings.templates._copy', ['i' => '__N__', 'copy' => ['label' => '', 'tint' => '#FFFFFF', 'hide_freight' => false]])</template>
      <button type="button" class="btn-ghost mt-2 text-sm" data-add-row="#copy-rows" data-template="#copy-template"><x-icon name="plus" class="size-4" /> {{ __('Add a copy') }}</button>
      <span class="hint">{{ __('At most :n copies.', ['n' => DesignSchema::MAX_COPIES]) }}</span>
    </details>

    <details class="card p-5" @if ($design['custom']) open @endif>
      <summary class="cursor-pointer font-semibold">{{ __('Extra lines') }}</summary>
      <p class="hint">{{ __('Printed under your details, e.g. “Udyam UDYAM-KL-07-0012345” or “Transporter ID 32AAACS1234F1Z1”.') }}</p>
      <table class="mt-3 w-full text-sm">
        <thead><tr class="text-xs text-ink-500"><th class="pb-1 text-start font-medium">{{ __('Label') }}</th><th class="pb-1 text-start font-medium">{{ __('Value') }}</th><th></th></tr></thead>
        <tbody id="custom-rows">
          @foreach ($design['custom'] as $i => $line)
            @include('settings.templates._custom', ['i' => $i, 'line' => $line])
          @endforeach
        </tbody>
      </table>
      <template id="custom-template">@include('settings.templates._custom', ['i' => '__N__', 'line' => ['label' => '', 'value' => '']])</template>
      <button type="button" class="btn-ghost mt-2 text-sm" data-add-row="#custom-rows" data-template="#custom-template"><x-icon name="plus" class="size-4" /> {{ __('Add a line') }}</button>
      <span class="hint">{{ __('At most :n lines.', ['n' => DesignSchema::MAX_CUSTOM]) }}</span>
    </details>

    <div class="sticky bottom-3 z-10 flex items-center justify-between gap-3 rounded-2xl border border-ink-100 bg-white/95 p-3 shadow-lg backdrop-blur">
      <span class="text-xs text-ink-500" data-preview-state aria-live="polite" data-busy="{{ __('Redrawing…') }}" data-done="{{ __('Preview up to date. Not saved yet.') }}" data-failed="{{ __('The preview could not be drawn; your changes are still in the form.') }}">{{ __('The preview follows your changes.') }}</span>
      <button class="btn-primary"><x-icon name="check" /> {{ __('Save') }}</button>
    </div>
  </form>

  {{-- The document --}}
  <div class="min-w-0 space-y-6">
    <section class="card overflow-hidden p-0">
      <div class="flex items-center justify-between border-b border-ink-100 px-4 py-2 text-sm">
        <span class="font-medium">{{ __('Preview') }}</span>
        <span class="text-xs text-ink-500">{{ $sampleNote }}</span>
      </div>
      <div class="overflow-hidden bg-ink-50" data-preview-frame>
        <iframe title="{{ __('Preview') }}" sandbox="allow-same-origin" class="block w-full border-0" style="height: 1150px" srcdoc="{{ $previewHtml }}" data-preview></iframe>
      </div>
    </section>

    <section class="card p-6" id="who">
      <h2 class="text-lg font-semibold">{{ __('Who prints with it') }}</h2>
      <p class="mt-1 text-sm text-ink-500">{{ $template->is_default
          ? __('This is the default: everything without its own template prints with it. Below, clients and branches that should use it even if another becomes the default.')
          : __('Clients and branches that print with this template. A client or branch has one template of each kind, so choosing it here takes it off the other.') }}</p>
      <form method="POST" action="{{ route('settings.templates.assign', $template) }}" class="mt-4 grid gap-4 sm:grid-cols-2">@csrf @method('PUT')
        <div><label class="label" for="as_parties">{{ __('Clients') }}</label>
          <select id="as_parties" name="party_ids[]" multiple size="8" class="input">
            @foreach ($parties as $p)<option value="{{ $p->id }}" @selected(in_array($p->id, array_map('intval', $template->party_ids ?? []), true))>{{ $p->name }}</option>@endforeach
          </select><span class="hint">{{ __('Ctrl or ⌘ + click to choose several.') }}</span></div>
        <div><label class="label" for="as_branches">{{ __('Branches') }}</label>
          <select id="as_branches" name="branch_ids[]" multiple size="8" class="input">
            @foreach ($branches as $b)<option value="{{ $b->id }}" @selected(in_array($b->id, array_map('intval', $template->branch_ids ?? []), true))>{{ $b->name }}</option>@endforeach
          </select></div>
        <div class="text-end sm:col-span-2"><button class="btn-secondary">{{ __('Save who prints with it') }}</button></div>
      </form>
    </section>

    <section class="card p-6">
      <h2 class="text-lg font-semibold">{{ __('Versions') }}</h2>
      <p class="mt-1 text-sm text-ink-500">{{ __('Every save is kept. Bringing an old version back saves it as the newest; nothing is lost.') }}</p>
      <table class="table mt-3">
        <thead><tr><th>{{ __('Version') }}</th><th>{{ __('Saved') }}</th><th>{{ __('By') }}</th><th></th></tr></thead>
        <tbody>
          @foreach ($template->versions as $v)
            <tr>
              <td class="font-mono">{{ $v->version }}@if ($v->version === $template->version) <span class="pill bg-ink-100 text-ink-700">{{ __('current') }}</span>@endif</td>
              <td>{{ $v->created_at?->format('d M Y, H:i') }}</td>
              <td>{{ $v->author?->name ?? '—' }}</td>
              <td class="text-end">@if ($v->version !== $template->version)
                <form method="POST" action="{{ route('settings.templates.rollback', $template) }}">@csrf<input type="hidden" name="version" value="{{ $v->version }}"><button class="btn-ghost text-xs"><x-icon name="undo" class="size-4" /> {{ __('Bring back') }}</button></form>
              @endif</td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </section>

    @unless ($template->is_default)
      <form method="POST" action="{{ route('settings.templates.destroy', $template) }}" class="text-end" data-confirm="{{ __('Remove this template? Bills already issued keep their look.') }}">@csrf @method('DELETE')
        <button class="btn-danger text-sm"><x-icon name="trash" class="size-4" /> {{ __('Remove template') }}</button>
      </form>
    @endunless
  </div>
</div>
@endsection
