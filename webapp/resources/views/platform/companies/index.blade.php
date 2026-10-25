@extends('platform.layout')
@section('title', 'Companies')
@section('content')
@php
  $labels = ['trial' => __('On trial'), 'active' => __('Paying'), 'lapsed' => __('Read-only'), 'suspended' => __('Suspended'), 'deleted' => __('Deleted')];
  $tones = ['trial' => 'bg-amber-50 text-amber-800', 'active' => 'bg-emerald-50 text-emerald-800', 'lapsed' => 'bg-ink-100 text-ink-700', 'suspended' => 'bg-red-50 text-overdue', 'deleted' => 'bg-ink-900 text-white'];
@endphp
<div class="flex flex-wrap items-end justify-between gap-3">
  <h1 class="font-display text-2xl font-semibold">{{ __('Companies') }}</h1>
  <form method="GET" class="flex flex-wrap gap-2">
    <input name="q" value="{{ $filters['q'] ?? '' }}" class="input w-56" placeholder="{{ __('Name') }}" aria-label="{{ __('Search by name') }}">
    <select name="status" class="input w-44" aria-label="{{ __('Status') }}">
      <option value="">{{ __('Every status') }}</option>
      @foreach ($labels as $key => $label)<option value="{{ $key }}" @selected(($filters['status'] ?? '') === $key)>{{ $label }} ({{ $counts[$key] ?? 0 }})</option>@endforeach
    </select>
    <button class="btn-secondary">{{ __('Show') }}</button>
  </form>
</div>

<section class="card overflow-x-auto">
  <table class="table">
    <thead><tr><th>{{ __('Company') }}</th><th>{{ __('Status') }}</th><th>{{ __('Plan') }}</th><th class="text-end">{{ __('Trucks') }}</th><th>{{ __('Trial ends') }}</th><th>{{ __('Last seen') }}</th><th>{{ __('Owner') }}</th></tr></thead>
    <tbody>
      @forelse ($companies as $company)
        <tr>
          <td class="font-medium"><a href="{{ route('platform.companies.show', $company) }}" class="text-brand-700 hover:underline">{{ $company->name }}</a> <span class="text-xs text-ink-500">{{ $company->country }}</span></td>
          <td><span class="pill {{ $tones[$company->status] ?? 'bg-ink-100 text-ink-700' }}">{{ $labels[$company->status] ?? $company->status }}</span></td>
          <td>{{ $company->plan?->name ?? '—' }}</td>
          <td class="text-end">{{ $trucks[$company->id] ?? 0 }}</td>
          <td class="nowrap">{{ $company->trial_ends_at?->format('d M Y') ?? '—' }}</td>
          <td class="nowrap">{{ isset($seen[$company->id]) ? \Carbon\CarbonImmutable::parse($seen[$company->id])->diffForHumans() : '—' }}</td>
          <td>{{ $company->owner?->email }}</td>
        </tr>
      @empty
        <tr><td colspan="7" class="text-ink-500">{{ __('No company matches.') }}</td></tr>
      @endforelse
    </tbody>
  </table>
</section>
{{ $companies->links() }}
@endsection
