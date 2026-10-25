@extends('layouts.settings')
@section('title', __('Messages'))
@section('settings')
<form method="POST" action="{{ route('settings.messages.update') }}" class="space-y-6">
  @csrf
  @method('PUT')
  <section class="card space-y-4 p-6">
    <h2 class="text-lg font-semibold"><x-icon name="bell" class="inline size-5 text-brand-700" /> {{ __('Overdue reminders') }}</h2>
    <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="reminders_enabled" value="1" class="size-4" @checked(old('reminders_enabled', $reminders))> {{ __('Email clients a reminder when a bill is overdue') }}</label>
    <div class="max-w-sm">
      <label class="label" for="reminder_days">{{ __('Days after the due date') }}</label>
      <input id="reminder_days" name="reminder_days" class="input" value="{{ old('reminder_days', $days) }}" maxlength="60" inputmode="numeric">
      <span class="hint">{{ __('For example 3, 15, 30: one email on each of these days. A client can be kept out on their page.') }}</span>
      @error('reminder_days')<span class="error">{{ $message }}</span>@enderror
    </div>
  </section>

  <section class="card space-y-2 p-6">
    <h2 class="text-lg font-semibold"><x-icon name="mail" class="inline size-5 text-brand-700" /> {{ __('What is sent') }}</h2>
    <p class="text-sm text-ink-600">{{ __('These words go with every bill, receipt and reminder. Use these to fill in each document’s details:') }}</p>
    <ul class="flex flex-wrap gap-2 text-xs">
      @foreach (\App\Modules\Invoicing\Services\Messages::PLACEHOLDERS as $code => $what)
        <li class="rounded-lg bg-ink-50 px-2 py-1"><code class="font-mono text-brand-800">{{ $code }}</code> <span class="text-ink-500">{{ __($what) }}</span></li>
      @endforeach
    </ul>
  </section>

  @foreach ($rows as $purpose => $channels)
    <section class="card space-y-5 p-6">
      <h2 class="text-lg font-semibold">{{ __(\App\Modules\Invoicing\Services\Messages::PURPOSES[$purpose]) }}</h2>
      @foreach ($channels as $channel => $t)
        <div class="grid gap-4 lg:grid-cols-2">
          <div class="space-y-2">
            <div class="flex items-center justify-between gap-2">
              <span class="label mb-0">{{ __(\App\Modules\Invoicing\Services\Messages::CHANNELS[$channel]) }}</span>
              @if ($t['custom'])<label class="flex items-center gap-2 text-xs text-ink-600"><input type="checkbox" name="{{ $purpose }}_{{ $channel }}_reset" value="1" class="size-4"> {{ __('Use the shipped words again') }}</label>@else<span class="text-xs text-ink-400">{{ __('Shipped words') }}</span>@endif
            </div>
            @if ($channel === 'email')
              <input name="{{ $purpose }}_{{ $channel }}_subject" class="input" value="{{ old($purpose . '_' . $channel . '_subject', $t['subject']) }}" maxlength="200" aria-label="{{ __('Subject') }}">
            @endif
            <textarea name="{{ $purpose }}_{{ $channel }}_body" rows="{{ $channel === 'email' ? 8 : 3 }}" class="input text-sm" maxlength="3000" aria-label="{{ __('Message') }}">{{ old($purpose . '_' . $channel . '_body', $t['body']) }}</textarea>
            @error($purpose . '_' . $channel . '_body')<span class="error">{{ $message }}</span>@enderror
          </div>
          <div class="rounded-2xl border border-dashed border-ink-200 bg-ink-50/60 p-4 text-sm">
            <p class="text-xs font-semibold uppercase tracking-wide text-ink-500">{{ __('As the client sees it') }}</p>
            @if ($t['preview']['subject'])<p class="mt-2 font-semibold">{{ $t['preview']['subject'] }}</p>@endif
            <p class="mt-2 whitespace-pre-line text-ink-800">{{ $t['preview']['body'] }}</p>
          </div>
        </div>
      @endforeach
    </section>
  @endforeach

  @if ($errors->any())<ul class="card space-y-1 p-4">@foreach ($errors->all() as $e)<li class="error">{{ $e }}</li>@endforeach</ul>@endif
  <button class="btn-primary">{{ __('Save messages') }}</button>
</form>
@endsection
