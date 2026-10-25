@extends('layouts.app')
@section('title', __('Dashboard'))
@section('content')
<section class="card relative overflow-hidden p-6 sm:p-8">
  <div class="pointer-events-none absolute -end-16 -top-16 size-56 rounded-full bg-brand-100 blur-2xl"></div>
  <div class="relative">
    <p class="text-sm font-medium text-brand-700">{{ $company->name }}</p>
    <h2 class="mt-1 font-display text-3xl font-semibold">{{ __('Welcome, :name', ['name' => auth()->user()->name]) }}</h2>
    <p class="mt-2 max-w-2xl text-sm text-ink-500">{{ __('This is your control room. Trips, approvals, money and documents appear here as each part is switched on.') }}</p>
  </div>
</section>

<div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
  @foreach ([
    ['truck', __('Trucks'), '—', 'bg-blue-50 text-blue-700'],
    ['route', __('Trips running'), '—', 'bg-amber-50 text-amber-700'],
    ['check', __('Entries to check'), '—', 'bg-emerald-50 text-emerald-700'],
    ['wallet', __('Revenue this month'), '—', 'bg-violet-50 text-violet-700'],
  ] as [$icon, $label, $value, $tile])
    <div class="stat">
      <div class="flex items-center justify-between">
        <span class="stat-label">{{ $label }}</span>
        <span class="nav-tile {{ $tile }}"><x-icon :name="$icon" class="size-4" /></span>
      </div>
      <div class="stat-value">{{ $value }}</div>
    </div>
  @endforeach
</div>
@endsection
