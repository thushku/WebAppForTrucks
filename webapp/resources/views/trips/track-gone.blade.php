<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>{{ __('Tracking ended') }}</title>
@vite(['resources/css/app.css'])
</head>
<body class="grid min-h-screen place-items-center bg-ink-50 px-4">
<main class="card max-w-md p-8 text-center">
  <h1 class="text-xl font-semibold">{{ __('This tracking link has ended') }}</h1>
  <p class="mt-2 text-sm text-ink-500">{{ $company ? __('The load was delivered a while ago or the LR was cancelled. Ask :company for news.', ['company' => $company->name]) : __('The link is not known. Ask the transporter for a new one.') }}</p>
</main>
</body>
</html>
