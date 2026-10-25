@extends('platform.layout')
@section('title', 'Overview')
@section('content')
<div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
  @foreach ([[__('Companies'), $counts['companies']], [__('On trial'), $counts['trial']], [__('Paying'), $counts['active']], [__('People'), $counts['accounts']], [__('Failed sign-ins (24 h)'), $failures]] as [$label, $value])
    <div class="stat"><div class="stat-label">{{ $label }}</div><div class="stat-value">{{ number_format($value) }}</div></div>
  @endforeach
</div>

<section class="card">
  <h2 class="border-b border-ink-100 px-5 py-4 font-display text-lg font-semibold">{{ __('Messages from the website') }}</h2>
  @if ($messages->isEmpty())
    <p class="px-5 py-6 text-sm text-ink-500">{{ __('No message waiting.') }}</p>
  @else
    <ul class="divide-y divide-ink-100">
      @foreach ($messages as $message)
        <li class="flex flex-wrap items-start gap-4 px-5 py-4">
          <div class="min-w-0 flex-1">
            <div class="text-sm"><b>{{ $message->name }}</b>@if ($message->company) · {{ $message->company }}@endif · <a href="mailto:{{ $message->email }}" class="text-brand-700 hover:underline">{{ $message->email }}</a>@if ($message->mobile) · {{ $message->mobile }}@endif</div>
            <div class="text-xs text-ink-500">{{ __(\App\Platform\Website\ContactMessage::TOPICS[$message->topic] ?? $message->topic) }} · {{ $message->created_at->format('d M Y H:i') }}</div>
            <p class="mt-2 whitespace-pre-line text-sm text-ink-800">{{ $message->message }}</p>
          </div>
          <form method="POST" action="{{ route('platform.messages.handled', $message) }}">@csrf<button class="btn-secondary px-3 py-1.5">{{ __('Answered') }}</button></form>
        </li>
      @endforeach
    </ul>
  @endif
</section>

<section class="card">
  <h2 class="border-b border-ink-100 px-5 py-4 font-display text-lg font-semibold">{{ __('Locked logins') }}</h2>
  @if ($locked->isEmpty())
    <p class="px-5 py-6 text-sm text-ink-500">{{ __('No login is locked.') }}</p>
  @else
    <table class="table">
      <thead><tr><th>{{ __('Person') }}</th><th>{{ __('Login') }}</th><th>{{ __('Locked') }}</th><th></th></tr></thead>
      <tbody>
        @foreach ($locked as $account)
          <tr>
            <td class="font-medium">{{ $account->name }}</td>
            <td>{{ $account->email ?? $account->mobile }}</td>
            <td>{{ $account->locked_hard_at ? __('Until unlocked') : __('Until :time', ['time' => $account->locked_until?->format('d M H:i')]) }}</td>
            <td class="text-end">
              <form method="POST" action="{{ route('platform.accounts.unlock', $account) }}">@csrf<button class="btn-secondary px-3 py-1.5">{{ __('Unlock') }}</button></form>
            </td>
          </tr>
        @endforeach
      </tbody>
    </table>
  @endif
</section>

<section class="card overflow-x-auto">
  <h2 class="border-b border-ink-100 px-5 py-4 font-display text-lg font-semibold">{{ __('Companies') }}</h2>
  <table class="table">
    <thead><tr><th>{{ __('Company') }}</th><th>{{ __('Country') }}</th><th>{{ __('Status') }}</th><th>{{ __('Trial ends') }}</th><th>{{ __('Owner') }}</th><th>{{ __('Since') }}</th></tr></thead>
    <tbody>
      @forelse ($companies as $company)
        <tr>
          <td class="font-medium"><a href="{{ route('platform.companies.show', $company) }}" class="text-brand-700 hover:underline">{{ $company->name }}</a></td>
          <td>{{ $company->country }}</td>
          <td><span class="pill bg-ink-100 text-ink-700">{{ $company->status }}</span></td>
          <td class="nowrap">{{ $company->trial_ends_at?->format('d M Y') ?? '—' }}</td>
          <td>{{ $company->owner?->email }}</td>
          <td class="nowrap">{{ $company->created_at->format('d M Y') }}</td>
        </tr>
      @empty
        <tr><td colspan="6" class="text-ink-500">{{ __('No companies yet.') }}</td></tr>
      @endforelse
    </tbody>
  </table>
</section>
@endsection
