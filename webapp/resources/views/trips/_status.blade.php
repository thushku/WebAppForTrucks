@php($tone = ['draft' => 'bg-ink-100 text-ink-700', 'assigned' => 'bg-sky-50 text-sent', 'accepted' => 'bg-sky-50 text-sent', 'running' => 'bg-brand-50 text-brand-700',
  'ended' => 'bg-amber-50 text-partial', 'settled' => 'bg-emerald-50 text-paid', 'closed' => 'bg-emerald-50 text-paid', 'cancelled' => 'bg-ink-100 text-ink-400'][$status] ?? 'bg-ink-100')
<span class="pill {{ $tone }}">{{ __(\App\Modules\Trips\Models\Trip::STATUSES[$status] ?? $status) }}</span>
