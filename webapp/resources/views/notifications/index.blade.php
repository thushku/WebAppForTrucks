@extends('layouts.app')
@section('title', __('Notifications'))
@section('content')
@php
  $tz = app(\App\Platform\Tenancy\CompanyContext::class)->company()->timezone ?: config('app.timezone');
@endphp
<div class="flex flex-wrap items-end justify-between gap-3">
  <div>
    <h1 class="font-display text-2xl font-semibold">{{ __('Notifications') }}</h1>
    <p class="text-sm text-ink-500">{{ __('What needs you: entries to approve, papers running out, incidents on the road. The same notices reach your phone when the owner app is signed in.') }}</p>
  </div>
  <div class="flex flex-wrap gap-2">
    <a href="{{ route('notifications.settings') }}" class="btn-secondary"><x-icon name="settings" /> {{ __('My notifications') }}</a>
    @if ($notifications->contains(fn ($n) => ! $n->read_at))
      <form method="POST" action="{{ route('notifications.read-all') }}">@csrf<button class="btn-secondary"><x-icon name="check" /> {{ __('Mark all as read') }}</button></form>
    @endif
  </div>
</div>

<section class="card divide-y divide-ink-100">
  @forelse ($notifications as $n)
    <a href="{{ route('notifications.open', $n->id) }}" class="flex gap-3 px-5 py-4 hover:bg-ink-50 {{ $n->read_at ? '' : 'bg-brand-50/40' }}">
      <span class="mt-1.5 size-2 shrink-0 rounded-full {{ $n->read_at ? 'bg-transparent' : ['high' => 'bg-overdue', 'medium' => 'bg-brand-600', 'low' => 'bg-ink-300'][$n->priority] ?? 'bg-brand-600' }}" aria-hidden="true"></span>
      <span class="min-w-0 flex-1">
        <span class="block font-medium {{ $n->read_at ? 'text-ink-700' : 'text-ink-900' }}">@if ($n->priority === 'high' && ! $n->read_at)<span class="pill me-1 bg-red-50 align-middle text-overdue">{{ __('Urgent') }}</span>@endif{{ $n->title }}@if ($n->times > 1) <span class="text-xs font-normal text-ink-500">· {{ trans_choice('updated :count time|updated :count times', $n->times - 1) }}</span>@endif</span>
        @if ($n->body)<span class="mt-0.5 block text-sm text-ink-600">{{ $n->body }}</span>@endif
        <span class="mt-1 block text-xs text-ink-400" title="{{ $n->updated_at->timezone($tz)->format('d M Y H:i') }}">{{ $n->updated_at->diffForHumans() }}</span>
      </span>
      @if (! $n->read_at)<span class="sr-only">{{ __('Unread') }}</span>@endif
    </a>
  @empty
    <p class="px-5 py-10 text-center text-sm text-ink-500">{{ __('Nothing yet. When an entry waits for you or a paper runs out, it shows here.') }}</p>
  @endforelse
</section>
{{ $notifications->links() }}
@endsection
