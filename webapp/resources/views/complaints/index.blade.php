@extends('layouts.app')
@section('title', __('Complaints'))
@section('content')
@php
  $priorityTone = ['urgent' => 'bg-red-50 text-overdue', 'high' => 'bg-amber-50 text-partial', 'normal' => 'bg-ink-100 text-ink-700', 'low' => 'bg-ink-50 text-ink-500'];
  $statuses = collect(\App\Modules\Fleet\Models\Complaint::STATUSES)->except($show === 'open' ? ['closed'] : []);
@endphp
<div class="flex flex-wrap items-center justify-between gap-3">
  <p class="text-sm text-ink-500">{{ __('Every truck’s complaints, most urgent first. Raise one from the truck page or the crew app.') }}</p>
  <a href="{{ route('complaints.index', $show === 'open' ? ['show' => 'all'] : []) }}" class="text-sm font-semibold text-brand-700 hover:underline">{{ $show === 'open' ? __('Show closed too') : __('Hide closed') }}</a>
</div>
<div class="grid gap-4 md:grid-cols-2 {{ $statuses->count() === 5 ? 'xl:grid-cols-5' : 'xl:grid-cols-4' }}">
  @foreach ($statuses as $status => $label)
    <section class="rounded-2xl border border-ink-100 bg-ink-50/60 p-3">
      <h2 class="flex items-center justify-between px-1 text-sm font-semibold">{{ __($label) }} <span class="text-xs text-ink-400">{{ ($columns[$status] ?? collect())->count() }}</span></h2>
      <ul class="mt-2 space-y-2">
        @foreach ($columns[$status] ?? [] as $c)
          <li>
            <a href="{{ route('complaints.show', $c) }}" class="card block p-3 hover:border-brand-300">
              <div class="flex items-center justify-between gap-2">
                <span class="docno font-mono text-xs font-semibold">{{ $c->truck->reg_no }}</span>
                <span class="pill {{ $priorityTone[$c->priority] }} px-2 py-0.5 text-[10px]">{{ __(ucfirst($c->priority)) }}</span>
              </div>
              <div class="mt-1 text-sm font-medium">{{ $c->title }}</div>
              <div class="mt-1 text-xs text-ink-500">{{ $c->number }} · {{ $c->created_at->format('d M') }}{{ $c->assignee ? ' · ' . $c->assignee->name : '' }}</div>
            </a>
          </li>
        @endforeach
      </ul>
    </section>
  @endforeach
</div>
@endsection
