<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>{{ __('Shared papers') }} — {{ $company->name }}</title>
@vite(['resources/css/app.css'])
</head>
<body class="min-h-screen bg-ink-50">
<main class="mx-auto max-w-3xl space-y-5 px-4 py-8">
  <header class="card p-6">
    <p class="text-sm text-ink-500">{{ __('Shared by :company', ['company' => $company->name]) }}</p>
    <h1 class="mt-1 text-2xl font-semibold">{{ $share->purpose }}</h1>
    <p class="mt-1 text-xs text-ink-500">{{ __('This link works until :date.', ['date' => $share->expires_at->setTimezone($company->timezone)->format('d M Y, H:i')]) }}</p>
  </header>
  @foreach ($documents as $d)
    <section class="card p-6">
      <div class="flex flex-wrap items-start justify-between gap-2">
        <div>
          <p class="text-xs text-ink-500">{{ $labels[$d->owner_type . ':' . $d->owner_id] ?? '' }}</p>
          <h2 class="text-lg font-semibold">{{ $compliance->typeName($d->document_type) }}</h2>
          <p class="docno font-mono text-sm">{{ $d->number }}</p>
        </div>
        @include('documents._state', ['document' => $d])
      </div>
      <div class="mt-4 grid gap-3 sm:grid-cols-2">
        @foreach ($d->currentFiles() as $f)
          @if ($f->isImage())
            <img src="{{ route('share.file', [$token, $f->id]) }}" alt="{{ $compliance->typeName($d->document_type) }}" class="w-full rounded-xl border border-ink-100 bg-white" loading="lazy">
          @else
            <a href="{{ route('share.file', [$token, $f->id]) }}" class="btn-secondary" target="_blank" rel="noopener"><x-icon name="download" /> {{ __('Open PDF') }}</a>
          @endif
        @endforeach
      </div>
    </section>
  @endforeach
  <p class="text-center text-xs text-ink-400">{{ $share->watermark }}</p>
</main>
</body>
</html>
