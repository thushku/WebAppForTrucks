{{-- A fine's status pill (M48); an unpaid one past its date (or old) is late. --}}
@if ($fine->status === 'unpaid' && $service->isLate($fine))
  <span class="pill bg-red-50 text-overdue">{{ __('Late') }}</span>
@elseif ($fine->status === 'paid')
  <span class="pill bg-emerald-50 text-paid">{{ __('Paid :date', ['date' => $fine->paid_on?->format('d M')]) }}</span>
@else
  <span class="pill {{ ['unpaid' => 'bg-brand-50 text-brand-700', 'disputed' => 'bg-amber-50 text-partial', 'cancelled' => 'bg-ink-100 text-ink-500'][$fine->status] ?? '' }}">{{ __(\App\Modules\Fleet\Models\Fine::STATUSES[$fine->status] ?? $fine->status) }}</span>
@endif
