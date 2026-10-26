{{-- Who bears a fine and where its recovery stands (M48). --}}
@if ($fine->bears === 'company')
  <span class="text-ink-600">{{ __('The company') }}</span>
@elseif ($fine->status === 'cancelled')
  <span class="text-ink-500">{{ __('The driver') }}</span>
@elseif ($fine->payslip?->run)
  @if ($fine->payslip->run->status === 'draft')
    <span class="text-partial">{{ __('In the draft pay run of :period', ['period' => $fine->payslip->run->periodLabel()]) }}</span>
  @else
    <span class="text-paid">{{ __('Taken from the pay of :period', ['period' => $fine->payslip->run->periodLabel()]) }}</span>
  @endif
@elseif ($fine->status === 'disputed')
  <span class="text-ink-600">{{ __('The driver, once the dispute is decided') }}</span>
@else
  <span class="text-ink-600">{{ __('The driver, from the next pay run') }}</span>
@endif
